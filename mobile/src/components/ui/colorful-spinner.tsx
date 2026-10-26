import { useEffect, useState } from "react";
import { Animated, Easing, View } from "react-native";
import { COLOR } from "@/lib/colors";

const SIZE = 40;
const BAR_COUNT = 8;
const BAR_WIDTH = 3.5;
const BAR_HEIGHT = 11;

const BAR_COLORS = [
  COLOR.phase.menstrual,
  COLOR.phase.follicular,
  COLOR.phase.ovulation,
  COLOR.phase.luteal,
  COLOR.accent,
  COLOR.badge,
  COLOR.phase.menstrual,
  COLOR.phase.follicular,
];

/**
 * Classic tick-mark spinner (distinct from the full-page BrandLoader's solid ring
 * on purpose — this one shows up inside a small save confirmation, and reusing the
 * exact page-loading motif there read as "the whole page is reloading").
 */
export function ColorfulSpinner() {
  const [rotation] = useState(() => new Animated.Value(0));

  useEffect(() => {
    const loop = Animated.loop(
      Animated.timing(rotation, {
        toValue: 1,
        duration: 900,
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
      {Array.from({ length: BAR_COUNT }).map((_, index) => {
        const angle = (360 / BAR_COUNT) * index;
        return (
          <View
            key={index}
            style={{
              position: "absolute",
              left: SIZE / 2 - BAR_WIDTH / 2,
              top: SIZE / 2 - BAR_HEIGHT / 2,
              width: BAR_WIDTH,
              height: BAR_HEIGHT,
              borderRadius: BAR_WIDTH / 2,
              backgroundColor: BAR_COLORS[index % BAR_COLORS.length],
              opacity: 0.4 + (0.6 * index) / BAR_COUNT,
              transform: [{ rotate: `${angle}deg` }, { translateY: -SIZE / 2 + BAR_HEIGHT / 2 }],
            }}
          />
        );
      })}
    </Animated.View>
  );
}
