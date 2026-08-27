<?php

namespace App\Services\Training;

use App\Enums\SessionType;
use Carbon\CarbonImmutable;

/**
 * Step 6 of the pipeline (§5): materializeSessions(blocks, paces, start_date) ->
 * dated sessions with target paces.
 *
 * Fixed weekdays for the whole plan (decision #5, 2026-08-27). Session shapes and
 * the run/walk progression are provisional — TODO(expert), §4.3/§4.5.
 */
final class SessionMaterializer
{
    /** Day offsets from the week's start, per days_per_week. Provisional spacing. */
    private const DAY_OFFSETS = [
        2 => [1, 4],
        3 => [0, 2, 4],
        4 => [0, 2, 4, 6],
    ];

    private const RUN_WALK_RUN_START_S = 60;
    private const RUN_WALK_WALK_START_S = 90;
    private const CONTINUOUS_THRESHOLD_S = 1200;   // 20 min continuous => bascule to continuous.
    private const RUN_WALK_RAMP_SHARE = 0.6;       // fraction of the plan spent ramping run:walk.
    private const LONG_SHARE = 0.30;               // fraction of weekly volume in the long run.
    private const MIN_SESSION_MIN = 10;
    private const WARMUP_COOLDOWN_S = 1200;        // 10 min warm-up + 10 min cool-down around quality.
    private const INTERVAL_WORK_S = 120;
    private const INTERVAL_RECOVERY_S = 60;
    private const HARD_VOLUME_FLOOR_MIN = 8;       // below this, no dedicated hard session.

    /**
     * @param array<string, array{0: int, 1: int}> $paces
     * @param list<WeekBlock> $blocks
     * @return list<SessionDraft>
     */
    public function materialize(array $blocks, array $paces, CarbonImmutable $startDate, int $daysPerWeek, Track $track, int $totalWeeks): array
    {
        $offsets = self::DAY_OFFSETS[$daysPerWeek]
            ?? throw new \InvalidArgumentException("unsupported days_per_week: {$daysPerWeek}");

        $rampWeeks = max(1, (int) round($totalWeeks * self::RUN_WALK_RAMP_SHARE));
        $sessions = [];

        foreach ($blocks as $block) {
            $runWalkWeek = $track === Track::RunWalk && $this->runInterval($block->weekIndex, $rampWeeks) < self::CONTINUOUS_THRESHOLD_S;

            $specs = $runWalkWeek
                ? $this->runWalkWeek($block, $daysPerWeek, $rampWeeks)
                : $this->continuousWeek($block, $daysPerWeek, $track);

            foreach ($offsets as $slot => $offset) {
                $spec = $specs[$slot];
                [$minPace, $maxPace] = $this->paceFor($spec['zone'], $paces);

                $sessions[] = new SessionDraft(
                    plannedDate: $startDate->addDays($block->weekIndex * 7 + $offset),
                    weekIndex: $block->weekIndex,
                    type: $spec['type'],
                    plannedLoad: $spec['load'],
                    targetPaceMinS: $minPace,
                    targetPaceMaxS: $maxPace,
                );
            }
        }

        return $sessions;
    }

    private function runInterval(int $weekIndex, int $rampWeeks): int
    {
        $progress = min(1.0, $weekIndex / $rampWeeks);

        return (int) round(
            self::RUN_WALK_RUN_START_S + ($progress * (self::CONTINUOUS_THRESHOLD_S - self::RUN_WALK_RUN_START_S))
        );
    }

    private function walkInterval(int $weekIndex, int $rampWeeks): int
    {
        $progress = min(1.0, $weekIndex / $rampWeeks);

        return max(0, (int) round(self::RUN_WALK_WALK_START_S * (1 - $progress)));
    }

    /**
     * @return array<int, array{type: SessionType, load: array<string,mixed>, zone: ?PaceZone}>
     */
    private function runWalkWeek(WeekBlock $block, int $daysPerWeek, int $rampWeeks): array
    {
        $runS = $this->runInterval($block->weekIndex, $rampWeeks);
        $walkS = $this->walkInterval($block->weekIndex, $rampWeeks);
        $perSessionActiveS = (int) round($block->totalVolumeMin * 60 / $daysPerWeek);
        $reps = max(3, (int) round($perSessionActiveS / max(1, $runS + $walkS)));

        $spec = [
            'type' => SessionType::RunWalk,
            'load' => ['mode' => 'run_walk', 'reps' => $reps, 'run_s' => $runS, 'walk_s' => $walkS],
            'zone' => PaceZone::Recovery,
        ];

        return array_fill(0, $daysPerWeek, $spec);
    }

    /**
     * @return array<int, array{type: SessionType, load: array<string,mixed>, zone: ?PaceZone}>
     */
    private function continuousWeek(WeekBlock $block, int $daysPerWeek, Track $track): array
    {
        $hasHard = $block->mesocycle !== Mesocycle::Base
            && $block->hardVolumeMin >= self::HARD_VOLUME_FLOOR_MIN;

        $hardSpec = $hasHard ? $this->hardSpec($block, $track) : null;
        $hardTotalMin = $hardSpec !== null ? (int) round($this->specSeconds($hardSpec) / 60) : 0;

        // Split the easy budget across the plain easy days + the long run, giving
        // the long run the biggest single slice so it is always the longest run.
        $easyBudgetMin = max($daysPerWeek * self::MIN_SESSION_MIN, $block->totalVolumeMin - $hardTotalMin);
        $easyDayCount = $daysPerWeek - ($hasHard ? 1 : 0); // includes the long run
        $plainEasyCount = $easyDayCount - 1;

        // The long run always takes the biggest single slice of the easy budget.
        $longShare = $plainEasyCount > 0 ? max(0.40, 1 / $easyDayCount + 0.12) : 1.0;
        $longMin = max(self::MIN_SESSION_MIN, (int) round($easyBudgetMin * $longShare));
        $perEasyMin = $plainEasyCount > 0
            ? max(self::MIN_SESSION_MIN, (int) round(($easyBudgetMin - $longMin) / $plainEasyCount))
            : 0;

        // Long run always closes the week; the hard session sits mid-week for 3–4
        // days, but on a 2-day week it takes the opening slot (slot 1 is the long).
        $lastSlot = $daysPerWeek - 1;
        $hardSlot = $daysPerWeek === 2 ? 0 : 1;

        $specs = [];
        for ($slot = 0; $slot < $daysPerWeek; $slot++) {
            if ($slot === $lastSlot) {
                $specs[$slot] = $this->easySpec(SessionType::Long, $longMin);
            } elseif ($hasHard && $slot === $hardSlot) {
                $specs[$slot] = $hardSpec;
            } else {
                $specs[$slot] = $this->easySpec(SessionType::Easy, $perEasyMin);
            }
        }

        return $specs;
    }

    /**
     * @param array{type: SessionType, load: array<string,mixed>, zone: ?PaceZone} $spec
     */
    private function specSeconds(array $spec): int
    {
        $load = $spec['load'];

        return match ($load['mode'] ?? null) {
            'duration' => (int) ($load['duration_s'] ?? 0),
            'intervals' => (int) ($load['reps'] ?? 0) * ((int) ($load['work_s'] ?? 0) + (int) ($load['recovery_s'] ?? 0)),
            'run_walk' => (int) ($load['reps'] ?? 0) * ((int) ($load['run_s'] ?? 0) + (int) ($load['walk_s'] ?? 0)),
            default => 0,
        };
    }

    /**
     * @return array{type: SessionType, load: array<string,mixed>, zone: ?PaceZone}
     */
    private function easySpec(SessionType $type, int $minutes): array
    {
        return [
            'type' => $type,
            'load' => ['mode' => 'duration', 'duration_s' => $minutes * 60],
            'zone' => PaceZone::Easy,
        ];
    }

    /**
     * @return array{type: SessionType, load: array<string,mixed>, zone: ?PaceZone}
     */
    private function hardSpec(WeekBlock $block, Track $track): array
    {
        $workS = $block->hardVolumeMin * 60;

        // A former beginner (run/walk origin) stays on tempo; a continuous runner
        // alternates VMA intervals and threshold week to week.
        if ($track === Track::RunWalk) {
            return [
                'type' => SessionType::Tempo,
                'load' => ['mode' => 'duration', 'duration_s' => $workS + self::WARMUP_COOLDOWN_S, 'work_s' => $workS],
                'zone' => PaceZone::Tempo,
            ];
        }

        if ($block->weekIndex % 2 === 0) {
            $reps = max(4, (int) round($workS / self::INTERVAL_WORK_S));

            return [
                'type' => SessionType::Interval,
                'load' => [
                    'mode' => 'intervals',
                    'reps' => $reps,
                    'work_s' => self::INTERVAL_WORK_S,
                    'recovery_s' => self::INTERVAL_RECOVERY_S,
                ],
                'zone' => PaceZone::Interval,
            ];
        }

        return [
            'type' => SessionType::Threshold,
            'load' => ['mode' => 'duration', 'duration_s' => $workS + self::WARMUP_COOLDOWN_S, 'work_s' => $workS],
            'zone' => PaceZone::Threshold,
        ];
    }

    /**
     * @param array<string, array{0: int, 1: int}> $paces
     * @return array{0: ?int, 1: ?int}
     */
    private function paceFor(?PaceZone $zone, array $paces): array
    {
        if ($zone === null || ! isset($paces[$zone->value])) {
            return [null, null];
        }

        return [$paces[$zone->value][0], $paces[$zone->value][1]];
    }
}
