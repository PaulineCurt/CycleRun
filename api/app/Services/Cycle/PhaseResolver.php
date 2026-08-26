<?php

namespace App\Services\Cycle;

use Carbon\CarbonImmutable;

/**
 * Counts backward from the end of the cycle (never a proportional split).
 * The evaluated date is always a parameter — never a hidden now().
 */
class PhaseResolver
{
    private const LUTEAL_DAYS = 14;

    private const OVULATION_WINDOW_RADIUS = 2;

    public function resolve(
        CarbonImmutable $date,
        CarbonImmutable $cycleStart,
        DurationEstimate $cycleLength,
        DurationEstimate $mensesLength,
    ): PhaseResult {
        $predictedNextStart = $cycleStart->addDays((int) round($cycleLength->days));
        $ovulationDay = $predictedNextStart->subDays(self::LUTEAL_DAYS);
        $ovulationWindowStart = $ovulationDay->subDays(self::OVULATION_WINDOW_RADIUS);
        $ovulationWindowEnd = $ovulationDay->addDays(self::OVULATION_WINDOW_RADIUS);
        $mensesEnd = $cycleStart->addDays((int) round($mensesLength->days) - 1);

        $phase = match (true) {
            $date->between($cycleStart, $mensesEnd) => Phase::Menstrual,
            $date->between($ovulationWindowStart, $ovulationWindowEnd) => Phase::Ovulation,
            $date->greaterThan($ovulationWindowEnd) && $date->lessThan($predictedNextStart) => Phase::Luteal,
            default => Phase::Follicular,
        };

        return new PhaseResult(
            phase: $phase,
            predictedNextStart: $predictedNextStart,
            ovulationWindowStart: $ovulationWindowStart,
            ovulationWindowEnd: $ovulationWindowEnd,
            lowConfidence: $cycleLength->source !== EstimateSource::Measured,
        );
    }
}
