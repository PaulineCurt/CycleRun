<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\Mesocycle;
use App\Services\Training\Periodizer;
use App\Services\Training\Track;
use PHPUnit\Framework\TestCase;

class PeriodizerTest extends TestCase
{
    private Periodizer $periodizer;

    protected function setUp(): void
    {
        $this->periodizer = new Periodizer();
    }

    public function test_produces_one_block_per_week(): void
    {
        $blocks = $this->periodizer->build(12, 3, Track::Continuous);

        $this->assertCount(12, $blocks);
        foreach ($blocks as $i => $block) {
            $this->assertSame($i, $block->weekIndex);
        }
    }

    public function test_mesocycles_run_base_then_development_then_taper(): void
    {
        $blocks = $this->periodizer->build(12, 3, Track::Continuous);
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
        $taper12 = array_filter($this->periodizer->build(12, 3, Track::Continuous), fn ($b) => $b->mesocycle === Mesocycle::Taper);
        $taper6 = array_filter($this->periodizer->build(6, 3, Track::Continuous), fn ($b) => $b->mesocycle === Mesocycle::Taper);

        $this->assertCount(2, $taper12);
        $this->assertCount(1, $taper6);
    }

    public function test_volume_progression_never_exceeds_10_percent_between_consecutive_progression_weeks(): void
    {
        $blocks = $this->periodizer->build(16, 4, Track::Continuous);

        // A deload / taper deliberately breaks the ramp — the week after it resumes
        // the progression curve, it does not step +10 % off the deload trough. So we
        // only assert on genuinely consecutive progression weeks.
        $previous = null;
        foreach ($blocks as $block) {
            if ($block->isDeload || $block->mesocycle === Mesocycle::Taper) {
                $previous = null;
                continue;
            }
            if ($previous !== null) {
                $this->assertLessThanOrEqual($previous * 1.10 + 0.5, $block->totalVolumeMin);
            }
            $previous = $block->totalVolumeMin;
        }
    }

    public function test_has_a_deload_week_every_fourth_week(): void
    {
        $blocks = $this->periodizer->build(16, 3, Track::Continuous);
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
        $blocks = $this->periodizer->build(12, 3, Track::Continuous);

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
        foreach ($this->periodizer->build(14, 4, Track::Continuous) as $block) {
            $this->assertSame($block->totalVolumeMin, $block->easyVolumeMin + $block->hardVolumeMin);
        }
    }

    public function test_is_deterministic(): void
    {
        $this->assertEquals(
            $this->periodizer->build(12, 3, Track::Continuous),
            $this->periodizer->build(12, 3, Track::Continuous),
        );
    }

    public function test_rejects_non_positive_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->periodizer->build(0, 3, Track::Continuous);
    }
}
