<?php

namespace App\Services\Training;

/**
 * Step 3 of the pipeline (§5 / §4.4 moteurB_three_levels.md): selectTrack(profile).
 *
 * The declared level decides: only a débutante (`startsRunWalk`) starts on the
 * course/marche protocol; intermédiaire / confirmée run continuously from week 1.
 *
 * The VMA is no longer a hard switch — it is a secondary net: if a *measured* VMA
 * badly contradicts a continuous level, `contradictsContinuous()` lets the caller
 * raise a product-side signal without forcing a downgrade.
 */
final class TrackSelector
{
    /** Below this VMA a continuous level looks doubtful. TODO(expert), §4.4. */
    private const RUN_WALK_VMA_CEILING = 10.5;

    public function select(LevelProfile $profile): Track
    {
        return $profile->startsRunWalk ? Track::RunWalk : Track::Continuous;
    }

    public function contradictsContinuous(float $vmaKmh): bool
    {
        return $vmaKmh < self::RUN_WALK_VMA_CEILING;
    }
}
