import { forwardRef, type ComponentProps } from "react";
import { Pressable, Text, type PressableProps } from "react-native";
import { cva, type VariantProps } from "class-variance-authority";
import { Ionicons } from "@expo/vector-icons";
import { cn } from "@/lib/utils";

type IconName = ComponentProps<typeof Ionicons>["name"];

/**
 * React Native Reusables-style primitive: owned in the codebase (not a runtime
 * dependency), themed to CycleRun's existing NativeWind tokens rather than the
 * usual shadcn CSS-variable scheme, so it drops straight into the current setup.
 */
const buttonVariants = cva("h-11 flex-row items-center justify-center rounded-lg", {
  variants: {
    variant: {
      primary: "bg-accent",
      secondary: "bg-surface",
      ghost: "bg-transparent",
      destructive: "border-2 border-danger bg-transparent",
    },
    disabled: {
      true: "opacity-40",
      false: "",
    },
  },
  defaultVariants: { variant: "primary", disabled: false },
});

const textVariants = cva("font-semibold", {
  variants: {
    variant: {
      primary: "text-ink",
      secondary: "text-ink",
      ghost: "text-ink opacity-60",
      destructive: "text-danger",
    },
  },
  defaultVariants: { variant: "primary" },
});

export type ButtonProps = PressableProps &
  VariantProps<typeof buttonVariants> & {
    label: string;
    icon?: IconName;
    className?: string;
  };

export const Button = forwardRef<React.ElementRef<typeof Pressable>, ButtonProps>(
  ({ label, icon, variant, disabled, className, ...props }, ref) => (
    <Pressable
      ref={ref}
      disabled={disabled ?? undefined}
      accessibilityRole="button"
      className={cn(buttonVariants({ variant, disabled: !!disabled }), "gap-2", className)}
      {...props}
    >
      {icon && (
        <Ionicons
          name={icon}
          size={18}
          color={variant === "destructive" ? "#C4292E" : "#12302B"}
          style={variant === "ghost" ? { opacity: 0.6 } : undefined}
        />
      )}
      <Text className={textVariants({ variant })}>{label}</Text>
    </Pressable>
  ),
);
Button.displayName = "Button";
