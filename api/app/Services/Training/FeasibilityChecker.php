<?php

namespace App\Services\Training;

/**
 * Step 2 of the pipeline (§5): checkFeasibility(level, goal, weeks) via Riegel.
 *
 * Feasibility *indicator*, not a promise (§4.2). When the requested goal_time is
 * too aggressive for the number of weeks, we alert and propose an adjusted goal
 * and/or a longer plan — we never silently accept it.
 *
 * The tuning constants below are provisional — decision #4 (2026-08-27) is still
 * open and must be calibrated with the coach on real generated plans.
 */
final class FeasibilityChecker
{
    /** Fraction of VMA a debutante→intermediate can hold over 5 km. TODO(expert). */
    private const SUSTAINABLE_FRACTION = 0.86;

    /** Plausible relative improvement per training week. TODO(expert), decision #4. */
    private const IMPROVEMENT_PER_WEEK = 0.006;

    /** Hard cap on total relative improvement over a single plan. TODO(expert). */
    private const MAX_IMPROVEMENT = 0.12;

    public function check(NormalizedLevel $level, int $goalDistanceM, ?int $goalTimeS, int $weeks): FeasibilityResult
    {
        if ($goalDistanceM <= 0 || $weeks <= 0) {
            throw new \InvalidArgumentException('goal distance and weeks must be positive');
        }

        $projectedS = $this->projectCurrentTime($level, $goalDistanceM);

        $improvement = min($weeks * self::IMPROVEMENT_PER_WEEK, self::MAX_IMPROVEMENT);
        $achievableS = (int) round($projectedS * (1 - $improvement));

        if ($goalTimeS === null) {
            return new FeasibilityResult(true, $projectedS, $achievableS, null, null, null);
        }

        if ($goalTimeS >= $achievableS) {
            return new FeasibilityResult(true, $projectedS, $achievableS, null, null, null);
        }

        // Goal is faster than what the plan can plausibly deliver.
        $requiredImprovement = 1 - $goalTimeS / $projectedS;

        if ($requiredImprovement > self::MAX_IMPROVEMENT) {
            return new FeasibilityResult(
                false,
                $projectedS,
                $achievableS,
                $achievableS,
                null,
                'Objectif hors de portée sur un plan 5 km de cette durée. '
                    . 'Cible proposée : ' . $this->formatTime($achievableS) . '.',
            );
        }

        $neededWeeks = (int) ceil($requiredImprovement / self::IMPROVEMENT_PER_WEEK);

        return new FeasibilityResult(
            false,
            $projectedS,
            $achievableS,
            $achievableS,
            $neededWeeks,
            'Objectif trop ambitieux pour ' . $weeks . ' semaines. '
                . 'Soit viser ' . $this->formatTime($achievableS) . ', '
                . 'soit allonger le plan à ~' . $neededWeeks . ' semaines.',
        );
    }

    private function projectCurrentTime(NormalizedLevel $level, int $goalDistanceM): int
    {
        if ($level->referenceDistanceM !== null && $level->referenceTimeS !== null) {
            return Riegel::predict($level->referenceTimeS, $level->referenceDistanceM, $goalDistanceM);
        }

        // Field test: no race to extrapolate from — estimate off VMA.
        $speedMs = $level->vmaKmh * self::SUSTAINABLE_FRACTION / 3.6;

        return (int) round($goalDistanceM / $speedMs);
    }

    private function formatTime(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
