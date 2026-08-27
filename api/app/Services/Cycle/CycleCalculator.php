<?php

namespace App\Services\Cycle;

use App\Models\Cycle;
use App\Models\ExclusionPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Pure calculation: inputs are facts (logged cycles), outputs are computed values.
 * Instantiable without a DB — takes a plain Collection<Cycle>.
 */
class CycleCalculator
{
    /** Cap on the sliding window: beyond this, the oldest values are dropped. */
    private const WINDOW = 6;

    private const DEFAULT_CYCLE_LENGTH = 28.0;

    private const DEFAULT_MENSES_LENGTH = 5.0;

    /** Safety net: an interval outside this range is dropped even without an exclusion period. */
    private const OUTLIER_MAX_DAYS = 60.0;

    private const OUTLIER_MIN_DAYS = 10.0;

    /**
     * @param  Collection<int, Cycle>  $cycles
     * @param  ?Collection<int, ExclusionPeriod>  $exclusionPeriods
     */
    public function cycleLength(
        Collection $cycles,
        ?int $declared = null,
        ?Collection $exclusionPeriods = null,
        ?int $override = null,
    ): DurationEstimate {
        if ($override !== null) {
            return new DurationEstimate((float) $override, null, 0, EstimateSource::Override);
        }

        $lengths = $this->cycleIntervalLengths($cycles, $exclusionPeriods ?? new Collection());

        return $this->estimate($lengths, $declared, self::DEFAULT_CYCLE_LENGTH);
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     */
    public function mensesLength(Collection $cycles, ?int $declared = null, ?int $override = null): DurationEstimate
    {
        if ($override !== null) {
            return new DurationEstimate((float) $override, null, 0, EstimateSource::Override);
        }

        return $this->estimate($this->mensesLengthValues($cycles), $declared, self::DEFAULT_MENSES_LENGTH);
    }

    /**
     * Amplitude (max − min) of measured menses lengths within the window. Null below 2
     * measured values — a single data point has no variation to report.
     *
     * @param  Collection<int, Cycle>  $cycles
     */
    public function mensesLengthVariation(Collection $cycles): ?float
    {
        $lengths = $this->mensesLengthValues($cycles)->slice(-self::WINDOW)->values();

        return $lengths->count() >= 2 ? $lengths->max() - $lengths->min() : null;
    }

    /**
     * Amplitude (max − min) of measured cycle lengths against a FIGO 2018 age threshold.
     * Never Regular below 2 measured cycles — irregularity needs evidence, not a default guess.
     *
     * @param  Collection<int, Cycle>  $cycles
     * @param  ?Collection<int, ExclusionPeriod>  $exclusionPeriods
     */
    public function regularity(
        Collection $cycles,
        ?CarbonImmutable $birthDate,
        CarbonImmutable $asOf,
        ?Collection $exclusionPeriods = null,
    ): RegularityResult {
        $lengths = $this->cycleIntervalLengths($cycles, $exclusionPeriods ?? new Collection())
            ->slice(-self::WINDOW)
            ->values();

        if ($lengths->count() < 2) {
            return new RegularityResult(Regularity::Unknown, lowConfidence: true, amplitudeDays: null);
        }

        $amplitude = $lengths->max() - $lengths->min();
        [$threshold, $forced] = $this->regularityThreshold($birthDate, $asOf);

        return new RegularityResult(
            regularity: $amplitude <= $threshold ? Regularity::Regular : Regularity::Irregular,
            lowConfidence: $forced,
            amplitudeDays: $amplitude,
        );
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     * @param  ?Collection<int, ExclusionPeriod>  $exclusionPeriods
     */
    public function frequency(Collection $cycles, ?Collection $exclusionPeriods = null, ?int $override = null): Frequency
    {
        $estimate = $this->cycleLength($cycles, declared: null, exclusionPeriods: $exclusionPeriods, override: $override);

        if (!$estimate->isTrustworthy()) {
            return Frequency::Unknown;
        }

        return match (true) {
            $estimate->days < 24.0 => Frequency::Frequent,
            $estimate->days > 38.0 => Frequency::Infrequent,
            default => Frequency::Normal,
        };
    }

    /**
     * Builds the [started_on → next started_on) interval lengths, dropping intervals that
     * overlap a declared exclusion period (pregnancy/breastfeeding — the Cycle rows themselves
     * are never removed) and outliers (>60j or <10j) the user hasn't annotated yet.
     *
     * @param  Collection<int, Cycle>  $cycles
     * @param  Collection<int, ExclusionPeriod>  $exclusionPeriods
     * @return Collection<int, float> sorted oldest to most recent
     */
    private function cycleIntervalLengths(Collection $cycles, Collection $exclusionPeriods): Collection
    {
        return $cycles
            ->sortBy('started_on')
            ->values()
            ->sliding(2)
            ->reject(fn (Collection $pair) => $this->overlapsAnyExclusion(
                $pair->first()->started_on,
                $pair->last()->started_on,
                $exclusionPeriods,
            ))
            ->map(fn (Collection $pair) => (float) $pair->first()->started_on->diffInDays($pair->last()->started_on))
            ->reject(fn (float $length) => $length > self::OUTLIER_MAX_DAYS || $length < self::OUTLIER_MIN_DAYS)
            ->values();
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     * @return Collection<int, float> sorted oldest to most recent
     */
    private function mensesLengthValues(Collection $cycles): Collection
    {
        return $cycles
            ->filter(fn (Cycle $cycle) => $cycle->menses_ended_on !== null)
            ->sortBy('started_on')
            ->values()
            ->map(fn (Cycle $cycle) => (float) $cycle->started_on->diffInDays($cycle->menses_ended_on) + 1);
    }

    /**
     * @param  Collection<int, ExclusionPeriod>  $exclusionPeriods
     */
    private function overlapsAnyExclusion(
        CarbonInterface $intervalStart,
        CarbonInterface $intervalEnd,
        Collection $exclusionPeriods,
    ): bool
    {
        return $exclusionPeriods->contains(function (ExclusionPeriod $period) use ($intervalStart, $intervalEnd) {
            $periodEnd = $period->ended_on ?? $period->started_on->copy()->addYears(100);

            return $intervalStart->lessThan($periodEnd) && $intervalEnd->greaterThan($period->started_on);
        });
    }

    /**
     * @return array{0: float, 1: bool} [threshold in days, lowConfidence forced]
     */
    private function regularityThreshold(?CarbonImmutable $birthDate, CarbonImmutable $asOf): array
    {
        if ($birthDate === null) {
            return [9.0, true];
        }

        $age = $birthDate->diffInYears($asOf);

        if ($age < 18 || $age > 45) {
            return [9.0, true];
        }

        if ($age >= 26 && $age <= 41) {
            return [7.0, false];
        }

        return [9.0, false];
    }

    /**
     * @param  Collection<int, float>  $values  sorted oldest to most recent
     */
    private function estimate(Collection $values, ?int $declared, float $default): DurationEstimate
    {
        $sample = $values->slice(-self::WINDOW)->values();

        if ($sample->isEmpty()) {
            return $declared !== null
                ? new DurationEstimate((float) $declared, null, 0, EstimateSource::Declared)
                : new DurationEstimate($default, null, 0, EstimateSource::Default);
        }

        return new DurationEstimate(
            days: $sample->avg(),
            stdDev: $sample->count() >= 2 ? $this->stdDev($sample) : null,
            sampleSize: $sample->count(),
            source: EstimateSource::Measured,
        );
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private function stdDev(Collection $values): float
    {
        $mean = $values->avg();
        $variance = $values->reduce(fn (float $carry, float $value) => $carry + ($value - $mean) ** 2, 0.0)
            / ($values->count() - 1);

        return sqrt($variance);
    }
}
