<?php

namespace App\Services\Training;

/**
 * Riegel endurance model (§4.2 moteurB.md): predict a time on one distance from a
 * time on another. Used as a *feasibility indicator*, never a promise. The 1.06
 * exponent degrades on large extrapolations and for lightly-trained profiles.
 */
final class Riegel
{
    private const EXPONENT = 1.06;

    public static function predict(int $knownTimeS, int $knownDistanceM, int $targetDistanceM): int
    {
        if ($knownTimeS <= 0 || $knownDistanceM <= 0 || $targetDistanceM <= 0) {
            throw new \InvalidArgumentException('times and distances must be positive');
        }

        return (int) round($knownTimeS * ($targetDistanceM / $knownDistanceM) ** self::EXPONENT);
    }
}
