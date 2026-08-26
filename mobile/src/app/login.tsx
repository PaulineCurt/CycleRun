import { useState } from "react";
import { Pressable, Text, TextInput, View } from "react-native";
import { login, register } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";

export default function Login() {
  const { setAuthed } = useAuth();
  const [mode, setMode] = useState<"login" | "register">("login");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit() {
    setError(null);
    setLoading(true);
    try {
      if (mode === "login") {
        await login(email, password);
      } else {
        await register(name, email, password);
      }
      setAuthed(true);
    } catch {
      setError("Une erreur est survenue. Vérifie tes identifiants.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <View className="flex-1 justify-center bg-prune px-6">
      <Text className="mb-8 text-3xl font-bold text-craie">
        {mode === "login" ? "Connexion" : "Créer un compte"}
      </Text>

      {mode === "register" && (
        <TextInput
          value={name}
          onChangeText={setName}
          placeholder="Nom"
          placeholderTextColor="#FBF5EC80"
          className="mb-3 rounded-lg bg-craie/10 px-4 py-3 text-craie"
        />
      )}

      <TextInput
        value={email}
        onChangeText={setEmail}
        placeholder="Email"
        placeholderTextColor="#FBF5EC80"
        autoCapitalize="none"
        keyboardType="email-address"
        className="mb-3 rounded-lg bg-craie/10 px-4 py-3 text-craie"
      />

      <TextInput
        value={password}
        onChangeText={setPassword}
        placeholder="Mot de passe"
        placeholderTextColor="#FBF5EC80"
        secureTextEntry
        className="mb-5 rounded-lg bg-craie/10 px-4 py-3 text-craie"
      />

      {error && <Text className="mb-4 text-corail">{error}</Text>}

      <Pressable
        onPress={submit}
        disabled={loading}
        className="mb-4 items-center rounded-lg bg-corail py-3"
      >
        <Text className="font-semibold text-craie">
          {loading ? "..." : mode === "login" ? "Se connecter" : "S'inscrire"}
        </Text>
      </Pressable>

      <Pressable onPress={() => setMode(mode === "login" ? "register" : "login")}>
        <Text className="text-center text-craie opacity-70">
          {mode === "login" ? "Pas de compte ? Inscris-toi" : "Déjà un compte ? Connecte-toi"}
        </Text>
      </Pressable>
    </View>
  );
}
