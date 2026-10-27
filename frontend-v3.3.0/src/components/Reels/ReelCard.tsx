import type { FC } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Eye, Heart, Play, ShoppingBag } from "lucide-react";
import Image from "next/image";
import { useTranslation } from "react-i18next";

import { formatCompactMetric } from "@/components/Media";
import type { Reel } from "@/components/Reels/types";

const warmedViewerPosters = new Set<string>();

const warmViewerPoster = (src: string) => {
  if (typeof window === "undefined" || warmedViewerPosters.has(src)) return;

  warmedViewerPosters.add(src);
  const image = new window.Image();
  image.decoding = "async";
  image.onerror = () => warmedViewerPosters.delete(src);
  image.src = src;
};

export interface ReelCardProps {
  reel: Reel;
  viewCount?: number;
  index?: number;
  variant?: "rail" | "grid";
  isDragging?: boolean;
  showLikes?: boolean;
  showTitle?: boolean;
  onOpen: (reel: Reel) => void;
}

const ReelCard: FC<ReelCardProps> = ({
  reel,
  viewCount,
  index = 0,
  variant = "rail",
  isDragging = false,
  showLikes = false,
  showTitle = true,
  onOpen,
}) => {
  const { t } = useTranslation();
  const prefersReducedMotion = useReducedMotion();
  const isGrid = variant === "grid";
  const likeCount = reel.likes + (reel.isLiked ? 1 : 0);
  const openLabel = t("reels.openLabel", {
    title: reel.title,
    publisher: reel.publisher,
    defaultValue: "Open {{title}} by {{publisher}}",
  });

  return (
    <motion.div
      className={
        isGrid
          ? "min-w-0"
          : "w-[calc((100%-0.75rem)/2)] shrink-0 snap-start sm:w-[164px] lg:w-[184px]"
      }
      role="listitem"
      whileHover={prefersReducedMotion || isDragging ? undefined : { y: -3 }}
      transition={{ duration: prefersReducedMotion ? 0 : 0.2 }}
    >
      <button
        type="button"
        aria-label={
          showLikes
            ? `${openLabel}, ${t("reels.likesCount", { count: likeCount })}`
            : openLabel
        }
        className={`group/reel w-full rounded-[1.25rem] text-left focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
          !isGrid && isDragging ? "cursor-grabbing" : "cursor-pointer"
        }`}
        data-reel-card-id={reel.id}
        onDragStart={(event) => event.preventDefault()}
        onClick={() => onOpen(reel)}
        onFocus={() => warmViewerPoster(reel.posterSrc)}
        onPointerDown={() => warmViewerPoster(reel.posterSrc)}
        onPointerEnter={() => warmViewerPoster(reel.posterSrc)}
      >
        <span
          className="relative block aspect-9/16 overflow-hidden rounded-[1.25rem] bg-content2 ring-1 ring-foreground/10 transition-transform duration-200 group-active/reel:scale-[0.985] motion-reduce:transition-none"
          data-reel-media-frame
        >
          <Image
            fill
            alt=""
            aria-hidden="true"
            draggable={false}
            className="transform-gpu object-cover transition-transform duration-500 [-webkit-user-drag:none] group-hover/reel:scale-[1.035] motion-reduce:transition-none"
            loading={index < 4 ? "eager" : "lazy"}
            sizes="(max-width: 639px) 138px, (max-width: 1023px) 164px, 184px"
            src={reel.posterSrc}
            style={{
              objectPosition: reel.posterPosition ?? "center",
            }}
          />
          <span className="pointer-events-none absolute inset-0 bg-linear-to-b from-black/15 via-transparent to-black/90" />

          <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-black/55 px-2 py-1 text-[10px] font-bold text-white backdrop-blur-md">
            <Play aria-hidden="true" className="fill-white" size={10} />
            {reel.durationLabel}
          </span>

          {isGrid && (reel.products?.length ?? 0) > 0 && (
            <span
              aria-label={t("pages.play.productsAvailable", {
                count: reel.products?.length ?? 0,
                defaultValue: "Linked products: {{count}}",
              })}
              className="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-black/55 px-2 py-1 text-[10px] font-bold text-white backdrop-blur-md"
            >
              <ShoppingBag aria-hidden="true" size={10} />
              <span aria-hidden="true">{reel.products?.length}</span>
            </span>
          )}

          <span className="absolute inset-x-3 bottom-3 text-white">
            {showTitle && (
              <span className="line-clamp-2 text-sm font-extrabold leading-tight drop-shadow-md">
                {reel.title}
              </span>
            )}
            {isGrid ? (
              <span className="mt-2 block text-[10px] font-semibold leading-snug text-white/90">
                <span className="block truncate">{reel.publisher}</span>
                <span className="mt-0.5 flex items-center justify-between gap-2">
                  <span className="truncate">
                    {reel.distance} · {reel.deliveryEstimate}
                  </span>
                  <span className="inline-flex shrink-0 items-center gap-2">
                    {showLikes && (
                      <span aria-hidden="true" className="inline-flex items-center gap-1">
                        <Heart aria-hidden="true" size={10} />
                        {formatCompactMetric(likeCount)}
                      </span>
                    )}
                    <span className="inline-flex items-center gap-1">
                      <Eye aria-hidden="true" size={10} />
                      {formatCompactMetric(viewCount ?? reel.views)}
                    </span>
                  </span>
                </span>
              </span>
            ) : (
              <span className="mt-2 flex items-center justify-between gap-2 text-[10px] font-semibold text-white/90">
                <span className="truncate">{reel.publisher}</span>
                <span className="inline-flex shrink-0 items-center gap-2">
                  {showLikes && (
                    <span aria-hidden="true" className="inline-flex items-center gap-1">
                      <Heart aria-hidden="true" size={11} />
                      {formatCompactMetric(likeCount)}
                    </span>
                  )}
                  <span className="inline-flex items-center gap-1">
                    <Eye aria-hidden="true" size={11} />
                    {formatCompactMetric(viewCount ?? reel.views)}
                  </span>
                </span>
              </span>
            )}
          </span>

          <span className="pointer-events-none absolute left-1/2 top-1/2 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 scale-90 items-center justify-center rounded-full border border-white/30 bg-black/35 text-white opacity-0 backdrop-blur-md transition-[opacity,transform] duration-200 group-hover/reel:scale-100 group-hover/reel:opacity-100 group-focus-visible/reel:scale-100 group-focus-visible/reel:opacity-100 motion-reduce:transition-none">
            <Play aria-hidden="true" className="ml-0.5 fill-white" size={19} />
          </span>
        </span>
      </button>
    </motion.div>
  );
};

export default ReelCard;
