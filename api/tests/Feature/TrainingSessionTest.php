<?php

namespace Tests\Feature;

use App\Enums\Methodology;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\Program;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makeProgram(): Program
    {
        return Program::create([
            'user_id' => User::factory()->create()->id,
            'goal_distance_m' => 5000,
            'methodology' => Methodology::Vma,
            'level_input' => ['kind' => 'field_test', 'vma_kmh' => 14.0],
            'paces' => ['easy' => [360, 400]],
            'duration_weeks' => 12,
            'days_per_week' => 3,
            'start_date' => '2026-09-01',
        ]);
    }

    private function makeSession(Program $program, array $overrides = []): TrainingSession
    {
        return TrainingSession::create(array_merge([
            'program_id' => $program->id,
            'planned_date' => '2026-09-02',
            'week_index' => 0,
            'type' => SessionType::Easy,
            'planned_load' => ['duration_min' => 30],
            'base_generated' => ['type' => 'easy', 'duration_min' => 30],
        ], $overrides));
    }

    public function test_status_defaults_to_planned(): void
    {
        $session = $this->makeSession($this->makeProgram());

        $session->refresh();

        $this->assertSame(SessionStatus::Planned, $session->status);
    }

    public function test_deleting_program_cascades_to_sessions(): void
    {
        $program = $this->makeProgram();
        $this->makeSession($program);

        $program->delete();

        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_deleting_user_cascades_through_program_to_sessions(): void
    {
        $program = $this->makeProgram();
        $this->makeSession($program);

        $program->user->delete();

        $this->assertDatabaseCount('training_sessions', 0);
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_type_and_status_are_cast_to_enums(): void
    {
        $session = $this->makeSession($this->makeProgram(), [
            'type' => SessionType::RunWalk,
            'status' => SessionStatus::Done,
        ]);

        $session->refresh();

        $this->assertSame(SessionType::RunWalk, $session->type);
        $this->assertSame(SessionStatus::Done, $session->status);
    }

    public function test_adaptation_and_actual_are_nullable(): void
    {
        $session = $this->makeSession($this->makeProgram());

        $session->refresh();

        $this->assertNull($session->adaptation);
        $this->assertNull($session->actual);
    }

    public function test_pace_bounds_are_nullable_and_cast_to_int(): void
    {
        $session = $this->makeSession($this->makeProgram(), [
            'target_pace_min_s' => 360,
            'target_pace_max_s' => 400,
        ]);

        $session->refresh();

        $this->assertSame(360, $session->target_pace_min_s);
        $this->assertSame(400, $session->target_pace_max_s);
    }

    public function test_jsonb_columns_round_trip_as_arrays(): void
    {
        $session = $this->makeSession($this->makeProgram(), [
            'adaptation' => ['phase' => 'luteal', 'confidence' => 0.4, 'rule' => 'reduced_volume'],
        ]);

        $session->refresh();

        // jsonb does not preserve object key order, so compare with == semantics.
        $this->assertEquals(['duration_min' => 30], $session->planned_load);
        $this->assertEquals(['phase' => 'luteal', 'confidence' => 0.4, 'rule' => 'reduced_volume'], $session->adaptation);
    }
}
