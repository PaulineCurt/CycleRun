<?php

namespace App\Services\Training;

/**
 * Step 3 of the pipeline (§5): selectTrack(level) -> run_walk | continuous.
 *
 * A true beginner (very low VMA) starts on the course/marche protocol; everyone
 * else runs continuously from week 1. The VMA ceiling is provisional — TODO(expert).
 */
final class TrackSelector
{
    /** Below this VMA the runner starts run/walk. TODO(expert), §4.3. */
    private const RUN_WALK_VMA_CEILING = 10.5;

    public function select(NormalizedLevel $level): Track
    {
        return $level->vmaKmh < self::RUN_WALK_VMA_CEILING
            ? Track::RunWalk
            : Track::Continuous;
    }
}
