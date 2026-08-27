import { useMemo, useRef } from "react";
import { Pressable, Text, View } from "react-native";
import { getMonthGrid, toDateKey } from "@/lib/calendar";
import type { CalendarDay } from "@/lib/api";
import { PHASE_LABEL, PHASE_RING_CLASS } from "@/lib/phase";

const WEEKDAY_LABELS = ["L", "M", "M", "J", "V", "S", "D"];
const DOUBLE_TAP_DELAY_MS = 300;

function accessibilityLabelFor(date: Date, info: CalendarDay | undefined, isToday: boolean) {
  const dateLabel = date.toLocaleDateString("fr-FR", { day: "numeric", month: "long" });
  const parts = [dateLabel];
  if (isToday) parts.push("aujourd'hui");
  if (info?.phase) parts.push(`phase ${PHASE_LABEL[info.phase].toLowerCase()}`);
  if (info?.is_projected) parts.push("estimation");
  parts.push("appuyer pour voir le détail, double-appuyer pour déclarer un début ou une fin de règles");
  return parts.join(", ");
}

export function CycleCalendarView({
  year,
  month,
  days,
  onPressDay,
  onDoubleTapDay,
}: {
  year: number;
  month: number;
  days: CalendarDay[];
  onPressDay: (date: Date, day: CalendarDay | undefined) => void;
  onDoubleTapDay: (date: Date, day: CalendarDay | undefined) => void;
}) {
  const weeks = useMemo(() => getMonthGrid(year, month), [year, month]);
  const byDate = useMemo(() => new Map(days.map((day) => [day.date, day])), [days]);
  const today = toDateKey(new Date());
  const lastTap = useRef<{ key: string; time: number } | null>(null);

  function handlePress(date: Date, dateKey: string, info: CalendarDay | undefined) {
    const now = Date.now();
    const previous = lastTap.current;

    if (previous && previous.key === dateKey && now - previous.time < DOUBLE_TAP_DELAY_MS) {
      lastTap.current = null;
      onDoubleTapDay(date, info);
      return;
    }

    lastTap.current = { key: dateKey, time: now };
    onPressDay(date, info);
  }

  return (
    <View className="w-full">
      <View className="mb-2 flex-row">
        {WEEKDAY_LABELS.map((label, index) => (
          <Text
            key={index}
            className="flex-1 text-center text-xs text-ink opacity-50"
            accessibilityElementsHidden
            importantForAccessibility="no"
          >
            {label}
          </Text>
        ))}
      </View>

      {weeks.map((week, weekIndex) => (
        <View key={weekIndex} className="mb-1 flex-row">
          {week.map((date, dayIndex) => {
            if (!date) return <View key={dayIndex} className="aspect-square flex-1" />;

            const dateKey = toDateKey(date);
            const info = byDate.get(dateKey);
            const isToday = dateKey === today;
            // Only the projected *next* period is a guess worth flagging — the current
            // cycle's own follicular/ovulation/luteal estimate renders solid like before.
            const isProjected = !!info?.is_projected;

            return (
              <Pressable
                key={dayIndex}
                onPress={() => handlePress(date, dateKey, info)}
                className="aspect-square flex-1 items-center justify-center"
                accessibilityRole="button"
                accessibilityLabel={accessibilityLabelFor(date, info, isToday)}
              >
                <View
                  className={`relative h-9 w-9 items-center justify-center rounded-full bg-ink/6 ${
                    info?.phase ? `border-2 ${PHASE_RING_CLASS[info.phase]}` : ""
                  } ${isProjected ? "border-dashed opacity-50" : ""}`}
                >
                  <Text className="text-xs font-semibold text-ink">{date.getDate()}</Text>
                  {isToday && (
                    <View className="absolute -bottom-1 h-1.5 w-1.5 rounded-full bg-accent" />
                  )}
                </View>
              </Pressable>
            );
          })}
        </View>
      ))}
    </View>
  );
}
