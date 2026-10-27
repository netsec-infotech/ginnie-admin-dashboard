import { Skeleton } from "@heroui/react";
import React from "react";

interface SkeletonTabButtonProps {
  size?: "sm" | "lg" | "compact" | "home" | "pill";
  hideIcon?: boolean;
}

const SkeletonTabButton: React.FC<SkeletonTabButtonProps> = ({
  size = "sm",
  hideIcon = false,
}) => {
  const isLarge = size === "lg";
  const isHome = size === "home";
  const isCompact = size === "compact";
  const isPill = size === "pill";
  return (
    <div
      className={`
        flex items-center justify-center ${isPill ? "flex-row gap-2" : isCompact ? `flex-row ${hideIcon ? "gap-0" : "gap-2"}` : hideIcon ? "flex-col gap-0" : "flex-col gap-2"}
        ${
          isLarge
            ? `px-1 sm:min-w-[80px] lg:min-w-[110px] lg:px-4 ${hideIcon ? "py-1" : "py-2 lg:py-3"}`
            : isHome
              ? "min-w-[76px] px-2.5 py-2 sm:min-w-[86px]"
              : isCompact
                ? "min-w-[88px] px-2 py-1"
                : isPill
                  ? "min-w-[72px] px-3 py-1"
                  : "px-2 py-1.5 sm:min-w-[72px] min-w-[64px]"
        }
        ${isPill ? "rounded-full" : isCompact ? "rounded-xl" : "border-b-2 border-transparent"}
      `}
    >
      {/* Icon Skeleton */}
      {!isPill && (
        <div
          aria-hidden={hideIcon}
          className={`
            flex items-center justify-center overflow-hidden rounded-lg bg-default-100 transition-[width,height,transform,opacity] ${isLarge ? "duration-200 ease-in-out" : "duration-300"} motion-reduce:transition-none dark:bg-transparent
            ${
              isLarge
                ? "w-10 h-10 md:w-14 md:h-14 lg:w-16 lg:h-16"
                : isHome
                  ? "w-10 h-10 md:w-11 md:h-11"
                  : isCompact || isPill
                    ? "w-5 h-5"
                    : "w-9 h-9 md:w-10 md:h-10"
            }
            ${hideIcon ? `${isCompact ? "!w-0" : ""} !h-0 scale-90 opacity-0` : "scale-100 opacity-100"}
          `}
        >
          <Skeleton
            className={`
              rounded-lg
              ${
                isLarge
                  ? "w-6 h-6 md:w-9 md:h-9 lg:w-11 lg:h-11"
                  : isHome
                    ? "w-7 h-7 md:w-8 md:h-8"
                    : isCompact || isPill
                      ? "w-4 h-4"
                      : "w-6 h-6 md:w-7 md:h-7"
              }
            `}
          />
        </div>
      )}

      {/* Title Skeleton */}
      <Skeleton
        className={`
          h-3 rounded-sm mt-1
          ${isLarge ? "w-12 lg:h-4 lg:w-16" : isHome ? "w-16" : isCompact || isPill ? "mt-0 w-10" : "w-14"}
        `}
      />
    </div>
  );
};

export default SkeletonTabButton;
