import { Category } from "@/types/ApiResponse";

// ─── Contrast helpers ─────────────────────────────────────────────────────────

/**
 * Returns true if the given hex color has a relative luminance > 0.35,
 * i.e. it is "light" and should have dark text overlaid on it.
 */
export function isLightColor(hex: string): boolean {
  const clean = hex.replace("#", "");
  if (clean.length < 6) return true;
  const r = parseInt(clean.slice(0, 2), 16) / 255;
  const g = parseInt(clean.slice(2, 4), 16) / 255;
  const b = parseInt(clean.slice(4, 6), 16) / 255;
  const toLinear = (c: number) =>
    c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  const lum =
    0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
  return lum > 0.35;
}

/**
 * Returns a safe background color from the category's `background_color`
 * field. Falls back to null (use theme default) if the color is white, black,
 * or missing. Also returns whether the chosen color is "light".
 */
export function useSafeBackgroundColor(color?: string | null): {
  bgColor: string | null;
  light: boolean;
} {
  const isUsable =
    color &&
    color !== "#ffffff" &&
    color !== "#FFFFFF" &&
    color !== "#000000" &&
    color.startsWith("#");
  return {
    bgColor: isUsable ? color! : null,
    light: isUsable ? isLightColor(color!) : false,
  };
}

// ─── Link builder ─────────────────────────────────────────────────────────────

/**
 * Builds the correct href for a category, handling both top-level categories
 * and subcategories (parent_slug present).
 */
export function categoryLink(category: Category): string {
  return category?.parent_slug
    ? `/categories/${category.parent_slug}?subcategory=${category.slug}`
    : `/categories/${category.slug}`;
}
