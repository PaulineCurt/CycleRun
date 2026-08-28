<?php

namespace App\Services\Training;

use App\Enums\SessionType;
use Carbon\CarbonImmutable;

/**
 * A dated, prescribed session before any cycle adaptation (§5 step 6). The
 * persister (B5) maps this straight onto a `training_sessions` row, copying it
 * into `base_generated` so the plan can always be recovered cycle-free.
 */
final readonly class SessionDraft
{
    /**
     * @param array<string, mixed> $plannedLoad shape depends on type:
     *   easy/long/tempo/threshold: { mode: 'duration', duration_s, [work_s] }
     *   interval:                  { mode: 'intervals', reps, work_s, recovery_s }
     *   run_walk:                  { mode: 'run_walk', reps, run_s, walk_s }
     *   rest/cross:                { mode: 'rest'|'cross', [duration_s] }
     */
    public function __construct(
        public CarbonImmutable $plannedDate,
        public int $weekIndex,
        public SessionType $type,
        public array $plannedLoad,
        public ?int $targetPaceMinS,
        public ?int $targetPaceMaxS,
    ) {
    }

    /** Total time this session spends in a hard zone (threshold/interval/tempo), for the 80/20 check. */
    public function hardSeconds(): int
    {
        if (! in_array($this->type, [SessionType::Tempo, SessionType::Threshold, SessionType::Interval], true)) {
            return 0;
        }

        return $this->workSeconds();
    }

    /** Total moving time of the session, in seconds. */
    public function totalSeconds(): int
    {
        return match ($this->plannedLoad['mode'] ?? null) {
            'duration' => (int) ($this->plannedLoad['duration_s'] ?? 0),
            'intervals' => (int) ($this->plannedLoad['reps'] ?? 0)
                * ((int) ($this->plannedLoad['work_s'] ?? 0) + (int) ($this->plannedLoad['recovery_s'] ?? 0)),
            'run_walk' => (int) ($this->plannedLoad['reps'] ?? 0)
                * ((int) ($this->plannedLoad['run_s'] ?? 0) + (int) ($this->plannedLoad['walk_s'] ?? 0)),
            'cross' => (int) ($this->plannedLoad['duration_s'] ?? 0),
            default => 0,
        };
    }

    private function workSeconds(): int
    {
        return match ($this->plannedLoad['mode'] ?? null) {
            'duration' => (int) ($this->plannedLoad['work_s'] ?? $this->plannedLoad['duration_s'] ?? 0),
            'intervals' => (int) ($this->plannedLoad['reps'] ?? 0) * (int) ($this->plannedLoad['work_s'] ?? 0),
            default => 0,
        };
    }

    public function withPlannedLoad(array $plannedLoad): self
    {
        return new self(
            $this->plannedDate,
            $this->weekIndex,
            $this->type,
            $plannedLoad,
            $this->targetPaceMinS,
            $this->targetPaceMaxS,
        );
    }

    public function withType(SessionType $type, ?int $paceMinS, ?int $paceMaxS): self
    {
        return new self($this->plannedDate, $this->weekIndex, $type, $this->plannedLoad, $paceMinS, $paceMaxS);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'planned_date' => $this->plannedDate->toDateString(),
            'week_index' => $this->weekIndex,
            'type' => $this->type->value,
            'planned_load' => $this->plannedLoad,
            'target_pace_min_s' => $this->targetPaceMinS,
            'target_pace_max_s' => $this->targetPaceMaxS,
        ];
    }
}
