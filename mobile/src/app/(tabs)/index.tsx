import { useCallback, useRef, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useFocusEffect } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { getCycleStatus, logout, type CycleStatus } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import { PHASE_LABEL } from "@/lib/phase";
import { CycleRing } from "@/components/CycleRing";
import { DailyFeelingCard } from "@/components/DailyFeelingCard";
import { PhaseTip } from "@/components/PhaseTip";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { PageLoader } from "@/components/ui/brand-loader";
import { Screen } from "@/components/ui/screen";
import { COLOR } from "@/lib/colors";

const BADGE = COLOR.badge;
const OVULATION_COLOR = COLOR.phase.ovulation;
const DANGER = COLOR.danger;

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "long" });
}

function daysUntil(iso: string) {
  const target = new Date(`${iso}T00:00:00`);
  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  return Math.round((target.getTime() - startOfToday.getTime()) / 86_400_000);
}

function countdownLabel(days: number) {
  if (days === 0) return "Aujourd'hui";
  if (days === 1) return "Demain";
  if (days > 1) return `Dans ${days} jours`;
  if (days === -1) return "En retard d'1 jour";
  return `En retard de ${-days} jours`;
}

export default function Home() {
  const { setAuthed } = useAuth();
  const [status, setStatus] = useState<CycleStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const hasLoadedOnce = useRef(false);

  useFocusEffect(
    useCallback(() => {
      // Only blank the screen on the very first load — refetching on every tab focus
      // shouldn't flash the loader over content we already have.
      if (!hasLoadedOnce.current) setLoading(true);
      setError(false);
      getCycleStatus()
        .then((data) => {
          setStatus(data);
          hasLoadedOnce.current = true;
        })
        .catch(() => setError(true))
        .finally(() => setLoading(false));
    }, []),
  );

  async function handleLogout() {
    await logout();
    setAuthed(false);
  }

  if (loading) {
    return (
      <View className="flex-1 bg-bg">
        <PageLoader />
      </View>
    );
  }

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Text className="mb-1 text-3xl font-bold text-ink">CycleRun</Text>
      <Text className="mb-8 text-base text-ink opacity-70">Ton cycle, ton entraînement.</Text>

      {error && (
        <Text className="text-danger">Impossible de charger ton statut du jour.</Text>
      )}

      {!error && status?.status === "no_data" && (
        <Card>
          <Text className="text-center text-base text-ink opacity-70">
            Aucune donnée pour l'instant. Va dans l'onglet Calendrier et tape sur le jour où tes
            dernières règles ont commencé pour démarrer le suivi.
          </Text>
          <Text className="mt-3 text-center text-sm text-ink opacity-60">
            Tu connais déjà la durée habituelle de ton cycle ? Renseigne-la dans l'onglet Profil
            pour des estimations plus justes dès le départ.
          </Text>
        </Card>
      )}

      {/* Ordered, composable blocks — a future "séance du jour" (module B) slots in
          at the top of this list without touching what's below it. */}
      {!error && status?.status === "ok" && (
        <View className="gap-4">
          <Card className="items-center">
            <CycleRing activePhase={status.phase}>
              <Text className="text-lg font-bold text-ink">
                {status.phase ? PHASE_LABEL[status.phase] : "Phase incertaine"}
              </Text>
              <Text className="mt-1 text-sm text-ink opacity-60">Jour {status.cycle_day}</Text>
            </CycleRing>

            {status.phase && (
              <View className="mt-4 w-full border-t border-ink/10 pt-4">
                <PhaseTip phase={status.phase} />
              </View>
            )}
          </Card>

          <Card className="items-center">
            <CardHeader icon="calendar-outline" color={BADGE} label="Prochaines règles" />
            <Text className="mt-1 text-2xl font-bold text-ink">
              {countdownLabel(daysUntil(status.predicted_next_start))}
            </Text>
            <Text className="mt-1 text-sm text-ink opacity-60">
              Autour du {formatDate(status.predicted_next_start)}
              {status.next_start_range &&
                ` (entre le ${formatDate(status.next_start_range.start)} et le ${formatDate(status.next_start_range.end)})`}
            </Text>
            {status.low_confidence && (
              <Text className="mt-2 text-center text-xs text-ink opacity-50">
                Ça va s'affiner à mesure que tu notes tes cycles.
              </Text>
            )}
          </Card>

          <DailyFeelingCard />

          {status.predictable && (
            <Card>
              <CardHeader icon="egg-outline" color={OVULATION_COLOR} label="Fenêtre d'ovulation" />
              <Text className="mt-1 text-sm text-ink opacity-80">
                Du {formatDate(status.ovulation_window.start)} au{" "}
                {formatDate(status.ovulation_window.end)}
              </Text>
            </Card>
          )}

          {status.severely_overdue ? (
            <View className="flex-row items-start rounded-2xl bg-danger/10 p-4">
              <Ionicons name="warning-outline" size={16} color={DANGER} style={{ marginTop: 1, marginRight: 8 }} />
              <Text className="flex-1 text-sm text-danger">
                Retard important par rapport à ton cycle habituel. Un avis médical est recommandé.
              </Text>
            </View>
          ) : (
            status.overdue && (
              <View className="flex-row items-start rounded-2xl bg-danger/10 p-4">
                <Ionicons name="warning-outline" size={16} color={DANGER} style={{ marginTop: 1, marginRight: 8 }} />
                <Text className="flex-1 text-sm text-danger">
                  Tes règles semblent en retard par rapport à ton cycle habituel.
                </Text>
              </View>
            )
          )}
        </View>
      )}

      <Pressable
        onPress={handleLogout}
        className="mt-8 h-11 items-center justify-center"
        accessibilityRole="button"
      >
        <Text className="text-center text-ink opacity-70">Déconnexion</Text>
      </Pressable>
    </Screen>
  );
}
