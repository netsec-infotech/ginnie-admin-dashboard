import Link from "next/link";
import { FC } from "react";
import CategoryIcon from "../CategoryIcon";
import { CategoryCardProps } from "../types";
import { categoryLink } from "../utils";

/**
 * pill variant — compact inline chip: small icon + title inside a rounded-full border.
 *
 * Best for: filter chip bars, tag rows, compact horizontal category lists,
 * search result filters.
 * Supports `isSelected` to highlight the active filter.
 */
const PillVariant: FC<CategoryCardProps> = ({
  category,
  isSelected,
  onClick,
  className = "",
}) => (
  <Link
    href={categoryLink(category)}
    onClick={onClick}
    title={category.title}
    className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full border
      transition-all duration-200 text-sm font-medium whitespace-nowrap
      ${
        isSelected
          ? "bg-primary/15 border-primary/40 text-primary dark:bg-primary/20 dark:text-primary-400"
          : "bg-default-50 border-default-200 text-default-700 dark:bg-[#1a1a1a] dark:border-white/10 dark:text-zinc-300 hover:border-primary/40 hover:text-primary dark:hover:border-primary/30 dark:hover:text-primary-400"
      } ${className}`}
  >
    <CategoryIcon category={category} imgClass="w-5 h-5" />
    <span>{category.title}</span>
  </Link>
);

export default PillVariant;
