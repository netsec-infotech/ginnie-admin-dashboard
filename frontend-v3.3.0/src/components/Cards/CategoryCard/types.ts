import { Category } from "@/types/ApiResponse";
import type { CSSProperties } from "react";

// ─── Variant types ────────────────────────────────────────────────────────────

export type CategoryCardVariant =
  | "icon"
  | "tile"
  | "banner"
  | "image-only"
  | "list"
  | "pill";

// ─── Shared props interface ───────────────────────────────────────────────────

export interface CategoryCardProps {
  category: Category;
  variant?: CategoryCardVariant;
  isSelected?: boolean;
  onClick?: () => void;
  className?: string;
  imageStyle?: CSSProperties;
}
