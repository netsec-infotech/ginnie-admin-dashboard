import type { FC } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Check } from "lucide-react";
import { Image } from "@heroui/react";
import { useTranslation } from "react-i18next";

import StoryArtwork from "@/components/Stories/StoryArtwork";
import type { StoryGroup } from "@/components/Stories/types";
import type { StoryGroupProgress } from "@/components/Stories/storyProgress";

const STORY_LAYOUT_TRANSITION = {
  type: "spring" as const,
  stiffness: 330,
  damping: 34,
  mass: 0.82,
};

const STORY_CARD_BACKGROUNDS = [
  "linear-gradient(155deg, #425768 0%, #263843 52%, #171e25 100%)",
  "linear-gradient(155deg, #68534a 0%, #40302c 52%, #211a1b 100%)",
  "linear-gradient(155deg, #52634f 0%, #304337 52%, #19231d 100%)",
  "linear-gradient(155deg, #60495f 0%, #3b2c40 52%, #201a27 100%)",
  "linear-gradient(155deg, #436063 0%, #2a4047 52%, #18252b 100%)",
] as const;

export type StoryTrayStyle = "avatar_circle" | "story_card";

export interface StoryTrayItemProps {
  group: StoryGroup;
  progress: StoryGroupProgress;
  isDragging?: boolean;
  ringColor?: string;
  showProfileName?: boolean;
  trayStyle?: StoryTrayStyle;
  onOpen: (group: StoryGroup) => void;
}

const StoryTrayItem: FC<StoryTrayItemProps> = ({
  group,
  progress,
  isDragging = false,
  ringColor,
  showProfileName = true,
  trayStyle = "avatar_circle",
  onOpen,
}) => {
  const { t } = useTranslation();
  const prefersReducedMotion = useReducedMotion();
  const publisherLabel =
    group.publisherType === "admin"
      ? t("stories.viewer.official", { defaultValue: "Official" })
      : t("stories.viewer.seller", { defaultValue: "Seller" });
  const statusLabel =
    progress.state === "seen"
      ? t("stories.viewed", { defaultValue: "Viewed" })
      : progress.state === "partial"
        ? t("stories.viewedProgress", {
            seen: progress.seen,
            total: progress.total,
            defaultValue: "{{seen}}/{{total}} viewed",
          })
        : t("stories.newCount", {
            count: progress.unseen,
            defaultValue: "{{count}} new",
          });
  const viewedPercentage =
    progress.total > 0
      ? Math.round(
          Math.min(100, Math.max(0, (progress.seen / progress.total) * 100)) *
            100,
        ) / 100
      : 0;
  const unseenPercentage = Math.round((100 - viewedPercentage) * 100) / 100;
  const ringClass =
    progress.state === "seen"
      ? "bg-default-300"
      : progress.state === "partial"
        ? ""
        : ringColor
          ? ""
          : "bg-linear-to-tr from-primary via-fuchsia-500 to-orange-400";
  const ringStyle =
    progress.state === "partial"
      ? {
          background: `conic-gradient(${ringColor ?? "#f97316"} 0 ${unseenPercentage}%, #d4d4d8 ${unseenPercentage}% 100%)`,
        }
      : progress.state === "unseen" && ringColor
        ? { backgroundColor: ringColor }
        : undefined;
  const firstItem = group.items[0];
  const storyPreviewSrc =
    firstItem?.mediaType === "image"
      ? firstItem.imageSrc
      : firstItem?.posterSrc;
  const cardBackgroundSrc = storyPreviewSrc ?? group.coverSrc;
  const colorSeed = Array.from(group.id).reduce(
    (hash, char) => Math.imul(hash ^ char.charCodeAt(0), 16777619),
    2166136261,
  );
  const cardBackground =
    STORY_CARD_BACKGROUNDS[(colorSeed >>> 0) % STORY_CARD_BACKGROUNDS.length];
  const ariaLabel = `${group.title}, ${statusLabel}, ${publisherLabel}, ${group.subtitle}`;

  return (
    <motion.div
      className={
        trayStyle === "story_card"
          ? "w-[94px] shrink-0 snap-start sm:w-[104px]"
          : "w-[76px] shrink-0 snap-start sm:w-[82px]"
      }
      data-story-group-id={group.id}
      data-story-style={trayStyle}
      layout={prefersReducedMotion ? false : "position"}
      role="listitem"
      transition={{
        layout: prefersReducedMotion
          ? { duration: 0 }
          : STORY_LAYOUT_TRANSITION,
      }}
    >
      <button
        type="button"
        aria-label={ariaLabel}
        className={`group/story flex w-full flex-col items-center focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
          trayStyle === "story_card" ? "rounded-[14px]" : "rounded-2xl"
        } ${isDragging ? "cursor-grabbing" : "cursor-pointer"}`}
        data-state={progress.state}
        onClick={() => onOpen(group)}
      >
        {trayStyle === "story_card" ? (
          <span
            className="relative isolate block h-[136px] w-full overflow-hidden rounded-[14px] shadow-sm transition-[transform,box-shadow] duration-200 group-hover/story:-translate-y-0.5 group-hover/story:shadow-md group-active/story:translate-y-0 group-active/story:shadow-sm motion-reduce:transition-none sm:h-[148px]"
            style={{ background: cardBackground }}
          >
            {cardBackgroundSrc && (
              <Image
                removeWrapper
                alt=""
                aria-hidden="true"
                className="pointer-events-none absolute -inset-3 h-[calc(100%+1.5rem)] w-[calc(100%+1.5rem)] max-w-none scale-110 object-cover blur-lg saturate-50"
                disableAnimation
                draggable={false}
                loading="lazy"
                src={cardBackgroundSrc}
                style={{ opacity: storyPreviewSrc ? 0.42 : 0.18 }}
              />
            )}
            <span className="pointer-events-none absolute inset-0 z-20 bg-linear-to-b from-white/5 via-transparent to-black/20" />
            <span
              className="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-20 bg-linear-to-t from-black/75 via-black/30 to-transparent"
              data-story-card-scrim
            />
            <span className="pointer-events-none absolute inset-0 z-20 rounded-[14px] ring-1 ring-inset ring-white/10" />

            <span
              className={`absolute left-2 top-2 z-30 flex h-8 w-8 rounded-full p-[2px] shadow-[0_1px_5px_rgba(0,0,0,0.25)] ${ringClass}`}
              data-story-progress-percent={
                progress.state === "partial" ? unseenPercentage : undefined
              }
              data-story-viewed-percent={
                progress.state === "partial" ? viewedPercentage : undefined
              }
              data-story-ring
              style={ringStyle}
            >
              <span className="h-full w-full rounded-full bg-white p-[2px]">
                <span className="block h-full w-full overflow-hidden rounded-full">
                  {group.coverSrc ? (
                    <Image
                      removeWrapper
                      alt=""
                      className="h-full w-full object-cover"
                      disableAnimation
                      draggable={false}
                      loading="eager"
                      src={group.coverSrc}
                    />
                  ) : (
                    <StoryArtwork compact visual={group.visual} />
                  )}
                </span>
              </span>
            </span>

            {showProfileName && (
              <span className="absolute inset-x-2 bottom-2 z-30 truncate text-left text-[11px] font-bold leading-tight tracking-tight text-white drop-shadow-[0_1px_3px_rgba(0,0,0,0.65)]">
                {group.title}
              </span>
            )}
          </span>
        ) : (
          <>
            <span
              className={`relative flex h-[68px] w-[68px] transform-gpu will-change-transform items-center justify-center rounded-full p-[3px] [backface-visibility:hidden] transition-[transform,box-shadow] duration-200 group-hover/story:scale-[1.035] group-hover/story:shadow-[0_0_0_3px] group-hover/story:shadow-primary/20 group-active/story:scale-95 group-active/story:shadow-none motion-reduce:transition-none sm:h-[72px] sm:w-[72px] ${ringClass}`}
              data-story-progress-percent={
                progress.state === "partial" ? unseenPercentage : undefined
              }
              data-story-viewed-percent={
                progress.state === "partial" ? viewedPercentage : undefined
              }
              data-story-ring
              style={ringStyle}
            >
              <span className="h-full w-full rounded-full bg-content1 p-[2px]">
                <span className="block h-full w-full overflow-hidden rounded-full">
                  <StoryArtwork
                    compact
                    imageSrc={group.coverSrc}
                    visual={group.visual}
                  />
                </span>
              </span>

              {progress.state === "seen" && (
                <span className="absolute -bottom-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-content1 bg-default-500 text-white">
                  <Check aria-hidden="true" size={11} strokeWidth={3} />
                </span>
              )}
            </span>

            {showProfileName && (
              <span className="mt-1.5 w-full truncate text-center text-xs font-semibold text-foreground">
                {group.title}
              </span>
            )}
          </>
        )}
      </button>
    </motion.div>
  );
};

export default StoryTrayItem;
