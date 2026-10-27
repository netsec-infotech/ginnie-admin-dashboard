import Link from "next/link";
import { FC } from "react";

import { CategoryCardProps } from "../types";
import { categoryLink } from "../utils";

const CUSTOM_CATEGORY_ARTWORK_MAX_WIDTH = 280;

/**
 * Displays builder-provided category artwork without adding storefront text,
 * gradients, or a CTA. Custom-image artwork already contains its complete
 * visual design, so preserving its natural aspect ratio avoids both cropping
 * and duplicated content.
 */
const ImageOnlyVariant: FC<CategoryCardProps> = ({
  category,
  onClick,
  className = "",
  imageStyle,
}) => {
  const source =
    category.custom_image ||
    category.image ||
    category.background_image_web ||
    category.background_image;

  if (!source) return null;

  return (
    <Link
      href={categoryLink(category)}
      onClick={onClick}
      aria-label={category.title}
      className={`inline-block max-w-full overflow-hidden bg-transparent align-top outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 ${className}`}
      style={{
        ...imageStyle,
        width: "fit-content",
        maxWidth: `min(100%, ${CUSTOM_CATEGORY_ARTWORK_MAX_WIDTH}px)`,
      }}
      data-category-card-variant="image-only"
    >
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={source}
        alt={category.title}
        className="block h-auto w-auto max-w-full object-contain"
        loading="lazy"
        decoding="async"
      />
    </Link>
  );
};

export default ImageOnlyVariant;
