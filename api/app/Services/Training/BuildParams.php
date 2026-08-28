<?php

namespace App\Services\Training;

use Carbon\CarbonImmutable;

/**
 * Everything buildProgram() needs. Every source of variation is an explicit
 * parameter — no now(), no randomness, no I/O (§2/§5 moteurB.md).
 */
final readonly class BuildParams
{
    /**
     * @param array<string, mixed> $levelInput  raw level_input (field_test | race_result)
     * @param array<string, mixed> $healthFlags initial health questionnaire flags
     */
    public function __construct(
        public array $levelInput,
        public int $goalDistanceM,
        public ?int $goalTimeS,
        public int $durationWeeks,
        public int $daysPerWeek,
        public array $healthFlags,
        public CarbonImmutable $startDate,
    ) {
    }
}
