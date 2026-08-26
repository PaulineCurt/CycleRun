<?php

namespace App\Services\Cycle;

use App\Models\Cycle;
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

    /**
     * @param  Collection<int, Cycle>  $cycles
     */
    public function cycleLength(Collection $cycles, ?int $declared = null): DurationEstimate
    {
        $diffs = $cycles
            ->sortBy('started_on')
            ->values()
            ->sliding(2)
            ->map(fn (Collection $pair) => (float) $pair->first()->started_on->diffInDays($pair->last()->started_on))
            ->values();

        return $this->estimate($diffs, $declared, self::DEFAULT_CYCLE_LENGTH);
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     */
    public function mensesLength(Collection $cycles, ?int $declared = null): DurationEstimate
    {
        $lengths = $cycles
            ->filter(fn (Cycle $cycle) => $cycle->menses_ended_on !== null)
            ->sortBy('started_on')
            ->values()
            ->map(fn (Cycle $cycle) => (float) $cycle->started_on->diffInDays($cycle->menses_ended_on) + 1);

        return $this->estimate($lengths, $declared, self::DEFAULT_MENSES_LENGTH);
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
