import { Category } from "@/types/ApiResponse";

// ─── Variant types ────────────────────────────────────────────────────────────

export type CategoryCardVariant = "icon" | "tile" | "banner" | "list" | "pill";

// ─── Shared props interface ───────────────────────────────────────────────────

export interface CategoryCardProps {
  category: Category;
  variant?: CategoryCardVariant;
  isSelected?: boolean;
  onClick?: () => void;
  className?: string;
}
