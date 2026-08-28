import { useCallback, useMemo, useRef, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useFocusEffect, useRouter } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import {
  getCycleCalendar,
  getDayLog,
  getProgram,
  getPrograms,
  type CalendarDay,
  type DayLog,
  type SessionType,
} from "@/lib/api";
import { toDateKey } from "@/lib/calendar";
import { PHASE_LABEL, PHASE_RING_CLASS } from "@/lib/phase";
import { CycleCalendarView } from "@/components/CycleCalendarView";
import { DayActionModal } from "@/components/DayActionModal";
import { DayLogSummary } from "@/components/DayLogSummary";
import { PageLoader } from "@/components/ui/brand-loader";
import { Screen } from "@/components/ui/screen";
import { COLOR } from "@/lib/colors";
import { SESSION_LABEL, pickActiveProgram, sessionAccent } from "@/lib/training";

const MONTH_LABEL_FORMAT = new Intl.DateTimeFormat("fr-FR", { month: "long", year: "numeric" });

function monthKey(year: number, month: number) {
  return `${year}-${String(month + 1).padStart(2, "0")}`;
}

type DaySession = { id: number; type: SessionType; postponed: boolean };

export default function CalendarScreen() {
  const router = useRouter();
  const today = new Date();
  const [viewedYear, setViewedYear] = useState(today.getFullYear());
  const [viewedMonth, setViewedMonth] = useState(today.getMonth());

  // Training sessions of the active/draft plan, keyed by date — overlaid as a dot
  // on the cycle grid so the two views line up. Best-effort: a failure here just
  // means no dots, the cycle calendar still works.
  const [trainingByDate, setTrainingByDate] = useState<Map<string, DaySession>>(new Map());

  const [calendarDays, setCalendarDays] = useState<CalendarDay[]>([]);
  const [calendarLoading, setCalendarLoading] = useState(true);
  const [calendarError, setCalendarError] = useState(false);
  const calendarCache = useRef(new Map<string, CalendarDay[]>());

  // Defaults to today (and resets to today on every focus, see below) so the info
  // panel — cycle day + today's logged symptoms — is visible immediately on landing,
  // with no tap required. It otherwise stays put across a modal open/close/save so
  // the panel keeps showing whatever day was tapped (and refreshes once new data
  // comes in) — only the modal's own visibility is transient.
  const [selectedDate, setSelectedDate] = useState<Date | null>(today);
  const [modalVisible, setModalVisible] = useState(false);

  const [dayLog, setDayLog] = useState<DayLog | null>(null);
  const [dayLogLoading, setDayLogLoading] = useState(true);
  // Flips true after the first day-log fetch settles (independently of whether that
  // day actually has a log) — used only to gate the initial full-page loader below,
  // so it waits for the calendar AND the day panel together instead of one popping
  // in a beat after the other.
  const [dayLogReady, setDayLogReady] = useState(false);

  const selectedDayInfo = useMemo(() => {
    if (!selectedDate) return undefined;
    const key = toDateKey(selectedDate);
    return calendarDays.find((day) => day.date === key);
  }, [selectedDate, calendarDays]);

  const sessionMarkers = useMemo(() => {
    const map = new Map<string, string>();
    for (const [date, session] of trainingByDate) {
      map.set(date, session.postponed ? COLOR.danger : sessionAccent(session.type));
    }
    return map;
  }, [trainingByDate]);

  const selectedSession = selectedDate ? trainingByDate.get(toDateKey(selectedDate)) : undefined;

  const loadTraining = useCallback(() => {
    getPrograms()
      .then(async (list) => {
        const chosen = pickActiveProgram(list);
        if (!chosen) {
          setTrainingByDate(new Map());
          return;
        }
        const program = await getProgram(chosen.id);
        setTrainingByDate(
          new Map(
            program.sessions.map((s) => [
              s.planned_date,
              { id: s.id, type: s.type, postponed: s.postponed },
            ]),
          ),
        );
      })
      .catch(() => setTrainingByDate(new Map()));
  }, []);

  const loadCalendar = useCallback((year: number, month: number) => {
    const key = monthKey(year, month);
    const cached = calendarCache.current.get(key);
    if (cached) {
      setCalendarDays(cached);
      setCalendarError(false);
      return;
    }

    setCalendarLoading(true);
    setCalendarError(false);
    getCycleCalendar(key)
      .then((data) => {
        calendarCache.current.set(key, data.days);
        setCalendarDays(data.days);
      })
      .catch(() => setCalendarError(true))
      .finally(() => setCalendarLoading(false));
  }, []);

  // Guards against out-of-order responses: if the user taps another day before this
  // one's request resolves, a late response for the earlier day must not overwrite
  // what's now on screen for the day actually selected.
  const dayLogRequestKey = useRef<string | null>(null);

  const loadDayLog = useCallback((dateKey: string) => {
    dayLogRequestKey.current = dateKey;
    setDayLogLoading(true);
    getDayLog(dateKey)
      .then((data) => {
        if (dayLogRequestKey.current === dateKey) setDayLog(data);
      })
      .catch(() => {
        if (dayLogRequestKey.current === dateKey) setDayLog(null);
      })
      .finally(() => {
        if (dayLogRequestKey.current !== dateKey) return;
        setDayLogLoading(false);
        setDayLogReady(true);
      });
  }, []);

  // Coming back to this tab always jumps back to the current month — browsing to a past
  // or future month is a one-visit detour, not a preference the tab should remember.
  // The cache itself is only invalidated after an actual edit (see handleDaySaved), so a
  // plain tab switch reuses what's already loaded instead of refetching every time.
  // The day-log refetches here too (not just on date change) — a symptom logged from
  // the Home tab (always "today") wouldn't otherwise show up until the selected date
  // changed and changed back, since this screen's state stays mounted across tabs.
  useFocusEffect(
    useCallback(() => {
      const now = new Date();
      setViewedYear(now.getFullYear());
      setViewedMonth(now.getMonth());
      setSelectedDate(now);
      loadCalendar(now.getFullYear(), now.getMonth());
      loadDayLog(toDateKey(now));
      loadTraining();
    }, [loadCalendar, loadDayLog, loadTraining]),
  );

  function goToPreviousMonth() {
    const date = new Date(viewedYear, viewedMonth - 1, 1);
    setViewedYear(date.getFullYear());
    setViewedMonth(date.getMonth());
    loadCalendar(date.getFullYear(), date.getMonth());
  }

  function goToNextMonth() {
    const date = new Date(viewedYear, viewedMonth + 1, 1);
    setViewedYear(date.getFullYear());
    setViewedMonth(date.getMonth());
    loadCalendar(date.getFullYear(), date.getMonth());
  }

  function handleDayPress(date: Date) {
    setSelectedDate(date);
    loadDayLog(toDateKey(date));
  }

  function handleDayDoubleTap(date: Date) {
    setSelectedDate(date);
    loadDayLog(toDateKey(date));
    setModalVisible(true);
  }

  function handleDaySaved() {
    calendarCache.current.clear();
    loadCalendar(viewedYear, viewedMonth);
    setModalVisible(false);
  }

  const selectedDateLabel = selectedDate
    ? selectedDate.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" })
    : null;

  // Only the very first load (nothing cached yet at all, and the day panel hasn't
  // resolved once either) blanks the whole page — switching months or days
  // afterwards just updates their own section in place, it shouldn't hide the
  // title/nav/hint text that are already known-good.
  if ((calendarLoading && calendarDays.length === 0) || !dayLogReady) {
    return (
      <View className="flex-1 bg-bg">
        <PageLoader />
      </View>
    );
  }

  return (
    <Screen className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
      <Text className="mb-6 text-3xl font-bold text-ink">Calendrier</Text>

      <View className="mb-3 flex-row items-center justify-between">
        <Pressable
          onPress={goToPreviousMonth}
          hitSlop={8}
          className="h-11 w-11 items-center justify-center"
          accessibilityRole="button"
          accessibilityLabel="Mois précédent"
        >
          <Text className="text-2xl text-ink">‹</Text>
        </Pressable>
        <Text className="text-base font-semibold capitalize text-ink">
          {MONTH_LABEL_FORMAT.format(new Date(viewedYear, viewedMonth, 1))}
        </Text>
        <Pressable
          onPress={goToNextMonth}
          hitSlop={8}
          className="h-11 w-11 items-center justify-center"
          accessibilityRole="button"
          accessibilityLabel="Mois suivant"
        >
          <Text className="text-2xl text-ink">›</Text>
        </Pressable>
      </View>

      <View style={{ opacity: calendarLoading ? 0.5 : 1 }}>
        <CycleCalendarView
          year={viewedYear}
          month={viewedMonth}
          days={calendarDays}
          sessionMarkers={sessionMarkers}
          onPressDay={(date) => handleDayPress(date)}
          onDoubleTapDay={(date) => handleDayDoubleTap(date)}
        />
      </View>

      {calendarError && <Text className="mt-4 text-danger">Impossible de charger ce mois.</Text>}

      <Text className="mt-3 text-center text-xs text-ink opacity-50">
        Double-tape un jour pour déclarer un début ou une fin de règles
      </Text>

      {selectedDate && (
        <View className="mt-6 rounded-2xl bg-surface p-4" style={{ opacity: dayLogLoading ? 0.5 : 1 }}>
          <Text className="mb-1 text-sm font-semibold capitalize text-ink">
            {selectedDateLabel}
          </Text>
          {selectedDayInfo?.cycle_day != null ? (
            <View className="flex-row items-center gap-2">
              {selectedDayInfo.phase && (
                <View
                  className={`h-2.5 w-2.5 rounded-full border-2 ${PHASE_RING_CLASS[selectedDayInfo.phase]}`}
                />
              )}
              <Text className="text-base text-ink opacity-80">
                Jour {selectedDayInfo.cycle_day} du cycle
                {selectedDayInfo.phase ? ` · ${PHASE_LABEL[selectedDayInfo.phase]}` : ""}
              </Text>
            </View>
          ) : (
            <Text className="text-base text-ink opacity-60">
              Aucune donnée de cycle pour ce jour.
            </Text>
          )}
          {selectedSession && (
            <Pressable
              onPress={() => router.push(`/training/session/${selectedSession.id}`)}
              className="mt-3 flex-row items-center gap-2 border-t border-ink/10 pt-3"
              accessibilityRole="button"
            >
              <View
                className="h-2.5 w-2.5 rounded-full"
                style={{
                  backgroundColor: selectedSession.postponed
                    ? COLOR.danger
                    : sessionAccent(selectedSession.type),
                }}
              />
              <Text className="flex-1 text-sm text-ink opacity-80">
                Séance : {SESSION_LABEL[selectedSession.type]}
                {selectedSession.postponed ? " · à reporter" : ""}
              </Text>
              <Ionicons name="chevron-forward" size={16} color={COLOR.ink} />
            </Pressable>
          )}

          <DayLogSummary log={dayLog} />
        </View>
      )}

      {modalVisible && selectedDate && (
        <DayActionModal
          date={selectedDate}
          day={selectedDayInfo}
          onClose={() => setModalVisible(false)}
          onSaved={handleDaySaved}
        />
      )}
    </Screen>
  );
}
