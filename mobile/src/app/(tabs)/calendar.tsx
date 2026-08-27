import { useCallback, useMemo, useRef, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import { useFocusEffect } from "expo-router";
import { getCycleCalendar, type CalendarDay } from "@/lib/api";
import { toDateKey } from "@/lib/calendar";
import { PHASE_LABEL, PHASE_RING_CLASS } from "@/lib/phase";
import { CycleCalendarView } from "@/components/CycleCalendarView";
import { DayActionModal } from "@/components/DayActionModal";

const MONTH_LABEL_FORMAT = new Intl.DateTimeFormat("fr-FR", { month: "long", year: "numeric" });

function monthKey(year: number, month: number) {
  return `${year}-${String(month + 1).padStart(2, "0")}`;
}

export default function CalendarScreen() {
  const today = new Date();
  const [viewedYear, setViewedYear] = useState(today.getFullYear());
  const [viewedMonth, setViewedMonth] = useState(today.getMonth());

  const [calendarDays, setCalendarDays] = useState<CalendarDay[]>([]);
  const [calendarLoading, setCalendarLoading] = useState(true);
  const [calendarError, setCalendarError] = useState(false);
  const calendarCache = useRef(new Map<string, CalendarDay[]>());

  // The selected date stays put across a modal open/close/save so the info panel below the
  // calendar keeps showing the tapped day (and refreshes once new data comes in) — only the
  // modal's own visibility is transient.
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [modalVisible, setModalVisible] = useState(false);

  const selectedDayInfo = useMemo(() => {
    if (!selectedDate) return undefined;
    const key = toDateKey(selectedDate);
    return calendarDays.find((day) => day.date === key);
  }, [selectedDate, calendarDays]);

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

  // Coming back to this tab always jumps back to the current month — browsing to a past
  // or future month is a one-visit detour, not a preference the tab should remember.
  // The cache itself is only invalidated after an actual edit (see handleDaySaved), so a
  // plain tab switch reuses what's already loaded instead of refetching every time.
  useFocusEffect(
    useCallback(() => {
      const now = new Date();
      setViewedYear(now.getFullYear());
      setViewedMonth(now.getMonth());
      loadCalendar(now.getFullYear(), now.getMonth());
    }, [loadCalendar]),
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
  }

  function handleDayDoubleTap(date: Date) {
    setSelectedDate(date);
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

  return (
    <ScrollView className="flex-1 bg-bg" contentContainerClassName="px-6 pb-10 pt-16">
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

      {calendarLoading && calendarDays.length === 0 ? (
        <ActivityIndicator color="#12302B" className="my-8" />
      ) : (
        <View style={{ opacity: calendarLoading ? 0.5 : 1 }}>
          <CycleCalendarView
            year={viewedYear}
            month={viewedMonth}
            days={calendarDays}
            onPressDay={(date) => handleDayPress(date)}
            onDoubleTapDay={(date) => handleDayDoubleTap(date)}
          />
        </View>
      )}

      {calendarError && <Text className="mt-4 text-danger">Impossible de charger ce mois.</Text>}

      <Text className="mt-3 text-center text-xs text-ink opacity-50">
        Double-tape un jour pour déclarer un début ou une fin de règles
      </Text>

      {selectedDate && (
        <View className="mt-6 rounded-2xl bg-surface p-4">
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
    </ScrollView>
  );
}
