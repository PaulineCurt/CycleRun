import type { ComponentProps } from "react";
import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { IconBadge } from "@/components/ui/icon-badge";

type IconName = ComponentProps<typeof Ionicons>["name"];

/**
 * The one header style for every card on a screen: icon badge + small uppercase
 * label. Used identically everywhere ("Prochaines règles", "Fenêtre d'ovulation",
 * "Général", "Comment tu te sens aujourd'hui ?") so weight/size/color never drift
 * from one card to the next.
 */
export function CardHeader({ icon, color, label }: { icon: IconName; color: string; label: string }) {
  return (
    <View className="mb-1 flex-row items-center">
      <IconBadge icon={icon} color={color} size={14} />
      <Text className="ml-2 text-xs uppercase tracking-wide text-ink opacity-50">{label}</Text>
    </View>
  );
}
