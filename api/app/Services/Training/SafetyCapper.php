<?php

namespace App\Services\Training;

use App\Enums\SessionType;

/**
 * Step 7 of the pipeline (§5 / §8 moteurB.md): applySafetyCaps(sessions,
 * health_flags) — hard ceilings from the initial health questionnaire plus
 * always-on overload warnings.
 *
 * Recognised health_flags keys (provisional vocabulary — TODO(expert)):
 *   max_session_min  int   hard cap on any single session's moving time
 *   no_high_intensity bool VMA / threshold work downgraded to tempo
 *   low_impact        bool the weekly hard session becomes cross-training
 */
final class SafetyCapper
{
    private const OVERLOAD_PROGRESSION = 1.10; // §4.5 / §8: >+10 %/week is flagged.

    /**
     * @param list<SessionDraft> $sessions
     * @param array<string, mixed> $healthFlags
     * @param list<WeekBlock> $blocks
     * @param array<string, array{0: int, 1: int}> $paces  so a downgraded session gets the right pace band
     * @return array{sessions: list<SessionDraft>, warnings: list<string>}
     */
    public function apply(array $sessions, array $healthFlags, array $blocks, array $paces = []): array
    {
        $warnings = [];

        if (! empty($healthFlags['no_high_intensity'])) {
            $sessions = array_map(fn (SessionDraft $s) => $this->downgradeIntensity($s, $paces), $sessions);
            $warnings[] = 'Intensité haute désactivée (drapeau santé) : le travail VMA/seuil est ramené en tempo.';
        }

        if (! empty($healthFlags['low_impact'])) {
            $sessions = array_map(fn (SessionDraft $s) => $this->toCross($s), $sessions);
            $warnings[] = 'Contrainte "faible impact" : la séance qualité hebdomadaire passe en cross-training.';
        }

        if (isset($healthFlags['max_session_min'])) {
            $capS = (int) $healthFlags['max_session_min'] * 60;
            if ($capS > 0) {
                $sessions = array_map(fn (SessionDraft $s) => $this->capDuration($s, $capS), $sessions);
                $warnings[] = "Séances plafonnées à {$healthFlags['max_session_min']} min (drapeau santé).";
            }
        }

        $warnings = array_merge($warnings, $this->overloadWarnings($blocks));

        return ['sessions' => array_values($sessions), 'warnings' => $warnings];
    }

    /**
     * @param array<string, array{0: int, 1: int}> $paces
     */
    private function downgradeIntensity(SessionDraft $session, array $paces): SessionDraft
    {
        if (! in_array($session->type, [SessionType::Interval, SessionType::Threshold], true)) {
            return $session;
        }

        $workS = $session->hardSeconds();
        // Drop the pace band to tempo too — keeping the VMA/threshold band would
        // still prescribe near-max effort to someone medically flagged off it.
        [$paceMin, $paceMax] = $paces[PaceZone::Tempo->value] ?? [null, null];

        return $session
            ->withType(SessionType::Tempo, $paceMin, $paceMax)
            ->withPlannedLoad(['mode' => 'duration', 'duration_s' => $workS + 1200, 'work_s' => $workS]);
    }

    private function toCross(SessionDraft $session): SessionDraft
    {
        if (! in_array($session->type, [SessionType::Interval, SessionType::Threshold, SessionType::Tempo], true)) {
            return $session;
        }

        return $session
            ->withType(SessionType::Cross, null, null)
            ->withPlannedLoad(['mode' => 'cross', 'duration_s' => max(1800, $session->totalSeconds())]);
    }

    private function capDuration(SessionDraft $session, int $capS): SessionDraft
    {
        if ($session->totalSeconds() <= $capS) {
            return $session;
        }

        $load = $session->plannedLoad;

        return match ($load['mode'] ?? null) {
            'duration', 'cross' => $session->withPlannedLoad([...$load, 'duration_s' => $capS]),
            'intervals' => $session->withPlannedLoad([
                ...$load,
                'reps' => max(2, (int) floor($capS / max(1, (int) $load['work_s'] + (int) $load['recovery_s']))),
            ]),
            'run_walk' => $session->withPlannedLoad([
                ...$load,
                'reps' => max(2, (int) floor($capS / max(1, (int) $load['run_s'] + (int) $load['walk_s']))),
            ]),
            default => $session,
        };
    }

    /**
     * @param list<WeekBlock> $blocks
     * @return list<string>
     */
    private function overloadWarnings(array $blocks): array
    {
        $warnings = [];
        $previous = null;

        foreach ($blocks as $block) {
            // A deload / taper deliberately drops volume; the week that follows
            // resumes the progression curve rather than stepping +10 % off the
            // trough, so it breaks the comparison chain instead of feeding it.
            if ($block->isDeload || $block->mesocycle === Mesocycle::Taper) {
                $previous = null;
                continue;
            }

            if ($previous !== null && $previous > 0
                && $block->totalVolumeMin > $previous * self::OVERLOAD_PROGRESSION + 0.5) {
                $pct = (int) round(($block->totalVolumeMin / $previous - 1) * 100);
                $warnings[] = "Semaine {$block->weekIndex} : progression de volume de +{$pct}% (au-delà du plafond +10%).";
            }

            $previous = $block->totalVolumeMin;
        }

        return $warnings;
    }
}
