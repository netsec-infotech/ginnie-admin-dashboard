import type { FC, RefObject } from "react";

export interface ReelPlaybackProgressProps {
  fillRef: RefObject<HTMLDivElement | null>;
  isHidden?: boolean;
  resetKey: string;
}

/** Thin mobile-only progress indicator driven by the active video element. */
const ReelPlaybackProgress: FC<ReelPlaybackProgressProps> = ({
  fillRef,
  isHidden = false,
  resetKey,
}) => {
  if (isHidden) return null;

  return (
    <div
      aria-hidden="true"
      className="pointer-events-none absolute inset-x-0 bottom-0 z-50 h-0.5 overflow-hidden bg-white/20 md:hidden"
      data-reel-progress
    >
      <div
        key={resetKey}
        ref={fillRef}
        className="h-full origin-left bg-white/90 transition-transform duration-200 ease-linear will-change-transform motion-reduce:transition-none"
        data-reel-progress-fill
        style={{ transform: "scaleX(0)" }}
      />
    </div>
  );
};

export default ReelPlaybackProgress;
