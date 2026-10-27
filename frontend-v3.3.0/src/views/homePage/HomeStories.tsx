import type { FC } from "react";
import { useTranslation } from "react-i18next";

import type { StoryGroup } from "@/components/Stories/types";
import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";
import { WatchAndBuyStoriesSection } from "@/components/WatchAndBuy";

export interface HomeStoriesProps {
  context?: "home" | "play";
  showSeeAll?: boolean;
  groups?: StoryGroup[];
  initialSeenStoryIds?: readonly string[];
}

/**
 * Compatibility wrapper for the home and Play placements. New placements can
 * render StoriesSection directly with their own data and progress namespace.
 */
const HomeStories: FC<HomeStoriesProps> = ({
  context = "home",
  showSeeAll = true,
  groups,
  initialSeenStoryIds,
}) => {
  const { t } = useTranslation();

  return (
    <WatchAndBuyStoriesSection
      title={t("pages.play.storiesTitle", { defaultValue: "Watch & Buy" })}
      description={t("pages.play.storiesDescription", {
        defaultValue: "Fresh updates from stores you can shop",
      })}
      groups={groups}
      initialSeenStoryIds={initialSeenStoryIds}
      inset={false}
      sectionId={`${context}-stories`}
      showSeeAll={showSeeAll}
      storageKey={STORY_PROGRESS_STORAGE_KEY}
    />
  );
};

export default HomeStories;
