import { useEffect, useState } from "react";
import { Animated, Easing, View } from "react-native";
import { PHASE_ORDER } from "@/lib/phase";
import { COLOR } from "@/lib/colors";
import { PhaseArcRing } from "@/components/ui/phase-arc-ring";

const SIZE = 56;
const STROKE = 6;
const GAP = 4;

const PHASE_COLORS = PHASE_ORDER.map((phase) => COLOR.phase[phase]);

/** On-brand loading spinner: the cycle ring, rotating — used instead of a generic spinner. */
export function BrandLoader() {
  const [rotation] = useState(() => new Animated.Value(0));

  useEffect(() => {
    const loop = Animated.loop(
      Animated.timing(rotation, {
        toValue: 1,
        duration: 1100,
        easing: Easing.linear,
        useNativeDriver: true,
      }),
    );
    loop.start();
    return () => loop.stop();
  }, [rotation]);

  const spin = rotation.interpolate({ inputRange: [0, 1], outputRange: ["0deg", "360deg"] });

  return (
    <Animated.View style={{ width: SIZE, height: SIZE, transform: [{ rotate: spin }] }}>
      <PhaseArcRing size={SIZE} stroke={STROKE} gap={GAP} colors={PHASE_COLORS} />
    </Animated.View>
  );
}

/** Centers the loader in whatever space it's given — drop it in place of page content. */
export function PageLoader() {
  return (
    <View className="flex-1 items-center justify-center py-24">
      <BrandLoader />
    </View>
  );
}
