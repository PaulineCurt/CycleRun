<?php

namespace App\Services\Training;

/**
 * Step 5 / a-posteriori check of the pipeline (§4.4 moteurB.md): the ~80/20
 * easy/hard split is verified on *time in zone* after sessions are materialized,
 * and weeks that drift too hard are rebalanced by adding easy volume — never by
 * cutting the hard stimulus.
 */
final class IntensityBalancer
{
    private const HARD_RATIO_TARGET = 0.20;
    private const HARD_RATIO_CEILING = 0.25;

    /** A rebalanced easy run should never grow past this (90 min). */
    private const MAX_EASY_SESSION_S = 5400;

    /**
     * @param list<SessionDraft> $sessions
     * @return list<SessionDraft>
     */
    public function balance(array $sessions): array
    {
        $byWeek = [];
        foreach ($sessions as $index => $session) {
            $byWeek[$session->weekIndex][] = $index;
        }

        foreach ($byWeek as $indexes) {
            $hard = 0;
            $total = 0;
            foreach ($indexes as $i) {
                $hard += $sessions[$i]->hardSeconds();
                $total += $sessions[$i]->totalSeconds();
            }

            if ($total === 0 || $hard === 0) {
                continue;
            }

            $ratio = $hard / $total;
            if ($ratio <= self::HARD_RATIO_CEILING) {
                continue;
            }

            // Easy seconds needed so that hard / (hard + easy) == target.
            $targetEasy = (int) ceil($hard * (1 - self::HARD_RATIO_TARGET) / self::HARD_RATIO_TARGET);
            $currentEasy = $total - $hard;
            $deficit = $targetEasy - $currentEasy;
            if ($deficit <= 0) {
                continue;
            }

            $longestEasy = $this->longestEasyDurationIndex($sessions, $indexes);
            if ($longestEasy === null) {
                continue;
            }

            $load = $sessions[$longestEasy]->plannedLoad;
            $load['duration_s'] = min(
                self::MAX_EASY_SESSION_S,
                (int) ($load['duration_s'] ?? 0) + $deficit,
            );
            $sessions[$longestEasy] = $sessions[$longestEasy]->withPlannedLoad($load);
        }

        return $sessions;
    }

    /**
     * @param list<SessionDraft> $sessions
     */
    public function weeklyHardRatio(array $sessions, int $weekIndex): float
    {
        $hard = 0;
        $total = 0;
        foreach ($sessions as $session) {
            if ($session->weekIndex !== $weekIndex) {
                continue;
            }
            $hard += $session->hardSeconds();
            $total += $session->totalSeconds();
        }

        return $total === 0 ? 0.0 : $hard / $total;
    }

    /**
     * @param list<SessionDraft> $sessions
     * @param list<int> $indexes
     */
    private function longestEasyDurationIndex(array $sessions, array $indexes): ?int
    {
        $best = null;
        $bestDuration = -1;
        foreach ($indexes as $i) {
            $session = $sessions[$i];
            if (($session->plannedLoad['mode'] ?? null) !== 'duration' || $session->hardSeconds() > 0) {
                continue;
            }
            $duration = (int) ($session->plannedLoad['duration_s'] ?? 0);
            if ($duration > $bestDuration) {
                $bestDuration = $duration;
                $best = $i;
            }
        }

        return $best;
    }
}
