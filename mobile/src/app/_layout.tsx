import { useEffect } from "react";
import { Stack, useRouter, useSegments } from "expo-router";
import { AuthProvider, useAuth } from "@/lib/auth-context";
import "@/global.css";

function Gate() {
  const { ready, authed } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    if (!ready) return;
    const onLogin = segments[0] === "login";
    const onRegister = segments[0] === "register";
    if (!authed && !onLogin && !onRegister) router.replace("/login");
    // Only /login auto-redirects once authed — /register drives its own post-signup
    // navigation (to /onboarding), so forcing "/" here would race with that.
    if (authed && onLogin) router.replace("/");
  }, [ready, authed, segments]);

  if (!ready) return null;

  return <Stack screenOptions={{ headerShown: false }} />;
}

export default function RootLayout() {
  return (
    <AuthProvider>
      <Gate />
    </AuthProvider>
  );
}
