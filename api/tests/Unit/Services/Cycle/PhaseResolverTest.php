<?php

namespace Tests\Unit\Services\Cycle;

use App\Services\Cycle\DurationEstimate;
use App\Services\Cycle\EstimateSource;
use App\Services\Cycle\Phase;
use App\Services\Cycle\PhaseResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PhaseResolverTest extends TestCase
{
    private function estimate(float $days, EstimateSource $source = EstimateSource::Measured): DurationEstimate
    {
        return new DurationEstimate($days, null, 3, $source);
    }

    public function test_ovulation_is_counted_backward_from_predicted_next_start_on_a_28_day_cycle(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');

        $result = $resolver->resolve(
            date: CarbonImmutable::parse('2026-01-15'),
            cycleStart: $cycleStart,
            cycleLength: $this->estimate(28.0),
            mensesLength: $this->estimate(5.0),
        );

        // predictedNextStart = day 29 (2026-01-29), ovulation = day 29-14 = day 15 (2026-01-15).
        $this->assertSame('2026-01-15', $result->ovulationWindowStart->addDays(2)->toDateString());
        $this->assertSame(Phase::Ovulation, $result->phase);
    }

    public function test_a_longer_cycle_stretches_the_follicular_phase_while_luteal_stays_14_days(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');

        $result = $resolver->resolve(
            date: CarbonImmutable::parse('2026-01-21'),
            cycleStart: $cycleStart,
            cycleLength: $this->estimate(34.0),
            mensesLength: $this->estimate(5.0),
        );

        // predictedNextStart = day 35 (2026-02-04), ovulation = day 35-14 = day 21 (2026-01-21).
        $this->assertSame('2026-02-04', $result->predictedNextStart->toDateString());
        $this->assertSame(Phase::Ovulation, $result->phase);

        // Luteal phase stays capped at 14 days before the next start, regardless of cycle length.
        $luteal = $resolver->resolve(
            date: $result->predictedNextStart->subDays(1),
            cycleStart: $cycleStart,
            cycleLength: $this->estimate(34.0),
            mensesLength: $this->estimate(5.0),
        );
        $this->assertSame(Phase::Luteal, $luteal->phase);

        // The day after menses end but before the ovulation window is follicular
        // (this phase absorbs the elongation of the cycle).
        $follicular = $resolver->resolve(
            date: CarbonImmutable::parse('2026-01-10'),
            cycleStart: $cycleStart,
            cycleLength: $this->estimate(34.0),
            mensesLength: $this->estimate(5.0),
        );
        $this->assertSame(Phase::Follicular, $follicular->phase);
    }

    public function test_menstrual_phase_spans_cycle_start_to_start_plus_duration_minus_one(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $mensesLength = $this->estimate(5.0);
        $cycleLength = $this->estimate(28.0);

        $lastMenstrualDay = $resolver->resolve(CarbonImmutable::parse('2026-01-05'), $cycleStart, $cycleLength, $mensesLength);
        $firstFollicularDay = $resolver->resolve(CarbonImmutable::parse('2026-01-06'), $cycleStart, $cycleLength, $mensesLength);

        $this->assertSame(Phase::Menstrual, $lastMenstrualDay->phase);
        $this->assertNotSame(Phase::Menstrual, $firstFollicularDay->phase);
    }

    public function test_ovulation_is_rendered_as_a_window_not_a_single_date(): void
    {
        $resolver = new PhaseResolver();

        $result = $resolver->resolve(
            date: CarbonImmutable::parse('2026-01-15'),
            cycleStart: CarbonImmutable::parse('2026-01-01'),
            cycleLength: $this->estimate(28.0),
            mensesLength: $this->estimate(5.0),
        );

        $this->assertTrue($result->ovulationWindowEnd->greaterThan($result->ovulationWindowStart));
    }

    public function test_low_confidence_toggles_with_estimate_source(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $date = CarbonImmutable::parse('2026-01-10');
        $mensesLength = $this->estimate(5.0);

        $measured = $resolver->resolve($date, $cycleStart, $this->estimate(28.0, EstimateSource::Measured), $mensesLength);
        $declared = $resolver->resolve($date, $cycleStart, $this->estimate(28.0, EstimateSource::Declared), $mensesLength);
        $default = $resolver->resolve($date, $cycleStart, $this->estimate(28.0, EstimateSource::Default), $mensesLength);

        $this->assertFalse($measured->lowConfidence);
        $this->assertTrue($declared->lowConfidence);
        $this->assertTrue($default->lowConfidence);
    }

    public function test_resolution_is_deterministic_for_the_same_inputs(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $date = CarbonImmutable::parse('2026-01-15');
        $cycleLength = $this->estimate(28.0);
        $mensesLength = $this->estimate(5.0);

        $first = $resolver->resolve($date, $cycleStart, $cycleLength, $mensesLength);
        $second = $resolver->resolve($date, $cycleStart, $cycleLength, $mensesLength);

        $this->assertSame($first->phase, $second->phase);
        $this->assertTrue($first->predictedNextStart->equalTo($second->predictedNextStart));
        $this->assertTrue($first->ovulationWindowStart->equalTo($second->ovulationWindowStart));
        $this->assertTrue($first->ovulationWindowEnd->equalTo($second->ovulationWindowEnd));
    }
}
