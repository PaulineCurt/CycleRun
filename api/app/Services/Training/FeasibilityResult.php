<?php

namespace App\Services\Training;

final readonly class FeasibilityResult
{
    public function __construct(
        public bool $feasible,
        /** Current projection on the goal distance, no training applied (seconds). */
        public int $projectedTimeS,
        /** Best estimate after `weeks` of training (seconds). */
        public int $achievableTimeS,
        /** Set when !feasible: a goal time that is realistic for the given weeks. */
        public ?int $suggestedGoalTimeS,
        /** Set when !feasible but the goal is reachable with a longer plan. */
        public ?int $suggestedDurationWeeks,
        public ?string $alert,
    ) {
    }
}
