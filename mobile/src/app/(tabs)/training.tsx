import { useCallback, useRef, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useFocusEffect, useRouter } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import {
  getProgram,
  getPrograms,
  type ProgramDetail,
  type ProgramSummary,
  type TrainingSession,
} from "@/lib/api";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { Button } from "@/components/ui/button";
import { PageLoader } from "@/components/ui/brand-loader";
import { Screen } from "@/components/ui/screen";
import { SessionCycleNote } from "@/components/SessionCycleNote";
import { COLOR } from "@/lib/colors";
import { toDateKey } from "@/lib/calendar";
import {
  SESSION_ICON,
  SESSION_LABEL,
  describeLoad,
  formatPaceRange,
  nextPlannedSession,
  pickActiveProgram,
  sessionAccent,
} from "@/lib/training";

const STATUS_LABEL: Record<ProgramSummary["status"], string> = {
  draft: "Brouillon",
  active: "En cours",
  done: "Terminé",
  archived: "Archivé",
};

function formatDay(iso: string) {
  return new Date(`${iso}T00:00:00`).toLocaleDateString("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
  });
}

export default function Training() {
  const router = useRouter();
  const [programs, setPrograms] = useState<ProgramSummary[] | null>(null);
  const [detail, setDetail] = useState<ProgramDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const hasLoadedOnce = useRef(false);

  useFocusEffect(
    useCallback(() => {
      if (!hasLoadedOnce.current) setLoading(true);
      setError(false);
      getPrograms()
        .then(async (list) => {
          setPrograms(list);
          const chosen = pickActiveProgram(list);
          setDetail(chosen ? await getProgram(chosen.id) : null);
          hasLoadedOnce.current = true;
        })
        .catch(() => setError(true))
        .finally(() => setLoading(false));
    }, []),
  );

  if (loading) {
    return (
      <View className="flex-1 bg-bg">
        <PageLoader />
      </View>
    );
  }

  const upcoming = detail ? nextPlannedSession(detail.sessions, toDateKey(new Date())) : null;
  const paceRange = upcoming
    ? formatPaceRange(upcoming.target_pace_min_s, upcoming.target_pace_max_s)
    : null;

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Text className="mb-1 text-3xl font-bold text-ink">Entraînement</Text>
      <Text className="mb-8 text-base text-ink opacity-70">Ton plan 5 km, séance par séance.</Text>

      {error && <Text className="text-danger">Impossible de charger ton entraînement.</Text>}

      {!error && (!programs || programs.length === 0) && (
        <Card>
          <Text className="text-center text-base text-ink opacity-70">
            Pas encore de plan. On te construit un programme 5 km progressif à partir de ton
            niveau et de tes disponibilités.
          </Text>
          <Button
            label="Créer mon plan"
            icon="add-circle-outline"
            className="mt-4"
            onPress={() => router.push("/training/new")}
          />
        </Card>
      )}

      {!error && detail && (
        <View className="gap-4">
          <Card>
            <CardHeader icon="flag-outline" color={COLOR.badge} label="Objectif" />
            <Text className="mt-1 text-2xl font-bold text-ink">
              {(detail.goal_distance_m / 1000).toLocaleString("fr-FR")} km
            </Text>
            <Text className="mt-1 text-sm text-ink opacity-60">
              {detail.duration_weeks} semaines · {detail.days_per_week} séances / semaine ·{" "}
              {STATUS_LABEL[detail.status]}
            </Text>
            <Button
              label="Voir le plan complet"
              variant="secondary"
              icon="list-outline"
              className="mt-4"
              onPress={() => router.push(`/training/${detail.id}`)}
            />
          </Card>

          {upcoming ? (
            <Pressable onPress={() => router.push(`/training/session/${upcoming.id}`)}>
              <Card>
                <CardHeader icon="play-outline" color={COLOR.accent} label="Prochaine séance" />
                <View className="mt-1 flex-row items-center gap-2">
                  <Ionicons
                    name={SESSION_ICON[upcoming.type] as never}
                    size={18}
                    color={sessionAccent(upcoming.type)}
                  />
                  <Text className="text-lg font-bold text-ink">{SESSION_LABEL[upcoming.type]}</Text>
                </View>
                <Text className="mt-1 text-sm capitalize text-ink opacity-60">
                  {formatDay(upcoming.planned_date)}
                </Text>
                <Text className="mt-2 text-sm text-ink opacity-80">{describeLoad(upcoming.planned_load)}</Text>
                {paceRange && (
                  <Text className="mt-1 text-sm text-ink opacity-60">Allure cible {paceRange}</Text>
                )}
                <SessionCycleNote session={upcoming} showBase />
              </Card>
            </Pressable>
          ) : (
            <Card>
              <Text className="text-center text-sm text-ink opacity-60">
                Aucune séance à venir — plan terminé ou tout est déjà fait. 🎉
              </Text>
            </Card>
          )}

          {detail.feasibility?.alert && (
            <View className="flex-row items-start rounded-2xl bg-danger/10 p-4">
              <Ionicons
                name="warning-outline"
                size={16}
                color={COLOR.danger}
                style={{ marginTop: 1, marginRight: 8 }}
              />
              <Text className="flex-1 text-sm text-danger">{detail.feasibility.alert}</Text>
            </View>
          )}

          <Pressable
            onPress={() => router.push("/training/new")}
            className="h-11 items-center justify-center"
            accessibilityRole="button"
          >
            <Text className="text-ink opacity-60">Créer un autre plan</Text>
          </Pressable>
        </View>
      )}

      <Text className="mt-8 text-center text-xs text-ink opacity-40">
        Plan indicatif — ne remplace ni un avis médical, ni un coach diplômé.
      </Text>
    </Screen>
  );
}
