<?php

namespace App\Services\Training;

/**
 * Output of buildProgram(): a full plan that is valid without ever having touched
 * the cycle (§1/§5 moteurB.md). The cycle adaptation layer (B4) is applied later,
 * at render time, on top of this.
 */
final readonly class BuildResult
{
    /**
     * @param list<WeekBlock> $blocks
     * @param list<SessionDraft> $sessions
     * @param list<string> $warnings
     */
    public function __construct(
        public NormalizedLevel $level,
        public FeasibilityResult $feasibility,
        public Track $track,
        public array $blocks,
        public array $sessions,
        public array $warnings,
    ) {
    }

    /**
     * Stable fingerprint of the generated plan — same params must yield the same
     * hash (§9 determinism test).
     */
    public function hash(): string
    {
        $payload = array_map(fn (SessionDraft $s) => $s->toArray(), $this->sessions);

        return hash('sha256', json_encode([
            'track' => $this->track->value,
            'sessions' => $payload,
        ], JSON_THROW_ON_ERROR));
    }
}
