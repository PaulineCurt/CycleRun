<?php

namespace App\Services\Cycle;

use Carbon\CarbonImmutable;

final readonly class PhaseResult
{
    public function __construct(
        public Phase $phase,
        public CarbonImmutable $predictedNextStart,
        public CarbonImmutable $ovulationWindowStart,
        public CarbonImmutable $ovulationWindowEnd,
        public bool $lowConfidence,
    ) {
    }
}
