<?php

namespace App\Services\Cycle;

final readonly class RegularityResult
{
    public function __construct(
        public Regularity $regularity,
        public bool $lowConfidence,
        public ?float $amplitudeDays,
    ) {
    }
}
