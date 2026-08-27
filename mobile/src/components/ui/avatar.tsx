import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { COLOR } from "@/lib/colors";
import { findAvatar } from "@/lib/avatars";

function initials(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  return (parts[0][0] + (parts[1]?.[0] ?? "")).toUpperCase();
}

/** Shows the user's chosen predefined avatar, or falls back to initials if unset. */
export function Avatar({ name, avatarId, size = 64 }: { name: string; avatarId?: string | null; size?: number }) {
  const avatar = avatarId ? findAvatar(avatarId) : null;

  return (
    <View
      style={{
        width: size,
        height: size,
        borderRadius: size / 2,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: `${avatar?.color ?? COLOR.accent}1F`,
      }}
    >
      {avatar ? (
        <Ionicons name={avatar.icon} size={size * 0.5} color={avatar.color} />
      ) : (
        <Text style={{ color: COLOR.accent, fontSize: size * 0.36 }} className="font-bold">
          {initials(name)}
        </Text>
      )}
    </View>
  );
}
