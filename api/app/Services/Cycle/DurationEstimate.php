<?php

namespace App\Services\Cycle;

final readonly class DurationEstimate
{
    public function __construct(
        public float $days,
        public ?float $stdDev,
        public int $sampleSize,
        public EstimateSource $source,
    ) {
    }

    /**
     * True for an estimate confident enough to drive predictions without hedging —
     * either genuinely computed from logged history, or a deliberate manual override
     * (which the user set precisely to make predictions more authoritative, not less).
     */
    public function isTrustworthy(): bool
    {
        return $this->source === EstimateSource::Measured || $this->source === EstimateSource::Override;
    }
}
