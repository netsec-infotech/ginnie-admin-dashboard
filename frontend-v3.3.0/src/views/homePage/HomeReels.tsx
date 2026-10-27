import { FC } from "react";
import { useTranslation } from "react-i18next";

import type { Reel } from "@/components/Reels/types";
import { WatchAndBuyReelsSection } from "@/components/WatchAndBuy";

interface HomeReelsProps {
  context?: "home" | "play";
  layout?: "rail" | "grid";
  showSeeAll?: boolean;
  reels?: Reel[];
}

/**
 * Home-specific compatibility wrapper. New surfaces should compose
 * `ReelsSection` directly with their own data, labels, and layout.
 */
const HomeReels: FC<HomeReelsProps> = ({
  context = "home",
  layout = "rail",
  showSeeAll = true,
  reels,
}) => {
  const { t } = useTranslation();

  return (
    <WatchAndBuyReelsSection
      title={t("pages.play.reelsTitle", {
        defaultValue: "Explore shoppable videos",
      })}
      description={t("pages.play.reelsDescription", {
        defaultValue:
          "Tap a video to watch, shop, and keep scrolling through the feed.",
      })}
      badgeLabel={null}
      inset={false}
      layout={layout}
      reels={reels}
      sectionId={`${context}-reels`}
      showSeeAll={showSeeAll}
    />
  );
};

export default HomeReels;
