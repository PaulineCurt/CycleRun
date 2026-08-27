<?php

namespace Tests\Unit\Services\Cycle;

use App\Services\Cycle\DurationEstimate;
use App\Services\Cycle\EstimateSource;
use App\Services\Cycle\Phase;
use App\Services\Cycle\PhaseResolver;
use App\Services\Cycle\Regularity;
use App\Services\Cycle\RegularityResult;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PhaseResolverTest extends TestCase
{
    private function estimate(float $days, EstimateSource $source = EstimateSource::Measured): DurationEstimate
    {
        return new DurationEstimate($days, null, 3, $source);
    }

    private function regular(): RegularityResult
    {
        return new RegularityResult(Regularity::Regular, lowConfidence: false, amplitudeDays: 3.0);
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
            regularity: $this->regular(),
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
            regularity: $this->regular(),
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
            regularity: $this->regular(),
        );
        $this->assertSame(Phase::Luteal, $luteal->phase);

        // The day after menses end but before the ovulation window is follicular
        // (this phase absorbs the elongation of the cycle).
        $follicular = $resolver->resolve(
            date: CarbonImmutable::parse('2026-01-10'),
            cycleStart: $cycleStart,
            cycleLength: $this->estimate(34.0),
            mensesLength: $this->estimate(5.0),
            regularity: $this->regular(),
        );
        $this->assertSame(Phase::Follicular, $follicular->phase);
    }

    public function test_menstrual_phase_spans_cycle_start_to_start_plus_duration_minus_one(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $mensesLength = $this->estimate(5.0);
        $cycleLength = $this->estimate(28.0);

        $lastMenstrualDay = $resolver->resolve(CarbonImmutable::parse('2026-01-05'), $cycleStart, $cycleLength, $mensesLength, $this->regular());
        $firstFollicularDay = $resolver->resolve(CarbonImmutable::parse('2026-01-06'), $cycleStart, $cycleLength, $mensesLength, $this->regular());

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
            regularity: $this->regular(),
        );

        $this->assertTrue($result->ovulationWindowEnd->greaterThan($result->ovulationWindowStart));
    }

    public function test_low_confidence_toggles_with_estimate_source(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $date = CarbonImmutable::parse('2026-01-10');
        $mensesLength = $this->estimate(5.0);

        $measured = $resolver->resolve($date, $cycleStart, $this->estimate(28.0, EstimateSource::Measured), $mensesLength, $this->regular());
        $declared = $resolver->resolve($date, $cycleStart, $this->estimate(28.0, EstimateSource::Declared), $mensesLength, $this->regular());
        $default = $resolver->resolve($date, $cycleStart, $this->estimate(28.0, EstimateSource::Default), $mensesLength, $this->regular());

        $this->assertFalse($measured->lowConfidence);
        $this->assertTrue($declared->lowConfidence);
        $this->assertTrue($default->lowConfidence);
    }

    public function test_low_confidence_is_also_forced_by_actual_irregularity(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $date = CarbonImmutable::parse('2026-01-10');
        $cycleLength = $this->estimate(28.0, EstimateSource::Measured);
        $mensesLength = $this->estimate(5.0);

        $irregular = new RegularityResult(Regularity::Irregular, lowConfidence: false, amplitudeDays: 12.0);

        $result = $resolver->resolve($date, $cycleStart, $cycleLength, $mensesLength, $irregular);

        $this->assertTrue($result->lowConfidence);
    }

    public function test_irregular_cycles_widen_the_ovulation_window_and_give_a_range_for_the_next_start(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $cycleLength = $this->estimate(28.0, EstimateSource::Measured);
        $mensesLength = $this->estimate(5.0);
        $irregular = new RegularityResult(Regularity::Irregular, lowConfidence: false, amplitudeDays: 8.0);

        $regularResult = $resolver->resolve(CarbonImmutable::parse('2026-01-10'), $cycleStart, $cycleLength, $mensesLength, $this->regular());
        $irregularResult = $resolver->resolve(CarbonImmutable::parse('2026-01-10'), $cycleStart, $cycleLength, $mensesLength, $irregular);

        $regularWidth = $regularResult->ovulationWindowStart->diffInDays($regularResult->ovulationWindowEnd);
        $irregularWidth = $irregularResult->ovulationWindowStart->diffInDays($irregularResult->ovulationWindowEnd);
        $this->assertTrue($irregularWidth > $regularWidth);

        $this->assertNull($regularResult->nextStartRangeStart);
        $this->assertNotNull($irregularResult->nextStartRangeStart);
        $this->assertNotNull($irregularResult->nextStartRangeEnd);
        $this->assertTrue($irregularResult->nextStartRangeStart->lessThan($irregularResult->predictedNextStart));
        $this->assertTrue($irregularResult->nextStartRangeEnd->greaterThan($irregularResult->predictedNextStart));
    }

    public function test_hard_cutoff_stops_predicting_when_the_ovulation_window_no_longer_fits(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        // A 16-day cycle with a widened (irregular) ovulation window leaves no room for a
        // sensible menstrual/ovulation/luteal split: the resolver must refuse to predict.
        $cycleLength = $this->estimate(16.0, EstimateSource::Measured);
        $mensesLength = $this->estimate(5.0);
        $irregular = new RegularityResult(Regularity::Irregular, lowConfidence: false, amplitudeDays: 20.0);

        $result = $resolver->resolve(CarbonImmutable::parse('2026-01-10'), $cycleStart, $cycleLength, $mensesLength, $irregular);

        $this->assertFalse($result->predictable);
        $this->assertNull($result->phase);
    }

    public function test_resolution_is_deterministic_for_the_same_inputs(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $date = CarbonImmutable::parse('2026-01-15');
        $cycleLength = $this->estimate(28.0);
        $mensesLength = $this->estimate(5.0);

        $first = $resolver->resolve($date, $cycleStart, $cycleLength, $mensesLength, $this->regular());
        $second = $resolver->resolve($date, $cycleStart, $cycleLength, $mensesLength, $this->regular());

        $this->assertSame($first->phase, $second->phase);
        $this->assertTrue($first->predictedNextStart->equalTo($second->predictedNextStart));
        $this->assertTrue($first->ovulationWindowStart->equalTo($second->ovulationWindowStart));
        $this->assertTrue($first->ovulationWindowEnd->equalTo($second->ovulationWindowEnd));
    }

    public function test_is_overdue_never_triggers_without_measured_data(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $asOf = $cycleStart->addDays(100);

        $this->assertFalse($resolver->isOverdue($asOf, $cycleStart, $this->estimate(28.0, EstimateSource::Default), null));
        $this->assertFalse($resolver->isOverdue($asOf, $cycleStart, $this->estimate(28.0, EstimateSource::Declared), null));
    }

    public function test_is_overdue_also_triggers_on_a_manual_override(): void
    {
        // A user sets an override precisely to make predictions MORE authoritative —
        // it must not be silently treated like an unmeasured guess.
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');

        $this->assertTrue(
            $resolver->isOverdue($cycleStart->addDays(39), $cycleStart, $this->estimate(28.0, EstimateSource::Override), amplitudeDays: 4.0)
        );
    }

    public function test_low_confidence_is_false_for_a_manual_override(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $date = CarbonImmutable::parse('2026-01-10');
        $mensesLength = $this->estimate(5.0);

        $result = $resolver->resolve($date, $cycleStart, $this->estimate(28.0, EstimateSource::Override), $mensesLength, $this->regular());

        $this->assertFalse($result->lowConfidence);
    }

    public function test_is_overdue_switches_at_max_of_38_or_mean_plus_amplitude(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');
        $cycleLength = $this->estimate(28.0, EstimateSource::Measured);

        // mean(28) + amplitude(4) = 32 < 38 -> the floor of 38 applies.
        $this->assertFalse($resolver->isOverdue($cycleStart->addDays(38), $cycleStart, $cycleLength, amplitudeDays: 4.0));
        $this->assertTrue($resolver->isOverdue($cycleStart->addDays(39), $cycleStart, $cycleLength, amplitudeDays: 4.0));

        // mean(28) + amplitude(15) = 43 > 38 -> the computed threshold applies.
        $this->assertFalse($resolver->isOverdue($cycleStart->addDays(43), $cycleStart, $cycleLength, amplitudeDays: 15.0));
        $this->assertTrue($resolver->isOverdue($cycleStart->addDays(44), $cycleStart, $cycleLength, amplitudeDays: 15.0));
    }

    public function test_is_severely_overdue_at_six_months(): void
    {
        $resolver = new PhaseResolver();
        $cycleStart = CarbonImmutable::parse('2026-01-01');

        $this->assertFalse($resolver->isSeverelyOverdue($cycleStart->addDays(181), $cycleStart));
        $this->assertTrue($resolver->isSeverelyOverdue($cycleStart->addDays(182), $cycleStart));
    }
}
