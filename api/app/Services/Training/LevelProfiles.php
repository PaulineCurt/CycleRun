<?php

namespace App\Services\Training;

use App\Enums\Level;

/**
 * The single source of truth for every per-level starting value
 * (§4.1/§4.2 moteurB_three_levels.md).
 *
 * All the provisional [prov.] / to-be-validated [coach] numbers live here and
 * nowhere else — the FFA-coach review is meant to be "edit this one file".
 * Universal constants (PaceZone fractions, the 80/20 target, VDOT/Riegel) are
 * deliberately NOT here: the VMA already personalises those (§4.3).
 */
final class LevelProfiles
{
    public static function for(Level $level): LevelProfile
    {
        return match ($level) {
            Level::Debutante => new LevelProfile(
                minutesPerSessionStart: 20,
                sessionMinutesCap: 45,
                baseShare: 0.55,
                weeklyProgression: 1.08,
                deloadEvery: 3,
                deloadFactor: 0.70,
                improvementPerWeek: 0.010,
                maxImprovement: 0.20,
                startsRunWalk: true,
                defaultVmaKmh: 9.5,
            ),
            Level::Intermediaire => new LevelProfile(
                minutesPerSessionStart: 30,
                sessionMinutesCap: 60,
                baseShare: 0.45,
                weeklyProgression: 1.10,
                deloadEvery: 4,
                deloadFactor: 0.65,
                improvementPerWeek: 0.006,
                maxImprovement: 0.12,
                startsRunWalk: false,
                defaultVmaKmh: 12.0,
            ),
            Level::Confirmee => new LevelProfile(
                minutesPerSessionStart: 40,
                sessionMinutesCap: 75,
                baseShare: 0.35,
                weeklyProgression: 1.11,
                deloadEvery: 4,
                deloadFactor: 0.60,
                improvementPerWeek: 0.004,
                maxImprovement: 0.08,
                startsRunWalk: false,
                defaultVmaKmh: 14.5,
            ),
        };
    }
}
