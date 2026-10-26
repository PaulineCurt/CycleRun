import type { Level } from "@/lib/api";
import { LEVEL_LABEL } from "@/lib/training";

/**
 * Non-blocking coherence check for the plan wizard (§5 moteurB_three_levels.md):
 * if the declared level strongly contradicts the anchoring data (VMA + sustained
 * load), we *propose* an adjustment — we never force it, the runner validates.
 *
 * Thresholds are provisional (§7.1) and belong to the coach review [coach]:
 *   intermédiaire ≈ 11–14 km/h & 2–3 séances/sem
 *   confirmée     > 14 km/h & 3+ séances/sem
 * Only a clear contradiction returns a suggestion; anything borderline → null.
 */
export function checkLevelCoherence(
  level: Level,
  vmaKmh: number | null,
  daysPerWeek: number,
): { suggested: Level; message: string } | null {
  // Stage 1 débutantes never reach this check, and with no pace anchor there is
  // nothing to contradict.
  if (level === "debutante" || vmaKmh === null) return null;

  if (level === "intermediaire" && vmaKmh > 15 && daysPerWeek >= 3) {
    return {
      suggested: "confirmee",
      message:
        `Une VMA de ${vmaKmh.toLocaleString("fr-FR")} km/h et ${daysPerWeek} séances/semaine ` +
        `correspondent plutôt au cran ${LEVEL_LABEL.confirmee.toLowerCase()}.`,
    };
  }

  if (level === "confirmee" && (vmaKmh < 12 || daysPerWeek < 3)) {
    return {
      suggested: "intermediaire",
      message:
        `Avec ${vmaKmh.toLocaleString("fr-FR")} km/h de VMA et ${daysPerWeek} séances/semaine, ` +
        `le cran ${LEVEL_LABEL.intermediaire.toLowerCase()} sera sans doute plus tenable.`,
    };
  }

  return null;
}
