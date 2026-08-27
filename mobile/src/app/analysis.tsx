import { useCallback, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import { useFocusEffect, useRouter } from "expo-router";
import { getCycleAnalysis, type CycleAnalysis } from "@/lib/api";

const HISTORY_PAGE_SIZE = 5;

const REGULARITY_MESSAGE: Record<CycleAnalysis["cycle"]["regularity"], string> = {
  regular: "Tes cycles sont réguliers.",
  irregular: "Tes cycles varient beaucoup — les prédictions restent indicatives.",
  unknown: "Pas encore assez de cycles logués pour juger de la régularité.",
};

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "long" });
}

function formatDays(value: number | null) {
  if (value == null) return "—";
  return `${value} jour${value > 1 ? "s" : ""}`;
}

export default function Analysis() {
  const router = useRouter();
  const [analysis, setAnalysis] = useState<CycleAnalysis | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [historyExpanded, setHistoryExpanded] = useState(false);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      setError(false);
      getCycleAnalysis()
        .then(setAnalysis)
        .catch(() => setError(true))
        .finally(() => setLoading(false));
    }, []),
  );

  const visibleHistory = analysis
    ? historyExpanded
      ? analysis.history
      : analysis.history.slice(0, HISTORY_PAGE_SIZE)
    : [];

  return (
    <ScrollView className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Pressable onPress={() => router.back()} className="mb-4 h-11 justify-center">
        <Text className="text-ink opacity-70">‹ Retour</Text>
      </Pressable>

      <Text className="mb-8 text-3xl font-bold text-ink">Analyse du cycle</Text>

      {loading && <ActivityIndicator color="#12302B" className="my-8" />}

      {!loading && error && (
        <Text className="text-danger">Impossible de charger ton analyse.</Text>
      )}

      {!loading && !error && analysis && (
        <View className="gap-4">
          <View className="rounded-2xl bg-surface p-4">
            <Text className="mb-3 text-sm font-semibold text-ink">Durée des règles</Text>
            <View className="flex-row justify-between">
              <View>
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Dernières règles
                </Text>
                <Text className="mt-1 text-xl font-bold text-ink">
                  {formatDays(analysis.menses.last_duration)}
                </Text>
              </View>
              <View>
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Variation
                </Text>
                <Text className="mt-1 text-xl font-bold text-ink">
                  {formatDays(analysis.menses.variation_days)}
                </Text>
              </View>
            </View>
          </View>

          <View className="rounded-2xl bg-surface p-4">
            <Text className="mb-3 text-sm font-semibold text-ink">Durée du cycle</Text>
            <View className="flex-row justify-between">
              <View>
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Dernier cycle
                </Text>
                <Text className="mt-1 text-xl font-bold text-ink">
                  {formatDays(analysis.cycle.last_duration)}
                </Text>
              </View>
              <View>
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Variation
                </Text>
                <Text className="mt-1 text-xl font-bold text-ink">
                  {formatDays(analysis.cycle.variation_days)}
                </Text>
              </View>
            </View>
            <Text className="mt-3 text-sm text-ink opacity-70">
              {REGULARITY_MESSAGE[analysis.cycle.regularity]}
            </Text>
          </View>

          <View className="rounded-2xl bg-surface p-4">
            <Text className="mb-3 text-sm font-semibold text-ink">Prévisions</Text>
            {analysis.forecast.length === 0 ? (
              <Text className="text-sm text-ink opacity-60">
                Pas assez de données pour anticiper les prochaines règles.
              </Text>
            ) : (
              <View className="gap-2">
                {analysis.forecast.map((entry, index) => (
                  <View key={entry.start} className="flex-row items-center justify-between">
                    <Text className="text-sm text-ink opacity-60">
                      {index === 0 ? "Prochaines" : `Dans ${index + 1} cycles`}
                    </Text>
                    <Text className="text-base text-ink">
                      Du {formatDate(entry.start)} au {formatDate(entry.end)}
                    </Text>
                  </View>
                ))}
              </View>
            )}
          </View>

          <View className="rounded-2xl bg-surface p-4">
            <Text className="mb-3 text-sm font-semibold text-ink">Historique des règles</Text>
            {analysis.history.length === 0 ? (
              <Text className="text-sm text-ink opacity-60">
                Aucune règle loguée pour l'instant.
              </Text>
            ) : (
              <View className="gap-3">
                {visibleHistory.map((entry, index) => (
                  <View
                    key={entry.started_on}
                    className={`flex-row items-center justify-between ${
                      index < visibleHistory.length - 1 ? "border-b border-ink/6 pb-3" : ""
                    }`}
                  >
                    <Text className="text-sm text-ink">Début le {formatDate(entry.started_on)}</Text>
                    <Text className="text-sm text-ink opacity-60">
                      {entry.menses_duration != null
                        ? `Règles : ${formatDays(entry.menses_duration)}`
                        : "Fin non loguée"}
                    </Text>
                  </View>
                ))}
              </View>
            )}

            {analysis.history.length > HISTORY_PAGE_SIZE && (
              <Pressable
                onPress={() => setHistoryExpanded((current) => !current)}
                className="mt-3 h-11 items-center justify-center"
                accessibilityRole="button"
              >
                <Text className="text-sm font-semibold text-ink opacity-70">
                  {historyExpanded ? "Voir moins" : "Voir plus"}
                </Text>
              </Pressable>
            )}
          </View>
        </View>
      )}
    </ScrollView>
  );
}
