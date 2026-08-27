import type { ComponentProps } from "react";
import { View } from "react-native";
import { Ionicons } from "@expo/vector-icons";

type IconName = ComponentProps<typeof Ionicons>["name"];

/**
 * A small circular badge: the icon tinted to `color`, sitting in a soft ~12%-opacity
 * fill of that same color. This is the app's one recurring "spot of color" accent —
 * used next to section titles instead of flat gray icons.
 */
export function IconBadge({ icon, color, size = 18 }: { icon: IconName; color: string; size?: number }) {
  return (
    <View
      style={{
        width: size + 14,
        height: size + 14,
        borderRadius: 999,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: `${color}1F`, // color + ~12% alpha (hex suffix)
      }}
    >
      <Ionicons name={icon} size={size} color={color} />
    </View>
  );
}
