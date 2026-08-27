import type { ComponentProps } from "react";
import type { Ionicons } from "@expo/vector-icons";
import { COLOR } from "@/lib/colors";

type IconName = ComponentProps<typeof Ionicons>["name"];

/**
 * Predefined avatar set — no photo upload. Keep the ids in sync by hand with the
 * API's App\Enums\Avatar. `null` (unset) falls back to initials.
 */
export const AVATARS: { id: string; icon: IconName; color: string }[] = [
  { id: "leaf", icon: "leaf-outline", color: COLOR.accent },
  { id: "moon", icon: "moon-outline", color: COLOR.phase.luteal },
  { id: "sun", icon: "sunny-outline", color: COLOR.phase.follicular },
  { id: "star", icon: "star-outline", color: COLOR.phase.ovulation },
  { id: "flower", icon: "flower-outline", color: COLOR.phase.menstrual },
  { id: "heart", icon: "heart-outline", color: COLOR.badge },
  { id: "sparkles", icon: "sparkles-outline", color: COLOR.ink },
  { id: "flame", icon: "flame-outline", color: COLOR.badge },
];

export function findAvatar(id: string | null) {
  return AVATARS.find((avatar) => avatar.id === id) ?? null;
}
