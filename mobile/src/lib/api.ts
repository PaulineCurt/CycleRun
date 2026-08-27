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
  if (res.status === 204) return undefined as T;
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

export type RegisterInput = {
  name: string;
  email: string;
  password: string;
  birthDate: string | null;
  termsAccepted: boolean;
  privacyAccepted: boolean;
  dataUsageAccepted: boolean;
};

export async function register(input: RegisterInput) {
  const data = await api<AuthResponse>("/register", {
    method: "POST",
    body: JSON.stringify({
      name: input.name,
      email: input.email,
      password: input.password,
      password_confirmation: input.password,
      birth_date: input.birthDate,
      terms_accepted: input.termsAccepted,
      privacy_accepted: input.privacyAccepted,
      data_usage_accepted: input.dataUsageAccepted,
    }),
  });
  await setToken(data.token);
  return data.user;
}

export async function logout() {
  await api("/logout", { method: "POST" }).catch(() => {});
  await clearToken();
}

export type Phase = "menstrual" | "follicular" | "ovulation" | "luteal";

export type CycleStatus =
  | { status: "no_data" }
  | {
      status: "ok";
      cycle_day: number;
      phase: Phase | null;
      predictable: boolean;
      predicted_next_start: string;
      ovulation_window: { start: string; end: string };
      next_start_range: { start: string; end: string } | null;
      low_confidence: boolean;
      regularity: "regular" | "irregular" | "unknown";
      frequency: "normal" | "infrequent" | "frequent" | "unknown";
      overdue: boolean;
      severely_overdue: boolean;
    };

export async function getCycleStatus() {
  return api<CycleStatus>("/cycle-status");
}

export type CalendarDay = {
  date: string;
  cycle_id: number | null;
  cycle_day: number | null;
  is_cycle_start: boolean;
  is_menses_end: boolean;
  phase: Phase | null;
  predictable: boolean;
  low_confidence: boolean;
  is_projected: boolean;
};

export async function getCycleCalendar(month: string) {
  return api<{ month: string; days: CalendarDay[] }>(`/cycle-calendar?month=${month}`);
}

type CycleRecord = { id: number; started_on: string; menses_ended_on: string | null };

export async function createCycle(startedOn: string) {
  return api<CycleRecord>("/cycles", {
    method: "POST",
    body: JSON.stringify({ started_on: startedOn }),
  });
}

export async function setMensesEndedOn(cycleId: number, mensesEndedOn: string | null) {
  return api<CycleRecord>(`/cycles/${cycleId}`, {
    method: "PATCH",
    body: JSON.stringify({ menses_ended_on: mensesEndedOn }),
  });
}

export async function deleteCycle(cycleId: number) {
  return api<undefined>(`/cycles/${cycleId}`, { method: "DELETE" });
}

export type Profile = {
  id: number;
  name: string;
  email: string;
  typical_cycle_length: number | null;
  typical_menses_length: number | null;
  birth_date: string | null;
  cycle_length_measured: boolean;
  cycle_length_average: number;
  menses_length_measured: boolean;
  menses_length_average: number;
};

export async function getProfile() {
  return api<Profile>("/profile");
}

export async function updateProfile(data: {
  typical_cycle_length?: number | null;
  typical_menses_length?: number | null;
}) {
  return api<Profile>("/profile", {
    method: "PATCH",
    body: JSON.stringify(data),
  });
}

export type CycleHistoryEntry = {
  started_on: string;
  menses_ended_on: string | null;
  menses_duration: number | null;
  cycle_duration: number | null;
};

export type CycleForecastEntry = { start: string; end: string };

export type CycleAnalysis = {
  menses: { last_duration: number | null; variation_days: number | null; sample_size: number };
  cycle: {
    last_duration: number | null;
    variation_days: number | null;
    regularity: "regular" | "irregular" | "unknown";
    sample_size: number;
  };
  history: CycleHistoryEntry[];
  forecast: CycleForecastEntry[];
};

export async function getCycleAnalysis() {
  return api<CycleAnalysis>("/cycle-analysis");
}
