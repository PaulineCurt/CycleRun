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
     * Aerobic cost of running at velocity `v` (m/min), in ml/kg/min.
     */
    public static function vo2AtVelocity(float $vMetersPerMin): float
    {
        return self::VO2_A + self::VO2_B * $vMetersPerMin + self::VO2_C * $vMetersPerMin ** 2;
    }

    /**
     * Fraction of VO2max sustainable for an effort lasting `tMin` minutes — the
     * D&G drop-off curve. Also used by FeasibilityChecker to estimate a 5 km time
     * from a bare VMA, so the whole engine reads one curve.
     */
    public static function percentVo2maxForDuration(float $tMin): float
    {
        return 0.8
            + 0.1894393 * exp(-0.012778 * $tMin)
            + 0.2989558 * exp(-0.1932605 * $tMin);
    }

    /**
     * Velocity (m/min) whose aerobic cost equals `$vo2` — positive root of
     * VO2(v) = vo2. Shared by toVmaKmh() and the feasibility fixed-point.
     */
    public static function velocityForVo2(float $vo2): float
    {
        $a = self::VO2_C;
        $b = self::VO2_B;
        $c = self::VO2_A - $vo2;

        $discriminant = $b ** 2 - 4 * $a * $c;

        return (-$b + sqrt($discriminant)) / (2 * $a);
    }

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

        return self::vo2AtVelocity($velocity) / self::percentVo2maxForDuration($tMin);
    }

    /**
     * Velocity at VO2max (VMA), in km/h, for a given VDOT — inverts VO2(v) = VDOT.
     */
    public static function toVmaKmh(float $vdot): float
    {
        return self::velocityForVo2($vdot) * 60 / 1000;
    }
}
