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
        private readonly LevelNormalizer $levelNormalizer = new LevelNormalizer(),
        private readonly FeasibilityChecker $feasibilityChecker = new FeasibilityChecker(),
        private readonly TrackSelector $trackSelector = new TrackSelector(),
        private readonly Periodizer $periodizer = new Periodizer(),
        private readonly SessionMaterializer $materializer = new SessionMaterializer(),
        private readonly IntensityBalancer $intensityBalancer = new IntensityBalancer(),
        private readonly SafetyCapper $safetyCapper = new SafetyCapper(),
    ) {
    }

    public function build(BuildParams $params): BuildResult
    {
        if ($params->durationWeeks < 1) {
            throw new \InvalidArgumentException('durationWeeks must be >= 1');
        }
        if ($params->daysPerWeek < 2 || $params->daysPerWeek > 4) {
            throw new \InvalidArgumentException('daysPerWeek must be 2..4 in v1');
        }

        $level = $this->levelNormalizer->normalize($params->levelInput);

        $feasibility = $this->feasibilityChecker->check(
            $level,
            $params->goalDistanceM,
            $params->goalTimeS,
            $params->durationWeeks,
        );

        $track = $this->trackSelector->select($level);

        $blocks = $this->periodizer->build($params->durationWeeks, $params->daysPerWeek, $track);

        $sessions = $this->materializer->materialize(
            $blocks,
            $level->paces,
            $params->startDate,
            $params->daysPerWeek,
            $track,
            $params->durationWeeks,
        );

        $sessions = $this->intensityBalancer->balance($sessions);

        $capped = $this->safetyCapper->apply($sessions, $params->healthFlags, $blocks, $level->paces);

        $warnings = $capped['warnings'];
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
        );
    }
}
