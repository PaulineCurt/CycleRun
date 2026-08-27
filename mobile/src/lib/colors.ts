/**
 * Hex mirrors of tailwind.config.js's palette, for the handful of places that need a
 * real color value instead of a className (icon `color` props, inline styles). Keep
 * these in sync with tailwind.config.js by hand — NativeWind classes can't be read
 * back into JS at runtime.
 */
export const COLOR = {
  ink: "#12302B",
  accent: "#16B88A",
  // Decorative icon-badge tint (title icons) — deliberately separate from `accent`,
  // which is reserved for interactive states (buttons, selected pills/days). The two
  // used to share one color; changing the interactive one shouldn't recolor every badge.
  badge: "#FF6355",
  danger: "#C4292E",
  phase: {
    menstrual: "#FF5D8F",
    follicular: "#FF9F1C",
    ovulation: "#2EC4B6",
    luteal: "#6A4C93",
  },
} as const;
