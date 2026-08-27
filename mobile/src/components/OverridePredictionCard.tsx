import { Text, View } from "react-native";
import { useRouter } from "expo-router";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { Button } from "@/components/ui/button";
import { COLOR } from "@/lib/colors";
import type { Profile } from "@/lib/api";

/** Entry point into /prediction-settings. */
export function OverridePredictionCard({ profile }: { profile: Profile }) {
  const router = useRouter();
  const hasOverride = profile.cycle_length_override != null || profile.menses_length_override != null;

  return (
    <Card>
      <View className="mb-2 flex-row items-center justify-between">
        <CardHeader icon="options-outline" color={COLOR.badge} label="Paramètres de prédiction" />
        {hasOverride && (
          <View className="rounded-full bg-danger/10 px-2 py-0.5">
            <Text className="text-[10px] font-semibold uppercase tracking-wide text-danger">
              Actif
            </Text>
          </View>
        )}
      </View>

      <Text className="mb-3 text-xs text-ink opacity-60">
        Pour les règles et le cycle — force une valeur qui remplace le calcul automatique.
      </Text>

      <Button
        variant="secondary"
        icon="settings-outline"
        label="Régler les prédictions"
        onPress={() => router.push("/prediction-settings")}
      />
    </Card>
  );
}
