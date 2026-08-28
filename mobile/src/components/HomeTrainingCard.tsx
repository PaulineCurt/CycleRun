import { useCallback, useEffect, useRef, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useFocusEffect, useRouter } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { getProgram, getPrograms, type ProgramDetail } from "@/lib/api";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { Button } from "@/components/ui/button";
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

function relativeDay(iso: string, today: string) {
  if (iso === today) return "aujourd'hui";
  const target = new Date(`${iso}T00:00:00`);
  const now = new Date(`${today}T00:00:00`);
  const days = Math.round((target.getTime() - now.getTime()) / 86_400_000);
  if (days === 1) return "demain";
  if (days > 1 && days < 7) {
    return target.toLocaleDateString("fr-FR", { weekday: "long" });
  }
  return target.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" });
}

/**
 * Module B's slot on the home screen: today's run (or the next one), already
 * carrying its cycle adaptation — the whole product pitch in one card. Falls back
 * to a create-plan nudge. Self-contained: fetches and refreshes on its own.
 */
export function HomeTrainingCard({ refreshSignal = 0 }: { refreshSignal?: number }) {
  const router = useRouter();
  const [detail, setDetail] = useState<ProgramDetail | null>(null);
  const [hasProgram, setHasProgram] = useState<boolean | null>(null);
  const loadedOnce = useRef(false);

  const load = useCallback(() => {
    getPrograms()
      .then(async (list) => {
        const chosen = pickActiveProgram(list);
        setHasProgram(chosen !== null);
        setDetail(chosen ? await getProgram(chosen.id) : null);
        loadedOnce.current = true;
      })
      .catch(() => {
        if (!loadedOnce.current) setHasProgram(null);
      });
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  // Re-fetch when something the plan depends on changed elsewhere on the screen
  // (e.g. today's pain was just logged, which flips a session to "à reporter").
  const firstRun = useRef(true);
  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false;
      return;
    }
    load();
  }, [refreshSignal, load]);

  // Stay invisible until the first fetch settles — no flash, no layout jump.
  if (hasProgram === null) return null;

  if (!hasProgram || !detail) {
    return (
      <Card>
        <CardHeader icon="fitness-outline" color={COLOR.accent} label="Entraînement" />
        <Text className="mt-1 text-sm text-ink opacity-70">
          Un plan 5 km progressif qui s'adapte à ton cycle, séance par séance.
        </Text>
        <Button
          variant="secondary"
          icon="add-circle-outline"
          label="Créer mon plan"
          className="mt-3"
          onPress={() => router.push("/training/new")}
        />
      </Card>
    );
  }

  const today = toDateKey(new Date());
  const session = nextPlannedSession(detail.sessions, today);
  const done = detail.sessions.filter((s) => s.status === "done").length;
  const total = detail.sessions.length;

  if (!session) {
    return (
      <Card>
        <CardHeader icon="fitness-outline" color={COLOR.accent} label="Entraînement" />
        <Text className="mt-1 text-sm text-ink opacity-70">
          {done === total
            ? "Plan terminé — bravo. 🎉"
            : "Aucune séance à venir pour le moment."}
        </Text>
        <Pressable onPress={() => router.push(`/training/${detail.id}`)} className="mt-2">
          <Text className="text-sm font-semibold text-accent">Voir le plan</Text>
        </Pressable>
      </Card>
    );
  }

  const isToday = session.planned_date === today;
  const paceRange = formatPaceRange(session.target_pace_min_s, session.target_pace_max_s);

  return (
    <Pressable onPress={() => router.push(`/training/session/${session.id}`)}>
      <Card>
        <CardHeader
          icon="fitness-outline"
          color={COLOR.accent}
          label={isToday ? "Séance du jour" : "Prochaine séance"}
        />
        <View className="mt-1 flex-row items-center gap-2">
          <Ionicons
            name={SESSION_ICON[session.type] as never}
            size={18}
            color={sessionAccent(session.type)}
          />
          <Text className="text-lg font-bold text-ink">{SESSION_LABEL[session.type]}</Text>
        </View>
        <Text className="mt-1 text-sm text-ink opacity-70">
          {isToday ? "" : `${relativeDay(session.planned_date, today)} · `}
          {describeLoad(session.planned_load)}
          {paceRange ? ` · ${paceRange}` : ""}
        </Text>

        <SessionCycleNote session={session} showBase />

        <Pressable
          onPress={() => router.push(`/training/${detail.id}`)}
          className="mt-3 flex-row items-center justify-between border-t border-ink/10 pt-3"
          accessibilityRole="button"
        >
          <Text className="text-xs text-ink opacity-50">
            {done}/{total} séances faites
          </Text>
          <Text className="text-xs font-semibold text-accent">Voir le plan ›</Text>
        </Pressable>
      </Card>
    </Pressable>
  );
}
