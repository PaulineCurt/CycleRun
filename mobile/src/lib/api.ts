import { Platform } from "react-native";
import * as SecureStore from "expo-secure-store";

const BASE = process.env.EXPO_PUBLIC_API_URL!;

// expo-secure-store ne supporte que iOS/Android : sur web on retombe sur localStorage.
export async function getToken() {
  if (Platform.OS === "web") return localStorage.getItem("token");
  return SecureStore.getItemAsync("token");
}

export async function setToken(token: string) {
  if (Platform.OS === "web") {
    localStorage.setItem("token", token);
    return;
  }
  await SecureStore.setItemAsync("token", token);
}

export async function clearToken() {
  if (Platform.OS === "web") {
    localStorage.removeItem("token");
    return;
  }
  await SecureStore.deleteItemAsync("token");
}

async function authHeader(): Promise<Record<string, string>> {
  const token = await getToken();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...(await authHeader()),
    ...(init.headers as Record<string, string> | undefined),
  };
  const res = await fetch(`${BASE}${path}`, { ...init, headers });
  if (!res.ok) throw new Error(`API ${res.status}`);
  return res.json();
}

type AuthResponse = { user: { id: number; name: string; email: string }; token: string };

export async function login(email: string, password: string) {
  const data = await api<AuthResponse>("/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  await setToken(data.token);
  return data.user;
}

export async function register(name: string, email: string, password: string) {
  const data = await api<AuthResponse>("/register", {
    method: "POST",
    body: JSON.stringify({
      name,
      email,
      password,
      password_confirmation: password,
    }),
  });
  await setToken(data.token);
  return data.user;
}

export async function logout() {
  await api("/logout", { method: "POST" }).catch(() => {});
  await clearToken();
}
