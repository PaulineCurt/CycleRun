import Svg, { Circle, G } from "react-native-svg";

/**
 * Shared geometry for the app's 4-equal-arc ring motif — used both by the cycle
 * status ring (CycleRing) and the on-brand loading spinner (BrandLoader), so the
 * arc math (circumference, gap, dash offsets) lives in exactly one place.
 */
export function PhaseArcRing({
  size,
  stroke,
  gap,
  colors,
}: {
  size: number;
  stroke: number;
  gap: number;
  /** One color per arc, in PHASE_ORDER. */
  colors: readonly string[];
}) {
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const arcLength = circumference / colors.length;

  return (
    <Svg width={size} height={size}>
      {/* Rotated -90° so the first arc starts at 12 o'clock instead of SVG's default 3 o'clock. */}
      <G rotation={-90} origin={`${size / 2}, ${size / 2}`}>
        {colors.map((color, index) => (
          <Circle
            key={index}
            cx={size / 2}
            cy={size / 2}
            r={radius}
            stroke={color}
            strokeWidth={stroke}
            strokeLinecap="round"
            strokeDasharray={`${arcLength - gap} ${circumference - (arcLength - gap)}`}
            strokeDashoffset={-(index * arcLength)}
            fill="none"
          />
        ))}
      </G>
    </Svg>
  );
}
