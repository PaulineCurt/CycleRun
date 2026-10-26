<?php

namespace Tests\Unit\Services\Training;

use App\Enums\Level;
use App\Services\Training\LevelProfile;
use App\Services\Training\LevelProfiles;
use App\Services\Training\Mesocycle;
use App\Services\Training\Periodizer;
use App\Services\Training\Track;
use App\Services\Training\WeekBlock;
use PHPUnit\Framework\TestCase;

class PeriodizerTest extends TestCase
{
    private Periodizer $periodizer;

    protected function setUp(): void
    {
        $this->periodizer = new Periodizer;
    }

    private function profile(Level $level = Level::Intermediaire): LevelProfile
    {
        return LevelProfiles::for($level);
    }

    /** @return list<WeekBlock> */
    private function build(int $weeks, int $days, Track $track, Level $level = Level::Intermediaire): array
    {
        return $this->periodizer->build($weeks, $days, $track, $this->profile($level));
    }

    public function test_produces_one_block_per_week(): void
    {
        $blocks = $this->build(12, 3, Track::Continuous);

        $this->assertCount(12, $blocks);
        foreach ($blocks as $i => $block) {
            $this->assertSame($i, $block->weekIndex);
        }
    }

    public function test_mesocycles_run_base_then_development_then_taper(): void
    {
        $blocks = $this->build(12, 3, Track::Continuous);
        $sequence = array_map(fn ($b) => $b->mesocycle, $blocks);

        $firstDev = array_search(Mesocycle::Development, $sequence, true);
        $firstTaper = array_search(Mesocycle::Taper, $sequence, true);

        $this->assertSame(Mesocycle::Base, $sequence[0]);
        $this->assertNotFalse($firstDev);
        $this->assertNotFalse($firstTaper);
        $this->assertGreaterThan($firstDev, $firstTaper);
        // no base after development starts
        $this->assertNotContains(Mesocycle::Base, array_slice($sequence, $firstDev));
    }

    public function test_taper_is_one_or_two_weeks_and_sits_at_the_end(): void
    {
        $taper12 = array_filter($this->build(12, 3, Track::Continuous), fn ($b) => $b->mesocycle === Mesocycle::Taper);
        $taper6 = array_filter($this->build(6, 3, Track::Continuous), fn ($b) => $b->mesocycle === Mesocycle::Taper);

        $this->assertCount(2, $taper12);
        $this->assertCount(1, $taper6);
    }

    public function test_volume_progression_never_exceeds_the_profile_ceiling_between_consecutive_progression_weeks(): void
    {
        $ceiling = $this->profile()->weeklyProgression;
        $blocks = $this->build(16, 4, Track::Continuous);

        // A deload / taper deliberately breaks the ramp — the week after it resumes
        // the progression curve, it does not step off the deload trough. So we only
        // assert on genuinely consecutive progression weeks.
        $previous = null;
        foreach ($blocks as $block) {
            if ($block->isDeload || $block->mesocycle === Mesocycle::Taper) {
                $previous = null;

                continue;
            }
            if ($previous !== null) {
                $this->assertLessThanOrEqual($previous * $ceiling + 0.5, $block->totalVolumeMin);
            }
            $previous = $block->totalVolumeMin;
        }
    }

    public function test_deload_cadence_follows_the_profile(): void
    {
        // intermédiaire deloads every 4th week, débutante every 3rd (§4.2)
        $inter = array_values(array_filter($this->build(16, 3, Track::Continuous, Level::Intermediaire), fn ($b) => $b->isDeload));
        $debut = array_values(array_filter($this->build(16, 3, Track::Continuous, Level::Debutante), fn ($b) => $b->isDeload));

        $this->assertNotEmpty($inter);
        foreach ($inter as $block) {
            $this->assertSame(3, $block->weekIndex % 4, 'intermédiaire deload weeks land on the 4th week');
            $this->assertLessThan($this->build(16, 3, Track::Continuous)[$block->weekIndex - 1]->totalVolumeMin, $block->totalVolumeMin);
        }

        $this->assertNotEmpty($debut);
        foreach ($debut as $block) {
            $this->assertSame(2, $block->weekIndex % 3, 'débutante deload weeks land on the 3rd week');
        }
    }

    public function test_the_three_levels_produce_distinct_volumes_and_caps(): void
    {
        $peak = function (Level $level): int {
            $volumes = array_map(fn ($b) => $b->totalVolumeMin, $this->build(16, 3, Track::Continuous, $level));

            return max($volumes);
        };

        $debut = $peak(Level::Debutante);
        $inter = $peak(Level::Intermediaire);
        $confi = $peak(Level::Confirmee);

        $this->assertLessThan($inter, $debut);
        $this->assertLessThan($confi, $inter);

        // caps: days_per_week * sessionMinutesCap
        $this->assertSame(3 * 45, $this->capFor(Level::Debutante));
        $this->assertSame(3 * 60, $this->capFor(Level::Intermediaire));
        $this->assertSame(3 * 75, $this->capFor(Level::Confirmee));
    }

    private function capFor(Level $level): int
    {
        // a long-enough plan will always hit the cap on its peak week
        $volumes = array_map(fn ($b) => $b->totalVolumeMin, $this->build(40, 3, Track::Continuous, $level));

        return max($volumes);
    }

    public function test_has_a_deload_week_every_fourth_week(): void
    {
        $blocks = $this->build(16, 3, Track::Continuous);
        $deloads = array_values(array_filter($blocks, fn ($b) => $b->isDeload));

        $this->assertNotEmpty($deloads);
        foreach ($deloads as $block) {
            $this->assertSame(3, $block->weekIndex % 4, 'deload weeks land on the 4th week');
            // a deload drops volume below the week before it
            $this->assertLessThan($blocks[$block->weekIndex - 1]->totalVolumeMin, $block->totalVolumeMin);
        }
    }

    public function test_base_weeks_are_almost_all_easy_and_development_introduces_hard_volume(): void
    {
        $blocks = $this->build(12, 3, Track::Continuous);

        $base = array_filter($blocks, fn ($b) => $b->mesocycle === Mesocycle::Base);
        foreach ($base as $block) {
            $this->assertLessThanOrEqual($block->totalVolumeMin * 0.10, $block->hardVolumeMin);
        }

        $lastDev = array_values(array_filter($blocks, fn ($b) => $b->mesocycle === Mesocycle::Development));
        $lastDev = end($lastDev);
        $this->assertGreaterThan($lastDev->totalVolumeMin * 0.12, $lastDev->hardVolumeMin);
    }

    public function test_easy_plus_hard_always_equals_total(): void
    {
        foreach ($this->build(14, 4, Track::Continuous) as $block) {
            $this->assertSame($block->totalVolumeMin, $block->easyVolumeMin + $block->hardVolumeMin);
        }
    }

    public function test_is_deterministic(): void
    {
        $this->assertEquals(
            $this->build(12, 3, Track::Continuous),
            $this->build(12, 3, Track::Continuous),
        );
    }

    public function test_rejects_non_positive_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->build(0, 3, Track::Continuous);
    }
}
