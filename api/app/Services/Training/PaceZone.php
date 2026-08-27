<?php

namespace App\Services\Training;

/**
 * Training pace zones expressed as a fraction of VMA (§4.1 moteurB.md).
 * Percentages are provisional — "à figer avec l'expert" (coach FFA).
 */
enum PaceZone: string
{
    case Recovery = 'recovery';
    case Easy = 'easy';
    case Tempo = 'tempo';
    case Threshold = 'threshold';
    case Interval = 'interval';

    /**
     * [low, high] fraction of VMA for this zone. `low` yields the slow bound of the
     * pace range, `high` the fast bound.
     *
     * @return array{0: float, 1: float}
     */
    public function vmaFraction(): array
    {
        return match ($this) {
            self::Recovery => [0.60, 0.65],
            self::Easy => [0.65, 0.75],
            self::Tempo => [0.75, 0.85],
            self::Threshold => [0.85, 0.90],
            self::Interval => [0.95, 1.05],
        };
    }
}
