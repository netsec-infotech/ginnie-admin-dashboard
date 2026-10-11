import { ChevronRight } from "lucide-react";
import Link from "next/link";
import { FC } from "react";
import CategoryIcon from "../CategoryIcon";
import { CategoryCardProps } from "../types";
import { categoryLink } from "../utils";

/**
 * list variant — full-width horizontal row: icon | title + count | arrow.
 *
 * Best for: sidebar category filters, category drawers, mobile category pages.
 * Supports `isSelected` for active state highlighting.
 */
const ListVariant: FC<CategoryCardProps> = ({
  category,
  isSelected,
  onClick,
  className = "",
}) => (
  <Link
    href={categoryLink(category)}
    onClick={onClick}
    title={category.title}
    className={`flex items-center gap-3 px-3 py-3 rounded-xl transition-all duration-200 group
      ${
        isSelected
          ? "bg-primary/10 dark:bg-primary/15"
          : "hover:bg-default-100 dark:hover:bg-[#1e1e1e]"
      } ${className}`}
  >
    {/* Icon box */}
    <div
      className={`flex items-center justify-center w-11 h-11 rounded-xl shrink-0 border transition-all
        ${
          isSelected
            ? "bg-primary/20 dark:bg-primary/30 border-primary/30"
            : "bg-default-100 dark:bg-white/5 border-default-200/50 dark:border-white/5 group-hover:border-primary/20"
        }`}
    >
      <CategoryIcon category={category} imgClass="w-6 h-6" />
    </div>

    {/* Text */}
    <div className="flex-1 min-w-0">
      <p
        className={`text-sm font-semibold truncate
          ${isSelected ? "text-primary" : "text-default-800 dark:text-default-200"}`}
      >
        {category.title}
      </p>
      {category.product_count > 0 && (
        <p className="text-xs text-default-400 dark:text-zinc-500 mt-0.5">
          {category.product_count} Products
        </p>
      )}
    </div>

    {/* Arrow */}
    <ChevronRight
      size={16}
      className={`shrink-0 transition-colors
        ${
          isSelected
            ? "text-primary"
            : "text-default-400 group-hover:text-default-600 dark:group-hover:text-zinc-300"
        }`}
    />
  </Link>
);

export default ListVariant;
