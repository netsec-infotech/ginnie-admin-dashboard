import type { FC } from "react";
import { Spinner } from "@heroui/react";
import { motion, useReducedMotion } from "framer-motion";
import { Pause } from "lucide-react";
import { useTranslation } from "react-i18next";

export interface StoryStatusLayerProps {
  showGestureHint: boolean;
  showHoldIndicator: boolean;
  isBuffering: boolean;
  mediaFailed: boolean;
  unavailable: boolean;
}

const StoryStatusLayer: FC<StoryStatusLayerProps> = ({
  showGestureHint,
  showHoldIndicator,
  isBuffering,
  mediaFailed,
  unavailable,
}) => {
  const { t } = useTranslation();
  const prefersReducedMotion = useReducedMotion();
  const canShowOverlay = !unavailable && !mediaFailed;

  return (
    <>
      {showGestureHint && canShowOverlay && (
        <motion.div
          aria-live="polite"
          className="pointer-events-none absolute inset-x-0 bottom-24 z-40 flex justify-center px-5"
          initial={prefersReducedMotion ? false : { opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: prefersReducedMotion ? 0 : 0.25 }}
        >
          <div className="rounded-full border border-white/15 bg-black/45 px-4 py-2 text-center text-xs font-semibold text-white/90 shadow-xl backdrop-blur-md">
            {t("stories.viewer.gestureHint", {
              defaultValue:
                "Tap left for previous · Tap right for next · Hold to pause · Swipe between stores",
            })}
          </div>
        </motion.div>
      )}

      {isBuffering && !mediaFailed && (
        <div className="pointer-events-none absolute inset-0 z-30 flex items-center justify-center">
          <Spinner
            color="white"
            label={t("stories.viewer.loading", {
              defaultValue: "Loading story",
            })}
          />
        </div>
      )}

      {showHoldIndicator && canShowOverlay && (
        <div
          aria-live="polite"
          className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center"
        >
          <div className="flex h-14 w-14 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-md">
            <Pause aria-hidden="true" fill="currentColor" size={26} />
            <span className="sr-only">
              {t("stories.viewer.paused", { defaultValue: "Paused" })}
            </span>
          </div>
        </div>
      )}
    </>
  );
};

export default StoryStatusLayer;
