<?php

namespace App\Services\Training;

use App\Enums\Level;

/**
 * Output of step 1 of the build pipeline (§5): a single VMA-based referential plus
 * the derived training paces, frozen at generation time.
 *
 * Carries the declared `level` and the `vmaProvenance` (§6 refinement ladder) so
 * downstream steps and persistence know how well anchored the VMA is.
 */
final readonly class NormalizedLevel
{
    /**
     * @param  array<string, array{0: int, 1: int}>  $paces  zone => [minSecPerKm, maxSecPerKm].
     *                                                       Empty when the VMA is a bare profile default (paces are hidden — the
     *                                                       beginner runs course/marche on effort, not on a min/km band).
     */
    public function __construct(
        public float $vmaKmh,
        public ?float $vdot,
        public LevelSource $source,
        public ?int $referenceDistanceM,
        public ?int $referenceTimeS,
        public array $paces,
        public Level $level,
        public VmaProvenance $vmaProvenance,
    ) {}
}
