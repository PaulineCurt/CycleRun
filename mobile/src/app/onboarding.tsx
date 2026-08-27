import { useState } from "react";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";
import { useRouter } from "expo-router";
import { createCycle, updateProfile } from "@/lib/api";
import { toDateKey } from "@/lib/calendar";
import { DatePickerCalendar } from "@/components/DatePickerCalendar";
import { ChoiceStep, ConfirmStep, WizardProgress } from "@/components/WizardStep";

type Frequency = "regular" | "irregular" | "unknown";
const STEPS = ["frequency", "last_period", "menses_length"] as const;
type Step = (typeof STEPS)[number];

const REASSURANCE: Record<Frequency, string> = {
  regular: "",
  irregular:
    "Pas de souci — les prédictions s'adapteront et resteront honnêtes sur leur marge d'erreur.",
  unknown: "Pas de souci, on apprendra au fur et à mesure de tes cycles logués.",
};

export default function Onboarding() {
  const router = useRouter();
  const [step, setStep] = useState<Step>("frequency");
  // Purely local — orients the copy on the next screens. Real regularity is always
  // recalculated from logged cycles (FIGO amplitude), never a stored self-report.
  const [frequency, setFrequency] = useState<Frequency>("unknown");

  const [lastPeriodDate, setLastPeriodDate] = useState<Date | null>(null);
  const [savingCycle, setSavingCycle] = useState(false);
  const [cycleError, setCycleError] = useState<string | null>(null);

  const [mensesLength, setMensesLength] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  function finish() {
    router.replace("/");
  }

  function goBack() {
    const currentIndex = STEPS.indexOf(step);
    setStep(STEPS[Math.max(0, currentIndex - 1)]);
  }

  async function handleLastPeriodContinue() {
    setCycleError(null);

    if (lastPeriodDate === null) {
      setStep("menses_length");
      return;
    }

    setSavingCycle(true);
    try {
      await createCycle(toDateKey(lastPeriodDate));
      setStep("menses_length");
    } catch {
      setCycleError("Impossible d'enregistrer cette date. Réessaie, ou passe cette étape.");
    } finally {
      setSavingCycle(false);
    }
  }

  async function handleFinish() {
    setSaveError(null);
    const trimmed = mensesLength.trim();

    if (trimmed === "") {
      finish();
      return;
    }

    const parsed = Number(trimmed);
    if (!Number.isFinite(parsed) || parsed <= 0) {
      setSaveError("Entre un nombre de jours valide, ou laisse le champ vide.");
      return;
    }

    setSaving(true);
    try {
      await updateProfile({ typical_menses_length: parsed });
      finish();
    } catch {
      setSaveError("Impossible d'enregistrer. Réessaie, ou passe cette étape.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <ScrollView className="flex-1 bg-bg" contentContainerClassName="flex-grow px-6 pb-8 pt-16">
      <View className="flex-1">
        {step !== "frequency" ? (
          <Pressable onPress={goBack} className="mb-2 h-11 justify-center">
            <Text className="text-ink opacity-70">‹ Retour</Text>
          </Pressable>
        ) : (
          <View className="mb-2 h-11" />
        )}

        <WizardProgress current={STEPS.indexOf(step)} total={STEPS.length} />

        {step === "frequency" && (
          <ChoiceStep
            title="Comment sont tes règles ?"
            description="Ça nous aide juste à adapter ce qu'on t'affiche ensuite."
            footerLabel="Passer"
            onFooter={() => setStep("last_period")}
          >
            {(
              [
                ["regular", "Régulières"],
                ["irregular", "Irrégulières ou imprévisibles"],
                ["unknown", "Je ne sais pas"],
              ] as [Frequency, string][]
            ).map(([value, label]) => (
              <Pressable
                key={value}
                onPress={() => {
                  setFrequency(value);
                  setStep("last_period");
                }}
                className="h-11 items-center justify-center rounded-lg bg-surface"
                accessibilityRole="button"
              >
                <Text className="font-semibold text-ink">{label}</Text>
              </Pressable>
            ))}
          </ChoiceStep>
        )}

        {step === "last_period" && (
          <ConfirmStep
            title="Quand ont commencé tes dernières règles ?"
            description={REASSURANCE[frequency] || undefined}
            primaryLabel={savingCycle ? "..." : "Continuer"}
            primaryDisabled={savingCycle || lastPeriodDate === null}
            onPrimary={handleLastPeriodContinue}
            secondaryLabel="Je ne sais pas / passer"
            onSecondary={() => setStep("menses_length")}
          >
            <DatePickerCalendar
              selectedDate={lastPeriodDate}
              onSelectDate={setLastPeriodDate}
              maxDate={new Date()}
            />
            {cycleError && <Text className="mt-2 text-danger">{cycleError}</Text>}
          </ConfirmStep>
        )}

        {step === "menses_length" && (
          <ConfirmStep
            title="Combien de jours durent tes règles d'habitude ?"
            description="Laisse vide si tu ne sais pas — on le calculera à partir de tes cycles logués."
            primaryLabel={saving ? "..." : "Terminer"}
            primaryDisabled={saving}
            onPrimary={handleFinish}
          >
            <TextInput
              value={mensesLength}
              onChangeText={setMensesLength}
              placeholder="Ex. 5"
              placeholderTextColor="#12302B73"
              keyboardType="number-pad"
              className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
            />
            {saveError && <Text className="mt-2 text-danger">{saveError}</Text>}
          </ConfirmStep>
        )}
      </View>
    </ScrollView>
  );
}
