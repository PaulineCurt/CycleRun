import { useMemo, useState } from "react";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";
import { useRouter } from "expo-router";
import { register } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import { ChoiceStep, ConfirmStep, WizardProgress } from "@/components/WizardStep";

const LEGAL_PLACEHOLDER = "Texte à venir — espace réservé.";
const CURRENT_YEAR = new Date().getFullYear();
const MIN_BIRTH_YEAR = CURRENT_YEAR - 100;
const MAX_BIRTH_YEAR = CURRENT_YEAR - 10;

const STEPS = ["birth_date", "consent", "auth_method", "credentials"] as const;
type Step = (typeof STEPS)[number];

export default function Register() {
  const router = useRouter();
  const { setAuthed } = useAuth();
  const [step, setStep] = useState<Step>("birth_date");

  const [birthYear, setBirthYear] = useState<number | null>(null);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [privacyAccepted, setPrivacyAccepted] = useState(false);
  const [dataUsageAccepted, setDataUsageAccepted] = useState(false);

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const allConsentsAccepted = termsAccepted && privacyAccepted && dataUsageAccepted;

  async function handleSubmit() {
    setError(null);
    setLoading(true);
    try {
      await register({
        name,
        email,
        password,
        // Only the year is asked (see YearList below), so this is a proxy date, not
        // a real birthday. Jan 1st would systematically overestimate age for
        // everyone born later in the year — diffInYears() counts a full extra year
        // until their real birthday passes — pushing users into the stricter FIGO
        // age bracket too early. Mid-year keeps the average error near zero instead
        // of biased in one direction.
        birthDate: birthYear === null ? null : `${birthYear}-07-01`,
        termsAccepted,
        privacyAccepted,
        dataUsageAccepted,
      });
      setAuthed(true);
      router.replace("/onboarding");
    } catch {
      setError("Une erreur est survenue. Vérifie les informations saisies.");
    } finally {
      setLoading(false);
    }
  }

  function goBack() {
    const currentIndex = STEPS.indexOf(step);
    setStep(STEPS[Math.max(0, currentIndex - 1)]);
  }

  return (
    <ScrollView className="flex-1 bg-bg" contentContainerClassName="flex-grow px-6 pb-8 pt-16">
      <View className="flex-1">
        <Pressable
          onPress={() => (step === "birth_date" ? router.back() : goBack())}
          className="mb-2 h-11 justify-center"
        >
          <Text className="text-ink opacity-70">‹ Retour</Text>
        </Pressable>

        <WizardProgress current={STEPS.indexOf(step)} total={STEPS.length} />

        {step === "birth_date" && (
          <ConfirmStep
            title="En quelle année es-tu née ?"
            description="On s'en sert uniquement pour ajuster les seuils de régularité du cycle (référence clinique FIGO 2018) — jamais pour autre chose."
            primaryLabel="Continuer"
            primaryDisabled={birthYear === null}
            onPrimary={() => setStep("consent")}
            secondaryLabel="Je préfère ne pas répondre"
            onSecondary={() => setStep("consent")}
          >
            <YearList selectedYear={birthYear} onSelect={setBirthYear} />
          </ConfirmStep>
        )}

        {step === "consent" && (
          <ConfirmStep
            title="Avant de continuer"
            primaryLabel="Continuer"
            primaryDisabled={!allConsentsAccepted}
            onPrimary={() => setStep("auth_method")}
          >
            <View className="gap-3">
              <ConsentRow
                label="J'accepte les Conditions Générales d'Utilisation"
                checked={termsAccepted}
                onToggle={() => setTermsAccepted((current) => !current)}
              />
              <ConsentRow
                label="J'accepte la Politique de confidentialité"
                checked={privacyAccepted}
                onToggle={() => setPrivacyAccepted((current) => !current)}
              />
              <ConsentRow
                label="J'accepte l'utilisation de mes données pour le suivi de cycle"
                checked={dataUsageAccepted}
                onToggle={() => setDataUsageAccepted((current) => !current)}
              />
            </View>
          </ConfirmStep>
        )}

        {step === "auth_method" && (
          <ChoiceStep title="Comment tu veux te connecter ?">
            <Pressable
              disabled
              className="h-11 items-center justify-center rounded-lg bg-ink/6 opacity-40"
            >
              <Text className="font-semibold text-ink">Continuer avec Google (bientôt)</Text>
            </Pressable>
            <Pressable
              disabled
              className="h-11 items-center justify-center rounded-lg bg-ink/6 opacity-40"
            >
              <Text className="font-semibold text-ink">Continuer avec Apple (bientôt)</Text>
            </Pressable>
            <Pressable
              onPress={() => setStep("credentials")}
              className="h-11 items-center justify-center rounded-lg bg-accent"
              accessibilityRole="button"
            >
              <Text className="font-semibold text-ink">Continuer avec e-mail</Text>
            </Pressable>
          </ChoiceStep>
        )}

        {step === "credentials" && (
          <ConfirmStep
            title="Crée ton compte"
            primaryLabel={loading ? "..." : "S'inscrire"}
            primaryDisabled={loading || !name || !email || !password}
            onPrimary={handleSubmit}
          >
            <View className="gap-3">
              <TextInput
                value={name}
                onChangeText={setName}
                placeholder="Nom"
                placeholderTextColor="#12302B73"
                className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
              />
              <TextInput
                value={email}
                onChangeText={setEmail}
                placeholder="Email"
                placeholderTextColor="#12302B73"
                autoCapitalize="none"
                keyboardType="email-address"
                className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
              />
              <TextInput
                value={password}
                onChangeText={setPassword}
                placeholder="Mot de passe"
                placeholderTextColor="#12302B73"
                secureTextEntry
                className="rounded-lg bg-ink/6 px-4 py-3 text-ink"
              />
              {error && <Text className="text-danger">{error}</Text>}
            </View>
          </ConfirmStep>
        )}
      </View>
    </ScrollView>
  );
}

function YearList({
  selectedYear,
  onSelect,
}: {
  selectedYear: number | null;
  onSelect: (year: number) => void;
}) {
  const years = useMemo(() => {
    const list: number[] = [];
    for (let year = MAX_BIRTH_YEAR; year >= MIN_BIRTH_YEAR; year--) list.push(year);
    return list;
  }, []);

  return (
    <View className="h-64 rounded-2xl bg-surface p-2">
      <ScrollView showsVerticalScrollIndicator={false}>
        {years.map((year) => (
          <Pressable
            key={year}
            onPress={() => onSelect(year)}
            className={`h-11 items-center justify-center rounded-lg ${
              year === selectedYear ? "bg-accent" : ""
            }`}
            accessibilityRole="button"
            accessibilityState={{ selected: year === selectedYear }}
          >
            <Text className="font-semibold text-ink">{year}</Text>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

function ConsentRow({
  label,
  checked,
  onToggle,
}: {
  label: string;
  checked: boolean;
  onToggle: () => void;
}) {
  return (
    <Pressable
      onPress={onToggle}
      className="rounded-2xl bg-surface p-4"
      accessibilityRole="checkbox"
      accessibilityState={{ checked }}
    >
      <View className="flex-row items-start gap-3">
        <View
          className={`mt-0.5 h-5 w-5 items-center justify-center rounded border-2 ${
            checked ? "border-accent bg-accent" : "border-ink/30"
          }`}
        >
          {checked && <Text className="text-xs font-bold text-ink">✓</Text>}
        </View>
        <View className="flex-1">
          <Text className="text-sm font-semibold text-ink">{label}</Text>
          <Text className="mt-1 text-xs text-ink opacity-50">{LEGAL_PLACEHOLDER}</Text>
        </View>
      </View>
    </Pressable>
  );
}
