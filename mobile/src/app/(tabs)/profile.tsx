import { useCallback, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, TextInput, View } from "react-native";
import { useFocusEffect, useRouter } from "expo-router";
import { getProfile, updateProfile, type Profile as ProfileData } from "@/lib/api";

function formatDays(value: number) {
  return `${value} jour${value > 1 ? "s" : ""}`;
}

export default function Profile() {
  const router = useRouter();
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [cycleLength, setCycleLength] = useState("");
  const [mensesLength, setMensesLength] = useState("");
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      setError(false);
      getProfile()
        .then((data) => {
          setProfile(data);
          setCycleLength(data.typical_cycle_length?.toString() ?? "");
          setMensesLength(data.typical_menses_length?.toString() ?? "");
        })
        .catch(() => setError(true))
        .finally(() => setLoading(false));
    }, []),
  );

  function parseField(value: string): number | null {
    if (value.trim() === "") return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : NaN;
  }

  async function handleSave() {
    if (!profile) return;
    setSaveError(null);
    setSaved(false);

    const payload: Parameters<typeof updateProfile>[0] = {};

    if (!profile.cycle_length_measured) {
      const parsedCycle = parseField(cycleLength);
      if (Number.isNaN(parsedCycle)) {
        setSaveError("Entre un nombre de jours valide, ou laisse le champ vide.");
        return;
      }
      payload.typical_cycle_length = parsedCycle;
    }

    if (!profile.menses_length_measured) {
      const parsedMenses = parseField(mensesLength);
      if (Number.isNaN(parsedMenses)) {
        setSaveError("Entre un nombre de jours valide, ou laisse le champ vide.");
        return;
      }
      payload.typical_menses_length = parsedMenses;
    }

    setSaving(true);
    try {
      await updateProfile(payload);
      setProfile(await getProfile());
      setSaved(true);
    } catch {
      setSaveError("Impossible d'enregistrer. Réessaie dans un instant.");
    } finally {
      setSaving(false);
    }
  }

  const bothLocked = profile?.cycle_length_measured && profile?.menses_length_measured;

  return (
    <ScrollView className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Text className="mb-1 text-3xl font-bold text-ink">Profil</Text>
      <Text className="mb-4 text-base text-ink opacity-70">Tes habitudes de cycle.</Text>

      <View className="mb-6 rounded-2xl bg-surface p-4">
        <Text className="text-sm text-ink opacity-80">
          {bothLocked
            ? "Ces durées sont désormais calculées à partir de tes cycles logués — plus besoin de les saisir toi-même."
            : "Renseigne la durée habituelle de ton cycle et de tes règles pour des estimations fiables dès le départ. Si tu ne les connais pas, laisse vide : elles seront calculées automatiquement une fois que tu auras logué quelques cycles."}
        </Text>
      </View>

      {loading && <ActivityIndicator color="#12302B" className="my-8" />}

      {!loading && error && (
        <Text className="text-danger">Impossible de charger ton profil.</Text>
      )}

      {!loading && !error && profile && (
        <View className="gap-4">
          <View className="rounded-2xl bg-surface p-4">
            <Text className="mb-2 text-sm font-semibold text-ink">
              Durée habituelle du cycle
            </Text>
            {profile.cycle_length_measured ? (
              <>
                <Text className="text-xl font-bold text-ink">
                  {formatDays(profile.cycle_length_average)}
                </Text>
                <Text className="mt-1 text-xs text-ink opacity-60">
                  Calculé à partir de tes cycles — modifie-le en corrigeant tes dates dans le
                  calendrier plutôt qu'ici.
                </Text>
              </>
            ) : (
              <>
                <Text className="mb-3 text-xs text-ink opacity-60">
                  En jours, entre le début de deux règles. Laisse vide si tu ne sais pas encore —
                  on se basera sur tes cycles au fur et à mesure que tu les logues.
                </Text>
                <TextInput
                  value={cycleLength}
                  onChangeText={setCycleLength}
                  placeholder="Ex. 28"
                  placeholderTextColor="#12302B73"
                  keyboardType="number-pad"
                  className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
                />
              </>
            )}
          </View>

          <View className="rounded-2xl bg-surface p-4">
            <Text className="mb-2 text-sm font-semibold text-ink">
              Durée habituelle des règles
            </Text>
            {profile.menses_length_measured ? (
              <>
                <Text className="text-xl font-bold text-ink">
                  {formatDays(profile.menses_length_average)}
                </Text>
                <Text className="mt-1 text-xs text-ink opacity-60">
                  Calculé à partir de tes cycles — modifie-le en corrigeant tes dates dans le
                  calendrier plutôt qu'ici.
                </Text>
              </>
            ) : (
              <>
                <Text className="mb-3 text-xs text-ink opacity-60">
                  En jours. Laisse vide si tu ne sais pas encore.
                </Text>
                <TextInput
                  value={mensesLength}
                  onChangeText={setMensesLength}
                  placeholder="Ex. 5"
                  placeholderTextColor="#12302B73"
                  keyboardType="number-pad"
                  className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
                />
              </>
            )}
          </View>

          {saveError && <Text className="text-danger">{saveError}</Text>}
          {saved && !saveError && <Text className="text-ink opacity-70">Enregistré.</Text>}

          {!bothLocked && (
            <Pressable
              onPress={handleSave}
              disabled={saving}
              className="h-11 items-center justify-center rounded-lg bg-accent"
              accessibilityRole="button"
            >
              <Text className="font-semibold text-ink">{saving ? "..." : "Enregistrer"}</Text>
            </Pressable>
          )}

          <Pressable
            onPress={() => router.push("/analysis")}
            className="h-11 items-center justify-center rounded-lg bg-ink/6"
            accessibilityRole="button"
          >
            <Text className="font-semibold text-ink">Voir l'analyse de mon cycle</Text>
          </Pressable>
        </View>
      )}
    </ScrollView>
  );
}
