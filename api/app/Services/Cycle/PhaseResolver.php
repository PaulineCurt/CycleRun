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

    /** Widened radius used once irregularity is confirmed — the window degrades honestly instead of overstating precision. */
    private const OVULATION_WINDOW_RADIUS_WIDE = 4;

    /** Clinical landmark for secondary amenorrhea — flags a delay serious enough to escalate messaging. */
    private const SEVERE_OVERDUE_DAYS = 182;

    public function resolve(
        CarbonImmutable $date,
        CarbonImmutable $cycleStart,
        DurationEstimate $cycleLength,
        DurationEstimate $mensesLength,
        RegularityResult $regularity,
    ): PhaseResult {
        $predictedNextStart = $cycleStart->addDays((int) round($cycleLength->days));
        $ovulationDay = $predictedNextStart->subDays(self::LUTEAL_DAYS);
        $radius = $regularity->regularity === Regularity::Irregular
            ? self::OVULATION_WINDOW_RADIUS_WIDE
            : self::OVULATION_WINDOW_RADIUS;
        $ovulationWindowStart = $ovulationDay->subDays($radius);
        $ovulationWindowEnd = $ovulationDay->addDays($radius);
        $mensesEnd = $cycleStart->addDays((int) round($mensesLength->days) - 1);

        // Hard cutoff: the ovulation window no longer fits in a sensible spot — it collides
        // with menstrual bleeding, or leaves no luteal gap before the next cycle. Better to
        // predict nothing than to promise a phase that isn't there.
        $predictable = $ovulationWindowStart->greaterThan($mensesEnd)
            && $ovulationWindowEnd->lessThan($predictedNextStart);

        $nextStartRangeStart = null;
        $nextStartRangeEnd = null;
        if ($regularity->regularity === Regularity::Irregular && $regularity->amplitudeDays !== null) {
            $spread = (int) round($regularity->amplitudeDays / 2);
            $nextStartRangeStart = $predictedNextStart->subDays($spread);
            $nextStartRangeEnd = $predictedNextStart->addDays($spread);
        }

        $phase = $predictable ? match (true) {
            $date->between($cycleStart, $mensesEnd) => Phase::Menstrual,
            $date->between($ovulationWindowStart, $ovulationWindowEnd) => Phase::Ovulation,
            $date->greaterThan($ovulationWindowEnd) && $date->lessThan($predictedNextStart) => Phase::Luteal,
            default => Phase::Follicular,
        } : null;

        return new PhaseResult(
            phase: $phase,
            predictable: $predictable,
            predictedNextStart: $predictedNextStart,
            ovulationWindowStart: $ovulationWindowStart,
            ovulationWindowEnd: $ovulationWindowEnd,
            nextStartRangeStart: $nextStartRangeStart,
            nextStartRangeEnd: $nextStartRangeEnd,
            lowConfidence: $cycleLength->source !== EstimateSource::Measured
                || $regularity->lowConfidence
                || $regularity->regularity === Regularity::Irregular,
        );
    }

    /**
     * Never triggers without measured history — an amorce/default cycle length stays neutral
     * rather than crying "late" over a generic 28-day guess.
     */
    public function isOverdue(
        CarbonImmutable $asOf,
        CarbonImmutable $cycleStart,
        DurationEstimate $cycleLength,
        ?float $amplitudeDays,
    ): bool {
        if ($cycleLength->source !== EstimateSource::Measured) {
            return false;
        }

        $daysSinceStart = $cycleStart->diffInDays($asOf);
        $threshold = max(38.0, $cycleLength->days + ($amplitudeDays ?? 0.0));

        return $daysSinceStart > $threshold;
    }

    public function isSeverelyOverdue(CarbonImmutable $asOf, CarbonImmutable $cycleStart): bool
    {
        return $cycleStart->diffInDays($asOf) >= self::SEVERE_OVERDUE_DAYS;
    }
}
