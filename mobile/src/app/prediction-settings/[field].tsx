import { useCallback, useRef, useState } from "react";
import { Alert, Pressable, Text, TextInput } from "react-native";
import { useFocusEffect, useLocalSearchParams, useRouter } from "expo-router";
import { getProfile, updateProfile, type Profile } from "@/lib/api";
import { CardHeader } from "@/components/ui/card-header";
import { SaveToast, type SaveToastHandle } from "@/components/ui/save-toast";
import { PageLoader } from "@/components/ui/brand-loader";
import { Screen } from "@/components/ui/screen";
import { FIELD_CONFIG, type PredictionField } from "./index";

/**
 * Edit screen for one prediction override. Saving always confirms first — it's a
 * consequential change (it silently overrides every prediction, even once there's
 * enough real history to normally lock the field).
 */
export default function EditPredictionField() {
  const router = useRouter();
  const { field } = useLocalSearchParams<{ field: PredictionField }>();
  const config = FIELD_CONFIG[field];

  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);
  const [value, setValue] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const saveToastRef = useRef<SaveToastHandle>(null);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      getProfile()
        .then((data) => {
          setProfile(data);
          setValue(data[config.overrideKey]?.toString() ?? "");
        })
        .finally(() => setLoading(false));
    }, [config.overrideKey]),
  );

  function parseValue(): number | null {
    if (value.trim() === "") return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : NaN;
  }

  function confirmAndSave() {
    const parsed = parseValue();
    if (Number.isNaN(parsed)) {
      setError("Entre un nombre de jours valide, ou laisse le champ vide.");
      return;
    }

    const label = config.label.toLowerCase();
    const message =
      parsed === null
        ? `Repasser ${label} au calcul automatique ?`
        : `Forcer ${label} à ${parsed} jours ? Ça remplacera les prédictions automatiques partout dans l'app.`;

    Alert.alert("Confirmer ce changement", message, [
      { text: "Annuler", style: "cancel" },
      { text: "Confirmer", style: "destructive", onPress: () => save(parsed) },
    ]);
  }

  async function save(parsed: number | null) {
    setSaving(true);
    setError(null);
    saveToastRef.current?.showSaving();
    try {
      await updateProfile({ [config.overrideKey]: parsed });
      saveToastRef.current?.showSuccess();
      // Let the confirmation flash briefly before leaving the screen, instead of
      // navigating away the instant it appears.
      setTimeout(() => router.back(), 500);
    } catch {
      setError("Impossible d'enregistrer. Réessaie dans un instant.");
      saveToastRef.current?.showError();
    } finally {
      setSaving(false);
    }
  }

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Pressable onPress={() => router.back()} className="mb-4 h-11 justify-center">
        <Text className="text-ink opacity-70">‹ Retour</Text>
      </Pressable>

      {loading ? (
        <PageLoader />
      ) : (
        <>
          <CardHeader icon={config.icon} color={config.color} label={config.label} />
          <Text className="mb-8 mt-2 text-sm text-ink opacity-60">
            Remplace le calcul automatique, même si tu as déjà assez de cycles enregistrés.
            Laisse vide pour repasser au calcul automatique.
          </Text>
        </>
      )}

      {!loading && profile && (
        <>
          <TextInput
            value={value}
            onChangeText={setValue}
            placeholder={`Auto : ${profile[config.averageKey]} jours`}
            placeholderTextColor="#12302B73"
            keyboardType="number-pad"
            className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
          />

          {error && <Text className="mt-2 text-danger">{error}</Text>}

          <Pressable
            onPress={confirmAndSave}
            disabled={saving}
            className={`mt-4 h-11 items-center justify-center rounded-lg bg-accent ${saving ? "opacity-40" : ""}`}
            accessibilityRole="button"
          >
            <Text className="font-semibold text-ink">{saving ? "..." : "Enregistrer"}</Text>
          </Pressable>
        </>
      )}

      <SaveToast ref={saveToastRef} />
    </Screen>
  );
}
