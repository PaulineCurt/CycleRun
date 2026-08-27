<?php

namespace App\Services\Cycle;

use Carbon\CarbonImmutable;

final readonly class PhaseResult
{
    public function __construct(
        public ?Phase $phase,
        public bool $predictable,
        public CarbonImmutable $predictedNextStart,
        public CarbonImmutable $ovulationWindowStart,
        public CarbonImmutable $ovulationWindowEnd,
        public ?CarbonImmutable $nextStartRangeStart,
        public ?CarbonImmutable $nextStartRangeEnd,
        public bool $lowConfidence,
    ) {
    }
}
