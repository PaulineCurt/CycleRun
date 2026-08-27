import { useState } from "react";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";
import { useRouter } from "expo-router";
import { createProgram, type LevelInput } from "@/lib/api";
import { toDateKey } from "@/lib/calendar";
import { DatePickerCalendar } from "@/components/DatePickerCalendar";
import { ChoiceStep, ConfirmStep, WizardProgress } from "@/components/WizardStep";

const STEPS = ["level", "cadence", "duration", "start", "goal"] as const;
type Step = (typeof STEPS)[number];

type Method = "vma" | "race";
const RACE_DISTANCES: [string, number][] = [
  ["2 km", 2000],
  ["5 km", 5000],
  ["10 km", 10000],
];

/** "25:30" or "25" -> seconds. Returns null on anything unparseable. */
function parseClock(input: string): number | null {
  const trimmed = input.trim();
  if (trimmed === "") return null;
  const parts = trimmed.split(":").map((p) => Number(p));
  if (parts.some((p) => !Number.isFinite(p) || p < 0)) return null;
  if (parts.length === 1) return Math.round(parts[0] * 60);
  if (parts.length === 2) return Math.round(parts[0] * 60 + parts[1]);
  return null;
}

export default function NewProgram() {
  const router = useRouter();
  const [step, setStep] = useState<Step>("level");

  const [method, setMethod] = useState<Method>("vma");
  const [vma, setVma] = useState("");
  const [raceDistance, setRaceDistance] = useState(5000);
  const [raceTime, setRaceTime] = useState("");
  const [levelError, setLevelError] = useState<string | null>(null);

  const [daysPerWeek, setDaysPerWeek] = useState(3);
  const [durationWeeks, setDurationWeeks] = useState(12);
  const [startDate, setStartDate] = useState<Date | null>(null);
  const [goalTime, setGoalTime] = useState("");

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  function goBack() {
    const i = STEPS.indexOf(step);
    setStep(STEPS[Math.max(0, i - 1)]);
  }

  function buildLevelInput(): LevelInput | null {
    if (method === "vma") {
      const value = Number(vma.trim().replace(",", "."));
      if (!Number.isFinite(value) || value < 6 || value > 25) return null;
      return { kind: "field_test", vma_kmh: value };
    }
    const seconds = parseClock(raceTime);
    if (seconds === null || seconds <= 0) return null;
    return { kind: "race_result", distance_m: raceDistance, time_s: seconds };
  }

  function handleLevelContinue() {
    setLevelError(null);
    if (buildLevelInput() === null) {
      setLevelError(
        method === "vma"
          ? "Entre une VMA plausible (entre 6 et 25 km/h)."
          : "Entre un temps valide, au format mm:ss.",
      );
      return;
    }
    setStep("cadence");
  }

  async function handleFinish({ skipGoal = false } = {}) {
    setSubmitError(null);
    const levelInput = buildLevelInput();
    if (levelInput === null || startDate === null) {
      setSubmitError("Il manque une information pour générer le plan.");
      return;
    }

    const goalSeconds = skipGoal ? null : parseClock(goalTime);
    setSubmitting(true);
    try {
      const program = await createProgram({
        level_input: levelInput,
        goal_time_s: goalSeconds ?? undefined,
        duration_weeks: durationWeeks,
        days_per_week: daysPerWeek,
        start_date: toDateKey(startDate),
      });
      router.replace(`/training/${program.id}`);
    } catch {
      setSubmitError("La génération a échoué. Vérifie tes réponses et réessaie.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <ScrollView className="flex-1 bg-bg" contentContainerClassName="flex-grow px-6 pb-8 pt-16">
      <View className="flex-1">
        {step !== "level" ? (
          <Pressable onPress={goBack} className="mb-2 h-11 justify-center">
            <Text className="text-ink opacity-70">‹ Retour</Text>
          </Pressable>
        ) : (
          <Pressable onPress={() => router.back()} className="mb-2 h-11 justify-center">
            <Text className="text-ink opacity-70">‹ Annuler</Text>
          </Pressable>
        )}

        <WizardProgress current={STEPS.indexOf(step)} total={STEPS.length} />

        {step === "level" && (
          <ConfirmStep
            title="Quel est ton niveau actuel ?"
            description="On en déduit tes allures d'entraînement. Choisis ce que tu connais."
            primaryLabel="Continuer"
            onPrimary={handleLevelContinue}
          >
            <View className="mb-4 flex-row gap-2">
              {(
                [
                  ["vma", "Ma VMA"],
                  ["race", "Un temps de course"],
                ] as [Method, string][]
              ).map(([value, label]) => (
                <Pressable
                  key={value}
                  onPress={() => setMethod(value)}
                  className={`h-11 flex-1 items-center justify-center rounded-lg ${
                    method === value ? "bg-accent" : "bg-surface"
                  }`}
                  accessibilityRole="button"
                >
                  <Text className="font-semibold text-ink">{label}</Text>
                </Pressable>
              ))}
            </View>

            {method === "vma" ? (
              <TextInput
                value={vma}
                onChangeText={setVma}
                placeholder="VMA en km/h — ex. 13.5"
                placeholderTextColor="#12302B73"
                keyboardType="decimal-pad"
                className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
              />
            ) : (
              <View className="gap-3">
                <View className="flex-row gap-2">
                  {RACE_DISTANCES.map(([label, meters]) => (
                    <Pressable
                      key={meters}
                      onPress={() => setRaceDistance(meters)}
                      className={`h-11 flex-1 items-center justify-center rounded-lg ${
                        raceDistance === meters ? "bg-accent" : "bg-surface"
                      }`}
                      accessibilityRole="button"
                    >
                      <Text className="font-semibold text-ink">{label}</Text>
                    </Pressable>
                  ))}
                </View>
                <TextInput
                  value={raceTime}
                  onChangeText={setRaceTime}
                  placeholder="Temps — ex. 27:30"
                  placeholderTextColor="#12302B73"
                  className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
                />
              </View>
            )}
            {levelError && <Text className="mt-2 text-danger">{levelError}</Text>}
          </ConfirmStep>
        )}

        {step === "cadence" && (
          <ChoiceStep
            title="Combien de séances par semaine ?"
            description="Sois réaliste sur ce que tu peux tenir sur toute la durée du plan."
          >
            {[2, 3, 4].map((n) => (
              <Pressable
                key={n}
                onPress={() => {
                  setDaysPerWeek(n);
                  setStep("duration");
                }}
                className="h-11 items-center justify-center rounded-lg bg-surface"
                accessibilityRole="button"
              >
                <Text className="font-semibold text-ink">{n} séances</Text>
              </Pressable>
            ))}
          </ChoiceStep>
        )}

        {step === "duration" && (
          <ChoiceStep
            title="Sur combien de semaines ?"
            description="8 à 12 semaines conviennent à la plupart des objectifs 5 km."
          >
            {[8, 10, 12, 16].map((n) => (
              <Pressable
                key={n}
                onPress={() => {
                  setDurationWeeks(n);
                  setStep("start");
                }}
                className="h-11 items-center justify-center rounded-lg bg-surface"
                accessibilityRole="button"
              >
                <Text className="font-semibold text-ink">{n} semaines</Text>
              </Pressable>
            ))}
          </ChoiceStep>
        )}

        {step === "start" && (
          <ConfirmStep
            title="Quand veux-tu commencer ?"
            primaryLabel="Continuer"
            primaryDisabled={startDate === null}
            onPrimary={() => setStep("goal")}
          >
            <DatePickerCalendar selectedDate={startDate} onSelectDate={setStartDate} />
          </ConfirmStep>
        )}

        {step === "goal" && (
          <ConfirmStep
            title="Un objectif de temps ?"
            description="Optionnel. On vérifie qu'il est réaliste et on te le dit franchement sinon."
            primaryLabel={submitting ? "Génération…" : "Générer mon plan"}
            primaryDisabled={submitting}
            onPrimary={() => handleFinish()}
            secondaryLabel="Sans objectif de temps"
            onSecondary={() => handleFinish({ skipGoal: true })}
          >
            <TextInput
              value={goalTime}
              onChangeText={setGoalTime}
              placeholder="Temps visé sur 5 km — ex. 26:00"
              placeholderTextColor="#12302B73"
              className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
            />
            {submitError && <Text className="mt-2 text-danger">{submitError}</Text>}
          </ConfirmStep>
        )}
      </View>
    </ScrollView>
  );
}
