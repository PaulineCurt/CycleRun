<?php

namespace App\Services\Training;

use App\Enums\SessionType;
use App\Services\Cycle\Phase;

/**
 * The §6 decision table, as a pure function of a PhaseSnapshot. Deliberately light
 * and indicative — the phase → performance link is young and highly variable, so
 * this never promises anything.
 *
 * A true fact (declared pain) always applies. A predicted phase applies only when
 * confidence clears the floor; below it we keep the base session, one notch more
 * prudent, and say so.
 */
final class CycleAdapter
{
    /** Above this, the full decision-table adaptation applies; below, a halved one. */
    private const CONFIDENCE_FLOOR = 0.5;

    public function adapt(PhaseSnapshot $snapshot, SessionType $sessionType): ?SessionAdaptation
    {
        // Nothing to adapt on a rest day — no phase nuance, and "à reporter" on a
        // day off makes no sense.
        if ($sessionType === SessionType::Rest) {
            return null;
        }

        if ($snapshot->phase === null && ! $snapshot->painFlag) {
            return null;
        }

        if ($snapshot->painFlag) {
            return new SessionAdaptation(
                $snapshot->phase?->value,
                $snapshot->confidence,
                'rest_or_postpone',
                'Douleur signalée — repos ou récup active, séance à reporter.',
                -1.0,
            );
        }

        if ($snapshot->phase === null) {
            return null;
        }

        [$rule, $headline, $delta] = match ($snapshot->phase) {
            Phase::Menstrual => ['lighten', 'Règles — séance allégée, repos possible si besoin.', -0.25],
            Phase::Follicular => ['full', 'Phase folliculaire — séance pleine, tu peux pousser.', 0.0],
            Phase::Ovulation => ['quality_window', 'Fenêtre de qualité — échauffement soigné, feu vert sur l’intensité.', 0.0],
            Phase::Luteal => ['ease_off', 'Phase lutéale — volume réduit, allure en aisance.', -0.15],
        };

        // Below the floor we still adapt — just half as strong, and labelled as an
        // estimate. Never a silent no-op (§6: "un cran plus prudent", not nothing).
        if ($snapshot->confidence < self::CONFIDENCE_FLOOR) {
            $delta /= 2;
            $headline = rtrim($headline, '.') . ' — estimation, cycle encore peu régulier.';
        }

        return new SessionAdaptation($snapshot->phase->value, $snapshot->confidence, $rule, $headline, $delta);
    }

    /**
     * Apply an adaptation to a base session (§6 "séance allégée / volume réduit").
     * Pure. The base session is never mutated by the caller — this returns the
     * adapted view; `base_generated` stays the fallback.
     *
     * @param array<string, mixed> $load  base planned_load
     * @return array{load: array<string, mixed>, pace_min_s: ?int, pace_max_s: ?int, postponed: bool}
     */
    public function applyTo(array $load, ?int $paceMinS, ?int $paceMaxS, SessionAdaptation $adaptation): array
    {
        // Declared pain: don't rewrite the session, just flag it for postponement.
        if ($adaptation->intensityDelta <= -1.0) {
            return ['load' => $load, 'pace_min_s' => $paceMinS, 'pace_max_s' => $paceMaxS, 'postponed' => true];
        }

        if ($adaptation->intensityDelta >= 0.0) {
            return ['load' => $load, 'pace_min_s' => $paceMinS, 'pace_max_s' => $paceMaxS, 'postponed' => false];
        }

        $scale = 1.0 + $adaptation->intensityDelta; // e.g. -0.25 -> 0.75
        $adapted = $load;

        switch ($load['mode'] ?? null) {
            case 'duration':
            case 'cross':
                if (isset($adapted['duration_s'])) {
                    $adapted['duration_s'] = (int) round($adapted['duration_s'] * $scale);
                }
                if (isset($adapted['work_s'])) {
                    $adapted['work_s'] = (int) round($adapted['work_s'] * $scale);
                }
                break;
            case 'intervals':
            case 'run_walk':
                $adapted['reps'] = max(2, (int) round((int) ($adapted['reps'] ?? 0) * $scale));
                break;
        }

        // "Allure en aisance" (luteal): let the slow end of the band breathe a little.
        $paceMax = $paceMaxS;
        if ($adaptation->rule === 'ease_off' && $paceMaxS !== null) {
            $paceMax = (int) round($paceMaxS * 1.03);
        }

        return ['load' => $adapted, 'pace_min_s' => $paceMinS, 'pace_max_s' => $paceMax, 'postponed' => false];
    }
}
