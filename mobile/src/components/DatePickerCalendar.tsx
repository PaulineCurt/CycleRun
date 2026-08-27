import { useMemo, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { getMonthGrid, toDateKey } from "@/lib/calendar";

const MONTH_LABEL_FORMAT = new Intl.DateTimeFormat("fr-FR", { month: "long", year: "numeric" });
const WEEKDAY_LABELS = ["L", "M", "M", "J", "V", "S", "D"];

export function DatePickerCalendar({
  selectedDate,
  onSelectDate,
  maxDate,
}: {
  selectedDate: Date | null;
  onSelectDate: (date: Date) => void;
  maxDate?: Date;
}) {
  const initial = selectedDate ?? new Date();
  const [viewedYear, setViewedYear] = useState(initial.getFullYear());
  const [viewedMonth, setViewedMonth] = useState(initial.getMonth());

  const weeks = useMemo(() => getMonthGrid(viewedYear, viewedMonth), [viewedYear, viewedMonth]);
  const selectedKey = selectedDate ? toDateKey(selectedDate) : null;
  const maxKey = maxDate ? toDateKey(maxDate) : null;

  function goToPreviousMonth() {
    const date = new Date(viewedYear, viewedMonth - 1, 1);
    setViewedYear(date.getFullYear());
    setViewedMonth(date.getMonth());
  }

  function goToNextMonth() {
    const date = new Date(viewedYear, viewedMonth + 1, 1);
    setViewedYear(date.getFullYear());
    setViewedMonth(date.getMonth());
  }

  return (
    <View className="w-full">
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
            const isSelected = dateKey === selectedKey;
            const isDisabled = maxKey != null && dateKey > maxKey;

            return (
              <Pressable
                key={dayIndex}
                onPress={() => !isDisabled && onSelectDate(date)}
                disabled={isDisabled}
                className="aspect-square flex-1 items-center justify-center"
                accessibilityRole="button"
                accessibilityLabel={date.toLocaleDateString("fr-FR", {
                  day: "numeric",
                  month: "long",
                })}
                accessibilityState={{ disabled: isDisabled, selected: isSelected }}
              >
                <View
                  className={`h-9 w-9 items-center justify-center rounded-full ${
                    isSelected ? "bg-accent" : "bg-ink/6"
                  }`}
                >
                  <Text
                    className={`text-xs font-semibold text-ink ${isDisabled ? "opacity-30" : ""}`}
                  >
                    {date.getDate()}
                  </Text>
                </View>
              </Pressable>
            );
          })}
        </View>
      ))}
    </View>
  );
}
