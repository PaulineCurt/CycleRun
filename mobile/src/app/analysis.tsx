import { useCallback, useState } from "react";
import { Pressable, ScrollView, Text, View } from "react-native";
import { useFocusEffect, useRouter } from "expo-router";
import { getCycleAnalysis, type CycleAnalysis } from "@/lib/api";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { PageLoader } from "@/components/ui/brand-loader";
import { COLOR } from "@/lib/colors";

const HISTORY_PAGE_SIZE = 5;

const REGULARITY_MESSAGE: Record<CycleAnalysis["cycle"]["regularity"], string> = {
  regular: "Tes cycles sont réguliers.",
  irregular: "Tes cycles varient beaucoup — les prédictions restent indicatives.",
  unknown: "Pas encore assez de cycles enregistrés pour juger de la régularité.",
};

const REGULARITY_BADGE: Record<CycleAnalysis["cycle"]["regularity"], { label: string; color: string }> = {
  regular: { label: "Régulier", color: COLOR.accent },
  irregular: { label: "Irrégulier", color: COLOR.badge },
  unknown: { label: "Inconnu", color: COLOR.ink },
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

  const regularity = analysis ? REGULARITY_BADGE[analysis.cycle.regularity] : null;

  return (
    <ScrollView className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Pressable onPress={() => router.back()} className="mb-4 h-11 justify-center">
        <Text className="text-ink opacity-70">‹ Retour</Text>
      </Pressable>

      {loading ? (
        <PageLoader />
      ) : (
        <>
          <Text className="mb-8 text-3xl font-bold text-ink">Analyse du cycle</Text>

          {error && (
            <Text className="text-danger">Impossible de charger ton analyse.</Text>
          )}
        </>
      )}

      {!loading && !error && analysis && regularity && (
        <View className="gap-4">
          <Card>
            <CardHeader icon="water-outline" color={COLOR.phase.menstrual} label="Durée des règles" />
            <View className="mt-2 flex-row justify-between">
              <View>
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Dernières règles
                </Text>
                <Text className="mt-1 text-2xl font-bold" style={{ color: COLOR.phase.menstrual }}>
                  {formatDays(analysis.menses.last_duration)}
                </Text>
              </View>
              <View className="items-end">
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Variation
                </Text>
                <Text className="mt-1 text-2xl font-bold text-ink opacity-70">
                  {formatDays(analysis.menses.variation_days)}
                </Text>
              </View>
            </View>
          </Card>

          <Card>
            <View className="flex-row items-start justify-between">
              <CardHeader icon="refresh-outline" color={COLOR.accent} label="Durée du cycle" />
              <View className="rounded-full px-2.5 py-1" style={{ backgroundColor: `${regularity.color}1F` }}>
                <Text className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: regularity.color }}>
                  {regularity.label}
                </Text>
              </View>
            </View>
            <View className="mt-2 flex-row justify-between">
              <View>
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Dernier cycle
                </Text>
                <Text className="mt-1 text-2xl font-bold" style={{ color: COLOR.accent }}>
                  {formatDays(analysis.cycle.last_duration)}
                </Text>
              </View>
              <View className="items-end">
                <Text className="text-xs uppercase tracking-wide text-ink opacity-50">
                  Variation
                </Text>
                <Text className="mt-1 text-2xl font-bold text-ink opacity-70">
                  {formatDays(analysis.cycle.variation_days)}
                </Text>
              </View>
            </View>
            <Text className="mt-3 text-sm text-ink opacity-70">
              {REGULARITY_MESSAGE[analysis.cycle.regularity]}
            </Text>
          </Card>

          <Card>
            <CardHeader icon="telescope-outline" color={COLOR.phase.ovulation} label="Prévisions" />
            {analysis.forecast.length === 0 ? (
              <Text className="mt-2 text-sm text-ink opacity-60">
                Pas assez de données pour anticiper les prochaines règles.
              </Text>
            ) : (
              <View className="mt-3 gap-2">
                {analysis.forecast.map((entry, index) => (
                  <View
                    key={entry.start}
                    className="flex-row items-center justify-between rounded-xl p-3"
                    style={{ backgroundColor: `${COLOR.phase.ovulation}14` }}
                  >
                    <View className="flex-row items-center">
                      <View
                        className="mr-2 h-2 w-2 rounded-full"
                        style={{ backgroundColor: COLOR.phase.ovulation }}
                      />
                      <Text className="text-xs font-semibold uppercase tracking-wide text-ink opacity-60">
                        {index === 0 ? "Prochaines" : `Dans ${index + 1} cycles`}
                      </Text>
                    </View>
                    <Text className="text-sm font-semibold text-ink">
                      Du {formatDate(entry.start)} au {formatDate(entry.end)}
                    </Text>
                  </View>
                ))}
              </View>
            )}
          </Card>

          <Card>
            <CardHeader icon="time-outline" color={COLOR.phase.luteal} label="Historique des règles" />
            {analysis.history.length === 0 ? (
              <Text className="mt-2 text-sm text-ink opacity-60">
                Aucune règle enregistrée pour l'instant.
              </Text>
            ) : (
              <View className="mt-3 gap-3">
                {visibleHistory.map((entry, index) => (
                  <View
                    key={entry.started_on}
                    className={`flex-row items-center justify-between ${
                      index < visibleHistory.length - 1 ? "border-b border-ink/6 pb-3" : ""
                    }`}
                  >
                    <View className="flex-row items-center">
                      <View
                        className="mr-2.5 h-2 w-2 rounded-full"
                        style={{ backgroundColor: COLOR.phase.menstrual }}
                      />
                      <Text className="text-sm text-ink">Début le {formatDate(entry.started_on)}</Text>
                    </View>
                    <Text className="text-sm text-ink opacity-60">
                      {entry.menses_duration != null
                        ? `Règles : ${formatDays(entry.menses_duration)}`
                        : "Fin non enregistrée"}
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
          </Card>
        </View>
      )}
    </ScrollView>
  );
}
