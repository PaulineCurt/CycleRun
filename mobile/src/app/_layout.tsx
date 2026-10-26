import { useEffect } from "react";
import { Stack, useRouter, useSegments } from "expo-router";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { BottomSheetModalProvider } from "@gorhom/bottom-sheet";
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
  }, [ready, authed, segments, router]);

  if (!ready) return null;

  return <Stack screenOptions={{ headerShown: false }} />;
}

export default function RootLayout() {
  return (
    // GestureHandlerRootView is required by react-native-gesture-handler (which
    // @gorhom/bottom-sheet is built on). BottomSheetModalProvider sits above the
    // whole app — not inside a tab screen — so its sheets render via a portal that
    // is guaranteed to sit above the tab bar, unlike a Modal invoked from within a
    // screen managed by react-native-screens.
    <GestureHandlerRootView style={{ flex: 1 }}>
      <BottomSheetModalProvider>
        <AuthProvider>
          <Gate />
        </AuthProvider>
      </BottomSheetModalProvider>
    </GestureHandlerRootView>
  );
}
