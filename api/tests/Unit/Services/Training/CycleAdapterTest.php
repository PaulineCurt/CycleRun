<?php

namespace Tests\Unit\Services\Training;

use App\Enums\SessionType;
use App\Services\Cycle\Phase;
use App\Services\Training\CycleAdapter;
use App\Services\Training\PhaseSnapshot;
use PHPUnit\Framework\TestCase;

class CycleAdapterTest extends TestCase
{
    private CycleAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new CycleAdapter();
    }

    public function test_no_phase_and_no_pain_yields_no_adaptation(): void
    {
        $this->assertNull($this->adapter->adapt(PhaseSnapshot::none(), SessionType::Easy));
    }

    public function test_rest_days_are_never_adapted(): void
    {
        $this->assertNull($this->adapter->adapt(new PhaseSnapshot(Phase::Luteal, 0.9, false), SessionType::Rest));
        $this->assertNull($this->adapter->adapt(new PhaseSnapshot(Phase::Menstrual, 0.9, true), SessionType::Rest));
    }

    public function test_declared_pain_always_wins_regardless_of_phase(): void
    {
        $snapshot = new PhaseSnapshot(Phase::Follicular, 0.9, true);

        $adaptation = $this->adapter->adapt($snapshot, SessionType::Interval);

        $this->assertSame('rest_or_postpone', $adaptation->rule);
        $this->assertSame(-1.0, $adaptation->intensityDelta);
        $this->assertSame('follicular', $adaptation->phase);
    }

    public function test_low_confidence_still_adapts_but_at_half_strength_and_labelled_as_an_estimate(): void
    {
        $full = $this->adapter->adapt(new PhaseSnapshot(Phase::Luteal, 0.9, false), SessionType::Threshold);
        $tentative = $this->adapter->adapt(new PhaseSnapshot(Phase::Luteal, 0.3, false), SessionType::Threshold);

        // same phase rule — not a no-op
        $this->assertSame('ease_off', $tentative->rule);
        // but softer than the confident version, and never zero
        $this->assertSame($full->intensityDelta / 2, $tentative->intensityDelta);
        $this->assertLessThan(0.0, $tentative->intensityDelta);
        $this->assertStringContainsString('estimation', $tentative->headline);
    }

    public function test_menstrual_phase_lightens_the_session(): void
    {
        $adaptation = $this->adapter->adapt(new PhaseSnapshot(Phase::Menstrual, 0.9, false), SessionType::Easy);

        $this->assertSame('lighten', $adaptation->rule);
        $this->assertLessThan(0, $adaptation->intensityDelta);
    }

    public function test_luteal_phase_eases_off_less_than_menstrual(): void
    {
        $luteal = $this->adapter->adapt(new PhaseSnapshot(Phase::Luteal, 0.9, false), SessionType::Easy);
        $menstrual = $this->adapter->adapt(new PhaseSnapshot(Phase::Menstrual, 0.9, false), SessionType::Easy);

        $this->assertSame('ease_off', $luteal->rule);
        $this->assertGreaterThan($menstrual->intensityDelta, $luteal->intensityDelta);
    }

    public function test_follicular_and_ovulation_do_not_reduce_load(): void
    {
        $follicular = $this->adapter->adapt(new PhaseSnapshot(Phase::Follicular, 0.9, false), SessionType::Easy);
        $ovulation = $this->adapter->adapt(new PhaseSnapshot(Phase::Ovulation, 0.9, false), SessionType::Interval);

        $this->assertSame(0.0, $follicular->intensityDelta);
        $this->assertSame('quality_window', $ovulation->rule);
        $this->assertSame(0.0, $ovulation->intensityDelta);
    }

    public function test_is_deterministic(): void
    {
        $snapshot = new PhaseSnapshot(Phase::Luteal, 0.9, false);

        $this->assertEquals(
            $this->adapter->adapt($snapshot, SessionType::Easy),
            $this->adapter->adapt($snapshot, SessionType::Easy),
        );
    }

    public function test_apply_to_shortens_a_duration_session_in_the_luteal_phase(): void
    {
        $adaptation = $this->adapter->adapt(new PhaseSnapshot(Phase::Luteal, 0.9, false), SessionType::Easy);

        $applied = $this->adapter->applyTo(['mode' => 'duration', 'duration_s' => 3600], 360, 400, $adaptation);

        $this->assertSame(3060, $applied['load']['duration_s']); // -15 %
        $this->assertFalse($applied['postponed']);
        $this->assertGreaterThan(400, $applied['pace_max_s']); // "allure en aisance"
    }

    public function test_apply_to_drops_reps_for_an_interval_session_in_the_menstrual_phase(): void
    {
        $adaptation = $this->adapter->adapt(new PhaseSnapshot(Phase::Menstrual, 0.9, false), SessionType::Interval);

        $applied = $this->adapter->applyTo(
            ['mode' => 'intervals', 'reps' => 8, 'work_s' => 120, 'recovery_s' => 60],
            240,
            270,
            $adaptation,
        );

        $this->assertSame(6, $applied['load']['reps']); // 8 * 0.75
    }

    public function test_apply_to_leaves_the_load_untouched_but_flags_postponement_on_pain(): void
    {
        $adaptation = $this->adapter->adapt(new PhaseSnapshot(null, 0.0, true), SessionType::Threshold);
        $base = ['mode' => 'duration', 'duration_s' => 2400, 'work_s' => 1200];

        $applied = $this->adapter->applyTo($base, 300, 330, $adaptation);

        $this->assertSame($base, $applied['load']);
        $this->assertTrue($applied['postponed']);
    }

    public function test_apply_to_is_a_no_op_for_follicular(): void
    {
        $adaptation = $this->adapter->adapt(new PhaseSnapshot(Phase::Follicular, 0.9, false), SessionType::Easy);
        $base = ['mode' => 'duration', 'duration_s' => 3600];

        $applied = $this->adapter->applyTo($base, 360, 400, $adaptation);

        $this->assertSame($base, $applied['load']);
        $this->assertSame(400, $applied['pace_max_s']);
        $this->assertFalse($applied['postponed']);
    }
}
