<?php

namespace App\Services\Training;

/**
 * The cycle-layer decision attached to a session at render time (§6/§7 moteurB.md),
 * stored separately from `base_generated` so it is always reversible.
 *
 * `intensityDelta` is a hint for the consumer/UI:
 *   0.0  -> no change   |   negative -> aim that fraction lighter   |   -1.0 -> skip / postpone
 */
final readonly class SessionAdaptation
{
    public function __construct(
        public ?string $phase,
        public float $confidence,
        public string $rule,
        public string $headline,
        public float $intensityDelta,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'confidence' => round($this->confidence, 2),
            'rule' => $this->rule,
            'headline' => $this->headline,
            'intensity_delta' => $this->intensityDelta,
        ];
    }
}
