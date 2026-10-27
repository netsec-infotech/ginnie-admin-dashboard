import { type FC, type ReactNode, useCallback, useMemo, useRef } from "react";
import useSWR from "swr";
import useSWRInfinite from "swr/infinite";

import LoadMoreSentinel from "@/components/Functional/LoadMoreSentinel";
import ReelsSection, {
  type ReelsSectionProps,
} from "@/components/Reels/ReelsSection";
import type { Reel } from "@/components/Reels/types";
import { useSettings } from "@/contexts/SettingsContext";
import { getCookie } from "@/lib/cookies";
import {
  getWatchAndBuyConfig,
  getWatchAndBuyReels,
  updateWatchAndBuyReelLikes,
} from "@/routes/api";
import type {
  WatchAndBuyReelFeedData,
  WatchAndBuyResponse,
} from "@/types/WatchAndBuy";

import { toReels } from "./adapters";
import { WATCH_AND_BUY_SWR_CONFIG } from "./swrConfig";
import { useWatchAndBuyLocation } from "./useWatchAndBuyLocation";

export interface WatchAndBuyReelsSectionProps extends Omit<
  ReelsSectionProps,
  | "reels"
  | "hasMore"
  | "isLoadingMore"
  | "onLoadMore"
  | "onReelLikeChange"
> {
  /** Controlled view models are useful for previews and isolated tests. */
  reels?: Reel[];
  /** Restrict the shared Reel feed to one store. */
  storeSlug?: string;
  /** Temporary store-detail media used until the scoped feed responds. */
  fallbackReels?: Reel[];
  perPage?: number;
  hideWhenEmpty?: boolean;
  emptyMessage?: string;
  emptyState?: ReactNode;
  footer?: ReactNode;
  /** Incrementally append cursor pages as a grid approaches the viewport end. */
  infiniteScroll?: boolean;
}

const WatchAndBuyReelsSection: FC<WatchAndBuyReelsSectionProps> = ({
  reels: controlledReels,
  storeSlug,
  fallbackReels = [],
  perPage = 10,
  hideWhenEmpty = true,
  emptyMessage = "No Reels are available nearby right now.",
  emptyState,
  footer,
  infiniteScroll = false,
  ...sectionProps
}) => {
  const { currencySymbol } = useSettings();
  const { latitude, longitude } = useWatchAndBuyLocation();
  const isControlled = controlledReels !== undefined;
  const loadMorePendingRef = useRef(false);
  const { data: configResponse } = useSWR(
    isControlled ? null : "watch-and-buy-config",
    getWatchAndBuyConfig,
    WATCH_AND_BUY_SWR_CONFIG,
  );
  const isEnabled = configResponse?.success
    ? configResponse.data?.enabled !== false
    : true;
  const getFeedKey = (
    pageIndex: number,
    previousPageData: WatchAndBuyResponse<WatchAndBuyReelFeedData> | null,
  ) => {
    if (isControlled || !isEnabled) return null;
    if (pageIndex > 0 && !infiniteScroll) return null;
    if (pageIndex > 0 && !previousPageData?.data?.meta.next_cursor) return null;

    return [
      "watch-and-buy-reels",
      latitude,
      longitude,
      perPage,
      storeSlug ?? null,
      pageIndex === 0 ? null : previousPageData?.data?.meta.next_cursor,
      pageIndex === 0 ? null : previousPageData?.data?.meta.session_id,
    ] as const;
  };
  const {
    data: feedPages,
    isLoading,
    isValidating,
    setSize,
    size,
  } = useSWRInfinite<WatchAndBuyResponse<WatchAndBuyReelFeedData>>(
    getFeedKey,
    (key) => {
      const cursor = key[5] as string | null;
      const sessionId = key[6] as string | null;
      return getWatchAndBuyReels({
        latitude,
        longitude,
        per_page: perPage,
        ...(storeSlug ? { store_slug: storeSlug } : {}),
        ...(cursor ? { cursor } : {}),
        ...(sessionId ? { session_id: sessionId } : {}),
      });
    },
    {
      ...WATCH_AND_BUY_SWR_CONFIG,
      revalidateAll: false,
      revalidateFirstPage: false,
    },
  );
  const feedItems = useMemo(() => {
    const uniqueItems = new Map<
      string,
      WatchAndBuyReelFeedData["items"][number]
    >();
    feedPages?.forEach((page) => {
      if (!page?.success) return;
      page.data?.items.forEach((item) => {
        uniqueItems.set(item.uuid || String(item.id), item);
      });
    });
    return [...uniqueItems.values()];
  }, [feedPages]);
  const reels = useMemo(() => {
    if (controlledReels !== undefined) return controlledReels;
    const feedReels = toReels(feedItems, currencySymbol);
    return feedReels.length > 0 ? feedReels : fallbackReels;
  }, [controlledReels, currencySymbol, fallbackReels, feedItems]);
  const lastPage = feedPages?.[feedPages.length - 1];
  const hasMore = Boolean(
    !isControlled &&
    infiniteScroll &&
    lastPage?.success &&
    lastPage.data?.meta.next_cursor,
  );
  const isLoadingMore = Boolean(
    !isControlled &&
    isValidating &&
    size > 1 &&
    feedPages?.[size - 1] === undefined,
  );
  const loadMore = useCallback(() => {
    if (!hasMore || isLoadingMore || loadMorePendingRef.current) return;
    loadMorePendingRef.current = true;
    void setSize((currentSize) => currentSize + 1).finally(() => {
      loadMorePendingRef.current = false;
    });
  }, [hasMore, isLoadingMore, setSize]);

  if (!isEnabled) return null;
  if (!isControlled && isLoading && reels.length === 0) {
    return (
      <div
        aria-label="Loading Reels"
        className="mb-8 h-64 animate-pulse rounded-2xl bg-default-100 motion-reduce:animate-none"
        role="status"
      />
    );
  }
  if (reels.length === 0 && hideWhenEmpty) return null;

  const syncLike = async (reel: Reel, liked: boolean) => {
    if (reel.apiId === undefined || !getCookie("access_token")) return true;
    const response = await updateWatchAndBuyReelLikes({
      latitude,
      longitude,
      items: [{ reel_id: reel.apiId, liked }],
    });
    return response.success;
  };

  return (
    <>
      <ReelsSection
        {...sectionProps}
        hasMore={hasMore}
        isLoadingMore={isLoadingMore}
        reels={reels}
        onLoadMore={loadMore}
        onReelLikeChange={syncLike}
      />
      {reels.length === 0 &&
        (emptyState ?? (
          <p
            className="-mt-5 mb-8 text-center text-sm text-foreground/55"
            role="status"
          >
            {emptyMessage}
          </p>
        ))}
      {reels.length > 0 && infiniteScroll && !isControlled && (
        <>
          <LoadMoreSentinel
            className="h-px w-full"
            disabled={!hasMore || isLoadingMore}
            rootMargin="600px 0px"
            onVisible={loadMore}
          />
          {isLoadingMore && (
            <div
              aria-label="Loading more Reels"
              className="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
              role="status"
            >
              {Array.from({ length: 4 }, (_, index) => (
                <span
                  key={index}
                  className="aspect-9/16 animate-pulse rounded-2xl bg-default-100 motion-reduce:animate-none"
                />
              ))}
            </div>
          )}
        </>
      )}
      {reels.length > 0 &&
        (!infiniteScroll || isControlled || (!hasMore && !isLoadingMore)) &&
        footer}
    </>
  );
};

export default WatchAndBuyReelsSection;
