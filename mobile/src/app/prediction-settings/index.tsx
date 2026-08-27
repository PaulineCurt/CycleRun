import { useCallback, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useFocusEffect, useRouter } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { getProfile, type Profile } from "@/lib/api";
import { CardHeader } from "@/components/ui/card-header";
import { PageLoader } from "@/components/ui/brand-loader";
import { Screen } from "@/components/ui/screen";
import { COLOR } from "@/lib/colors";

export const FIELD_CONFIG = {
  cycle: {
    label: "Durée du cycle",
    icon: "refresh-outline" as const,
    color: COLOR.accent,
    overrideKey: "cycle_length_override" as const,
    averageKey: "cycle_length_average" as const,
  },
  menses: {
    label: "Durée des règles",
    icon: "water-outline" as const,
    color: COLOR.phase.menstrual,
    overrideKey: "menses_length_override" as const,
    averageKey: "menses_length_average" as const,
  },
};

export type PredictionField = keyof typeof FIELD_CONFIG;

/** List of the two overridable predictions — tap one to go edit it on its own screen. */
export default function PredictionSettings() {
  const router = useRouter();
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      setError(false);
      getProfile()
        .then(setProfile)
        .catch(() => setError(true))
        .finally(() => setLoading(false));
    }, []),
  );

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Pressable onPress={() => router.back()} className="mb-4 h-11 justify-center">
        <Text className="text-ink opacity-70">‹ Retour</Text>
      </Pressable>

      {loading ? (
        <PageLoader />
      ) : (
        <>
          <Text className="mb-1 text-3xl font-bold text-ink">Paramètres de prédiction</Text>
          <Text className="mb-8 text-base text-ink opacity-70">Pour les règles et le cycle.</Text>

          {error && (
            <Text className="text-danger">Impossible de charger ton profil.</Text>
          )}
        </>
      )}

      {!loading && !error && profile && (
        <View className="gap-3">
          <Text className="text-sm text-ink opacity-60">
            Force une valeur qui remplace le calcul automatique, même si tu as déjà assez de
            cycles enregistrés.
          </Text>

          {(Object.keys(FIELD_CONFIG) as PredictionField[]).map((field) => {
            const config = FIELD_CONFIG[field];
            const override = profile[config.overrideKey];
            const average = profile[config.averageKey];

            return (
              <Pressable
                key={field}
                onPress={() => router.push(`/prediction-settings/${field}`)}
                className="flex-row items-center justify-between rounded-2xl bg-surface p-4"
                accessibilityRole="button"
              >
                <View className="flex-1">
                  <CardHeader icon={config.icon} color={config.color} label={config.label} />
                  <Text className="mt-1 text-sm text-ink opacity-70">
                    {override != null ? `Forcé à ${override} jours` : `Auto : ${average} jours`}
                  </Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color="#12302B" style={{ opacity: 0.4 }} />
              </Pressable>
            );
          })}
        </View>
      )}
    </Screen>
  );
}
