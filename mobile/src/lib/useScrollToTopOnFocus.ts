import { useCallback, useRef } from "react";
import type { ScrollViewInstance } from "react-native";
import { useFocusEffect } from "expo-router";

/**
 * Tabs stay mounted when you switch away from them, so their ScrollView keeps
 * whatever scroll position it had — coming back to a tab (or landing on a screen
 * pushed on top of one) should feel like arriving fresh, not resuming mid-scroll.
 */
export function useScrollToTopOnFocus() {
  const scrollRef = useRef<ScrollViewInstance>(null);

  useFocusEffect(
    useCallback(() => {
      scrollRef.current?.scrollTo({ y: 0, animated: false });
    }, []),
  );

  return scrollRef;
}
