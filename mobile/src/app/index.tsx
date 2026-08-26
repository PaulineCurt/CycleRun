import { Pressable, Text, View } from "react-native";
import { logout } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";

const PHASES = [
  { label: "Menstruelle", className: "bg-phase-menstruelle" },
  { label: "Folliculaire", className: "bg-phase-folliculaire" },
  { label: "Ovulation", className: "bg-phase-ovulation" },
  { label: "Lutéale", className: "bg-phase-luteale" },
];

export default function Index() {
  const { setAuthed } = useAuth();

  async function handleLogout() {
    await logout();
    setAuthed(false);
  }

  return (
    <View className="flex-1 items-center justify-center bg-prune px-6">
      <Text className="mb-2 text-4xl font-bold text-craie">CycleRun</Text>
      <Text className="mb-10 text-base text-craie opacity-70">
        Ton cycle, ton entraînement.
      </Text>

      <View className="w-full gap-3">
        {PHASES.map((phase) => (
          <View key={phase.label} className="flex-row items-center gap-3">
            <View className={`h-3.5 w-3.5 rounded-full ${phase.className}`} />
            <Text className="text-base text-craie">{phase.label}</Text>
          </View>
        ))}
      </View>

      <Pressable onPress={handleLogout} className="mt-10">
        <Text className="text-craie opacity-70">Déconnexion</Text>
      </Pressable>
    </View>
  );
}
