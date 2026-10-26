<?php

namespace App\Services\Training;

/**
 * Step 2 of the pipeline (§5): checkFeasibility(profile, level, goal, weeks).
 *
 * Feasibility *indicator*, not a promise (§4.2). When the requested goal_time is
 * too aggressive for the number of weeks, we alert and propose an adjusted goal
 * and/or a longer plan — we never silently accept it.
 *
 * The improvement bounds come from the LevelProfile (§4.2/§4.5). The current-time
 * projection off a bare VMA is derived from the D&G drop-off curve in Vdot — no
 * flat "sustainable fraction" scalar anymore (§4.5).
 */
final class FeasibilityChecker
{
    /** Fixed-point iterations to resolve the 5 km time off the VO2max curve. */
    private const CURVE_ITERATIONS = 8;

    public function check(
        LevelProfile $profile,
        NormalizedLevel $level,
        int $goalDistanceM,
        ?int $goalTimeS,
        int $weeks,
    ): FeasibilityResult {
        if ($goalDistanceM <= 0 || $weeks <= 0) {
            throw new \InvalidArgumentException('goal distance and weeks must be positive');
        }

        $projectedS = $this->projectCurrentTime($level, $goalDistanceM);

        $improvement = min($weeks * $profile->improvementPerWeek, $profile->maxImprovement);
        $achievableS = (int) round($projectedS * (1 - $improvement));

        if ($goalTimeS === null) {
            return new FeasibilityResult(true, $projectedS, $achievableS, null, null, null);
        }

        if ($goalTimeS >= $achievableS) {
            return new FeasibilityResult(true, $projectedS, $achievableS, null, null, null);
        }

        // Goal is faster than what the plan can plausibly deliver.
        $requiredImprovement = 1 - $goalTimeS / $projectedS;

        if ($requiredImprovement > $profile->maxImprovement) {
            return new FeasibilityResult(
                false,
                $projectedS,
                $achievableS,
                $achievableS,
                null,
                'Objectif hors de portée sur un plan 5 km de cette durée. '
                    .'Cible proposée : '.$this->formatTime($achievableS).'.',
            );
        }

        $neededWeeks = (int) ceil($requiredImprovement / $profile->improvementPerWeek);

        return new FeasibilityResult(
            false,
            $projectedS,
            $achievableS,
            $achievableS,
            $neededWeeks,
            'Objectif trop ambitieux pour '.$weeks.' semaines. '
                .'Soit viser '.$this->formatTime($achievableS).', '
                .'soit allonger le plan à ~'.$neededWeeks.' semaines.',
        );
    }

    private function projectCurrentTime(NormalizedLevel $level, int $goalDistanceM): int
    {
        if ($level->referenceDistanceM !== null && $level->referenceTimeS !== null) {
            return Riegel::predict($level->referenceTimeS, $level->referenceDistanceM, $goalDistanceM);
        }

        return $this->estimateTimeFromVma($level->vmaKmh, $goalDistanceM);
    }

    /**
     * No race to extrapolate from: solve for the time `t` at which the runner's
     * sustainable cost VO2max·%VO2max(t) equals the cost of covering the distance
     * in `t`. Fixed-point on the D&G curve — converges in a handful of steps.
     */
    private function estimateTimeFromVma(float $vmaKmh, int $goalDistanceM): int
    {
        $vo2max = Vdot::vo2AtVelocity($vmaKmh * 1000 / 60); // cost at vVO2max (m/min)

        // Seed: a naive fraction of VMA, just to enter the loop.
        $tMin = $goalDistanceM / ($vmaKmh * 1000 / 60 * 0.86);

        for ($i = 0; $i < self::CURVE_ITERATIONS; $i++) {
            $sustainableVo2 = $vo2max * Vdot::percentVo2maxForDuration($tMin);
            $velocity = Vdot::velocityForVo2($sustainableVo2); // m/min
            $tMin = $goalDistanceM / $velocity;
        }

        return (int) round($tMin * 60);
    }

    private function formatTime(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
