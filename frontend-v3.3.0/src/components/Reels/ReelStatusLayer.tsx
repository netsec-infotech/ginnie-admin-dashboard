import type { FC } from "react";
import { Button, Spinner } from "@heroui/react";
import { Play, RotateCcw } from "lucide-react";
import { useTranslation } from "react-i18next";

export interface ReelStatusLayerProps {
  isHidden?: boolean;
  isPaused: boolean;
  isBuffering: boolean;
  showBufferSpinner: boolean;
  mediaFailed: boolean;
  onRetry: () => void;
  onNext: () => void;
}

const ReelStatusLayer: FC<ReelStatusLayerProps> = ({
  isHidden = false,
  isPaused,
  isBuffering,
  showBufferSpinner,
  mediaFailed,
  onRetry,
  onNext,
}) => {
  const { t } = useTranslation();

  if (isHidden) return null;

  if (mediaFailed) {
    return (
      <div
        className="absolute inset-0 z-20 flex items-center justify-center bg-black/45 px-8 text-center text-white backdrop-blur-sm"
        role="alert"
      >
        <div>
          <p className="text-lg font-bold">
            {t("reels.viewer.unavailable", {
              defaultValue: "This reel could not be played",
            })}
          </p>
          <p className="mt-2 text-sm text-white/80">
            {t("reels.viewer.unavailableDescription", {
              defaultValue:
                "The seller details and destination are still available.",
            })}
          </p>
          <div className="mt-5 flex justify-center gap-2">
            <Button
              data-reel-control
              startContent={<RotateCcw aria-hidden="true" size={17} />}
              variant="flat"
              onPress={onRetry}
            >
              {t("reels.viewer.retry", { defaultValue: "Retry" })}
            </Button>
            <Button data-reel-control color="primary" onPress={onNext}>
              {t("reels.viewer.next", { defaultValue: "Next reel" })}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  if (showBufferSpinner) {
    return (
      <div
        aria-live="polite"
        className="pointer-events-none absolute inset-0 z-20 flex items-center justify-center"
      >
        <span className="flex items-center gap-2 rounded-full bg-black/55 px-4 py-2 text-xs font-semibold text-white backdrop-blur-md">
          <Spinner color="white" size="sm" />
          {t("reels.viewer.loading", { defaultValue: "Loading reel" })}
        </span>
      </div>
    );
  }

  if (isPaused && !isBuffering) {
    return (
      <div
        aria-live="polite"
        className="pointer-events-none absolute inset-0 z-20 flex items-center justify-center"
      >
        <span className="flex h-16 w-16 items-center justify-center rounded-full border border-white/20 bg-black/45 text-white backdrop-blur-md">
          <Play aria-hidden="true" className="ml-1 fill-white" size={27} />
          <span className="sr-only">
            {t("reels.viewer.paused", { defaultValue: "Reel paused" })}
          </span>
        </span>
      </div>
    );
  }

  return null;
};

export default ReelStatusLayer;
