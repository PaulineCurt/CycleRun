import type { ReactNode } from "react";
import { Pressable, Text, View } from "react-native";

/** Progress dots for a linear multi-step flow — current step widens and fills. */
export function WizardProgress({ current, total }: { current: number; total: number }) {
  return (
    <View className="mb-6 flex-row justify-center gap-2">
      {Array.from({ length: total }).map((_, index) => (
        <View
          key={index}
          className={`h-1.5 rounded-full ${
            index === current ? "w-6 bg-accent" : "w-1.5 bg-ink/15"
          }`}
        />
      ))}
    </View>
  );
}

/**
 * A step where the user makes one or more selections and then confirms with a single
 * primary action. Title and content sit together near the top (no dead-centering a
 * short block in the middle of the screen); a flexible spacer absorbs the leftover
 * space so the primary button still lands at the very bottom of the screen.
 */
export function ConfirmStep({
  title,
  description,
  children,
  primaryLabel,
  primaryDisabled,
  onPrimary,
  secondaryLabel,
  onSecondary,
}: {
  title: string;
  description?: string;
  children: ReactNode;
  primaryLabel: string;
  primaryDisabled?: boolean;
  onPrimary: () => void;
  secondaryLabel?: string;
  onSecondary?: () => void;
}) {
  return (
    <View className="flex-1">
      <Text className="text-2xl font-bold text-ink">{title}</Text>
      {description && <Text className="mt-2 text-sm text-ink opacity-70">{description}</Text>}

      <View className="mt-6">{children}</View>

      <View className="flex-1" />

      <View className="gap-3">
        <Pressable
          onPress={onPrimary}
          disabled={primaryDisabled}
          className={`h-11 items-center justify-center rounded-lg bg-accent ${
            primaryDisabled ? "opacity-40" : ""
          }`}
          accessibilityRole="button"
        >
          <Text className="font-semibold text-ink">{primaryLabel}</Text>
        </Pressable>
        {secondaryLabel && (
          <Pressable
            onPress={onSecondary}
            className="h-11 items-center justify-center"
            accessibilityRole="button"
          >
            <Text className="text-ink opacity-60">{secondaryLabel}</Text>
          </Pressable>
        )}
      </View>
    </View>
  );
}

/**
 * A step where each option IS the action (tapping one advances immediately). Choices
 * sit directly under the title — grouped near the top, not floating centered in the
 * screen — since there's no separate "confirm" button to anchor at the bottom.
 */
export function ChoiceStep({
  title,
  description,
  children,
  footerLabel,
  onFooter,
}: {
  title: string;
  description?: string;
  children: ReactNode;
  footerLabel?: string;
  onFooter?: () => void;
}) {
  return (
    <View className="flex-1">
      <Text className="text-2xl font-bold text-ink">{title}</Text>
      {description && <Text className="mt-2 text-sm text-ink opacity-70">{description}</Text>}

      <View className="mt-6 gap-3">{children}</View>

      <View className="flex-1" />

      {footerLabel && (
        <Pressable
          onPress={onFooter}
          className="h-11 items-center justify-center"
          accessibilityRole="button"
        >
          <Text className="text-ink opacity-60">{footerLabel}</Text>
        </Pressable>
      )}
    </View>
  );
}
