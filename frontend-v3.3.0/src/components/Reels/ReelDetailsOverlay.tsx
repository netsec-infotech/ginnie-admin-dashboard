import type { FC } from "react";
import { Button } from "@heroui/react";
import { ArrowUpRight, Eye } from "lucide-react";
import { useTranslation } from "react-i18next";

import { formatCompactMetric } from "@/components/Media";
import ReelProductStrip from "@/components/Reels/ReelProductStrip";
import type { Reel } from "@/components/Reels/types";

export interface ReelDetailsOverlayProps {
  reel: Reel;
  viewCount?: number;
  isHidden?: boolean;
  shareFeedback: string | null;
  onNavigate: (href: string) => void;
}

const ReelDetailsOverlay: FC<ReelDetailsOverlayProps> = ({
  reel,
  viewCount,
  isHidden = false,
  shareFeedback,
  onNavigate,
}) => {
  const { t } = useTranslation();
  const products = reel.products ?? [];

  return (
    <div
      inert={isHidden ? true : undefined}
      aria-hidden={isHidden || undefined}
      data-reel-chrome="details"
      data-reel-bottom-overlay
      className={`pointer-events-none absolute inset-x-0 -bottom-px z-30 bg-linear-to-t from-black/55 via-black/30 to-transparent px-[max(1rem,env(safe-area-inset-left))] pb-[max(0.9rem,env(safe-area-inset-bottom))] pt-24 text-white transition-opacity duration-150 motion-reduce:transition-none sm:px-5 ${
        isHidden ? "opacity-0" : "opacity-100"
      }`}
    >
      <div className="max-w-[calc(100%-3.75rem)] drop-shadow-[0_2px_10px_rgba(0,0,0,0.7)]">
        <h3 className="line-clamp-1 text-lg font-extrabold leading-tight tracking-[-0.02em] sm:text-xl">
          {reel.title}
        </h3>
        <p className="mt-1 line-clamp-1 text-xs leading-relaxed text-white/85 sm:text-sm">
          {reel.caption}
        </p>
        <p className="mt-1.5 flex items-center gap-2 text-[11px] font-semibold text-white/80">
          <span>{`${reel.publisher} · ${reel.distance}`}</span>
          <span aria-hidden="true">·</span>
          <span
            aria-label={t("reels.viewer.views", {
              count: formatCompactMetric(viewCount ?? reel.views),
              defaultValue: "{{count}} views",
            })}
            className="inline-flex items-center gap-1"
          >
            <Eye aria-hidden="true" size={12} />
            <span aria-hidden="true">
              {formatCompactMetric(viewCount ?? reel.views)}
            </span>
          </span>
        </p>
      </div>

      <ReelProductStrip
        products={products}
        seeAll={reel.cta}
        onNavigate={onNavigate}
      />

      {reel.cta && (
        <div className="pointer-events-auto mt-3 flex justify-end">
          <Button
            data-reel-control
            className="h-9 min-w-0 shrink-0 bg-white/95 px-4 text-xs font-bold text-black shadow-[0_5px_18px_rgba(0,0,0,0.2)] transition-[background-color,transform] hover:bg-white focus-visible:scale-[1.03] focus-visible:bg-white"
            endContent={<ArrowUpRight aria-hidden="true" size={14} />}
            radius="full"
            size="sm"
            onPress={() => onNavigate(reel.cta!.href)}
          >
            {reel.cta.label}
          </Button>
        </div>
      )}

      {shareFeedback && (
        <span
          aria-live="polite"
          className="mt-2 block text-center text-xs font-semibold text-white"
        >
          {shareFeedback}
        </span>
      )}
    </div>
  );
};

export default ReelDetailsOverlay;
