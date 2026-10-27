import Link from "next/link";
import { FC } from "react";
import { CategoryCardProps } from "../types";
import { categoryLink } from "../utils";

/**
 * tile variant — square card with image as full background and title at bottom.
 *
 * Best for: 2-col / 3-col category grids, `/categories` page.
 * Uses `background_image` or `image` as full cover background.
 */
const TileVariant: FC<CategoryCardProps> = ({
  category,
  onClick,
  className = "",
}) => {
  const bgImg = category.background_image || category.image;

  return (
    <Link
      href={categoryLink(category)}
      onClick={onClick}
      title={category.title}
      className={`group block w-full ${className}`}
    >
      <div className="relative overflow-hidden w-full aspect-square rounded-2xl border border-default-200/70 dark:border-default-100/20 bg-content1 shadow-[0_12px_30px_-5px_rgba(0,0,0,0.16)] dark:shadow-[0_20px_45px_-10px_rgba(0,0,0,0.95)] transition-all duration-300">
        {/* Isolated Background Image (Full Cover) */}
        {bgImg ? (
          <div className="absolute inset-0 w-full h-full overflow-hidden p-4">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={bgImg}
              alt={category.title}
              className="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105"
              loading="lazy"
            />
          </div>
        ) : (
          <div className="absolute inset-0 bg-gradient-to-br from-default-100 to-default-200" />
        )}

        {/* Title at the bottom with localized text gradient overlay */}
        <div className="absolute bottom-0 left-0 right-0 p-4 pt-10 z-10 bg-gradient-to-t from-content1/95 via-content1/55 to-transparent pointer-events-none">
          <p className="text-xs sm:text-sm font-semibold truncate text-foreground">
            {category.title}
          </p>
        </div>
      </div>
    </Link>
  );
};

export default TileVariant;
