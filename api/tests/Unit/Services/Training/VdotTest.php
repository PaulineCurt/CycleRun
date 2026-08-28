<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\Vdot;
use PHPUnit\Framework\TestCase;

class VdotTest extends TestCase
{
    /**
     * Anchor: 5 km in 19:57 is VDOT 50 in Daniels' table. The raw D&G equations
     * must land on it tightly.
     */
    public function test_vdot_for_5k_in_19_57_is_about_50(): void
    {
        $this->assertEqualsWithDelta(50.0, Vdot::fromRace(5000, 1197), 0.5);
    }

    public function test_vdot_for_5k_in_25_00_is_about_38(): void
    {
        $this->assertEqualsWithDelta(38.3, Vdot::fromRace(5000, 1500), 1.0);
    }

    /**
     * On longer efforts the raw equations run ~1.5–2 VDOT above Daniels' adjusted
     * book table (10 km @ 40:00 ≈ 50 in the book). Pinned so the drift is a known,
     * deliberate property rather than a silent bug.
     */
    public function test_vdot_for_10k_in_40_00_tracks_daniels_within_known_drift(): void
    {
        $this->assertEqualsWithDelta(50.0, Vdot::fromRace(10000, 2400), 2.0);
    }

    public function test_vma_from_vdot_50_is_about_15_6_kmh(): void
    {
        $this->assertEqualsWithDelta(15.65, Vdot::toVmaKmh(50.0), 0.2);
    }

    public function test_race_result_round_trips_to_a_sane_vma(): void
    {
        $vma = Vdot::toVmaKmh(Vdot::fromRace(5000, 1197));

        $this->assertGreaterThan(15.0, $vma);
        $this->assertLessThan(16.5, $vma);
    }

    public function test_faster_race_yields_higher_vdot(): void
    {
        $this->assertGreaterThan(
            Vdot::fromRace(5000, 1500),
            Vdot::fromRace(5000, 1200),
        );
    }

    public function test_non_positive_input_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Vdot::fromRace(5000, 0);
    }
}
