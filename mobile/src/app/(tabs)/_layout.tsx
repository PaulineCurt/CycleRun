import type { ComponentProps } from "react";
import type { ColorValue } from "react-native";
import { Tabs } from "expo-router";
import { Ionicons } from "@expo/vector-icons";

// expo-router types the tab tint as ColorValue (nullable); ours are plain hex strings.
function tabIcon(name: ComponentProps<typeof Ionicons>["name"]) {
  return function TabIcon({ color, size }: { color: ColorValue; size: number }) {
    return <Ionicons name={name} color={color as string} size={size} />;
  };
}

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: "#12302B",
        tabBarInactiveTintColor: "#12302B99",
        tabBarStyle: { backgroundColor: "#FFFFFF" },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: "Accueil",
          tabBarIcon: tabIcon("home"),
        }}
      />
      <Tabs.Screen
        name="calendar"
        options={{
          title: "Calendrier",
          tabBarIcon: tabIcon("calendar"),
        }}
      />
      <Tabs.Screen
        name="training"
        options={{
          title: "Entraînement",
          tabBarIcon: tabIcon("fitness"),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: "Profil",
          tabBarIcon: tabIcon("person"),
        }}
      />
    </Tabs>
  );
}
