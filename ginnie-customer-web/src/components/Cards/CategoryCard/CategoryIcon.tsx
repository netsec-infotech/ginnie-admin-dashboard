import { Category } from "@/types/ApiResponse";
import { Image } from "@heroui/react";
import { LayoutGrid } from "lucide-react";
import { FC } from "react";

interface CategoryIconProps {
  category: Category;
  /** Tailwind size classes applied to both the image and the fallback icon. */
  imgClass?: string;
}

/**
 * Renders the category image if available, or a generic LayoutGrid icon as
 * a fallback. Shared by all card variants.
 */
const CategoryIcon: FC<CategoryIconProps> = ({
  category,
  imgClass = "w-8 h-8",
}) =>
  category.image ? (
    <Image
      src={category.image}
      alt={category.title}
      removeWrapper
      className={`${imgClass} object-contain`}
      loading="eager"
    />
  ) : (
    <LayoutGrid className={`${imgClass} text-default-400`} />
  );

export default CategoryIcon;
