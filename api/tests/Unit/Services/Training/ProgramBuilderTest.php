<?php

namespace Tests\Unit\Services\Training;

use App\Enums\Level;
use App\Enums\SessionType;
use App\Services\Training\BuildParams;
use App\Services\Training\ProgramBuilder;
use App\Services\Training\Track;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ProgramBuilderTest extends TestCase
{
    private ProgramBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProgramBuilder;
    }

    private function params(array $overrides = []): BuildParams
    {
        return new BuildParams(
            levelInput: array_key_exists('levelInput', $overrides)
                ? $overrides['levelInput']
                : ['kind' => 'field_test', 'vma_kmh' => 14.0],
            level: $overrides['level'] ?? Level::Intermediaire,
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

    public function test_the_declared_level_decides_the_track_not_the_vma(): void
    {
        // débutante starts course/marche...
        $debutante = $this->builder->build($this->params(['level' => Level::Debutante, 'levelInput' => null]));
        $this->assertSame(Track::RunWalk, $debutante->track);
        $this->assertSame(SessionType::RunWalk, $debutante->sessions[0]->type);

        // ...while a low-VMA intermédiaire still runs continuously (level decides)
        $intermediaire = $this->builder->build($this->params([
            'level' => Level::Intermediaire,
            'levelInput' => ['kind' => 'field_test', 'vma_kmh' => 9.0],
        ]));
        $this->assertSame(Track::Continuous, $intermediaire->track);
    }

    public function test_same_params_and_level_produce_an_identical_plan_hash(): void
    {
        foreach (Level::cases() as $level) {
            $a = $this->builder->build($this->params(['level' => $level, 'levelInput' => null]));
            $b = $this->builder->build($this->params(['level' => $level, 'levelInput' => null]));

            $this->assertSame($a->hash(), $b->hash(), "determinism holds for {$level->value}");
        }
    }

    public function test_the_three_levels_produce_distinct_plans_and_volumes(): void
    {
        $volume = [];
        $hashes = [];
        foreach (Level::cases() as $level) {
            $result = $this->builder->build($this->params([
                'level' => $level,
                'levelInput' => ['kind' => 'field_test', 'vma_kmh' => 14.0],
                'durationWeeks' => 16,
            ]));
            $hashes[$level->value] = $result->hash();
            $volume[$level->value] = array_sum(array_map(fn ($s) => $s->totalSeconds(), $result->sessions));
        }

        $this->assertCount(3, array_unique($hashes));
        $this->assertLessThan($volume['intermediaire'], $volume['debutante']);
        $this->assertLessThan($volume['confirmee'], $volume['intermediaire']);
    }

    public function test_pace_zones_and_the_80_20_target_are_identical_across_levels(): void
    {
        // §4.3: PaceZone fractions and the intensity target are universal — the
        // VMA already personalises paces, the level must not touch them.
        $bands = [];
        $ratios = [];
        foreach (Level::cases() as $level) {
            $result = $this->builder->build($this->params([
                'level' => $level,
                'levelInput' => ['kind' => 'field_test', 'vma_kmh' => 14.0],
                'durationWeeks' => 16,
                'daysPerWeek' => 4,
            ]));

            $easy = null;
            foreach ($result->sessions as $s) {
                if ($s->type === SessionType::Easy) {
                    $easy = [$s->targetPaceMinS, $s->targetPaceMaxS];
                    break;
                }
            }
            $bands[$level->value] = $easy;

            $hard = array_sum(array_map(fn ($s) => $s->hardSeconds(), $result->sessions));
            $total = array_sum(array_map(fn ($s) => $s->totalSeconds(), $result->sessions));
            $ratios[$level->value] = round($hard / $total, 3);
        }

        // same VMA => same easy band, whatever the level
        $this->assertSame($bands['debutante'], $bands['intermediaire']);
        $this->assertSame($bands['intermediaire'], $bands['confirmee']);
        // 80/20 stays within the ceiling for every level
        foreach ($ratios as $ratio) {
            $this->assertLessThan(0.25, $ratio);
        }
    }

    public function test_a_plan_never_flags_its_own_volume_progression_as_overload(): void
    {
        // OVERLOAD_PROGRESSION must read the profile's weeklyProgression, not a
        // hard-coded 1.10 — otherwise the generator emits weeks its own checker
        // flags (§4.2). Confirmée progresses at 1.11.
        foreach (Level::cases() as $level) {
            $result = $this->builder->build($this->params([
                'level' => $level,
                'levelInput' => ['kind' => 'field_test', 'vma_kmh' => 14.0],
                'durationWeeks' => 16,
            ]));

            $overloadWarnings = array_filter(
                $result->warnings,
                fn ($w) => str_contains($w, 'progression de volume'),
            );
            $this->assertEmpty($overloadWarnings, "no self-inflicted overload warning for {$level->value}");
        }
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
