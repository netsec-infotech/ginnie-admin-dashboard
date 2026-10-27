import Link from "next/link";
import { FC } from "react";
import CategoryIcon from "../CategoryIcon";
import { CategoryCardProps } from "../types";
import { categoryLink } from "../utils";

/**
 * icon variant — tall image card with a separate label row.
 *
 * Best for: horizontal category rails and category grids that use the default
 * card presentation. The navbar has its own CategoryTabs presentation.
 */
const IconVariant: FC<CategoryCardProps> = ({
  category,
  isSelected,
  onClick,
  className = "",
}) => (
  <div className={`flex flex-col items-center ${className}`}>
    <div className="flex flex-col items-center w-full min-w-0">
      <div className="w-full max-w-full overflow-hidden flex items-center justify-center">
        <Link
          href={categoryLink(category)}
          onClick={onClick}
          title={category.title}
          className="flex flex-col text-foreground box-border outline-solid outline-transparent focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-focus focus-visible:outline-offset-2 shadow-none cursor-pointer motion-reduce:transition-none relative overflow-hidden w-full h-24 sm:h-28 p-0 hover:scale-110 transition-transform border-none rounded-3xl bg-transparent"
        >
          <div className="absolute left-1/2 bottom-0 -translate-x-1/2 flex justify-center w-full">
            <CategoryIcon
              category={category}
              imgClass="relative z-10 shadow-black/5 shadow-none transition-transform motion-reduce:transition-none w-16 h-16 sm:w-20 sm:h-20 rounded-lg object-contain block"
            />
          </div>
        </Link>
      </div>
      <div className="h-8 flex items-center w-full min-w-0">
        <h2
          title={category.title}
          className={`text-center truncate w-full text-xs font-medium px-1 ${
            isSelected ? "text-primary" : "text-foreground"
          }`}
        >
          {category.title}
        </h2>
      </div>
    </div>
  </div>
);

export default IconVariant;
