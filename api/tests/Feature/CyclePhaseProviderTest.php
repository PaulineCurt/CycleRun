<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\DayLog;
use App\Models\User;
use App\Services\Cycle\Phase;
use App\Services\Training\CyclePhaseProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CyclePhaseProviderTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRegularHistory(): User
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);

        foreach (['2026-05-04', '2026-06-01', '2026-06-29', '2026-07-27'] as $start) {
            Cycle::create([
                'user_id' => $user->id,
                'started_on' => $start,
                'menses_ended_on' => CarbonImmutable::parse($start)->addDays(4)->toDateString(),
            ]);
        }

        return $user;
    }

    public function test_no_cycles_returns_an_empty_snapshot(): void
    {
        $snapshot = (new CyclePhaseProvider())->forDate(User::factory()->create(), CarbonImmutable::parse('2026-07-29'));

        $this->assertNull($snapshot->phase);
        $this->assertSame(0.0, $snapshot->confidence);
        $this->assertFalse($snapshot->painFlag);
    }

    public function test_regular_history_resolves_a_phase_with_high_confidence(): void
    {
        $snapshot = (new CyclePhaseProvider())->forDate($this->userWithRegularHistory(), CarbonImmutable::parse('2026-07-29'));

        $this->assertSame(Phase::Menstrual, $snapshot->phase);
        $this->assertGreaterThanOrEqual(0.8, $snapshot->confidence);
    }

    public function test_pain_log_at_threshold_sets_the_pain_flag(): void
    {
        $user = $this->userWithRegularHistory();
        DayLog::create(['user_id' => $user->id, 'date' => '2026-07-29', 'pain' => 2]);

        $snapshot = (new CyclePhaseProvider())->forDate($user, CarbonImmutable::parse('2026-07-29'));

        $this->assertTrue($snapshot->painFlag);
    }

    public function test_pain_below_threshold_does_not_set_the_flag(): void
    {
        $user = $this->userWithRegularHistory();
        DayLog::create(['user_id' => $user->id, 'date' => '2026-07-29', 'pain' => 1]);

        $snapshot = (new CyclePhaseProvider())->forDate($user, CarbonImmutable::parse('2026-07-29'));

        $this->assertFalse($snapshot->painFlag);
    }

    public function test_a_future_date_projects_the_cycle_forward_instead_of_the_follicular_catch_all(): void
    {
        // Last logged cycle starts 2026-07-27; ~28-day cycle. A date in what would be
        // the menstrual window of the *second* projected cycle must resolve to
        // menstrual, not fall through to follicular.
        $snapshot = (new CyclePhaseProvider())->forDate(
            $this->userWithRegularHistory(),
            CarbonImmutable::parse('2026-09-22'),
        );

        $this->assertSame(Phase::Menstrual, $snapshot->phase);
        $this->assertGreaterThanOrEqual(0.8, $snapshot->confidence);
    }

    public function test_confidence_is_usable_before_the_cycle_is_proven_regular(): void
    {
        // Two logged cycles: length is measurable, but regularity is still "unknown".
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-06-29', 'menses_ended_on' => '2026-07-03']);
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-07-27', 'menses_ended_on' => '2026-07-31']);

        $snapshot = (new CyclePhaseProvider())->forDate($user, CarbonImmutable::parse('2026-07-29'));

        $this->assertNotNull($snapshot->phase);
        $this->assertGreaterThanOrEqual(0.5, $snapshot->confidence);
    }
}
