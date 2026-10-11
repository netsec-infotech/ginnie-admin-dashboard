import { trackCategoryView } from "@/lib/analytics";
import { FC, memo, useMemo } from "react";
import { CategoryCardProps, CategoryCardVariant } from "./types";
import BannerVariant from "./variants/BannerVariant";
import IconVariant from "./variants/IconVariant";
import ListVariant from "./variants/ListVariant";
import PillVariant from "./variants/PillVariant";
import TileVariant from "./variants/TileVariant";

// ─── Variant registry ─────────────────────────────────────────────────────────

const VARIANT_MAP: Record<CategoryCardVariant, FC<CategoryCardProps>> = {
  icon: IconVariant,
  tile: TileVariant,
  banner: BannerVariant,
  list: ListVariant,
  pill: PillVariant,
};

// ─── Main component ───────────────────────────────────────────────────────────

/**
 * CategoryCard — a single reusable card for displaying a category.
 *
 * Five visual variants are available via the `variant` prop:
 *
 * - `"icon"`   (default) — vertical icon + label chip. Used in horizontal
 *              scroll rails and the homepage category section.
 * - `"tile"`   — square card with category image and gradient title overlay.
 *              Use in 2-col / 3-col grids.
 * - `"banner"` — wide card (h-28/h-32) with product image floating right.
 *              Use in featured / hero sections.
 * - `"list"`   — full-width row (icon | title + count | arrow). Use in
 *              sidebar filters, drawers, and the mobile categories page.
 * - `"pill"`   — compact inline chip. Use in filter bars and tag rows.
 *
 * All existing callers that import `CategoryCard` without a `variant` prop
 * continue to work unchanged — they receive the `"icon"` variant by default.
 *
 * @example
 * // Backward-compatible (icon variant)
 * <CategoryCard category={cat} />
 *
 * // Explicit variant
 * <CategoryCard category={cat} variant="tile" />
 * <CategoryCard category={cat} variant="list" isSelected={active === cat.slug} />
 */
const CategoryCard: FC<CategoryCardProps> = ({
  category,
  variant = "icon",
  isSelected = false,
  onClick,
  className,
}) => {
  const VariantComponent = VARIANT_MAP[variant];

  // Wrap onClick so analytics fires for every variant without duplicating the
  // tracking call inside each variant file.
  const handleClick = useMemo(
    () => () => {
      trackCategoryView(category?.id?.toString(), category?.title);
      onClick?.();
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [category?.id, category?.title]
  );

  return (
    <VariantComponent
      category={category}
      variant={variant}
      isSelected={isSelected}
      onClick={handleClick}
      className={className}
    />
  );
};

export default memo(CategoryCard);

// ─── Named re-exports for advanced usage ─────────────────────────────────────

export type { CategoryCardProps, CategoryCardVariant } from "./types";
export { categoryLink, isLightColor, useSafeBackgroundColor } from "./utils";
export { default as CategoryIcon } from "./CategoryIcon";
export { default as BannerVariant } from "./variants/BannerVariant";
export { default as IconVariant } from "./variants/IconVariant";
export { default as ListVariant } from "./variants/ListVariant";
export { default as PillVariant } from "./variants/PillVariant";
export { default as TileVariant } from "./variants/TileVariant";
