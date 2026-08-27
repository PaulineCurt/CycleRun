import type { Phase } from "@/lib/api";

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
