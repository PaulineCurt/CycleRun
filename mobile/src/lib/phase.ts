import type { Phase } from "@/lib/api";

/** Canonical phase order — matches the calendar/legend everywhere in the app. */
export const PHASE_ORDER: Phase[] = ["menstrual", "follicular", "ovulation", "luteal"];

export const PHASE_LABEL: Record<Phase, string> = {
  menstrual: "Menstruelle",
  follicular: "Folliculaire",
  ovulation: "Ovulation",
  luteal: "Lutéale",
};

export const PHASE_RING_CLASS: Record<Phase, string> = {
  menstrual: "border-phase-menstrual",
  follicular: "border-phase-follicular",
  ovulation: "border-phase-ovulation",
  luteal: "border-phase-luteal",
};

export const PHASE_FILL_CLASS: Record<Phase, string> = {
  menstrual: "bg-phase-menstrual",
  follicular: "bg-phase-follicular",
  ovulation: "bg-phase-ovulation",
  luteal: "bg-phase-luteal",
};

/** Text colour that stays legible on top of PHASE_FILL_CLASS. */
export const PHASE_ON_FILL_TEXT_CLASS: Record<Phase, string> = {
  menstrual: "text-ink",
  follicular: "text-ink",
  ovulation: "text-ink",
  luteal: "text-surface",
};
