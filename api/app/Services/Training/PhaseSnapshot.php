<?php

namespace App\Services\Training;

use App\Services\Cycle\Phase;

/**
 * The Moteur A → Moteur B interface contract (§6 moteurB.md):
 * getPhaseForDate(user_id, date) -> { phase, confidence, pain_flag }.
 *
 * `confidence` is low for hard-to-predict cycles (irregular, sparse history) — the
 * adapter then stays cautious instead of faking precision.
 */
final readonly class PhaseSnapshot
{
    public function __construct(
        public ?Phase $phase,
        public float $confidence,
        public bool $painFlag,
    ) {
    }

    public static function none(bool $painFlag = false): self
    {
        return new self(null, 0.0, $painFlag);
    }
}
