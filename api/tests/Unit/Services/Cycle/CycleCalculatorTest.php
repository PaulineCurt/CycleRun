<?php

namespace Tests\Unit\Services\Cycle;

use App\Enums\ExclusionType;
use App\Models\Cycle;
use App\Models\ExclusionPeriod;
use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\EstimateSource;
use App\Services\Cycle\Frequency;
use App\Services\Cycle\Regularity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class CycleCalculatorTest extends TestCase
{
    private function cycle(string $startedOn, ?string $mensesEndedOn = null): Cycle
    {
        return new Cycle([
            'started_on' => $startedOn,
            'menses_ended_on' => $mensesEndedOn,
        ]);
    }

    private function exclusion(string $startedOn, ?string $endedOn = null, ExclusionType $type = ExclusionType::Pregnancy): ExclusionPeriod
    {
        return new ExclusionPeriod([
            'type' => $type,
            'started_on' => $startedOn,
            'ended_on' => $endedOn,
        ]);
    }

    public function test_menses_length_fencepost_is_inclusive(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-22', '2026-01-25')]);

        $estimate = $calculator->mensesLength($cycles);

        $this->assertSame(4.0, $estimate->days);
    }

    public function test_menses_length_is_measurable_from_a_single_completed_period(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01', '2026-01-05')]);

        $estimate = $calculator->mensesLength($cycles);

        $this->assertSame(EstimateSource::Measured, $estimate->source);
        $this->assertSame(1, $estimate->sampleSize);
        $this->assertNull($estimate->stdDev);
    }

    public function test_cycle_length_requires_two_started_on_dates(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01')]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(EstimateSource::Default, $estimate->source);
        $this->assertSame(28.0, $estimate->days);
    }

    public function test_cycle_length_falls_back_to_declared_value_when_not_measurable(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01')]);

        $estimate = $calculator->cycleLength($cycles, declared: 30);

        $this->assertSame(EstimateSource::Declared, $estimate->source);
        $this->assertSame(30.0, $estimate->days);
    }

    public function test_cycle_length_falls_back_to_default_when_no_declared_value(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(EstimateSource::Default, $estimate->source);
        $this->assertSame(28.0, $estimate->days);
    }

    public function test_cycle_length_override_wins_even_with_measured_cycles(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01'), $this->cycle('2026-01-29')]);

        $estimate = $calculator->cycleLength($cycles, override: 35);

        $this->assertSame(EstimateSource::Override, $estimate->source);
        $this->assertSame(35.0, $estimate->days);
    }

    public function test_menses_length_override_wins_even_with_a_measured_period(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01', mensesEndedOn: '2026-01-05')]);

        $estimate = $calculator->mensesLength($cycles, override: 7);

        $this->assertSame(EstimateSource::Override, $estimate->source);
        $this->assertSame(7.0, $estimate->days);
    }

    public function test_cycle_length_window_caps_at_six_measurements_dropping_the_oldest(): void
    {
        $calculator = new CycleCalculator();

        // 8 cycle starts spaced 30 days apart -> 7 diffs, capped to the 6 most recent.
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-31'),
            $this->cycle('2026-03-02'),
            $this->cycle('2026-04-01'),
            $this->cycle('2026-05-01'),
            $this->cycle('2026-05-31'),
            $this->cycle('2026-06-30'),
            $this->cycle('2026-07-30'),
        ]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(6, $estimate->sampleSize);
        $this->assertSame(30.0, $estimate->days);
    }

    public function test_cycle_length_variance_on_known_series(): void
    {
        $calculator = new CycleCalculator();

        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-28'), // +27
            $this->cycle('2026-02-25'), // +28
            $this->cycle('2026-03-26'), // +29
        ]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(28.0, $estimate->days);
        $this->assertSame(1.0, $estimate->stdDev);
    }

    public function test_cycle_without_menses_ended_on_is_ignored_by_menses_length_but_kept_by_cycle_length(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([
            $this->cycle('2026-01-01', null),
            $this->cycle('2026-01-29', '2026-02-02'),
        ]);

        $cycleLength = $calculator->cycleLength($cycles);
        $mensesLength = $calculator->mensesLength($cycles);

        $this->assertSame(EstimateSource::Measured, $cycleLength->source);
        $this->assertSame(28.0, $cycleLength->days);
        $this->assertSame(1, $mensesLength->sampleSize);
        $this->assertSame(5.0, $mensesLength->days);
    }

    public function test_regularity_is_regular_within_the_figo_amplitude_threshold_for_the_18_25_bracket(): void
    {
        $calculator = new CycleCalculator();
        // Diffs: 25, 28, 34 -> amplitude 9.
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-26'),
            $this->cycle('2026-02-23'),
            $this->cycle('2026-03-29'),
        ]);
        $birthDate = CarbonImmutable::parse('2006-01-01'); // 20 years old
        $asOf = CarbonImmutable::parse('2026-04-01');

        $result = $calculator->regularity($cycles, $birthDate, $asOf);

        $this->assertSame(9.0, $result->amplitudeDays);
        $this->assertSame(Regularity::Regular, $result->regularity);
        $this->assertFalse($result->lowConfidence);
    }

    public function test_regularity_is_irregular_at_the_same_amplitude_for_the_26_41_bracket(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-26'),
            $this->cycle('2026-02-23'),
            $this->cycle('2026-03-29'),
        ]);
        $birthDate = CarbonImmutable::parse('1996-01-01'); // 30 years old
        $asOf = CarbonImmutable::parse('2026-04-01');

        $result = $calculator->regularity($cycles, $birthDate, $asOf);

        $this->assertSame(Regularity::Irregular, $result->regularity);
        $this->assertFalse($result->lowConfidence);
    }

    public function test_regularity_threshold_switches_exactly_at_7_versus_9_days_for_the_26_41_bracket(): void
    {
        $calculator = new CycleCalculator();
        $birthDate = CarbonImmutable::parse('1996-01-01'); // 30 years old
        $asOf = CarbonImmutable::parse('2026-04-01');

        // Diffs 28, 35 -> amplitude 7.
        $atThreshold = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-29'),
            $this->cycle('2026-03-05'),
        ]);
        // Diffs 28, 36 -> amplitude 8.
        $overThreshold = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-29'),
            $this->cycle('2026-03-06'),
        ]);

        $this->assertSame(Regularity::Regular, $calculator->regularity($atThreshold, $birthDate, $asOf)->regularity);
        $this->assertSame(Regularity::Irregular, $calculator->regularity($overThreshold, $birthDate, $asOf)->regularity);
    }

    public function test_regularity_is_unknown_below_two_measured_cycles(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01')]);
        $birthDate = CarbonImmutable::parse('1996-01-01');
        $asOf = CarbonImmutable::parse('2026-04-01');

        $result = $calculator->regularity($cycles, $birthDate, $asOf);

        $this->assertSame(Regularity::Unknown, $result->regularity);
        $this->assertTrue($result->lowConfidence);
        $this->assertNull($result->amplitudeDays);
    }

    public function test_regularity_is_permissive_and_low_confidence_outside_18_45(): void
    {
        $calculator = new CycleCalculator();
        // Same series as the 26-41 case (irregular there), amplitude 9.
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-26'),
            $this->cycle('2026-02-23'),
            $this->cycle('2026-03-29'),
        ]);
        $asOf = CarbonImmutable::parse('2026-04-01');
        $tooYoung = CarbonImmutable::parse('2015-01-01'); // 11 years old
        $tooOld = CarbonImmutable::parse('1970-01-01'); // 56 years old

        $youngResult = $calculator->regularity($cycles, $tooYoung, $asOf);
        $oldResult = $calculator->regularity($cycles, $tooOld, $asOf);

        $this->assertSame(Regularity::Regular, $youngResult->regularity);
        $this->assertTrue($youngResult->lowConfidence);
        $this->assertSame(Regularity::Regular, $oldResult->regularity);
        $this->assertTrue($oldResult->lowConfidence);
    }

    public function test_regularity_treats_a_missing_birth_date_like_outside_18_45(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-26'),
            $this->cycle('2026-02-23'),
            $this->cycle('2026-03-29'),
        ]);
        $asOf = CarbonImmutable::parse('2026-04-01');

        $result = $calculator->regularity($cycles, null, $asOf);

        $this->assertSame(Regularity::Regular, $result->regularity);
        $this->assertTrue($result->lowConfidence);
    }

    public function test_frequency_classifies_from_the_mean_cycle_length(): void
    {
        $calculator = new CycleCalculator();

        $frequent = new Collection([$this->cycle('2026-01-01'), $this->cycle('2026-01-23')]); // 22
        $infrequent = new Collection([$this->cycle('2026-01-01'), $this->cycle('2026-02-10')]); // 40
        $normal = new Collection([$this->cycle('2026-01-01'), $this->cycle('2026-01-31')]); // 30

        $this->assertSame(Frequency::Frequent, $calculator->frequency($frequent));
        $this->assertSame(Frequency::Infrequent, $calculator->frequency($infrequent));
        $this->assertSame(Frequency::Normal, $calculator->frequency($normal));
    }

    public function test_frequency_is_unknown_when_the_length_is_not_measured(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01')]);

        $this->assertSame(Frequency::Unknown, $calculator->frequency($cycles));
    }

    public function test_frequency_classifies_from_a_manual_override_with_no_cycles_logged_at_all(): void
    {
        $calculator = new CycleCalculator();

        $this->assertSame(Frequency::Infrequent, $calculator->frequency(new Collection(), override: 45));
    }

    public function test_an_interval_overlapping_a_pregnancy_is_excluded_from_the_cycle_length_calculation(): void
    {
        $calculator = new CycleCalculator();
        // Diffs without exclusion: 45, 28.
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-02-15'),
            $this->cycle('2026-03-15'),
        ]);
        $pregnancy = new Collection([
            $this->exclusion('2026-01-10', '2026-01-20'),
        ]);

        $withoutExclusion = $calculator->cycleLength($cycles);
        $withExclusion = $calculator->cycleLength($cycles, exclusionPeriods: $pregnancy);

        $this->assertSame(2, $withoutExclusion->sampleSize);
        $this->assertSame(1, $withExclusion->sampleSize);
        $this->assertSame(28.0, $withExclusion->days);
    }

    public function test_an_unannotated_outlier_interval_is_dropped_by_the_automatic_safety_net(): void
    {
        $calculator = new CycleCalculator();
        // 73-day gap (never annotated as an exclusion) followed by a normal 28-day cycle.
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-03-15'),
            $this->cycle('2026-04-12'),
        ]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(1, $estimate->sampleSize);
        $this->assertSame(28.0, $estimate->days);
    }

    public function test_menses_length_variation_is_the_amplitude_of_measured_durations(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([
            $this->cycle('2026-01-01', '2026-01-04'), // 4 days
            $this->cycle('2026-02-01', '2026-02-06'), // 6 days
            $this->cycle('2026-03-01', '2026-03-04'), // 4 days
        ]);

        $this->assertSame(2.0, $calculator->mensesLengthVariation($cycles));
    }

    public function test_menses_length_variation_is_null_below_two_measured_values(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01', '2026-01-04')]);

        $this->assertNull($calculator->mensesLengthVariation($cycles));
    }
}
