<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Program;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'level' => 'intermediaire',
            'level_input' => ['kind' => 'field_test', 'vma_kmh' => 14.0],
            'duration_weeks' => 12,
            'days_per_week' => 3,
            'start_date' => '2026-09-07',
        ], $overrides);
    }

    public function test_guests_cannot_access_programs(): void
    {
        $this->getJson('/api/programs')->assertUnauthorized();
    }

    public function test_a_user_can_generate_and_persist_a_program(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/programs', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonCount(36, 'sessions'); // 12 weeks x 3 days

        $this->assertDatabaseCount('programs', 1);
        $this->assertDatabaseCount('training_sessions', 36);
    }

    public function test_persisted_sessions_snapshot_base_generated_and_start_unadapted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/programs', $this->validPayload())->assertCreated();

        $session = Program::first()->sessions()->first();
        $this->assertArrayHasKey('type', $session->base_generated);
        $this->assertNull($session->adaptation);
    }

    public function test_generation_is_deterministic_same_payload_same_plan(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $a = $this->postJson('/api/programs', $this->validPayload())->json('sessions');
        $b = $this->postJson('/api/programs', $this->validPayload())->json('sessions');

        $this->assertSame(
            array_map(fn ($s) => [$s['planned_date'], $s['type'], $s['planned_load']], $a),
            array_map(fn ($s) => [$s['planned_date'], $s['type'], $s['planned_load']], $b),
        );
    }

    public function test_an_aggressive_goal_time_returns_a_feasibility_alert(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/programs', $this->validPayload(['goal_time_s' => 1100]));

        $response->assertCreated()->assertJsonPath('feasibility.feasible', false);
        $this->assertNotNull($response->json('feasibility.alert'));
        $this->assertNotEmpty($response->json('warnings'));
    }

    public function test_feasibility_and_warnings_persist_and_come_back_on_show(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/programs', $this->validPayload(['goal_time_s' => 1100]))->json('id');

        // re-fetched later (not just on the generation response)
        $response = $this->getJson("/api/programs/{$id}")->assertOk();

        $this->assertFalse($response->json('feasibility.feasible'));
        $this->assertNotNull($response->json('feasibility.alert'));
        $this->assertNotEmpty($response->json('warnings'));
    }

    public function test_race_result_input_is_recorded_as_vdot_methodology(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/programs', $this->validPayload([
            'level_input' => ['kind' => 'race_result', 'distance_m' => 5000, 'time_s' => 1500],
        ]));

        $response->assertCreated()->assertJsonPath('methodology', 'vdot');
    }

    public function test_invalid_level_input_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/programs', $this->validPayload([
            'level_input' => ['kind' => 'field_test'],
        ]))->assertStatus(422);
    }

    public function test_days_per_week_outside_v1_range_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/programs', $this->validPayload(['days_per_week' => 6]))->assertStatus(422);
    }

    public function test_level_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = $this->validPayload();
        unset($payload['level']);

        $this->postJson('/api/programs', $payload)->assertStatus(422);
    }

    public function test_a_debutante_can_generate_a_plan_with_no_level_input(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = $this->validPayload(['level' => 'debutante']);
        unset($payload['level_input']);

        $response = $this->postJson('/api/programs', $payload)
            ->assertCreated()
            ->assertJsonPath('level', 'debutante')
            ->assertJsonPath('vma_source', 'default');

        // course/marche protocol, and no pace band on those sessions
        $this->assertSame('run_walk', $response->json('sessions.0.type'));
        $this->assertNull($response->json('sessions.0.target_pace_min_s'));
        $this->assertNull(Program::first()->level_input);
    }

    public function test_level_and_vma_source_come_back_on_show(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');

        $this->getJson("/api/programs/{$id}")->assertOk()
            ->assertJsonPath('level', 'intermediaire')
            ->assertJsonPath('vma_source', 'declared');
    }

    public function test_generation_is_deterministic_per_level(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $pick = fn ($sessions) => array_map(
            fn ($s) => [$s['planned_date'], $s['type'], $s['planned_load']],
            $sessions,
        );

        foreach (['debutante', 'intermediaire', 'confirmee'] as $level) {
            $a = $this->postJson('/api/programs', $this->validPayload(['level' => $level]))->json('sessions');
            $b = $this->postJson('/api/programs', $this->validPayload(['level' => $level]))->json('sessions');
            $this->assertSame($pick($a), $pick($b), "deterministic for {$level}");
        }
    }

    public function test_level_can_be_edited_without_regenerating_the_plan(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');
        $before = $this->getJson("/api/programs/{$id}")->json('sessions');

        $this->patchJson("/api/programs/{$id}", ['level' => 'confirmee'])
            ->assertOk()
            ->assertJsonPath('level', 'confirmee');

        $after = $this->getJson("/api/programs/{$id}")->json('sessions');
        $this->assertSame(
            array_map(fn ($s) => [$s['planned_date'], $s['type']], $before),
            array_map(fn ($s) => [$s['planned_date'], $s['type']], $after),
            'the plan in flight is untouched by a level edit',
        );
    }

    public function test_show_applies_the_cycle_adaptation_overlay_at_read_time(): void
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        foreach (['2026-05-04', '2026-06-01', '2026-06-29', '2026-07-27'] as $start) {
            Cycle::create([
                'user_id' => $user->id,
                'started_on' => $start,
                'menses_ended_on' => CarbonImmutable::parse($start)->addDays(4)->toDateString(),
            ]);
        }
        Sanctum::actingAs($user);

        // a plan whose first week covers the last logged cycle's menstrual days
        $programId = $this->postJson('/api/programs', $this->validPayload(['start_date' => '2026-07-27']))
            ->assertCreated()->json('id');

        $sessions = $this->getJson("/api/programs/{$programId}")->assertOk()->json('sessions');
        $adapted = array_filter($sessions, fn ($s) => $s['adaptation'] !== null);

        $this->assertNotEmpty($adapted);
        // sessions carry where the cycle is that day
        $withPhase = array_filter($sessions, fn ($s) => ($s['cycle']['phase'] ?? null) !== null);
        $this->assertNotEmpty($withPhase);
        // and the stored rows were not mutated
        $this->assertNull(Program::find($programId)->sessions()->first()->adaptation);
    }

    public function test_acting_on_a_session_freezes_its_cycle_adaptation_onto_the_row(): void
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        foreach (['2026-05-04', '2026-06-01', '2026-06-29', '2026-07-27'] as $start) {
            Cycle::create([
                'user_id' => $user->id,
                'started_on' => $start,
                'menses_ended_on' => CarbonImmutable::parse($start)->addDays(4)->toDateString(),
            ]);
        }
        Sanctum::actingAs($user);
        $programId = $this->postJson('/api/programs', $this->validPayload(['start_date' => '2026-07-27']))->json('id');

        // first session falls in the menstrual window of the last logged cycle
        $sessionId = $this->getJson("/api/programs/{$programId}")->json('sessions.0.id');
        $this->assertNull(TrainingSession::find($sessionId)->adaptation);

        $this->patchJson("/api/training-sessions/{$sessionId}", ['status' => 'done'])->assertOk();

        $frozen = TrainingSession::find($sessionId);
        $this->assertNotNull($frozen->adaptation);
        $this->assertSame('menstrual', $frozen->adaptation['phase']);
    }

    public function test_showing_a_plan_does_not_run_a_cycle_query_per_session(): void
    {
        $user = User::factory()->create(['birth_date' => '1994-06-15']);
        foreach (['2026-05-04', '2026-06-01', '2026-06-29', '2026-07-27'] as $start) {
            Cycle::create([
                'user_id' => $user->id,
                'started_on' => $start,
                'menses_ended_on' => CarbonImmutable::parse($start)->addDays(4)->toDateString(),
            ]);
        }
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id'); // 12 x 3 = 36 sessions

        $count = 0;
        \DB::listen(function () use (&$count) {
            $count++;
        });
        $this->getJson("/api/programs/{$id}")->assertOk();

        // Decoration loads cycles/exclusions/day-logs once for the whole batch, so the
        // query count is a small constant, not ~36+ (one lookup per session).
        $this->assertLessThan(15, $count, "expected a bounded query count, got {$count}");
    }

    public function test_a_user_cannot_see_another_users_program(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/programs/{$id}")->assertNotFound();
    }

    public function test_status_can_be_advanced_and_program_can_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');

        $this->patchJson("/api/programs/{$id}", ['status' => 'active'])
            ->assertOk()->assertJsonPath('status', 'active');

        $this->deleteJson("/api/programs/{$id}")->assertNoContent();
        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_a_session_outcome_can_be_recorded(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');
        $sessionId = $this->getJson("/api/programs/{$id}")->json('sessions.0.id');

        $this->patchJson("/api/training-sessions/{$sessionId}", [
            'status' => 'done',
            'actual' => ['rpe' => 6, 'note' => 'ok'],
        ])->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('actual.rpe', 6);
    }

    public function test_actual_is_merged_not_overwritten_across_patches(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');
        $sessionId = $this->getJson("/api/programs/{$id}")->json('sessions.0.id');

        $this->patchJson("/api/training-sessions/{$sessionId}", ['actual' => ['distance_m' => 5100]]);
        $response = $this->patchJson("/api/training-sessions/{$sessionId}", [
            'status' => 'done',
            'actual' => ['rpe' => 7],
        ])->assertOk();

        $this->assertSame(7, $response->json('actual.rpe'));
        $this->assertSame(5100, $response->json('actual.distance_m')); // not lost
    }

    public function test_a_single_session_can_be_fetched_with_its_adaptation_overlay(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');
        $sessionId = $this->getJson("/api/programs/{$id}")->json('sessions.0.id');

        $this->getJson("/api/training-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('id', $sessionId)
            ->assertJsonStructure(['id', 'type', 'planned_load', 'status', 'adaptation', 'actual']);
    }

    public function test_a_user_cannot_touch_another_users_session(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/programs', $this->validPayload())->json('id');
        $sessionId = $this->getJson("/api/programs/{$id}")->json('sessions.0.id');

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/training-sessions/{$sessionId}", ['status' => 'done'])->assertNotFound();
    }
}
