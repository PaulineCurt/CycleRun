<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\BuildParams;
use App\Services\Training\ProgramBuilder;
use App\Enums\SessionType;
use App\Services\Training\Track;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ProgramBuilderTest extends TestCase
{
    private ProgramBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProgramBuilder();
    }

    private function params(array $overrides = []): BuildParams
    {
        return new BuildParams(
            levelInput: $overrides['levelInput'] ?? ['kind' => 'field_test', 'vma_kmh' => 14.0],
            goalDistanceM: $overrides['goalDistanceM'] ?? 5000,
            goalTimeS: $overrides['goalTimeS'] ?? null,
            durationWeeks: $overrides['durationWeeks'] ?? 12,
            daysPerWeek: $overrides['daysPerWeek'] ?? 3,
            healthFlags: $overrides['healthFlags'] ?? [],
            startDate: CarbonImmutable::parse($overrides['startDate'] ?? '2026-09-07'),
        );
    }

    public function test_builds_a_full_plan_without_touching_the_cycle(): void
    {
        $result = $this->builder->build($this->params());

        $this->assertCount(12, $result->blocks);
        $this->assertCount(12 * 3, $result->sessions);
        $this->assertSame(Track::Continuous, $result->track);
        // no adaptation field anywhere — the builder output is cycle-free by construction
        foreach ($result->sessions as $session) {
            $this->assertNotSame(SessionType::Rest, $session->type);
        }
    }

    public function test_low_vma_runner_gets_the_run_walk_track(): void
    {
        $result = $this->builder->build($this->params([
            'levelInput' => ['kind' => 'field_test', 'vma_kmh' => 9.0],
        ]));

        $this->assertSame(Track::RunWalk, $result->track);
        $this->assertSame(SessionType::RunWalk, $result->sessions[0]->type);
    }

    public function test_same_params_produce_an_identical_plan_hash(): void
    {
        $a = $this->builder->build($this->params());
        $b = $this->builder->build($this->params());

        $this->assertSame($a->hash(), $b->hash());
    }

    public function test_different_days_per_week_produce_different_plans(): void
    {
        $three = $this->builder->build($this->params(['daysPerWeek' => 3]));
        $four = $this->builder->build($this->params(['daysPerWeek' => 4]));

        $this->assertNotSame($three->hash(), $four->hash());
    }

    public function test_aggressive_goal_time_surfaces_a_feasibility_alert_in_warnings(): void
    {
        $result = $this->builder->build($this->params(['goalTimeS' => 1100]));

        $this->assertFalse($result->feasibility->feasible);
        $this->assertNotEmpty($result->warnings);
        $this->assertSame($result->feasibility->alert, $result->warnings[0]);
    }

    public function test_health_flag_caps_are_applied_to_the_generated_sessions(): void
    {
        $result = $this->builder->build($this->params(['healthFlags' => ['max_session_min' => 40]]));

        foreach ($result->sessions as $session) {
            $this->assertLessThanOrEqual(40 * 60, $session->totalSeconds());
        }
    }

    public function test_overall_intensity_stays_close_to_80_20(): void
    {
        $result = $this->builder->build($this->params(['durationWeeks' => 16, 'daysPerWeek' => 4]));

        $hard = array_sum(array_map(fn ($s) => $s->hardSeconds(), $result->sessions));
        $total = array_sum(array_map(fn ($s) => $s->totalSeconds(), $result->sessions));

        $this->assertLessThan(0.25, $hard / $total);
    }

    public function test_rejects_days_per_week_outside_v1_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->build($this->params(['daysPerWeek' => 6]));
    }

    public function test_short_plan_still_produces_a_coherent_structure(): void
    {
        $result = $this->builder->build($this->params(['durationWeeks' => 4, 'daysPerWeek' => 2]));

        $this->assertCount(4, $result->blocks);
        $this->assertCount(8, $result->sessions);
    }
}
