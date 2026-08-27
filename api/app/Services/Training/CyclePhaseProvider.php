<?php

namespace App\Services\Training;

use App\Models\User;
use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\PhaseResolver;
use App\Services\Cycle\PhaseResult;
use App\Services\Cycle\Regularity;
use App\Services\Cycle\RegularityResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Implements the §6 contract: reads Moteur A for a given date and returns a plain
 * phase snapshot the training layer can act on. This is the ONLY place Moteur B
 * touches the cycle engine.
 */
final class CyclePhaseProvider
{
    /** Pain 0..3 — moderate or above counts as "douleur déclarée" (§6). Provisional. */
    private const PAIN_THRESHOLD = 2;

    public function __construct(
        private readonly CycleCalculator $calculator = new CycleCalculator(),
        private readonly PhaseResolver $resolver = new PhaseResolver(),
    ) {
    }

    /**
     * @param ?Collection<int, \App\Models\DayLog> $dayLogs  when the caller decorates
     *   many sessions at once, it passes the day logs it already loaded so this
     *   doesn't re-query per date. Falls back to a single query when omitted.
     */
    public function forDate(User $user, CarbonImmutable $date, ?Collection $dayLogs = null): PhaseSnapshot
    {
        $painFlag = $this->painReported($user, $date, $dayLogs);

        $cycles = $user->relationLoaded('cycles')
            ? $user->cycles->sortBy('started_on')->values()
            : $user->cycles()->orderBy('started_on')->get();
        $currentCycle = $cycles->last(fn ($cycle) => $cycle->started_on->lessThanOrEqualTo($date));

        if ($currentCycle === null) {
            return PhaseSnapshot::none($painFlag);
        }

        $exclusionPeriods = $user->exclusionPeriods;
        $cycleLength = $this->calculator->cycleLength(
            $cycles,
            $user->typical_cycle_length,
            $exclusionPeriods,
            $user->cycle_length_override,
        );
        $mensesLength = $this->calculator->mensesLength(
            $cycles,
            $user->typical_menses_length,
            $user->menses_length_override,
        );
        $regularity = $this->calculator->regularity(
            $cycles,
            $user->birth_date?->toImmutable(),
            $date,
            $exclusionPeriods,
        );

        $cycleStart = $currentCycle->started_on->toImmutable();
        $phase = $this->resolver->resolve(
            date: $date,
            cycleStart: $cycleStart,
            cycleLength: $cycleLength,
            mensesLength: $mensesLength,
            regularity: $regularity,
        );

        // A training plan runs weeks into the future, well past the current cycle's
        // predicted end. Project forward by whole cycle lengths and re-resolve —
        // same as the cycle calendar — otherwise every far session lands in the
        // follicular catch-all and the two views disagree.
        if ($date->greaterThanOrEqualTo($phase->predictedNextStart)) {
            $cycleLengthDays = max(1, (int) round($cycleLength->days));
            $cyclesAhead = intdiv($phase->predictedNextStart->diffInDays($date), $cycleLengthDays);
            $projectedStart = $phase->predictedNextStart->addDays($cyclesAhead * $cycleLengthDays);

            $phase = $this->resolver->resolve(
                date: $date,
                cycleStart: $projectedStart,
                cycleLength: $cycleLength,
                mensesLength: $mensesLength,
                regularity: $regularity,
            );
        }

        return new PhaseSnapshot(
            phase: $phase->phase,
            confidence: $this->confidence($regularity, $phase),
            painFlag: $painFlag,
        );
    }

    /**
     * Confidence gates *how strong* an adaptation is, not whether it happens: a
     * resolvable phase is usable even before the cycle is proven regular. Only a
     * genuinely unpredictable cycle (no sensible ovulation window) scores 0.
     */
    private function confidence(RegularityResult $regularity, PhaseResult $phase): float
    {
        if (! $phase->predictable) {
            return 0.0;
        }

        $base = match ($regularity->regularity) {
            Regularity::Regular => 0.9,
            Regularity::Unknown => 0.6,   // phase usable, regularity just not proven yet
            Regularity::Irregular => 0.35,
        };

        // Phase derived from a declared/default length rather than measured history:
        // usable, but never claim high confidence off a generic 28-day assumption.
        return $phase->lowConfidence ? min($base, 0.5) : $base;
    }

    /**
     * @param ?Collection<int, \App\Models\DayLog> $dayLogs
     */
    private function painReported(User $user, CarbonImmutable $date, ?Collection $dayLogs): bool
    {
        $key = $date->toDateString();

        $log = $dayLogs !== null
            ? $dayLogs->first(fn ($entry) => $entry->date->toDateString() === $key)
            : $user->dayLogs()->where('date', $key)->first();

        return $log !== null && $log->pain !== null && $log->pain >= self::PAIN_THRESHOLD;
    }
}
