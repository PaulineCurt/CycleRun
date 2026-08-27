import { useEffect, useRef, useState, type ComponentProps } from "react";
import { ActivityIndicator, Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { BottomSheetModal, BottomSheetScrollView } from "@gorhom/bottom-sheet";
import { getDayLog, upsertDayLog, type SymptomMap } from "@/lib/api";
import { toDateKey } from "@/lib/calendar";
import { INTENSITY_LABEL, SYMPTOM_CATEGORIES } from "@/lib/symptoms";
import { CardHeader } from "@/components/ui/card-header";
import { CategoryIcon } from "@/components/ui/category-icon";
import { PageLoader } from "@/components/ui/brand-loader";
import { SaveToast, type SaveToastHandle } from "@/components/ui/save-toast";
import { Sheet } from "@/components/ui/sheet";
import { COLOR } from "@/lib/colors";

const BADGE = COLOR.badge;
const ENERGY_OPTIONS = [1, 2, 3, 4, 5];
const PAIN_OPTIONS = [0, 1, 2, 3];
const INTENSITY_OPTIONS = [0, 1, 2, 3];
const SNAP_POINTS = ["85%"];

type Pending = { energy: number | null; pain: number | null; symptoms: SymptomMap };

/**
 * Bottom sheet for the day's full feeling check-in: energy, general pain, and
 * symptoms grouped by body area, each rated 0-3. Selections are staged locally —
 * nothing is written until "Terminé" is pressed, which does a single save (the
 * button itself becomes the loading indicator while it's in flight). Built on
 * @gorhom/bottom-sheet rather than RN's own <Modal> — it renders through a portal
 * above the tab bar reliably (a hand-rolled Modal/PanResponder combo kept failing
 * on native, likely due to react-native-screens containing the Modal inside the
 * tab's own screen), and its scroll view natively cooperates with the
 * drag-to-close gesture instead of fighting it.
 */
export function DayFeelingSheet({
  onClose,
  onSaved,
}: {
  onClose: () => void;
  /** Fired right after a successful save (before the sheet auto-dismisses). */
  onSaved?: () => void;
}) {
  const today = toDateKey(new Date());
  const [loading, setLoading] = useState(true);
  const [pending, setPending] = useState<Pending>({ energy: null, pain: null, symptoms: {} });
  const [saving, setSaving] = useState(false);
  const sheetRef = useRef<BottomSheetModal>(null);
  const saveToastRef = useRef<SaveToastHandle>(null);

  useEffect(() => {
    getDayLog(today)
      .then((data) => {
        setPending({
          energy: data?.energy ?? null,
          pain: data?.pain ?? null,
          symptoms: data?.symptoms ?? {},
        });
      })
      .finally(() => setLoading(false));
  }, [today]);

  function setEnergy(value: number) {
    setPending((current) => ({ ...current, energy: value }));
  }

  function setPain(value: number) {
    setPending((current) => ({ ...current, pain: value }));
  }

  function setSymptom(key: string, value: number) {
    setPending((current) => ({ ...current, symptoms: { ...current.symptoms, [key]: value } }));
  }

  async function handleFinish() {
    setSaving(true);
    saveToastRef.current?.showSaving();
    try {
      await upsertDayLog(today, pending);
      onSaved?.();
      saveToastRef.current?.showSuccess();
      // Let the confirmation flash briefly before the sheet closes itself.
      setTimeout(() => sheetRef.current?.dismiss(), 500);
    } catch {
      saveToastRef.current?.showError();
    } finally {
      setSaving(false);
    }
  }

  return (
    <Sheet ref={sheetRef} snapPoints={SNAP_POINTS} enableDynamicSizing={false} onDismiss={onClose}>
      <BottomSheetScrollView
        contentContainerStyle={{ paddingHorizontal: 24, paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}
      >
        {loading ? (
          <PageLoader />
        ) : (
          <>
            <View className="mb-4">
              <CardHeader icon="happy-outline" color={BADGE} label="Comment tu te sens aujourd'hui ?" />
            </View>
            <PillRow
              label="Énergie"
              icon="flash-outline"
              options={ENERGY_OPTIONS.map((value) => ({
                value,
                label: String(value),
                selected: pending.energy === value,
              }))}
              onSelect={(value) => setEnergy(value)}
            />

            <PillRow
              label="Douleur générale"
              icon="bandage-outline"
              options={PAIN_OPTIONS.map((value) => ({
                value,
                label: String(value),
                selected: pending.pain === value,
              }))}
              onSelect={(value) => setPain(value)}
            />

            {SYMPTOM_CATEGORIES.map((category) => (
              <View key={category.label} className="mb-5">
                <View className="mb-2 flex-row items-center">
                  <CategoryIcon
                    icon={category.icon}
                    size={16}
                    color={COLOR.ink}
                    style={{ opacity: 0.7, marginRight: 6 }}
                  />
                  <Text className="text-sm font-semibold text-ink opacity-70">{category.label}</Text>
                </View>
                {category.symptoms.map((symptom) => (
                  <View key={symptom.key} className="mb-3">
                    <Text className="mb-1.5 text-sm text-ink">{symptom.label}</Text>
                    <PillRow
                      options={INTENSITY_OPTIONS.map((value) => ({
                        value,
                        label: INTENSITY_LABEL[value],
                        selected: pending.symptoms[symptom.key] === value,
                      }))}
                      onSelect={(value) => setSymptom(symptom.key, value)}
                      compact
                    />
                  </View>
                ))}
              </View>
            ))}

            <Pressable
              onPress={handleFinish}
              disabled={saving}
              className={`mt-2 h-11 items-center justify-center rounded-lg bg-accent ${saving ? "opacity-70" : ""}`}
              accessibilityRole="button"
            >
              {saving ? (
                <ActivityIndicator color={COLOR.ink} />
              ) : (
                <Text className="font-semibold text-ink">Terminé</Text>
              )}
            </Pressable>
          </>
        )}
      </BottomSheetScrollView>
      <SaveToast ref={saveToastRef} />
    </Sheet>
  );
}

function PillRow({
  label,
  icon,
  options,
  onSelect,
  compact,
}: {
  label?: string;
  icon?: ComponentProps<typeof Ionicons>["name"];
  options: { value: number; label: string; selected: boolean }[];
  onSelect: (value: number) => void;
  compact?: boolean;
}) {
  return (
    <View className={label ? "mb-4" : ""}>
      {label && (
        <View className="mb-2 flex-row items-center">
          {icon && (
            <Ionicons name={icon} size={16} color={COLOR.ink} style={{ opacity: 0.7, marginRight: 6 }} />
          )}
          <Text className="text-sm font-semibold text-ink opacity-70">{label}</Text>
        </View>
      )}
      {/* Margin-based spacing rather than the `gap` utility: on web, NativeWind's gap
          polyfill can push this row's measured width past its container, which turns
          it into an unwanted horizontally-scrollable strip that steals wheel/trackpad
          scroll away from the sheet's outer ScrollView. */}
      <View className="-mb-2 -mr-2 flex-row flex-wrap">
        {options.map((option) => (
          <Pressable
            key={option.value}
            onPress={() => onSelect(option.value)}
            accessibilityRole="button"
            accessibilityState={{ selected: option.selected }}
            className={`mb-2 mr-2 items-center justify-center rounded-full ${compact ? "h-8 px-3" : "h-9 min-w-9 px-3"} ${
              option.selected ? "bg-accent" : "bg-ink/6"
            }`}
          >
            <Text className={`font-semibold text-ink ${compact ? "text-[11px]" : "text-xs"}`}>
              {option.label}
            </Text>
          </Pressable>
        ))}
      </View>
    </View>
  );
}
