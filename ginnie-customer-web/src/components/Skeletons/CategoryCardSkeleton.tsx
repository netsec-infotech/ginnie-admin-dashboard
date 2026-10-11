import { CategoryCardVariant } from "@/components/Cards/CategoryCard";
import { Skeleton } from "@heroui/react";
import { FC } from "react";

interface CategoryCardSkeletonProps {
  variant?: CategoryCardVariant;
}

const CategoryCardSkeleton: FC<CategoryCardSkeletonProps> = ({
  variant = "icon",
}) => {
  if (variant === "tile") {
    return (
      <div className="w-full aspect-square rounded-2xl overflow-hidden">
        <Skeleton className="w-full h-full rounded-2xl" />
      </div>
    );
  }

  if (variant === "banner") {
    return (
      <div className="w-full h-28 sm:h-32 rounded-2xl overflow-hidden">
        <Skeleton className="w-full h-full rounded-2xl" />
      </div>
    );
  }

  if (variant === "list") {
    return (
      <div className="flex items-center gap-3 px-3 py-3">
        <Skeleton className="w-11 h-11 rounded-xl shrink-0" />
        <div className="flex-1 flex flex-col gap-1.5">
          <Skeleton className="h-3.5 w-2/3 rounded" />
          <Skeleton className="h-2.5 w-1/3 rounded" />
        </div>
      </div>
    );
  }

  if (variant === "pill") {
    return (
      <Skeleton className="h-8 w-24 rounded-full" />
    );
  }

  // default: tall image card with a separate label row
  return (
    <div className="flex flex-col items-center w-full min-w-0">
      <div className="w-full max-w-full overflow-hidden flex items-center justify-center">
        <div className="relative w-full h-24 sm:h-28">
          <Skeleton className="absolute left-1/2 bottom-0 -translate-x-1/2 w-16 h-16 sm:w-20 sm:h-20 rounded-lg" />
        </div>
      </div>
      <div className="h-8 flex items-center w-full min-w-0 justify-center">
        <Skeleton className="h-3 w-3/4 rounded" />
      </div>
    </div>
  );
};

export default CategoryCardSkeleton;

