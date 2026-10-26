import { useCallback, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useFocusEffect, useLocalSearchParams, useRouter } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import {
  getProgram,
  updateProgram,
  updateProgramStatus,
  type Level,
  type ProgramDetail,
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
  LEVEL_LABEL,
  SESSION_ICON,
  SESSION_LABEL,
  describeLoad,
  formatClock,
  formatPaceRange,
  sessionAccent,
} from "@/lib/training";

const LEVELS: Level[] = ["debutante", "intermediaire", "confirmee"];

const STATUS_BADGE_COLOR = COLOR.badge;

function groupByWeek(sessions: TrainingSession[]): [number, TrainingSession[]][] {
  const map = new Map<number, TrainingSession[]>();
  for (const s of sessions) {
    const bucket = map.get(s.week_index) ?? [];
    bucket.push(s);
    map.set(s.week_index, bucket);
  }
  return [...map.entries()].sort((a, b) => a[0] - b[0]);
}

function dayLabel(iso: string) {
  return new Date(`${iso}T00:00:00`).toLocaleDateString("fr-FR", {
    weekday: "short",
    day: "numeric",
    month: "short",
  });
}

export default function ProgramPlan() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const programId = Number(id);

  const [program, setProgram] = useState<ProgramDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [activating, setActivating] = useState(false);
  const [savingLevel, setSavingLevel] = useState(false);

  const load = useCallback(() => {
    setError(false);
    getProgram(programId)
      .then(setProgram)
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, [programId]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  async function activate() {
    setActivating(true);
    try {
      await updateProgramStatus(programId, "active");
      load();
    } finally {
      setActivating(false);
    }
  }

  async function changeLevel(next: Level) {
    if (savingLevel || next === program?.level) return;
    setSavingLevel(true);
    try {
      await updateProgram(programId, { level: next });
      load();
    } finally {
      setSavingLevel(false);
    }
  }

  const today = toDateKey(new Date());

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-12 pt-16">
      <Pressable onPress={() => router.back()} className="mb-4 h-11 justify-center">
        <Text className="text-ink opacity-70">‹ Retour</Text>
      </Pressable>

      {loading ? (
        <PageLoader />
      ) : error || !program ? (
        <Text className="text-danger">Impossible de charger ce plan.</Text>
      ) : (
        <>
          <Text className="mb-1 text-3xl font-bold text-ink">
            Plan {(program.goal_distance_m / 1000).toLocaleString("fr-FR")} km
          </Text>
          <Text className="mb-1 text-sm text-ink opacity-60">
            {program.duration_weeks} semaines · {program.days_per_week} séances / semaine
            {program.goal_time_s ? ` · objectif ${formatClock(program.goal_time_s)}` : ""}
          </Text>
          {(() => {
            const done = program.sessions.filter((s) => s.status === "done").length;
            const total = program.sessions.length;
            return (
              <Text className="mb-6 text-sm font-semibold text-ink opacity-80">
                {done} séance{done > 1 ? "s" : ""} faite{done > 1 ? "s" : ""} sur {total}
              </Text>
            );
          })()}

          <View className="mb-6">
            <Text className="mb-2 text-xs uppercase tracking-wide text-ink opacity-50">Niveau</Text>
            <View className="flex-row gap-2">
              {LEVELS.map((lvl) => (
                <Pressable
                  key={lvl}
                  onPress={() => changeLevel(lvl)}
                  disabled={savingLevel}
                  className={`h-10 flex-1 items-center justify-center rounded-lg ${
                    program.level === lvl ? "bg-accent" : "bg-surface"
                  }`}
                  accessibilityRole="button"
                >
                  <Text className="text-xs font-semibold text-ink">{LEVEL_LABEL[lvl]}</Text>
                </Pressable>
              ))}
            </View>
            <Text className="mt-1.5 text-xs text-ink opacity-50">
              Ajuster le niveau ne régénère pas le plan en cours.
            </Text>
          </View>

          {program.feasibility?.alert && (
            <View className="mb-4 flex-row items-start rounded-2xl bg-danger/10 p-4">
              <Ionicons
                name="warning-outline"
                size={16}
                color={COLOR.danger}
                style={{ marginTop: 1, marginRight: 8 }}
              />
              <Text className="flex-1 text-sm text-danger">{program.feasibility.alert}</Text>
            </View>
          )}

          {program.warnings && program.warnings.length > 0 && (
            <Card className="mb-4">
              <CardHeader icon="information-circle-outline" color={STATUS_BADGE_COLOR} label="À savoir" />
              <View className="mt-1 gap-1">
                {program.warnings.map((w, i) => (
                  <Text key={i} className="text-sm text-ink opacity-70">
                    • {w}
                  </Text>
                ))}
              </View>
            </Card>
          )}

          {program.status === "draft" && (
            <Button
              label={activating ? "…" : "Activer ce plan"}
              icon="play-outline"
              className="mb-6"
              disabled={activating}
              onPress={activate}
            />
          )}

          <View className="gap-5">
            {groupByWeek(program.sessions).map(([week, sessions]) => (
              <View key={week}>
                <Text className="mb-2 text-xs uppercase tracking-wide text-ink opacity-50">
                  Semaine {week + 1}
                </Text>
                <View className="gap-2">
                  {sessions.map((session) => {
                    const paceRange = formatPaceRange(
                      session.target_pace_min_s,
                      session.target_pace_max_s,
                    );
                    const isPast = session.planned_date < today;
                    return (
                      <Pressable
                        key={session.id}
                        onPress={() => router.push(`/training/session/${session.id}`)}
                      >
                        <Card className={session.status !== "planned" || isPast ? "opacity-60" : ""}>
                          <View className="flex-row items-center gap-3">
                            <Ionicons
                              name={SESSION_ICON[session.type] as never}
                              size={20}
                              color={sessionAccent(session.type)}
                            />
                            <View className="flex-1">
                              <Text className="font-semibold text-ink">
                                {SESSION_LABEL[session.type]}
                              </Text>
                              <Text className="text-xs text-ink opacity-60">
                                {dayLabel(session.planned_date)} · {describeLoad(session.planned_load)}
                                {paceRange ? ` · ${paceRange}` : ""}
                              </Text>
                            </View>
                            {session.status === "done" && (
                              <Ionicons name="checkmark-circle" size={18} color={COLOR.accent} />
                            )}
                            {session.status === "skipped" && (
                              <Ionicons name="close-circle" size={18} color={COLOR.danger} />
                            )}
                          </View>
                          <SessionCycleNote session={session} />
                        </Card>
                      </Pressable>
                    );
                  })}
                </View>
              </View>
            ))}
          </View>
        </>
      )}
    </Screen>
  );
}
