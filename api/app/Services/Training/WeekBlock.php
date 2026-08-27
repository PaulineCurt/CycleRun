<?php

namespace App\Services\Training;

/**
 * One week of the periodization plan — a volume target, not yet dated sessions.
 * Volumes are in minutes (decision #2, 2026-08-27: duration-based for v1).
 */
final readonly class WeekBlock
{
    public function __construct(
        public int $weekIndex,
        public Mesocycle $mesocycle,
        public bool $isDeload,
        public int $totalVolumeMin,
        public int $hardVolumeMin,
        public int $easyVolumeMin,
    ) {
    }
}
