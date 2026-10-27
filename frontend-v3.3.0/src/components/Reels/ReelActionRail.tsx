import type { FC } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Heart, Share2, Volume2, VolumeX } from "lucide-react";
import { useTranslation } from "react-i18next";

import { formatCompactMetric } from "@/components/Media";

export interface ReelActionRailProps {
  isHidden?: boolean;
  isLiked: boolean;
  likeCount: number;
  isMuted: boolean;
  positionClassName: string;
  onLike: () => void;
  onShare: () => void;
  onMutedChange: (muted: boolean) => void;
}

const ReelActionRail: FC<ReelActionRailProps> = ({
  isHidden = false,
  isLiked,
  likeCount,
  isMuted,
  positionClassName,
  onLike,
  onShare,
  onMutedChange,
}) => {
  const { t } = useTranslation();
  const prefersReducedMotion = useReducedMotion();
  const actionClassName =
    "pointer-events-auto flex h-11 w-11 items-center justify-center rounded-full border-0 bg-black/35 text-white outline-none backdrop-blur-md transition-[background-color,transform] hover:bg-black/55 focus-visible:scale-105 focus-visible:bg-black/70 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-black/60 motion-reduce:transition-none";

  return (
    <div
      inert={isHidden ? true : undefined}
      aria-hidden={isHidden || undefined}
      data-reel-chrome="actions"
      className={`absolute right-3 z-40 flex flex-col items-center gap-2.5 text-white transition-opacity duration-150 motion-reduce:transition-none sm:right-4 ${positionClassName} ${
        isHidden ? "opacity-0" : "opacity-100"
      }`}
    >
      <div className="flex flex-col items-center gap-0.5">
        <motion.button
          data-reel-control
          type="button"
          aria-label={t(isLiked ? "reels.viewer.unlike" : "reels.viewer.like", {
            defaultValue: isLiked ? "Unlike reel" : "Like reel",
          })}
          aria-pressed={isLiked}
          className={actionClassName}
          whileTap={prefersReducedMotion ? undefined : { scale: 0.82 }}
          onClick={onLike}
        >
          <motion.span
            animate={
              prefersReducedMotion || !isLiked
                ? { scale: 1 }
                : { scale: [1, 1.32, 0.92, 1] }
            }
            transition={{
              duration: prefersReducedMotion ? 0 : isLiked ? 0.42 : 0.16,
              ease: [0.22, 1, 0.36, 1],
            }}
          >
            <Heart
              aria-hidden="true"
              className={isLiked ? "fill-rose-500 text-rose-500" : undefined}
              size={22}
            />
          </motion.span>
        </motion.button>
        <span className="text-[10px] font-bold drop-shadow-md">
          {formatCompactMetric(likeCount)}
        </span>
      </div>

      <div className="flex flex-col items-center gap-0.5">
        <button
          data-reel-control
          type="button"
          aria-label={t("reels.viewer.share", { defaultValue: "Share reel" })}
          className={actionClassName}
          onClick={onShare}
        >
          <Share2 aria-hidden="true" className="-translate-x-px" size={21} />
        </button>
        <span className="text-[10px] font-bold drop-shadow-md">
          {t("reels.viewer.shareLabel", { defaultValue: "Share" })}
        </span>
      </div>

      <div className="flex flex-col items-center gap-0.5">
        <button
          data-reel-control
          type="button"
          aria-label={
            isMuted
              ? t("reels.viewer.unmute", { defaultValue: "Unmute" })
              : t("reels.viewer.mute", { defaultValue: "Mute" })
          }
          className={actionClassName}
          onClick={() => onMutedChange(!isMuted)}
        >
          {isMuted ? (
            <VolumeX aria-hidden="true" size={21} />
          ) : (
            <Volume2 aria-hidden="true" size={21} />
          )}
        </button>
        <span className="text-[10px] font-bold drop-shadow-md">
          {t("reels.viewer.soundLabel", { defaultValue: "Sound" })}
        </span>
      </div>
    </div>
  );
};

export default ReelActionRail;
