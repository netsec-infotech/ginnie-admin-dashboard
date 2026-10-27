import { FC, ReactNode, useCallback, useEffect, useId, useState } from "react";
import { Clapperboard } from "lucide-react";
import { useRouter } from "next/router";
import { useTranslation } from "react-i18next";

import { MediaSectionHeader } from "@/components/Media";
import ReelCollection from "@/components/Reels/ReelCollection";
import ReelViewer from "@/components/Reels/ReelViewer";
import type { Reel } from "@/components/Reels/types";
import { useLocalReelViews } from "@/components/Reels/useLocalReelViews";
import { useSettings } from "@/contexts/SettingsContext";
import { getSiteSeo } from "@/config/siteSeo";

export interface ReelsSectionProps {
  reels: Reel[];
  sectionId?: string;
  inset?: boolean;
  layout?: "rail" | "grid";
  showLikes?: boolean;
  showTitle?: boolean;
  itemGap?: number;
  title?: ReactNode;
  description?: ReactNode;
  badgeLabel?: ReactNode | null;
  showHeader?: boolean;
  showSeeAll?: boolean;
  seeAllHref?: string;
  seeAllLabel?: ReactNode;
  railLabel?: string;
  reelQueryParam?: string;
  sharedReelId?: string | null;
  hasMore?: boolean;
  isLoadingMore?: boolean;
  onLoadMore?: () => void;
  onNavigate?: (href: string) => void | Promise<unknown>;
  onReelLikeChange?: (reel: Reel, liked: boolean) => boolean | Promise<boolean>;
  className?: string;
}

const ReelsSection: FC<ReelsSectionProps> = ({
  reels,
  sectionId,
  inset = false,
  layout = "rail",
  showLikes = false,
  showTitle = true,
  itemGap,
  title,
  description,
  badgeLabel,
  showHeader = true,
  showSeeAll = false,
  seeAllHref = "/play/",
  seeAllLabel,
  railLabel,
  reelQueryParam = "reel",
  sharedReelId,
  hasMore = false,
  isLoadingMore = false,
  onLoadMore,
  onNavigate,
  onReelLikeChange,
  className = "",
}) => {
  const { webSettings } = useSettings();
  const siteName = getSiteSeo(webSettings).siteName;
  const { t } = useTranslation();
  const router = useRouter();
  const generatedSectionId = useId();
  const [selectedReelId, setSelectedReelId] = useState<string | null>(null);
  const { recordView, viewCounts } = useLocalReelViews(reels);
  const routerReelQuery = router.query?.[reelQueryParam];
  const routerReelId = Array.isArray(routerReelQuery)
    ? routerReelQuery[0]
    : routerReelQuery;
  const requestedReelId =
    sharedReelId === undefined ? routerReelId : sharedReelId;
  const isGrid = layout === "grid";
  const resolvedSectionId =
    sectionId ?? `reels-${generatedSectionId.replace(/:/g, "")}`;
  const titleId = `${resolvedSectionId}-title`;
  const resolvedTitle =
    title ??
    (isGrid
      ? t("pages.play.reelsTitle", { defaultValue: "Reels" })
      : t("reels.title", { defaultValue: "Play" }));
  const resolvedDescription =
    description ??
    (isGrid
      ? t("pages.play.reelsDescription", {
          defaultValue: "Fresh videos from nearby sellers and {{siteName}}",
          siteName,
        })
      : t("reels.description", {
          defaultValue: "Short videos from stores around you",
        }));
  const resolvedBadgeLabel =
    badgeLabel === undefined
      ? isGrid
        ? null
        : t("reels.label", { defaultValue: "Reels" })
      : badgeLabel;
  const selection =
    selectedReelId === null
      ? null
      : reels.findIndex((reel) => reel.id === selectedReelId);
  const selectedReel =
    selection === null || selection < 0 ? null : (reels[selection] ?? null);

  useEffect(() => {
    if (!requestedReelId) return;
    const reel = reels.find((candidate) => candidate.id === requestedReelId);
    if (!reel) return;

    const timer = window.setTimeout(() => setSelectedReelId(reel.id), 0);
    return () => window.clearTimeout(timer);
  }, [reels, requestedReelId]);

  useEffect(() => {
    if (selectedReelId === null || selection !== -1) return;

    const timer = window.setTimeout(() => setSelectedReelId(null), 0);
    return () => window.clearTimeout(timer);
  }, [selectedReelId, selection]);

  useEffect(() => {
    if (!selectedReel || onNavigate) return;

    const destinations = [
      selectedReel.cta?.href,
      ...(selectedReel.products ?? [])
        .slice(0, 2)
        .map((product) => product.href),
    ].filter((href): href is string => Boolean(href));

    for (const href of new Set(destinations)) {
      void router.prefetch?.(href);
    }
  }, [onNavigate, router, selectedReel]);

  const navigateFromViewer = useCallback(
    async (href: string) => {
      const didNavigate = onNavigate
        ? await onNavigate(href)
        : await router.push(href);
      if (didNavigate !== false) setSelectedReelId(null);
      return didNavigate;
    },
    [onNavigate, router, setSelectedReelId],
  );

  return (
    <section
      aria-labelledby={showHeader ? titleId : undefined}
      className={`mb-8 min-w-0 max-w-full overflow-hidden ${
        inset ? "px-1 sm:px-6" : ""
      } ${className}`.trim()}
      data-testid={resolvedSectionId}
      id={resolvedSectionId}
    >
      <div className="min-w-0 max-w-full">
        {showHeader && (
          <MediaSectionHeader
            badge={resolvedBadgeLabel}
            badgeColor="secondary"
            description={resolvedDescription}
            icon={
              <span
                className="rounded-full bg-linear-to-r from-primary-500 to-primary-400 p-2"
                data-reels-section-icon
              >
                <Clapperboard
                  aria-hidden="true"
                  className="text-white"
                  size={16}
                />
              </span>
            }
            seeAllHref={seeAllHref}
            seeAllLabel={seeAllLabel ?? t("see_all")}
            showSeeAll={showSeeAll}
            title={resolvedTitle}
            titleId={titleId}
          />
        )}

        <ReelCollection
          showLikes={showLikes}
          showTitle={showTitle}
          itemGap={itemGap}
          label={
            railLabel ??
            t("reels.railLabel", {
              defaultValue: "Nearby store reels",
            })
          }
          layout={layout}
          reels={reels}
          viewCounts={viewCounts}
          onOpen={(reel) => setSelectedReelId(reel.id)}
        />
      </div>

      <ReelViewer
        key={selectedReel ? `${selectedReel.id}:${selection}` : "closed"}
        reels={reels}
        selection={selectedReel ? selection : null}
        hasMore={hasMore}
        isLoadingMore={isLoadingMore}
        shareQueryParam={reelQueryParam}
        viewCounts={viewCounts}
        onLikeChange={onReelLikeChange}
        onLoadMore={onLoadMore}
        onClose={() => setSelectedReelId(null)}
        onView={recordView}
        onNavigate={navigateFromViewer}
      />
    </section>
  );
};

export default ReelsSection;
