import type { FC, RefObject } from "react";
import { Button } from "@heroui/react";
import { BadgeCheck, Store, Volume2, VolumeX, X } from "lucide-react";
import { useTranslation } from "react-i18next";

import StoryArtwork from "@/components/Stories/StoryArtwork";
import type { StoryGroup, StoryItem } from "@/components/Stories/types";

export interface StoryViewerHeaderProps {
  group: StoryGroup;
  item: StoryItem;
  itemIndex: number;
  progress: number;
  bufferedProgress: number;
  progressFillRef: RefObject<HTMLDivElement | null>;
  isMuted: boolean;
  onMutedChange: (muted: boolean) => void;
  onPublisherPress: (href: string) => void;
  onClose: () => void;
}

const StoryViewerHeader: FC<StoryViewerHeaderProps> = ({
  group,
  item,
  itemIndex,
  progress,
  bufferedProgress,
  progressFillRef,
  isMuted,
  onMutedChange,
  onPublisherPress,
  onClose,
}) => {
  const { t } = useTranslation();
  const publisherLabel =
    group.publisherType === "admin"
      ? t("stories.viewer.official", { defaultValue: "Official" })
      : t("stories.viewer.seller", { defaultValue: "Seller" });

  return (
    <div className="pointer-events-none absolute inset-x-0 top-0 z-20 bg-linear-to-b from-black/70 via-black/25 to-transparent pb-12 pl-[max(0.75rem,env(safe-area-inset-left))] pr-[max(0.75rem,env(safe-area-inset-right))] pt-[max(0.75rem,env(safe-area-inset-top))]">
      <div className="flex gap-1.5" aria-hidden="true">
        {group.items.map((story, index) => {
          const segmentProgress =
            index < itemIndex ? 1 : index === itemIndex ? progress : 0;

          return (
            <div
              key={story.id}
              className="relative h-[3px] flex-1 overflow-hidden rounded-full bg-white/25"
            >
              {index === itemIndex && item.mediaType === "video" && (
                <div
                  aria-hidden="true"
                  data-story-buffer-fill
                  className="absolute inset-0 origin-left rounded-full bg-white/45 will-change-transform"
                  style={{ transform: `scaleX(${bufferedProgress})` }}
                />
              )}
              <div
                ref={index === itemIndex ? progressFillRef : undefined}
                data-story-progress-fill={
                  index === itemIndex ? "active" : "inactive"
                }
                className="relative z-10 h-full origin-left rounded-full bg-white will-change-transform"
                style={{ transform: `scaleX(${segmentProgress})` }}
              />
            </div>
          );
        })}
      </div>

      <div className="pointer-events-auto mt-3 flex items-center gap-3">
        <button
          data-story-control={group.publisherHref ? "publisher" : undefined}
          type="button"
          aria-label={
            group.publisherHref
              ? t("stories.viewer.openPublisher", {
                  title: group.title,
                  defaultValue: "Open {{title}} store",
                })
              : undefined
          }
          className="group/publisher flex min-w-0 flex-1 items-center gap-3 rounded-xl text-left outline-none enabled:cursor-pointer enabled:focus-visible:ring-2 enabled:focus-visible:ring-white/90 disabled:cursor-default"
          disabled={!group.publisherHref}
          onClick={() => {
            if (group.publisherHref) onPublisherPress(group.publisherHref);
          }}
        >
          <div
            className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-white/80 shadow-[0_8px_24px_rgba(0,0,0,0.22)] transition-transform duration-150 group-hover/publisher:scale-105 motion-reduce:transition-none"
            style={{
              background: group.coverSrc ? "#18181b" : group.visual.background,
            }}
          >
            <StoryArtwork
              compact
              imageSrc={group.coverSrc}
              visual={group.visual}
            />
          </div>
          <div className="min-w-0 flex-1 text-white">
            <p className="flex items-center gap-1.5 text-sm font-bold">
              <span className="truncate">{group.title}</span>
              {group.publisherType === "admin" ? (
                <BadgeCheck
                  aria-hidden="true"
                  className="shrink-0 text-sky-300"
                  size={14}
                />
              ) : (
                <Store
                  aria-hidden="true"
                  className="shrink-0 text-white/70"
                  size={13}
                />
              )}
            </p>
            <p className="truncate text-xs text-white/85">
              {`${publisherLabel} · ${group.subtitle} · ${itemIndex + 1}/${group.items.length}`}
            </p>
          </div>
        </button>

        {item.mediaType === "video" && !item.unavailable && (
          <Button
            isIconOnly
            data-story-control
            aria-label={
              isMuted
                ? t("stories.viewer.unmute", { defaultValue: "Unmute" })
                : t("stories.viewer.mute", { defaultValue: "Mute" })
            }
            className="border border-white/10 bg-black/25 text-white backdrop-blur-md"
            radius="full"
            variant="flat"
            onPress={() => onMutedChange(!isMuted)}
          >
            {isMuted ? (
              <VolumeX aria-hidden="true" size={20} />
            ) : (
              <Volume2 aria-hidden="true" size={20} />
            )}
          </Button>
        )}

        <Button
          isIconOnly
          data-story-control
          aria-label={t("stories.viewer.close", {
            defaultValue: "Close stories",
          })}
          className="border border-white/10 bg-black/25 text-white backdrop-blur-md"
          radius="full"
          variant="flat"
          onPress={onClose}
        >
          <X aria-hidden="true" size={21} />
        </Button>
      </div>
    </div>
  );
};

export default StoryViewerHeader;
