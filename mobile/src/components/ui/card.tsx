import { View, type ViewProps } from "react-native";
import { cn } from "@/lib/utils";

/** React Native Reusables-style primitive, themed to CycleRun's existing surface token. */
export function Card({ className, ...props }: ViewProps) {
  return <View className={cn("rounded-2xl bg-surface p-4", className)} {...props} />;
}
