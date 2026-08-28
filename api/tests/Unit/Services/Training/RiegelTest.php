<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\Riegel;
use PHPUnit\Framework\TestCase;

class RiegelTest extends TestCase
{
    public function test_5k_20_00_projects_to_about_41_42_over_10k(): void
    {
        // 1200 · 2^1.06 ≈ 2502 s = 41:42
        $this->assertEqualsWithDelta(2502, Riegel::predict(1200, 5000, 10000), 1);
    }

    public function test_10k_40_00_projects_to_about_19_11_over_5k(): void
    {
        // 2400 · 0.5^1.06 ≈ 1151 s = 19:11
        $this->assertEqualsWithDelta(1151, Riegel::predict(2400, 10000, 5000), 1);
    }

    public function test_prediction_is_symmetric_round_trip_within_a_second(): void
    {
        $up = Riegel::predict(1200, 5000, 10000);
        $back = Riegel::predict($up, 10000, 5000);

        $this->assertEqualsWithDelta(1200, $back, 1);
    }

    public function test_same_distance_returns_same_time(): void
    {
        $this->assertSame(1200, Riegel::predict(1200, 5000, 5000));
    }

    public function test_non_positive_input_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Riegel::predict(1200, 0, 5000);
    }
}
