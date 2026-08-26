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
}
