import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import type { DayLog } from "@/lib/api";
import { INTENSITY_LABEL, SYMPTOM_CATEGORIES } from "@/lib/symptoms";
import { CategoryIcon } from "@/components/ui/category-icon";

/**
 * Read-only recap of what was logged for a given day — shown under the calendar.
 * Purely presentational: the parent screen owns the fetch (and its loading state)
 * so this panel appears in the same wave as the calendar/cycle-day info, instead
 * of popping in a beat later from its own independent request.
 */
export function DayLogSummary({ log }: { log: DayLog | null }) {
  const symptoms = log?.symptoms ?? {};
  const hasLoggedSymptom = Object.values(symptoms).some((value) => value > 0);
  const hasAnything = log && (log.energy != null || log.pain != null || hasLoggedSymptom);

  if (!hasAnything) {
    return <Text className="mt-3 text-sm text-ink opacity-50">Aucun ressenti noté pour ce jour.</Text>;
  }

  // Walk the same categorized catalogue as the feeling sheet, in its exact order,
  // instead of Object.entries()'s insertion order (whichever symptom was tapped
  // first) — so this read-only recap always lists things the same way the sheet does.
  const categoriesWithData = SYMPTOM_CATEGORIES.map((category) => ({
    ...category,
    logged: category.symptoms.filter((symptom) => (symptoms[symptom.key] ?? 0) > 0),
  })).filter((category) => category.logged.length > 0);

  return (
    <View className="mt-3 gap-3">
      {(log!.energy != null || log!.pain != null) && (
        <View className="gap-2">
          {log!.energy != null && (
            <View className="flex-row items-center">
              <Ionicons name="flash-outline" size={14} color="#12302B" style={{ opacity: 0.6, marginRight: 6 }} />
              <Text className="text-sm text-ink opacity-80">Énergie : {log!.energy}/5</Text>
            </View>
          )}
          {log!.pain != null && (
            <View className="flex-row items-center">
              <Ionicons name="bandage-outline" size={14} color="#12302B" style={{ opacity: 0.6, marginRight: 6 }} />
              <Text className="text-sm text-ink opacity-80">Douleur générale : {log!.pain}/3</Text>
            </View>
          )}
        </View>
      )}

      {categoriesWithData.map((category) => (
        <View key={category.label}>
          <View className="mb-1 flex-row items-center">
            <CategoryIcon icon={category.icon} size={14} color="#12302B" style={{ opacity: 0.6, marginRight: 6 }} />
            <Text className="text-xs font-semibold uppercase tracking-wide text-ink opacity-50">
              {category.label}
            </Text>
          </View>
          {category.logged.map((symptom) => (
            <Text key={symptom.key} className="text-sm text-ink opacity-80">
              {symptom.label} · {INTENSITY_LABEL[symptoms[symptom.key]]}
            </Text>
          ))}
        </View>
      ))}
    </View>
  );
}
