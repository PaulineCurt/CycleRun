import type { Phase } from "@/lib/api";

/**
 * One neutral, general-population sentence per phase — explicitly labeled "général"
 * wherever it's shown. Never phrased as "your body does X": it's indicative content,
 * not a personalized reading, and it has zero dependency on logged data.
 */
export const PHASE_TIP: Record<Phase, string> = {
  menstrual:
    "En général, l'énergie est souvent plus basse en tout début de cycle — pas de règle universelle, mais rien d'anormal à lever le pied.",
  follicular:
    "En général, l'énergie remonte progressivement sur cette phase pour la plupart des personnes.",
  ovulation:
    "En général, c'est souvent la phase où l'énergie perçue est la plus haute dans le cycle.",
  luteal:
    "En général, cette phase peut s'accompagner de plus de fatigue ou d'irritabilité chez certaines personnes.",
};
