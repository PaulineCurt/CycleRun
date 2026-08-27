import { View } from "react-native";
import type { Phase } from "@/lib/api";
import { PHASE_ORDER } from "@/lib/phase";
import { COLOR } from "@/lib/colors";
import { PhaseArcRing } from "@/components/ui/phase-arc-ring";

const SIZE = 160;
const STROKE = 14;
const GAP = 6;
const TRACK_COLOR = "#12302B1A"; // ink at low opacity, for the un-highlighted arcs

/**
 * The cycle ring: 4 equal arcs, one per phase, matching the calendar's legend order.
 * The current phase's arc is highlighted; the rest sit dim as a neutral track. This is
 * the one place in the app that spends visual boldness — everything else stays calm.
 */
export function CycleRing({
  activePhase,
  children,
}: {
  activePhase: Phase | null;
  children?: React.ReactNode;
}) {
  const colors = PHASE_ORDER.map((phase) => (phase === activePhase ? COLOR.phase[phase] : TRACK_COLOR));

  return (
    <View className="items-center justify-center" style={{ width: SIZE, height: SIZE }}>
      <View style={{ position: "absolute" }}>
        <PhaseArcRing size={SIZE} stroke={STROKE} gap={GAP} colors={colors} />
      </View>
      {children}
    </View>
  );
}
