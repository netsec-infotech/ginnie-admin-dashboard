import React, { useEffect, useRef } from "react";
import { Image } from "@heroui/react";
import { LayoutGrid } from "lucide-react";
import { motion, useReducedMotion } from "framer-motion";

interface Category {
  id: number;
  title: string;
  slug: string;
  image: string;
  icon: string;
  active_icon: string;
  description: string | null;
  status: string;
}

interface TabButtonProps {
  slug: string;
  title: string;
  category?: Category | null;
  isSelected: boolean;
  isLoading?: boolean;
  onClick?: () => void;
  onPointerEnter?: React.PointerEventHandler<HTMLButtonElement>;
  onFocus?: React.FocusEventHandler<HTMLButtonElement>;
  staticIcon?: React.ReactNode;
  titleOnly?: boolean;
  hideIcon?: boolean;
  size?: "sm" | "lg" | "compact" | "home" | "pill";
  surface?: "default" | "home";
}

// Matches the navbar enter easing
const HOME_LAYOUT_TRANSITION = {
  duration: 0.38,
  ease: [0.0, 0, 0.2, 1] as const,
};

const TabButton: React.FC<TabButtonProps> = ({
  slug,
  title,
  category = null,
  isSelected,
  isLoading = false,
  onClick,
  onPointerEnter,
  onFocus,
  staticIcon,
  titleOnly = false,
  hideIcon = false,
  size = "sm",
  surface = "default",
}) => {
  const buttonRef = useRef<HTMLButtonElement | null>(null);
  const prefersReducedMotion = useReducedMotion();

  // Auto trigger when "see-more" is visible
  useEffect(() => {
    if (slug !== "see-more" || !buttonRef.current) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && !isLoading) {
          onClick?.();
        }
      },
      { threshold: 1 },
    );

    observer.observe(buttonRef.current);
    return () => observer.disconnect();
  }, [slug, isLoading, onClick]);

  const isLarge = size === "lg";
  const isCompact = size === "compact";
  const isPill = size === "pill";
  const isHomeSize = size === "home";
  const isHomeSurface = surface === "home";
  const iconUrl = isHomeSurface
    ? category?.active_icon || category?.icon || category?.image
    : isSelected
      ? category?.active_icon || category?.icon || category?.image
      : category?.icon || category?.image || category?.active_icon;
  const shouldAnimateHomeLayout =
    isHomeSurface && isCompact && !prefersReducedMotion;
  const standardLayoutTransition = isLarge
    ? "duration-200 ease-[cubic-bezier(0.4,0,0.2,1)]"
    : `${isHomeSize ? "duration-300" : "duration-[380ms]"} ease-[cubic-bezier(0.0,0,0.2,1)]`;

  return (
    <motion.button
      ref={buttonRef}
      layout={shouldAnimateHomeLayout}
      transition={
        shouldAnimateHomeLayout ? { layout: HOME_LAYOUT_TRANSITION } : undefined
      }
      type="button"
      onClick={onClick}
      onPointerEnter={onPointerEnter}
      onFocus={onFocus}
      disabled={isLoading}
      aria-pressed={isSelected}
      className={`
        flex items-center justify-center ${titleOnly || isPill ? "flex-row gap-2" : isCompact ? `flex-row ${hideIcon ? "gap-0" : "gap-2"}` : hideIcon ? "flex-col gap-0" : "flex-col gap-1.5"}
        ${
          isLarge
            ? `px-2 sm:min-w-[80px] lg:min-w-[110px] lg:px-4 ${hideIcon ? "py-1" : "py-2 lg:py-3"}`
            : isHomeSize
              ? "min-w-[76px] px-2.5 py-2 sm:min-w-[86px]"
              : isCompact
                ? "min-w-fit px-2.5 py-1"
                : isPill
                  ? "min-w-fit px-3 py-1"
                  : isHomeSurface
                    ? "px-2 py-1.5 sm:min-w-[66px] min-w-[54px]"
                    : "px-2 py-1.5 sm:min-w-[72px] min-w-[56px]"
        }
        transition-[min-width,padding,gap,background-color,border-color,box-shadow,color,border-radius] motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--home-category-focus-ring)]
        ${
          isPill
            ? "transition-none rounded-full"
            : isCompact
              ? "duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)] rounded-xl"
              : `${standardLayoutTransition} ${isHomeSurface ? "rounded-2xl" : "rounded-xl"}`
        }
        ${
          isPill
            ? isSelected
              ? "border border-primary/30 bg-primary/12 text-primary shadow-sm dark:border-primary/40 dark:bg-primary/18 dark:text-primary-300"
              : "border border-default-200/60 bg-default-100/70 text-default-700 hover:border-default-300/70 hover:bg-default-200/60 dark:border-white/10 dark:bg-white/5 dark:text-default-300 dark:hover:border-white/20 dark:hover:bg-white/10"
            : isCompact
              ? isSelected
                ? "border border-primary/25 bg-primary/10 text-primary shadow-sm dark:border-primary/30 dark:bg-primary/15 dark:text-primary-300"
                : "border border-transparent text-default-700 hover:border-default-200/70 hover:bg-default-100/60 dark:text-default-300 dark:hover:border-white/10 dark:hover:bg-white/5"
              : isSelected
                ? isHomeSurface
                  ? "bg-white/50 text-primary dark:bg-white/50 dark:text-primary-400"
                  : "bg-default-100 text-primary dark:bg-[#222]"
                : isHomeSurface
                  ? "text-default-700 hover:bg-white/50 dark:text-default-200 dark:hover:bg-white/50"
                  : "bg-transparent text-default-600 dark:text-zinc-400 hover:bg-default-100 hover:text-default-900 dark:hover:bg-[#222] dark:hover:text-zinc-200"
        }
        ${isLoading ? "opacity-50 cursor-not-allowed" : "cursor-pointer"}
        ${isHomeSurface ? "!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))] backdrop-blur-sm" : ""}
      `}
    >
      {/* Icon */}
      {!titleOnly && !isPill && (
        <motion.div
          aria-hidden={hideIcon}
          layout={shouldAnimateHomeLayout ? "position" : false}
          transition={{ layout: HOME_LAYOUT_TRANSITION }}
          className={`
            flex shrink-0 transform-gpu items-center justify-center
            ${isPill ? "rounded-full border-none" : isCompact ? "rounded-md border border-transparent" : isHomeSurface ? "rounded-xl !border-0 !bg-transparent" : "rounded-xl border border-default-200/50 dark:border-white/5"}
            overflow-hidden transition-[width,height,transform,border-radius,background-color,border-color,opacity] motion-reduce:transition-none
            ${isPill || isCompact ? "duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)]" : standardLayoutTransition}
            ${
              isLarge
                ? "w-10 h-10 md:w-14 md:h-14 lg:w-16 lg:h-16"
                : isHomeSize
                  ? "w-10 h-10 md:w-11 md:h-11"
                  : isCompact || isPill
                    ? "w-5 h-5"
                    : "w-9 h-9 md:w-11 md:h-11"
            }
            ${
              isCompact || isPill
                ? isSelected
                  ? "bg-transparent text-primary dark:text-primary-300"
                  : "bg-transparent text-default-600 dark:text-default-300"
                : isSelected
                  ? isHomeSurface
                    ? "!border-0 !bg-transparent text-primary dark:!bg-transparent dark:text-primary-400"
                    : "bg-primary/20 text-primary dark:bg-primary/30"
                  : isHomeSurface
                    ? "!border-0 !bg-transparent text-default-600 dark:!bg-transparent dark:text-default-200"
                    : "bg-default-100 dark:bg-white/5 text-default-600 dark:text-zinc-300"
            }
            ${isHomeSurface ? "!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))]" : ""}
            ${hideIcon ? `${isCompact ? "!w-0" : ""} !h-0 scale-90 opacity-0` : "scale-100 opacity-100"}
          `}
        >
          {iconUrl ? (
            <Image
              src={iconUrl}
              alt={title}
              loading="eager"
              radius="none"
              className={`
                object-contain transition-[width,height,transform] motion-reduce:transition-none
                ${isPill || isCompact ? "duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)]" : standardLayoutTransition}
                ${
                  isLarge
                    ? "w-6 h-6 md:w-9 md:h-9 lg:w-11 lg:h-11"
                    : isHomeSize
                      ? "w-7 h-7 md:w-8 md:h-8"
                      : isCompact || isPill
                        ? "w-4 h-4"
                        : "w-6 h-6 md:w-8 md:h-8"
                }
              `}
            />
          ) : (
            staticIcon || (
              <LayoutGrid
                className={`
                ${
                  isLarge
                    ? "w-6 h-6 md:w-9 md:h-9 lg:w-11 lg:h-11"
                    : isHomeSize
                      ? "w-7 h-7 md:w-8 md:h-8"
                      : isCompact || isPill
                        ? "w-4 h-4"
                        : "w-6 h-6 md:w-8 md:h-8"
                }
                ${
                  isHomeSurface
                    ? isSelected
                      ? "text-primary dark:text-primary-400"
                      : "text-default-600 dark:text-default-200"
                    : isSelected
                      ? "text-primary"
                      : "text-default-400"
                }
                ${isHomeSurface ? "!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))]" : ""}
              `}
              />
            )
          )}
        </motion.div>
      )}

      {/* Title */}
      <motion.span
        layout={shouldAnimateHomeLayout ? "position" : false}
        transition={
          shouldAnimateHomeLayout
            ? { layout: HOME_LAYOUT_TRANSITION }
            : undefined
        }
        className={`
          text-xxs sm:text-xs text-center leading-tight truncate transition-[max-width,font-size,color] motion-reduce:transition-none
          ${isPill ? "transition-none" : isCompact ? "duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)]" : standardLayoutTransition}
          ${
            isLarge
              ? "lg:text-sm max-w-[80px] sm:max-w-[100px] lg:max-w-[120px]"
              : isHomeSize
                ? "text-xs sm:text-[13px] max-w-[76px] sm:max-w-[86px]"
                : isPill
                  ? "text-xs max-w-[104px] text-left font-medium"
                  : isCompact
                    ? "text-xs max-w-[104px] text-left"
                    : "max-w-[60px] sm:max-w-[80px]"
          }
          ${
            isHomeSurface
              ? isSelected
                ? "font-semibold text-primary dark:text-primary-400"
                : "font-medium text-default-700 dark:text-default-500"
              : isSelected
                ? "font-semibold text-primary dark:text-primary-400"
                : "font-medium"
          }
          ${isHomeSurface ? "!text-[var(--home-header-foreground,var(--home-category-foreground,#111827))]" : ""}
        `}
      >
        {title}
      </motion.span>

      {/* Loader */}
      {isLoading && (
        <div className="mt-1 animate-spin rounded-sm h-4 w-4 border-b-2 border-secondary" />
      )}
    </motion.button>
  );
};

export default TabButton;
