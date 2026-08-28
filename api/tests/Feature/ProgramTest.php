<?php

namespace Tests\Feature;

use App\Enums\Methodology;
use App\Enums\ProgramStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    private function makeProgram(User $user, array $overrides = []): Program
    {
        return Program::create(array_merge([
            'user_id' => $user->id,
            'goal_distance_m' => 5000,
            'goal_time_s' => null,
            'methodology' => Methodology::Vma,
            'level_input' => ['kind' => 'field_test', 'vma_kmh' => 14.0],
            'paces' => ['easy' => [360, 400], 'threshold' => [270, 285]],
            'duration_weeks' => 12,
            'days_per_week' => 3,
            'start_date' => '2026-09-01',
        ], $overrides));
    }

    public function test_status_defaults_to_draft(): void
    {
        $program = $this->makeProgram(User::factory()->create());

        $program->refresh();

        $this->assertSame(ProgramStatus::Draft, $program->status);
    }

    public function test_deleting_user_cascades_to_programs(): void
    {
        $user = User::factory()->create();
        $this->makeProgram($user);

        $user->delete();

        $this->assertDatabaseCount('programs', 0);
    }

    public function test_methodology_and_status_are_cast_to_enums(): void
    {
        $program = $this->makeProgram(User::factory()->create(), [
            'methodology' => Methodology::Vdot,
            'status' => ProgramStatus::Active,
        ]);

        $program->refresh();

        $this->assertSame(Methodology::Vdot, $program->methodology);
        $this->assertSame(ProgramStatus::Active, $program->status);
    }

    public function test_jsonb_columns_round_trip_as_arrays(): void
    {
        $program = $this->makeProgram(User::factory()->create());

        $program->refresh();

        // jsonb normalises numbers (14.0 -> 14) and does not preserve key order,
        // so compare with == semantics rather than ===.
        $this->assertEquals(['kind' => 'field_test', 'vma_kmh' => 14.0], $program->level_input);
        $this->assertEquals(['easy' => [360, 400], 'threshold' => [270, 285]], $program->paces);
    }

    public function test_goal_time_s_is_nullable(): void
    {
        $program = $this->makeProgram(User::factory()->create());

        $program->refresh();

        $this->assertNull($program->goal_time_s);
    }

    public function test_start_date_is_cast_to_carbon(): void
    {
        $program = $this->makeProgram(User::factory()->create());

        $program->refresh();

        $this->assertSame('2026-09-01', $program->start_date->toDateString());
    }

    public function test_feasibility_and_warnings_round_trip_as_arrays(): void
    {
        $program = $this->makeProgram(User::factory()->create(), [
            'feasibility' => ['feasible' => false, 'alert' => 'trop ambitieux'],
            'warnings' => ['Semaine 4 : +30%'],
        ]);

        $program->refresh();

        $this->assertSame(false, $program->feasibility['feasible']);
        $this->assertSame(['Semaine 4 : +30%'], $program->warnings);
    }

    public function test_feasibility_and_warnings_are_nullable(): void
    {
        $program = $this->makeProgram(User::factory()->create());

        $program->refresh();

        $this->assertNull($program->feasibility);
        $this->assertNull($program->warnings);
    }
}
