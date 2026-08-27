import { useCallback, useRef, useState } from "react";
import { Pressable, Text, TextInput, View } from "react-native";
import { useFocusEffect, useLocalSearchParams, useRouter } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import {
  getTrainingSession,
  updateTrainingSession,
  type SessionStatus,
  type TrainingSession,
} from "@/lib/api";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { Button } from "@/components/ui/button";
import { PageLoader } from "@/components/ui/brand-loader";
import { Screen } from "@/components/ui/screen";
import { SaveToast, type SaveToastHandle } from "@/components/ui/save-toast";
import { SessionCycleNote } from "@/components/SessionCycleNote";
import { COLOR } from "@/lib/colors";
import {
  SESSION_ICON,
  SESSION_LABEL,
  describeLoad,
  formatPaceRange,
  sessionAccent,
} from "@/lib/training";

const RPE_VALUES = [2, 4, 6, 8, 10];

function fullDay(iso: string) {
  return new Date(`${iso}T00:00:00`).toLocaleDateString("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
  });
}

export default function SessionDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const sessionId = Number(id);

  const [session, setSession] = useState<TrainingSession | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [rpe, setRpe] = useState<number | null>(null);
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const saveToastRef = useRef<SaveToastHandle>(null);

  useFocusEffect(
    useCallback(() => {
      setError(false);
      getTrainingSession(sessionId)
        .then((data) => {
          setSession(data);
          setRpe(data.actual?.rpe ?? null);
          setNote(data.actual?.note ?? "");
        })
        .catch(() => setError(true))
        .finally(() => setLoading(false));
    }, [sessionId]),
  );

  async function save(status: SessionStatus) {
    setSaving(true);
    setSaveError(null);
    saveToastRef.current?.showSaving();
    try {
      const updated = await updateTrainingSession(sessionId, {
        status,
        actual:
          status === "done"
            ? { rpe: rpe ?? undefined, note: note.trim() || undefined }
            : undefined,
      });
      setSession((prev) => (prev ? { ...prev, ...updated } : updated));
      saveToastRef.current?.showSuccess(status === "done" ? "Séance faite !" : "Séance sautée");
      // Let the confirmation flash before leaving; the plan screen refetches on focus.
      setTimeout(() => router.back(), 600);
    } catch {
      setSaveError("Impossible d'enregistrer. Vérifie ta connexion et réessaie.");
      saveToastRef.current?.showError();
    } finally {
      setSaving(false);
    }
  }

  const paceRange = session
    ? formatPaceRange(session.target_pace_min_s, session.target_pace_max_s)
    : null;

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-12 pt-16">
      <Pressable onPress={() => router.back()} className="mb-4 h-11 justify-center">
        <Text className="text-ink opacity-70">‹ Retour</Text>
      </Pressable>

      {loading ? (
        <PageLoader />
      ) : error || !session ? (
        <Text className="text-danger">Impossible de charger cette séance.</Text>
      ) : (
        <View className="gap-4">
          <View className="flex-row items-center gap-3">
            <Ionicons
              name={SESSION_ICON[session.type] as never}
              size={26}
              color={sessionAccent(session.type)}
            />
            <View className="flex-1">
              <Text className="text-2xl font-bold text-ink">{SESSION_LABEL[session.type]}</Text>
              <Text className="text-sm capitalize text-ink opacity-60">
                {fullDay(session.planned_date)}
              </Text>
            </View>
          </View>

          {session.postponed && (
            <View className="flex-row items-start rounded-2xl bg-danger/10 p-4">
              <Ionicons
                name="alert-circle-outline"
                size={16}
                color={COLOR.danger}
                style={{ marginTop: 1, marginRight: 8 }}
              />
              <Text className="flex-1 text-sm text-danger">
                Douleur signalée ce jour-là — mieux vaut reporter cette séance ou la remplacer
                par du repos / de la récup active.
              </Text>
            </View>
          )}

          <Card>
            <CardHeader icon="barbell-outline" color={COLOR.accent} label="Contenu" />
            <Text className="mt-1 text-base text-ink">{describeLoad(session.planned_load)}</Text>
            {paceRange && (
              <Text className="mt-1 text-sm text-ink opacity-60">Allure cible {paceRange}</Text>
            )}
            {session.adapted && session.base && (
              <Text className="mt-2 text-xs text-ink opacity-45">
                Ajustée pour ton cycle · séance de base : {describeLoad(session.base.planned_load)}
              </Text>
            )}
            <SessionCycleNote session={session} />
          </Card>

          {session.status !== "planned" && (
            <Text className="text-sm text-ink opacity-60">
              Séance marquée : {session.status === "done" ? "faite" : session.status === "skipped" ? "sautée" : "déplacée"}.
            </Text>
          )}

          <Card>
            <CardHeader icon="pulse-outline" color={COLOR.badge} label="Ressenti (optionnel)" />
            <Text className="mb-2 mt-1 text-xs text-ink opacity-50">Effort perçu (1 = très facile, 10 = maximal)</Text>
            <View className="flex-row gap-2">
              {RPE_VALUES.map((value) => (
                <Pressable
                  key={value}
                  onPress={() => setRpe(value === rpe ? null : value)}
                  className={`h-10 flex-1 items-center justify-center rounded-lg ${
                    rpe === value ? "bg-accent" : "bg-ink/6"
                  }`}
                  accessibilityRole="button"
                >
                  <Text className="font-semibold text-ink">{value}</Text>
                </Pressable>
              ))}
            </View>
            <TextInput
              value={note}
              onChangeText={setNote}
              placeholder="Une note sur la séance…"
              placeholderTextColor="#12302B73"
              multiline
              className="mt-3 min-h-[64px] rounded-lg bg-ink/6 px-4 py-3 text-ink"
            />
          </Card>

          <View className="gap-3">
            <Button
              label={
                saving
                  ? "…"
                  : session.status === "done"
                    ? "Mettre à jour le ressenti"
                    : "Séance faite"
              }
              icon="checkmark-outline"
              disabled={saving}
              onPress={() => save("done")}
            />
            {session.status !== "skipped" && (
              <Button
                label="Je l'ai sautée"
                variant="secondary"
                icon="close-outline"
                disabled={saving}
                onPress={() => save("skipped")}
              />
            )}
            {session.status !== "planned" && (
              <Button
                label="Remettre en « à faire »"
                variant="ghost"
                disabled={saving}
                onPress={() => save("planned")}
              />
            )}
            {saveError && <Text className="text-center text-danger">{saveError}</Text>}
          </View>
        </View>
      )}

      <SaveToast ref={saveToastRef} />
    </Screen>
  );
}
