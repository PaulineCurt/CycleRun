<?php

namespace App\Services\Training;

/**
 * Daniels & Gilbert VDOT equations (§4.1 moteurB.md).
 *
 * VDOT is only an *import* path in v1: a race result becomes a VDOT, which is then
 * converted to a VMA estimate so the rest of the engine works from a single
 * referential (decision #1, 2026-08-27).
 *
 * These are the raw D&G equations, not Daniels' adjusted book tables — the two can
 * differ by ~1–2 VDOT on longer efforts. Tests pin that expected drift.
 */
final class Vdot
{
    // VO2(v) = A + B·v + C·v²   (v in m/min)
    private const VO2_A = -4.60;
    private const VO2_B = 0.182258;
    private const VO2_C = 0.000104;

    /**
     * VDOT for a race performance.
     */
    public static function fromRace(int $distanceM, int $timeS): float
    {
        if ($distanceM <= 0 || $timeS <= 0) {
            throw new \InvalidArgumentException('distance and time must be positive');
        }

        $tMin = $timeS / 60.0;
        $velocity = $distanceM / $tMin; // m/min

        $percentVo2max = 0.8
            + 0.1894393 * exp(-0.012778 * $tMin)
            + 0.2989558 * exp(-0.1932605 * $tMin);

        $vo2 = self::VO2_A + self::VO2_B * $velocity + self::VO2_C * $velocity ** 2;

        return $vo2 / $percentVo2max;
    }

    /**
     * Velocity at VO2max (VMA), in km/h, for a given VDOT — inverts VO2(v) = VDOT.
     */
    public static function toVmaKmh(float $vdot): float
    {
        // C·v² + B·v + (A − VDOT) = 0  →  positive root
        $a = self::VO2_C;
        $b = self::VO2_B;
        $c = self::VO2_A - $vdot;

        $discriminant = $b ** 2 - 4 * $a * $c;
        $velocity = (-$b + sqrt($discriminant)) / (2 * $a); // m/min

        return $velocity * 60 / 1000;
    }
}
