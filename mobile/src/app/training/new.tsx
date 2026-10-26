import { useState } from "react";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";
import { useRouter } from "expo-router";
import { createProgram, type Level, type LevelInput } from "@/lib/api";
import { toDateKey } from "@/lib/calendar";
import { checkLevelCoherence } from "@/lib/levelCoherence";
import { LEVEL_LABEL } from "@/lib/training";
import { DatePickerCalendar } from "@/components/DatePickerCalendar";
import { ChoiceStep, ConfirmStep, WizardProgress } from "@/components/WizardStep";

// A débutante ends stage 1 with no pace test; everyone else goes through stage 2
// (cran + optional pace anchor). Wording is a placeholder — produit à finaliser.
const STEPS_BEGINNER = ["experience", "cadence", "duration", "start", "goal"] as const;
const STEPS_EXPERIENCED = ["experience", "cadence", "stage2", "duration", "start", "goal"] as const;
type Step = (typeof STEPS_EXPERIENCED)[number];

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
  const [step, setStep] = useState<Step>("experience");

  const [level, setLevel] = useState<Level>("intermediaire");
  const [method, setMethod] = useState<Method>("vma");
  const [vma, setVma] = useState("");
  const [raceDistance, setRaceDistance] = useState(5000);
  const [raceTime, setRaceTime] = useState("");
  const [coherenceDismissed, setCoherenceDismissed] = useState(false);

  const [daysPerWeek, setDaysPerWeek] = useState(3);
  const [durationWeeks, setDurationWeeks] = useState(12);
  const [startDate, setStartDate] = useState<Date | null>(null);
  const [goalTime, setGoalTime] = useState("");

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const steps = level === "debutante" ? STEPS_BEGINNER : STEPS_EXPERIENCED;

  function goBack() {
    const i = (steps as readonly string[]).indexOf(step);
    setStep(steps[Math.max(0, i - 1)] as Step);
  }

  /** Optional now: a débutante — or anyone skipping the anchor — has no level_input. */
  function buildLevelInput(): LevelInput | null {
    if (level === "debutante") return null;
    if (method === "vma") {
      const value = Number(vma.trim().replace(",", "."));
      if (!Number.isFinite(value) || value < 6 || value > 25) return null;
      return { kind: "field_test", vma_kmh: value };
    }
    const seconds = parseClock(raceTime);
    if (seconds === null || seconds <= 0) return null;
    return { kind: "race_result", distance_m: raceDistance, time_s: seconds };
  }

  const anchorVma =
    method === "vma" ? Number(vma.trim().replace(",", ".")) : NaN;
  const coherence = coherenceDismissed
    ? null
    : checkLevelCoherence(level, Number.isFinite(anchorVma) ? anchorVma : null, daysPerWeek);

  function chooseExperience(next: Level) {
    setLevel(next);
    setCoherenceDismissed(false);
    setStep("cadence");
  }

  async function handleFinish({ skipGoal = false } = {}) {
    setSubmitError(null);
    if (startDate === null) {
      setSubmitError("Il manque une information pour générer le plan.");
      return;
    }

    const levelInput = buildLevelInput();
    const goalSeconds = skipGoal ? null : parseClock(goalTime);
    setSubmitting(true);
    try {
      const program = await createProgram({
        level,
        level_input: levelInput ?? undefined,
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
        {step !== "experience" ? (
          <Pressable onPress={goBack} className="mb-2 h-11 justify-center">
            <Text className="text-ink opacity-70">‹ Retour</Text>
          </Pressable>
        ) : (
          <Pressable onPress={() => router.back()} className="mb-2 h-11 justify-center">
            <Text className="text-ink opacity-70">‹ Annuler</Text>
          </Pressable>
        )}

        <WizardProgress current={(steps as readonly string[]).indexOf(step)} total={steps.length} />

        {step === "experience" && (
          <ChoiceStep
            title="Tu cours en ce moment ?"
            description="On adapte le point de départ du plan à ton niveau réel."
          >
            {(
              [
                ["debutante", "Je débute", "Pas ou peu de course ces derniers mois"],
                ["intermediaire", "Un peu", "Quelques sorties par semaine, sans plan"],
                ["confirmee", "Régulièrement", "Entraînement suivi, plusieurs séances/semaine"],
              ] as [Level, string, string][]
            ).map(([value, label, hint]) => (
              <Pressable
                key={value}
                onPress={() => chooseExperience(value)}
                className="rounded-lg bg-surface px-4 py-3"
                accessibilityRole="button"
              >
                <Text className="font-semibold text-ink">{label}</Text>
                <Text className="mt-0.5 text-xs text-ink opacity-60">{hint}</Text>
              </Pressable>
            ))}
          </ChoiceStep>
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
                  setStep(level === "debutante" ? "duration" : "stage2");
                }}
                className="h-11 items-center justify-center rounded-lg bg-surface"
                accessibilityRole="button"
              >
                <Text className="font-semibold text-ink">{n} séances</Text>
              </Pressable>
            ))}
          </ChoiceStep>
        )}

        {step === "stage2" && (
          <ConfirmStep
            title="Précise ton niveau"
            description="Choisis ton cran, et ajoute un repère d'allure si tu en as un (facultatif)."
            primaryLabel="Continuer"
            onPrimary={() => setStep("duration")}
            secondaryLabel="Sans repère d'allure"
            onSecondary={() => {
              setVma("");
              setRaceTime("");
              setStep("duration");
            }}
          >
            <View className="mb-4 flex-row gap-2">
              {(
                [
                  ["intermediaire", LEVEL_LABEL.intermediaire],
                  ["confirmee", LEVEL_LABEL.confirmee],
                ] as [Level, string][]
              ).map(([value, label]) => (
                <Pressable
                  key={value}
                  onPress={() => {
                    setLevel(value);
                    setCoherenceDismissed(false);
                  }}
                  className={`h-11 flex-1 items-center justify-center rounded-lg ${
                    level === value ? "bg-accent" : "bg-surface"
                  }`}
                  accessibilityRole="button"
                >
                  <Text className="font-semibold text-ink">{label}</Text>
                </Pressable>
              ))}
            </View>

            <View className="mb-3 flex-row gap-2">
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

            {coherence && (
              <View className="mt-4 rounded-2xl bg-surface p-4">
                <Text className="text-sm text-ink opacity-80">{coherence.message}</Text>
                <View className="mt-3 flex-row gap-2">
                  <Pressable
                    onPress={() => {
                      setLevel(coherence.suggested);
                      setCoherenceDismissed(true);
                    }}
                    className="h-10 flex-1 items-center justify-center rounded-lg bg-accent"
                    accessibilityRole="button"
                  >
                    <Text className="text-sm font-semibold text-ink">
                      Passer en {LEVEL_LABEL[coherence.suggested].toLowerCase()}
                    </Text>
                  </Pressable>
                  <Pressable
                    onPress={() => setCoherenceDismissed(true)}
                    className="h-10 flex-1 items-center justify-center rounded-lg bg-ink/6"
                    accessibilityRole="button"
                  >
                    <Text className="text-sm text-ink opacity-70">Garder mon choix</Text>
                  </Pressable>
                </View>
              </View>
            )}
          </ConfirmStep>
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
