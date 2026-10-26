<?php

namespace App\Services\Training;

/**
 * The starting values that vary by declared level (§4.2 moteurB_three_levels.md).
 *
 * A LevelProfile is resolved once, before the pipeline, and injected into
 * Periodizer / SessionMaterializer (via paces) / TrackSelector / FeasibilityChecker.
 * The level *selects* a profile — it never recomputes any methodology.
 *
 * Do not build instances ad hoc: the only source of the numbers is LevelProfiles.
 */
final readonly class LevelProfile
{
    public function __construct(
        /** Week-0 session length, minutes. [prov.] — was Periodizer::MINUTES_PER_SESSION_START. */
        public int $minutesPerSessionStart,
        /** Hard cap on avg minutes/session. [prov.] — was Periodizer::SESSION_MINUTES_CAP. */
        public int $sessionMinutesCap,
        /** Fraction of working weeks spent in base. [coach] — was Periodizer::BASE_SHARE. */
        public float $baseShare,
        /** Weekly volume progression ceiling. [coach] — was Periodizer::WEEKLY_PROGRESSION. */
        public float $weeklyProgression,
        /** Deload cadence in weeks. [coach] — was Periodizer::DELOAD_EVERY. */
        public int $deloadEvery,
        /** Volume factor on a deload week. [coach] — was Periodizer::DELOAD_FACTOR. */
        public float $deloadFactor,
        /** Plausible relative improvement per training week. [coach] — was FeasibilityChecker. */
        public float $improvementPerWeek,
        /** Hard cap on total relative improvement over one plan. [coach] — was FeasibilityChecker. */
        public float $maxImprovement,
        /** True beginner: starts on the course/marche protocol. Drives TrackSelector. */
        public bool $startsRunWalk,
        /** Fallback VMA (km/h) when no test is given — feasibility & pace seed only. [prov.] */
        public float $defaultVmaKmh,
    ) {}
}
