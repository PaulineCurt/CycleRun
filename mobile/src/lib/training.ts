import type {
  Level,
  Phase,
  PlannedLoad,
  ProgramSummary,
  SessionAdaptation,
  SessionType,
  TrainingSession,
} from "@/lib/api";
import { COLOR } from "@/lib/colors";
import { PHASE_LABEL } from "@/lib/phase";

/**
 * The one program a user is "on": their active plan, else the latest draft, else
 * whatever is most recent. Shared by the home card, the training tab and the
 * calendar so they never disagree on which plan they're showing.
 */
export function pickActiveProgram(programs: ProgramSummary[]): ProgramSummary | null {
  if (programs.length === 0) return null;
  return (
    programs.find((p) => p.status === "active") ??
    programs.find((p) => p.status === "draft") ??
    programs[0]
  );
}

/** Today's session if there is one, otherwise the next still-planned session. */
export function nextPlannedSession(
  sessions: TrainingSession[],
  todayKey: string,
): TrainingSession | null {
  return (
    sessions
      .filter((s) => s.status === "planned" && s.planned_date >= todayKey)
      .sort((a, b) => a.planned_date.localeCompare(b.planned_date))[0] ?? null
  );
}

/** Cycle phase for a session date — colour + short label, matching the calendar. */
export function phaseChip(phase: Phase): { color: string; label: string } {
  return { color: COLOR.phase[phase], label: PHASE_LABEL[phase] };
}

export const LEVEL_LABEL: Record<Level, string> = {
  debutante: "Débutante",
  intermediaire: "Intermédiaire",
  confirmee: "Confirmée",
};

export const SESSION_LABEL: Record<SessionType, string> = {
  run_walk: "Course / marche",
  easy: "Footing facile",
  tempo: "Tempo",
  threshold: "Seuil",
  interval: "Fractionné VMA",
  long: "Sortie longue",
  rest: "Repos",
  cross: "Cross-training",
};

/** Ionicons name per session type — keeps the plan list scannable. */
export const SESSION_ICON: Record<SessionType, string> = {
  run_walk: "walk-outline",
  easy: "leaf-outline",
  tempo: "speedometer-outline",
  threshold: "trending-up-outline",
  interval: "flash-outline",
  long: "map-outline",
  rest: "bed-outline",
  cross: "bicycle-outline",
};

const HARD_TYPES: SessionType[] = ["tempo", "threshold", "interval"];

export function sessionAccent(type: SessionType): string {
  if (HARD_TYPES.includes(type)) return COLOR.badge;
  if (type === "rest") return COLOR.ink;
  if (type === "long") return COLOR.phase.luteal;
  return COLOR.accent;
}

/** 372 -> "6:12". */
export function formatPace(secondsPerKm: number): string {
  const m = Math.floor(secondsPerKm / 60);
  const s = Math.round(secondsPerKm % 60);
  return `${m}:${String(s).padStart(2, "0")}`;
}

export function formatPaceRange(min: number | null, max: number | null): string | null {
  if (min == null || max == null) return null;
  return `${formatPace(min)}–${formatPace(max)} /km`;
}

/** 1500 -> "25 min", 5400 -> "1 h 30". */
export function formatDuration(seconds: number): string {
  const totalMin = Math.round(seconds / 60);
  if (totalMin < 60) return `${totalMin} min`;
  const h = Math.floor(totalMin / 60);
  const m = totalMin % 60;
  return m === 0 ? `${h} h` : `${h} h ${String(m).padStart(2, "0")}`;
}

/** 1500 -> "25:00" (for goal / projected times). */
export function formatClock(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = Math.round(seconds % 60);
  return `${m}:${String(s).padStart(2, "0")}`;
}

export function describeLoad(load: PlannedLoad): string {
  switch (load.mode) {
    case "duration":
      return formatDuration(load.duration_s ?? 0);
    case "intervals":
      return `${load.reps ?? 0} × ${formatDuration(load.work_s ?? 0)} (récup ${formatDuration(
        load.recovery_s ?? 0,
      )})`;
    case "run_walk":
      return `${load.reps ?? 0} × (cours ${formatDuration(load.run_s ?? 0)} / marche ${formatDuration(
        load.walk_s ?? 0,
      )})`;
    case "cross":
      return `${formatDuration(load.duration_s ?? 0)} sans impact`;
    default:
      return "Repos";
  }
}

export function adaptationTone(adaptation: SessionAdaptation): { color: string; icon: string } {
  switch (adaptation.rule) {
    case "rest_or_postpone":
      return { color: COLOR.danger, icon: "alert-circle-outline" };
    case "lighten":
    case "ease_off":
      return { color: COLOR.phase.luteal, icon: "arrow-down-circle-outline" };
    case "quality_window":
    case "full":
      return { color: COLOR.accent, icon: "checkmark-circle-outline" };
    default:
      return { color: COLOR.ink, icon: "information-circle-outline" };
  }
}
