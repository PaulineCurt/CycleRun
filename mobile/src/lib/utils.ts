import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

/** Merges conditional class names then resolves Tailwind conflicts (standard shadcn/RNR helper). */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
