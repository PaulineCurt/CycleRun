<?php

namespace App\Services\Training;

use App\Enums\SessionStatus;
use App\Models\TrainingSession;
use App\Models\User;

/**
 * Render-time layer (§6 moteurB.md): overlay the cycle on top of the persisted
 * plan, in memory. It never rewrites `base_generated` and never saves — drop this
 * step and you are back to the plain generated plan.
 *
 * Only `planned` sessions re-derive. Once a session has been acted on (done /
 * skipped / moved) its recorded `adaptation` is frozen — history must not shift
 * under a cycle estimate that keeps moving as more data comes in.
 *
 * Sets these transient attributes on each session (not columns):
 *   cycle      { phase, confidence, pain_flag } | null   — where the cycle is that day
 *   adapted    bool                                       — planned_load was lightened
 *   postponed  bool                                       — declared pain: move / rest
 */
final class CycleAdaptationDecorator
{
    public function __construct(
        private readonly CyclePhaseProvider $phaseProvider = new CyclePhaseProvider(),
        private readonly CycleAdapter $adapter = new CycleAdapter(),
    ) {
    }

    /**
     * @param iterable<TrainingSession> $sessions
     */
    public function decorate(User $user, iterable $sessions): void
    {
        $sessions = $sessions instanceof \Traversable ? iterator_to_array($sessions) : (array) $sessions;

        $livePlanned = array_filter(
            $sessions,
            fn (TrainingSession $s) => $s->status === null || $s->status === SessionStatus::Planned,
        );

        // Load once for the whole batch instead of per session (cycles/exclusions are
        // safe to loadMissing; day logs are fetched just for the dates in play).
        $dayLogs = null;
        if ($livePlanned !== []) {
            $user->loadMissing(['cycles', 'exclusionPeriods']);
            $dates = array_values(array_unique(array_map(
                fn (TrainingSession $s) => $s->planned_date->toDateString(),
                $livePlanned,
            )));
            $dayLogs = $user->dayLogs()->whereIn('date', $dates)->get();
        }

        foreach ($sessions as $session) {
            // A freshly built session with no status yet counts as planned.
            if ($session->status !== null && $session->status !== SessionStatus::Planned) {
                $this->applyFrozen($session);
                continue;
            }

            $snapshot = $this->phaseProvider->forDate($user, $session->planned_date->toImmutable(), $dayLogs);
            $adaptation = $this->adapter->adapt($snapshot, $session->type);

            $session->setAttribute('cycle', $this->cyclePayload($snapshot));
            $session->adaptation = $adaptation?->toArray();
            $session->setAttribute('adapted', $adaptation !== null && $adaptation->intensityDelta < 0.0);
            $session->setAttribute('postponed', $adaptation !== null && $adaptation->intensityDelta <= -1.0);

            if ($adaptation !== null && $adaptation->intensityDelta < 0.0) {
                $applied = $this->adapter->applyTo(
                    $session->planned_load,
                    $session->target_pace_min_s,
                    $session->target_pace_max_s,
                    $adaptation,
                );
                $session->planned_load = $applied['load'];
                $session->target_pace_min_s = $applied['pace_min_s'];
                $session->target_pace_max_s = $applied['pace_max_s'];
                // Read-time overlay on real columns — re-baseline so an accidental
                // save() never persists it.
                $session->syncOriginalAttributes(['planned_load', 'target_pace_min_s', 'target_pace_max_s']);
            }

            // Same for `adaptation`: the live estimate must not leak into the column
            // that stays null until the session is acted on (§6/§7 "frozen history").
            $session->syncOriginalAttribute('adaptation');
        }
    }

    private function applyFrozen(TrainingSession $session): void
    {
        $frozen = $session->adaptation;
        $session->setAttribute('cycle', $frozen === null ? null : [
            'phase' => $frozen['phase'] ?? null,
            'confidence' => $frozen['confidence'] ?? null,
            'frozen' => true,
        ]);
        $session->setAttribute('adapted', false);
        $session->setAttribute('postponed', false);
    }

    /** @return array<string, mixed>|null */
    private function cyclePayload(PhaseSnapshot $snapshot): ?array
    {
        if ($snapshot->phase === null && ! $snapshot->painFlag) {
            return null;
        }

        return [
            'phase' => $snapshot->phase?->value,
            'confidence' => round($snapshot->confidence, 2),
            'pain_flag' => $snapshot->painFlag,
        ];
    }
}
