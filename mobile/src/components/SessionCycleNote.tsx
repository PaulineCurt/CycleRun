import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import type { TrainingSession } from "@/lib/api";
import { adaptationTone, describeLoad, phaseChip } from "@/lib/training";

/**
 * The cycle overlay strip shown under a session: where the cycle is that day,
 * whether the plan lightened the session, and the one-line advice (§6). Renders
 * nothing when there is no cycle signal for the date.
 */
export function SessionCycleNote({
  session,
  showBase = false,
}: {
  session: TrainingSession;
  showBase?: boolean;
}) {
  const { cycle, adaptation, adapted, postponed, base } = session;
  if (!cycle && !adaptation) return null;

  const chip = cycle?.phase ? phaseChip(cycle.phase) : null;
  const tone = adaptation ? adaptationTone(adaptation) : null;

  return (
    <View className="mt-2 gap-1 border-t border-ink/10 pt-2">
      <View className="flex-row flex-wrap items-center gap-x-2 gap-y-1">
        {chip && (
          <View className="flex-row items-center gap-1.5">
            <View
              style={{ width: 8, height: 8, borderRadius: 999, backgroundColor: chip.color }}
            />
            <Text className="text-xs font-medium text-ink opacity-70">{chip.label}</Text>
          </View>
        )}
        {postponed ? (
          <Text className="text-xs font-semibold text-danger">· À reporter</Text>
        ) : adapted ? (
          <Text className="text-xs text-ink opacity-60">· séance allégée</Text>
        ) : null}
        {cycle?.frozen && <Text className="text-xs text-ink opacity-35">· figé</Text>}
      </View>

      {adaptation && (
        <View className="flex-row items-start gap-2">
          {tone && (
            <Ionicons
              name={tone.icon as never}
              size={13}
              color={tone.color}
              style={{ marginTop: 1 }}
            />
          )}
          <Text className="flex-1 text-xs text-ink opacity-70">{adaptation.headline}</Text>
        </View>
      )}

      {showBase && adapted && base && (
        <Text className="text-xs text-ink opacity-40">
          Séance de base : {describeLoad(base.planned_load)}
        </Text>
      )}
    </View>
  );
}
