import { Ionicons, MaterialCommunityIcons } from "@expo/vector-icons";
import type { CategoryIcon as CategoryIconValue } from "@/lib/symptoms";

/** Renders a symptom category's icon from whichever icon family it's tagged with. */
export function CategoryIcon({
  icon,
  size,
  color,
  style,
}: {
  icon: CategoryIconValue;
  size: number;
  color: string;
  style?: object;
}) {
  if (icon.lib === "material-community") {
    return <MaterialCommunityIcons name={icon.name} size={size} color={color} style={style} />;
  }
  return <Ionicons name={icon.name} size={size} color={color} style={style} />;
}
