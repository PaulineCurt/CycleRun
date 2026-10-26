<?php

namespace App\Services\Training;

/**
 * buildProgram(params) -> Program (§5 moteurB.md).
 *
 * A pure chain of transformations: same params in => same plan out, every time.
 * No DB, no network, no AI, no hidden clock. The cycle is never consulted here —
 * that is a separate render-time layer (B4).
 */
final class ProgramBuilder
{
    public function __construct(
        private readonly LevelNormalizer $levelNormalizer = new LevelNormalizer,
        private readonly FeasibilityChecker $feasibilityChecker = new FeasibilityChecker,
        private readonly TrackSelector $trackSelector = new TrackSelector,
        private readonly Periodizer $periodizer = new Periodizer,
        private readonly SessionMaterializer $materializer = new SessionMaterializer,
        private readonly IntensityBalancer $intensityBalancer = new IntensityBalancer,
        private readonly SafetyCapper $safetyCapper = new SafetyCapper,
    ) {}

    public function build(BuildParams $params): BuildResult
    {
        if ($params->durationWeeks < 1) {
            throw new \InvalidArgumentException('durationWeeks must be >= 1');
        }
        if ($params->daysPerWeek < 2 || $params->daysPerWeek > 4) {
            throw new \InvalidArgumentException('daysPerWeek must be 2..4 in v1');
        }

        $profile = LevelProfiles::for($params->level);

        $level = $this->levelNormalizer->normalize($params->level, $params->levelInput);

        $feasibility = $this->feasibilityChecker->check(
            $profile,
            $level,
            $params->goalDistanceM,
            $params->goalTimeS,
            $params->durationWeeks,
        );

        $track = $this->trackSelector->select($profile);

        $blocks = $this->periodizer->build($params->durationWeeks, $params->daysPerWeek, $track, $profile);

        $sessions = $this->materializer->materialize(
            $blocks,
            $level->paces,
            $params->startDate,
            $params->daysPerWeek,
            $track,
            $params->durationWeeks,
        );

        $sessions = $this->intensityBalancer->balance($sessions);

        $capped = $this->safetyCapper->apply(
            $sessions,
            $params->healthFlags,
            $blocks,
            $profile->weeklyProgression,
            $level->paces,
        );

        $warnings = $capped['warnings'];

        // Secondary net (§4.4): a *measured* VMA well under the continuous floor on
        // a continuous level is surfaced, never auto-downgraded. Not reachable at
        // creation today (provenance is Default/Declared) — here for the re-plan.
        if (! $profile->startsRunWalk
            && $level->vmaProvenance === VmaProvenance::Measured
            && $this->trackSelector->contradictsContinuous($level->vmaKmh)) {
            $warnings[] = 'VMA mesurée basse pour un cran en course continue : '
                .'un passage course/marche peut être proposé.';
        }

        if ($feasibility->alert !== null) {
            array_unshift($warnings, $feasibility->alert);
        }

        return new BuildResult(
            level: $level,
            feasibility: $feasibility,
            track: $track,
            blocks: $blocks,
            sessions: $capped['sessions'],
            warnings: $warnings,
            declaredLevel: $params->level,
        );
    }
}
