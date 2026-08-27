/**
 * Symptom catalogue shown in the daily feeling sheet, grouped by body area. Keys are
 * stored as-is in `DayLog.symptoms` (severity 0-3) — see api.ts's `SymptomMap`.
 * Menstrual flow itself is deliberately NOT here: it's logged from the calendar
 * (period start/end), not from a same-day-only "how do you feel" sheet.
 */
import type { ComponentProps } from "react";
import type { Ionicons, MaterialCommunityIcons } from "@expo/vector-icons";

/** Tags which icon family a category's icon comes from — see `CategoryIcon`. */
export type CategoryIcon =
  | { lib: "ionicons"; name: ComponentProps<typeof Ionicons>["name"] }
  | { lib: "material-community"; name: ComponentProps<typeof MaterialCommunityIcons>["name"] };

export const SYMPTOM_CATEGORIES: {
  label: string;
  icon: CategoryIcon;
  symptoms: { key: string; label: string }[];
}[] = [
  {
    label: "Tête",
    icon: { lib: "ionicons", name: "pulse-outline" },
    symptoms: [
      { key: "headache", label: "Céphalées" },
      { key: "migraine", label: "Migraines" },
      { key: "dizziness", label: "Vertiges" },
    ],
  },
  {
    label: "Peau & général",
    icon: { lib: "ionicons", name: "sparkles-outline" },
    symptoms: [
      { key: "acne", label: "Acné" },
      { key: "fever", label: "Fièvre" },
    ],
  },
  {
    label: "Corps",
    icon: { lib: "ionicons", name: "body-outline" },
    symptoms: [
      { key: "joint_pain", label: "Douleurs articulaires" },
      { key: "muscle_soreness", label: "Courbatures" },
      { key: "breast_tenderness", label: "Poitrine sensible" },
      { key: "back_pain", label: "Mal de dos" },
    ],
  },
  {
    label: "Fluide",
    icon: { lib: "ionicons", name: "water-outline" },
    symptoms: [
      { key: "water_retention", label: "Rétention d'eau" },
      { key: "dry_mouth", label: "Bouche sèche" },
      { key: "increased_thirst", label: "Soif accrue" },
    ],
  },
  {
    label: "Abdomen",
    icon: { lib: "material-community", name: "stomach" },
    symptoms: [
      { key: "cramps", label: "Crampes" },
      { key: "bloating", label: "Ballonnements" },
      { key: "nausea", label: "Nausées" },
      { key: "constipation", label: "Constipation" },
    ],
  },
  {
    label: "Mental",
    icon: { lib: "ionicons", name: "cloud-outline" },
    symptoms: [
      { key: "irritability", label: "Irritabilité" },
      { key: "anxiety", label: "Anxiété" },
      { key: "low_mood", label: "Tristesse" },
      { key: "poor_concentration", label: "Difficulté de concentration" },
    ],
  },
];

export const INTENSITY_LABEL: Record<number, string> = {
  0: "Aucune",
  1: "Légère",
  2: "Modérée",
  3: "Forte",
};
