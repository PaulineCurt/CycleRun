<?php

namespace Tests\Feature;

use App\Enums\Methodology;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\Cycle;
use App\Models\DayLog;
use App\Models\Program;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Training\CycleAdaptationDecorator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleAdaptationDecoratorTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(User $user, string $date): TrainingSession
    {
        $program = Program::create([
            'user_id' => $user->id,
            'goal_distance_m' => 5000,
            'methodology' => Methodology::Vma,
            'level_input' => ['kind' => 'field_test', 'vma_kmh' => 14.0],
            'paces' => ['easy' => [343, 396]],
            'duration_weeks' => 12,
            'days_per_week' => 3,
            'start_date' => '2026-07-01',
        ]);

        return TrainingSession::create([
            'program_id' => $program->id,
            'planned_date' => $date,
            'week_index' => 0,
            'type' => SessionType::Interval,
            'planned_load' => ['mode' => 'intervals', 'reps' => 8, 'work_s' => 120, 'recovery_s' => 60],
            'base_generated' => ['type' => 'interval', 'reps' => 8],
            'status' => SessionStatus::Planned,
        ]);
    }

    private function regularHistory(User $user): void
    {
        foreach (['2026-05-04', '2026-06-01', '2026-06-29', '2026-07-27'] as $start) {
            Cycle::create([
                'user_id' => $user->id,
                'started_on' => $start,
                'menses_ended_on' => CarbonImmutable::parse($start)->addDays(4)->toDateString(),
            ]);
        }
    }

    public function test_overlays_adaptation_in_memory_without_persisting_or_touching_base(): void
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        $this->regularHistory($user);
        $session = $this->makeSession($user, '2026-07-29'); // menstrual window

        (new CycleAdaptationDecorator())->decorate($user, [$session]);

        $this->assertNotNull($session->adaptation);
        $this->assertSame('menstrual', $session->adaptation['phase']);

        // nothing was written, and base_generated is untouched
        $fresh = TrainingSession::find($session->id);
        $this->assertNull($fresh->adaptation);
        $this->assertEquals(['type' => 'interval', 'reps' => 8], $fresh->base_generated);
    }

    public function test_cycle_off_leaves_the_adaptation_null(): void
    {
        $user = User::factory()->create();
        $session = $this->makeSession($user, '2026-07-29');

        (new CycleAdaptationDecorator())->decorate($user, [$session]);

        $this->assertNull($session->adaptation);
    }

    public function test_declared_pain_overlays_rest_even_with_no_cycle_history(): void
    {
        $user = User::factory()->create();
        DayLog::create(['user_id' => $user->id, 'date' => '2026-07-29', 'pain' => 3]);
        $session = $this->makeSession($user, '2026-07-29');

        (new CycleAdaptationDecorator())->decorate($user, [$session]);

        $this->assertSame('rest_or_postpone', $session->adaptation['rule']);
        $this->assertTrue($session->postponed);
        // the load is not rewritten for a postponement
        $this->assertEquals($session->base_generated['reps'] ?? null, $session->planned_load['reps']);
    }

    public function test_planned_session_in_menstrual_phase_has_its_load_lightened_in_memory(): void
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        $this->regularHistory($user);
        $session = $this->makeSession($user, '2026-07-29'); // menstrual window
        $baseReps = $session->planned_load['reps'];

        (new CycleAdaptationDecorator())->decorate($user, [$session]);

        $this->assertTrue($session->adapted);
        $this->assertLessThan($baseReps, $session->planned_load['reps']);
        // base_generated is the untouched fallback
        $this->assertSame($baseReps, $session->base_generated['reps']);
        $this->assertSame('menstrual', $session->cycle['phase']);
    }

    public function test_decorated_session_is_not_dirty_on_overlay_columns(): void
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        $this->regularHistory($user);
        $session = $this->makeSession($user, '2026-07-29');

        (new CycleAdaptationDecorator())->decorate($user, [$session]);

        // the overlay touches real columns in memory but must not mark them dirty —
        // a stray save() must never persist a moving cycle estimate
        $this->assertArrayNotHasKey('adaptation', $session->getDirty());
        $this->assertArrayNotHasKey('planned_load', $session->getDirty());
        $this->assertArrayNotHasKey('target_pace_min_s', $session->getDirty());
    }

    public function test_a_session_already_acted_on_is_not_re_derived(): void
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        $this->regularHistory($user);
        $session = $this->makeSession($user, '2026-07-29');
        $session->update(['status' => \App\Enums\SessionStatus::Done]);
        $originalLoad = $session->planned_load;

        (new CycleAdaptationDecorator())->decorate($user, [$session]);

        $this->assertFalse($session->adapted);
        $this->assertEquals($originalLoad, $session->planned_load);
    }
}
