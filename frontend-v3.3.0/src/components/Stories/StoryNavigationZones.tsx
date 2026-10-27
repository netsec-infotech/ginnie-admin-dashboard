import type { FC } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslation } from "react-i18next";

export interface StoryNavigationZonesProps {
  isFirst: boolean;
  canGoNext: boolean;
  onPrevious: () => void;
  onNext: () => void;
}

const StoryNavigationZones: FC<StoryNavigationZonesProps> = ({
  isFirst,
  canGoNext,
  onPrevious,
  onNext,
}) => {
  const { t } = useTranslation();

  return (
    <>
      <button
        data-story-control
        type="button"
        aria-disabled={isFirst}
        aria-label={t("stories.viewer.previous", {
          defaultValue: "Previous story",
        })}
        className="group/nav absolute inset-y-24 left-0 z-30 hidden w-[22%] cursor-pointer items-center justify-start px-3 outline-none md:flex"
        onClick={onPrevious}
      >
        <span
          className={`flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-black/30 text-white shadow-lg backdrop-blur-md transition duration-200 motion-reduce:transition-none ${
            isFirst
              ? "invisible"
              : "translate-x-1 opacity-0 group-hover/storyviewer:translate-x-0 group-hover/storyviewer:opacity-100 group-focus-within/storyviewer:translate-x-0 group-focus-within/storyviewer:opacity-100"
          }`}
        >
          <ChevronLeft aria-hidden="true" size={24} />
        </span>
      </button>

      {canGoNext && (
        <button
          data-story-control
          type="button"
          aria-label={t("stories.viewer.next", {
            defaultValue: "Next story",
          })}
          className="group/nav absolute inset-y-24 right-0 z-30 hidden w-[22%] cursor-pointer items-center justify-end px-3 outline-none md:flex"
          onClick={onNext}
        >
          <span className="flex h-11 w-11 -translate-x-1 items-center justify-center rounded-full border border-white/10 bg-black/30 text-white opacity-0 shadow-lg backdrop-blur-md transition duration-200 group-hover/storyviewer:translate-x-0 group-hover/storyviewer:opacity-100 group-focus-within/storyviewer:translate-x-0 group-focus-within/storyviewer:opacity-100 motion-reduce:transition-none">
            <ChevronRight aria-hidden="true" size={24} />
          </span>
        </button>
      )}
    </>
  );
};

export default StoryNavigationZones;
