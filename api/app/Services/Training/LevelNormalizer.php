<?php

namespace App\Services\Training;

use App\Enums\Level;

/**
 * Step 1 of the pipeline (§5): normalizeLevel(level, level_input) -> { vma, paces{} }.
 *
 * Pure. Resolves the declared level plus the (optional) raw level_input to a
 * single VMA, a full pace table and a provenance marker (§6). VDOT is an import
 * path only (decision #1).
 */
final class LevelNormalizer
{
    /**
     * @param  array<string, mixed>|null  $levelInput  null when no anchor was given
     *                                                 (débutante, end of stage 1)
     */
    public function normalize(Level $level, ?array $levelInput): NormalizedLevel
    {
        if ($levelInput === null) {
            return $this->fromProfile($level);
        }

        $kind = $levelInput['kind'] ?? null;

        return match ($kind) {
            'field_test' => $this->fromFieldTest($level, $levelInput),
            'race_result' => $this->fromRaceResult($level, $levelInput),
            default => throw new \InvalidArgumentException('unknown level_input kind: '.var_export($kind, true)),
        };
    }

    /**
     * Build the pace table for a given VMA. Paces are seconds per km:
     * sPerKm = 3600 / (vmaKmh * fraction). Higher fraction => faster => fewer seconds,
     * so the zone's high fraction gives the min bound and its low fraction the max.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public function paces(float $vmaKmh): array
    {
        $table = [];

        foreach (PaceZone::cases() as $zone) {
            [$low, $high] = $zone->vmaFraction();
            $table[$zone->value] = [
                (int) round(3600 / ($vmaKmh * $high)),
                (int) round(3600 / ($vmaKmh * $low)),
            ];
        }

        return $table;
    }

    /**
     * No test given: seed the VMA from the level profile and hide the pace table
     * (empty => paceFor() yields [null, null], so course/marche sessions carry no
     * min/km band — the beginner runs on effort).
     */
    private function fromProfile(Level $level): NormalizedLevel
    {
        return new NormalizedLevel(
            vmaKmh: LevelProfiles::for($level)->defaultVmaKmh,
            vdot: null,
            source: LevelSource::Profile,
            referenceDistanceM: null,
            referenceTimeS: null,
            paces: [],
            level: $level,
            vmaProvenance: VmaProvenance::Default,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fromFieldTest(Level $level, array $input): NormalizedLevel
    {
        if (isset($input['vma_kmh'])) {
            $vma = (float) $input['vma_kmh'];
        } elseif (isset($input['half_cooper_m'])) {
            // 6-minute test: VMA (km/h) = distance covered (m) / 100.
            $vma = (float) $input['half_cooper_m'] / 100;
        } else {
            throw new \InvalidArgumentException('field_test needs vma_kmh or half_cooper_m');
        }

        if ($vma <= 0) {
            throw new \InvalidArgumentException('VMA must be positive');
        }

        return new NormalizedLevel(
            vmaKmh: $vma,
            vdot: null,
            source: LevelSource::FieldTest,
            referenceDistanceM: null,
            referenceTimeS: null,
            paces: $this->paces($vma),
            level: $level,
            vmaProvenance: VmaProvenance::Declared,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fromRaceResult(Level $level, array $input): NormalizedLevel
    {
        $distanceM = (int) ($input['distance_m'] ?? 0);
        $timeS = (int) ($input['time_s'] ?? 0);

        if ($distanceM <= 0 || $timeS <= 0) {
            throw new \InvalidArgumentException('race_result needs positive distance_m and time_s');
        }

        $vdot = Vdot::fromRace($distanceM, $timeS);
        $vma = Vdot::toVmaKmh($vdot);

        return new NormalizedLevel(
            vmaKmh: $vma,
            vdot: $vdot,
            source: LevelSource::RaceResult,
            referenceDistanceM: $distanceM,
            referenceTimeS: $timeS,
            paces: $this->paces($vma),
            level: $level,
            vmaProvenance: VmaProvenance::Declared,
        );
    }
}
