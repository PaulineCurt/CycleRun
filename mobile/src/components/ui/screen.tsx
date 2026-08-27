import { ScrollView, type ScrollViewProps } from "react-native";
import { useScrollToTopOnFocus } from "@/lib/useScrollToTopOnFocus";

/**
 * Drop-in replacement for <ScrollView> as a screen's root: scrolls back to the top
 * on every focus, so switching tabs (which stay mounted, keeping their scroll
 * position) or landing on a pushed screen always feels like arriving fresh.
 */
export function Screen(props: ScrollViewProps) {
  const scrollRef = useScrollToTopOnFocus();
  return <ScrollView ref={scrollRef} {...props} />;
}
