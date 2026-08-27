import { useCallback, useRef, useState } from "react";
import { Pressable, Text, TextInput, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useFocusEffect, useRouter } from "expo-router";
import { getProfile, updateProfile, type Profile as ProfileData } from "@/lib/api";
import { OverridePredictionCard } from "@/components/OverridePredictionCard";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { Avatar } from "@/components/ui/avatar";
import { AvatarPickerSheet } from "@/components/AvatarPickerSheet";
import { SaveToast, type SaveToastHandle } from "@/components/ui/save-toast";
import { PageLoader } from "@/components/ui/brand-loader";
import { Screen } from "@/components/ui/screen";
import { COLOR } from "@/lib/colors";

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
  const [saveError, setSaveError] = useState<string | null>(null);
  const [avatarPickerOpen, setAvatarPickerOpen] = useState(false);
  const saveToastRef = useRef<SaveToastHandle>(null);

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

    const payload: Parameters<typeof updateProfile>[0] = {};

    if (!cycleLocked) {
      const parsedCycle = parseField(cycleLength);
      if (Number.isNaN(parsedCycle)) {
        setSaveError("Entre un nombre de jours valide, ou laisse le champ vide.");
        return;
      }
      payload.typical_cycle_length = parsedCycle;
    }

    if (!mensesLocked) {
      const parsedMenses = parseField(mensesLength);
      if (Number.isNaN(parsedMenses)) {
        setSaveError("Entre un nombre de jours valide, ou laisse le champ vide.");
        return;
      }
      payload.typical_menses_length = parsedMenses;
    }

    setSaving(true);
    saveToastRef.current?.showSaving();
    try {
      await updateProfile(payload);
      setProfile(await getProfile());
      saveToastRef.current?.showSuccess();
    } catch {
      setSaveError("Impossible d'enregistrer. Réessaie dans un instant.");
      saveToastRef.current?.showError();
    } finally {
      setSaving(false);
    }
  }

  // A declared value locks the field immediately, not just once it's measured — it
  // was answered once (at registration or here), so it's treated as settled rather
  // than asked again every time the profile is opened. Changing it afterwards goes
  // through "Régler les prédictions" (a deliberate override, with confirmation),
  // not a plain always-open text field.
  const cycleLocked = Boolean(profile?.cycle_length_measured || profile?.typical_cycle_length != null);
  const mensesLocked = Boolean(profile?.menses_length_measured || profile?.typical_menses_length != null);
  const bothLocked = cycleLocked && mensesLocked;

  if (loading) {
    return (
      <View className="flex-1 bg-bg">
        <PageLoader />
      </View>
    );
  }

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      {profile && (
        <View className="mb-6 flex-row items-center">
          <Pressable onPress={() => setAvatarPickerOpen(true)} accessibilityRole="button">
            <View>
              <Avatar name={profile.name} avatarId={profile.avatar} />
              <View
                className="absolute -bottom-1 -right-1 h-6 w-6 items-center justify-center rounded-full bg-surface"
                style={{ borderWidth: 1.5, borderColor: COLOR.accent }}
              >
                <Ionicons name="pencil" size={11} color={COLOR.accent} />
              </View>
            </View>
          </Pressable>
          <View className="ml-4">
            <Text className="text-2xl font-bold text-ink">{profile.name}</Text>
            <Text className="text-sm text-ink opacity-60">Tes habitudes de cycle.</Text>
          </View>
        </View>
      )}

      {error && (
        <Text className="text-danger">Impossible de charger ton profil.</Text>
      )}

      {!error && profile && (
        <View className="gap-4">
          <Card>
            <View className="mb-2">
              <CardHeader icon="refresh-outline" color={COLOR.accent} label="Durée habituelle du cycle" />
            </View>
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
            ) : cycleLocked ? (
              <>
                <Text className="text-xl font-bold text-ink">
                  {formatDays(profile.typical_cycle_length!)}
                </Text>
                <Text className="mt-1 text-xs text-ink opacity-60">
                  Valeur que tu as renseignée — modifie-la depuis "Régler les prédictions"
                  ci-dessous.
                </Text>
              </>
            ) : (
              <>
                <Text className="mb-3 text-xs text-ink opacity-60">
                  En jours, entre le début de deux règles. Laisse vide si tu ne sais pas encore —
                  on se basera sur tes cycles au fur et à mesure que tu les enregistres.
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
          </Card>

          <Card>
            <View className="mb-2">
              <CardHeader icon="water-outline" color={COLOR.phase.menstrual} label="Durée habituelle des règles" />
            </View>
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
            ) : mensesLocked ? (
              <>
                <Text className="text-xl font-bold text-ink">
                  {formatDays(profile.typical_menses_length!)}
                </Text>
                <Text className="mt-1 text-xs text-ink opacity-60">
                  Valeur que tu as renseignée — modifie-la depuis "Régler les prédictions"
                  ci-dessous.
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
          </Card>

          {saveError && <Text className="text-danger">{saveError}</Text>}

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

          <OverridePredictionCard profile={profile} />

          <Pressable onPress={() => router.push("/analysis")} accessibilityRole="button">
            <Card className="flex-row items-center justify-between">
              <CardHeader icon="analytics-outline" color={COLOR.phase.ovulation} label="Analyse du cycle" />
              <Ionicons name="chevron-forward" size={18} color="#12302B" style={{ opacity: 0.4 }} />
            </Card>
          </Pressable>
        </View>
      )}

      {avatarPickerOpen && profile && (
        <AvatarPickerSheet
          currentAvatar={profile.avatar}
          onClose={() => setAvatarPickerOpen(false)}
          onSaved={setProfile}
        />
      )}

      <SaveToast ref={saveToastRef} />
    </Screen>
  );
}
