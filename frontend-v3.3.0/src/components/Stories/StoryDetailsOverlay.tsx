import type { FC } from "react";
import { Button } from "@heroui/react";
import { motion, useReducedMotion } from "framer-motion";
import { ArrowUpRight, Eye, Heart, Share2 } from "lucide-react";
import { useTranslation } from "react-i18next";

import { formatCompactMetric } from "@/components/Media";
import type { StoryItem } from "@/components/Stories/types";

export interface StoryDetailsOverlayProps {
  item: StoryItem;
  isLiked: boolean;
  displayedLikeCount?: number;
  shareFeedback: string | null;
  onLike: () => void;
  onShare: () => void;
  onNavigate: (href: string) => void;
}

const StoryDetailsOverlay: FC<StoryDetailsOverlayProps> = ({
  item,
  isLiked,
  displayedLikeCount,
  shareFeedback,
  onLike,
  onShare,
  onNavigate,
}) => {
  const { t } = useTranslation();
  const prefersReducedMotion = useReducedMotion();

  return (
    <div className="pointer-events-none absolute inset-x-0 bottom-0 z-40 bg-linear-to-t from-black/90 via-black/45 to-transparent px-[max(1rem,env(safe-area-inset-left))] pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-36 text-white sm:px-[max(1.5rem,env(safe-area-inset-left))]">
      {(item.title || item.subtitle) && (
        <div className="mb-5 max-w-[24rem] drop-shadow-[0_2px_10px_rgba(0,0,0,0.65)]">
          {item.title && (
            <h3 className="line-clamp-2 text-balance text-2xl font-black leading-tight tracking-[-0.025em] sm:text-[1.75rem]">
              {item.title}
            </h3>
          )}
          {item.subtitle && (
            <p className="mt-2 line-clamp-3 text-sm font-medium leading-relaxed text-white/85 sm:text-base">
              {item.subtitle}
            </p>
          )}
        </div>
      )}
      <div className="mb-4 flex items-center gap-2">
        {displayedLikeCount !== undefined && (
          <button
            data-story-control
            type="button"
            aria-label={
              isLiked
                ? t("stories.viewer.unlike", { defaultValue: "Unlike story" })
                : t("stories.viewer.like", { defaultValue: "Like story" })
            }
            aria-pressed={isLiked}
            className={`pointer-events-auto inline-flex h-10 items-center gap-2 rounded-full border px-3 text-sm font-bold shadow-lg backdrop-blur-md transition-colors motion-reduce:transition-none ${
              isLiked
                ? "border-rose-400/40 bg-rose-500/20 text-rose-400"
                : "border-white/15 bg-black/30 text-white"
            }`}
            onClick={onLike}
          >
            <motion.span
              key={`${item.id}-${isLiked}`}
              aria-hidden="true"
              initial={prefersReducedMotion ? false : { scale: 0.65 }}
              animate={{ scale: isLiked ? [0.65, 1.35, 0.92, 1] : 1 }}
              transition={
                prefersReducedMotion
                  ? { duration: 0 }
                  : { duration: 0.42, ease: [0.22, 1, 0.36, 1] }
              }
            >
              <Heart
                fill={isLiked ? "currentColor" : "none"}
                size={20}
                strokeWidth={2.2}
              />
            </motion.span>
            <span>{formatCompactMetric(displayedLikeCount)}</span>
          </button>
        )}

        <button
          data-story-control
          type="button"
          aria-label={t("stories.viewer.share", {
            defaultValue: "Share story",
          })}
          className="pointer-events-auto flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-black/30 text-white shadow-lg backdrop-blur-md transition-colors hover:bg-white/15 motion-reduce:transition-none"
          onClick={onShare}
        >
          <Share2 aria-hidden="true" size={19} />
        </button>

        {item.views24h !== undefined && (
          <span
            aria-label={t("stories.viewer.views", {
              count: formatCompactMetric(item.views24h),
              defaultValue: "{{count}} views",
            })}
            className="ml-auto inline-flex h-10 items-center gap-1.5 rounded-full border border-white/10 bg-black/25 px-3 text-xs font-semibold text-white/85 backdrop-blur-md"
          >
            <Eye aria-hidden="true" size={17} />
            <span>{formatCompactMetric(item.views24h)}</span>
          </span>
        )}
      </div>

      {shareFeedback && (
        <p
          aria-live="polite"
          className="mb-3 text-center text-xs font-semibold text-white/90"
        >
          {shareFeedback}
        </p>
      )}
      {item.href && item.ctaLabel && (
        <div className="pointer-events-auto">
          <Button
            data-story-control
            fullWidth
            className="border border-white/25 font-bold text-zinc-950 shadow-[0_14px_40px_rgba(0,0,0,0.28)]"
            endContent={<ArrowUpRight aria-hidden="true" size={18} />}
            radius="full"
            size="lg"
            style={{ backgroundColor: item.visual.accent }}
            onPress={() => onNavigate(item.href!)}
          >
            {item.ctaLabel}
          </Button>
        </div>
      )}
    </div>
  );
};

export default StoryDetailsOverlay;
