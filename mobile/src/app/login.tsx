import { useState } from "react";
import { Pressable, Text, TextInput, View } from "react-native";
import { useRouter } from "expo-router";
import { login } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";

export default function Login() {
  const router = useRouter();
  const { setAuthed } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit() {
    setError(null);
    setLoading(true);
    try {
      await login(email, password);
      setAuthed(true);
    } catch {
      setError("Une erreur est survenue. Vérifie tes identifiants.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <View className="flex-1 justify-center bg-bg px-6">
      <Text className="mb-8 text-3xl font-bold text-ink">Connexion</Text>

      <TextInput
        value={email}
        onChangeText={setEmail}
        placeholder="Email"
        placeholderTextColor="#12302B73"
        autoCapitalize="none"
        keyboardType="email-address"
        className="mb-3 rounded-lg bg-ink/6 px-4 py-3 text-ink"
      />

      <TextInput
        value={password}
        onChangeText={setPassword}
        placeholder="Mot de passe"
        placeholderTextColor="#12302B73"
        secureTextEntry
        className="mb-5 rounded-lg bg-ink/6 px-4 py-3 text-ink"
      />

      {error && <Text className="mb-4 text-danger">{error}</Text>}

      <Pressable
        onPress={submit}
        disabled={loading}
        className="mb-4 items-center rounded-lg bg-accent py-3"
      >
        <Text className="font-semibold text-ink">{loading ? "..." : "Se connecter"}</Text>
      </Pressable>

      <Pressable onPress={() => router.push("/register")}>
        <Text className="text-center text-ink opacity-70">Pas de compte ? Inscris-toi</Text>
      </Pressable>
    </View>
  );
}
