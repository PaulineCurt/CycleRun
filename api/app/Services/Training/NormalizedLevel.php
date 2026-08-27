<?php

namespace App\Services\Training;

/**
 * Output of step 1 of the build pipeline (§5): a single VMA-based referential plus
 * the derived training paces, frozen at generation time.
 */
final readonly class NormalizedLevel
{
    /**
     * @param array<string, array{0: int, 1: int}> $paces zone => [minSecPerKm, maxSecPerKm]
     */
    public function __construct(
        public float $vmaKmh,
        public ?float $vdot,
        public LevelSource $source,
        public ?int $referenceDistanceM,
        public ?int $referenceTimeS,
        public array $paces,
    ) {
    }
}
