<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\IntensityBalancer;
use App\Services\Training\SessionDraft;
use App\Enums\SessionType;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class IntensityBalancerTest extends TestCase
{
    private function session(SessionType $type, array $load, int $week = 0): SessionDraft
    {
        return new SessionDraft(CarbonImmutable::parse('2026-09-07'), $week, $type, $load, null, null);
    }

    public function test_leaves_a_week_already_within_the_80_20_band_untouched(): void
    {
        $sessions = [
            $this->session(SessionType::Easy, ['mode' => 'duration', 'duration_s' => 2400]),
            $this->session(SessionType::Threshold, ['mode' => 'duration', 'duration_s' => 1800, 'work_s' => 600]),
        ];

        $balanced = (new IntensityBalancer())->balance($sessions);

        $this->assertEquals($sessions, $balanced);
    }

    public function test_adds_easy_volume_when_a_week_drifts_too_hard(): void
    {
        $sessions = [
            $this->session(SessionType::Easy, ['mode' => 'duration', 'duration_s' => 600]),
            $this->session(SessionType::Interval, ['mode' => 'intervals', 'reps' => 10, 'work_s' => 120, 'recovery_s' => 60]),
        ];
        // hard = 1200 s, total = 600 + 1800 = 2400 -> ratio 0.5, way over 0.25

        $balancer = new IntensityBalancer();
        $this->assertGreaterThan(0.25, $balancer->weeklyHardRatio($sessions, 0));

        $balanced = $balancer->balance($sessions);

        $this->assertLessThanOrEqual(0.201, $balancer->weeklyHardRatio($balanced, 0));
        // the hard session is never cut
        $this->assertSame($sessions[1]->plannedLoad, $balanced[1]->plannedLoad);
        // the easy session absorbed the extra volume
        $this->assertGreaterThan(600, $balanced[0]->plannedLoad['duration_s']);
    }

    public function test_never_shortens_the_hard_stimulus(): void
    {
        $sessions = [
            $this->session(SessionType::Easy, ['mode' => 'duration', 'duration_s' => 1200]),
            $this->session(SessionType::Threshold, ['mode' => 'duration', 'duration_s' => 3600, 'work_s' => 3000]),
        ];

        $balanced = (new IntensityBalancer())->balance($sessions);

        $this->assertSame(3000, $balanced[1]->plannedLoad['work_s']);
    }

    public function test_is_deterministic(): void
    {
        $sessions = [
            $this->session(SessionType::Easy, ['mode' => 'duration', 'duration_s' => 600]),
            $this->session(SessionType::Interval, ['mode' => 'intervals', 'reps' => 8, 'work_s' => 120, 'recovery_s' => 60]),
        ];

        $this->assertEquals(
            (new IntensityBalancer())->balance($sessions),
            (new IntensityBalancer())->balance($sessions),
        );
    }
}
