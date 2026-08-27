import { Text, View } from "react-native";
import { PHASE_TIP } from "@/lib/phaseTip";
import type { Phase } from "@/lib/api";

/**
 * General-population info, explicitly labeled — never a personalized reading.
 * Meant to sit inside the phase/status card, not as its own standalone card.
 */
export function PhaseTip({ phase }: { phase: Phase }) {
  return (
    <View className="w-full">
      <Text className="mt-1 text-sm text-ink opacity-80">{PHASE_TIP[phase]}</Text>
    </View>
  );
}
