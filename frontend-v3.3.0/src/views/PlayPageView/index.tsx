import { Clapperboard, CirclePlay } from "lucide-react";
import { useTranslation } from "react-i18next";

import { MediaEmptyState } from "@/components/Media";
import type { Reel } from "@/components/Reels/types";
import type { StoryGroup } from "@/components/Stories/types";
import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";
import {
  WatchAndBuyReelsSection,
  WatchAndBuyStoriesSection,
} from "@/components/WatchAndBuy";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import PageHeader from "@/components/custom/PageHeader";

interface PlayPageViewProps {
  reels?: Reel[];
  storyGroups?: StoryGroup[];
}

const PlayPageView = ({ reels, storyGroups }: PlayPageViewProps) => {
  const { t } = useTranslation();
  const title = t("pageTitle.play", { defaultValue: "Play" });
  const description = t("pages.play.description", {
    defaultValue: "Stories and short videos from sellers around you.",
  });
  const endOfFeed = (
    <div
      className="mb-10 flex items-center justify-center gap-3 px-1 text-center text-xs text-foreground/45 sm:px-6"
      role="status"
    >
      <span aria-hidden="true" className="h-px w-10 bg-foreground/10" />
      {t("pages.play.endOfFeed", {
        defaultValue: "You're all caught up for now.",
      })}
      <span aria-hidden="true" className="h-px w-10 bg-foreground/10" />
    </div>
  );

  return (
    <div className="min-h-screen min-w-0 w-full" data-testid="play-page">
      <MyBreadcrumbs breadcrumbs={[{ href: "/play/", label: title }]} />

      <PageHeader title={title} subtitle={description} />

      <WatchAndBuyStoriesSection
        title={t("pages.play.storiesTitle", { defaultValue: "Watch & Buy" })}
        description={t("pages.play.storiesDescription", {
          defaultValue: "Fresh updates from stores you can shop",
        })}
        groups={storyGroups}
        hideWhenEmpty={false}
        sectionId="play-stories"
        showSeeAll={false}
        storageKey={STORY_PROGRESS_STORAGE_KEY}
        emptyState={
          <MediaEmptyState
            description={t("pages.play.emptyStoriesDescription", {
              defaultValue:
                "Nearby sellers haven't posted any active Stories. Check back soon for fresh updates.",
            })}
            icon={CirclePlay}
            testId="play-stories-empty"
            title={t("pages.play.emptyStoriesTitle", {
              defaultValue: "No Stories yet",
            })}
          />
        }
      />
      <WatchAndBuyReelsSection
        title={t("pages.play.reelsTitle", {
          defaultValue: "Explore shoppable videos",
        })}
        description={t("pages.play.reelsDescription", {
          defaultValue:
            "Tap a video to watch, shop, and keep scrolling through the feed.",
        })}
        emptyState={
          <MediaEmptyState
            description={t("pages.play.emptyReelsDescription", {
              defaultValue:
                "There are no nearby Reels to watch right now. New seller videos will appear here.",
            })}
            icon={Clapperboard}
            testId="play-reels-empty"
            title={t("pages.play.emptyReelsTitle", {
              defaultValue: "No Reels nearby",
            })}
          />
        }
        footer={endOfFeed}
        hideWhenEmpty={false}
        infiniteScroll
        layout="grid"
        perPage={10}
        reels={reels}
        sectionId="play-reels"
        showSeeAll={false}
      />
    </div>
  );
};

export default PlayPageView;
