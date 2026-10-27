import {
  type FC,
  type ReactNode,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { useRouter } from "next/router";
import { useSelector } from "react-redux";
import useSWR from "swr";
import useSWRInfinite from "swr/infinite";

import StoriesSection, {
  type StoriesSectionProps,
} from "@/components/Stories/StoriesSection";
import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";
import type { StoryGroup } from "@/components/Stories/types";
import { getCookie } from "@/lib/cookies";
import type { RootState } from "@/lib/redux/store";
import {
  getWatchAndBuyConfig,
  getWatchAndBuyProfileStories,
  getWatchAndBuyStoryTray,
  markWatchAndBuyStoriesSeen,
} from "@/routes/api";
import type {
  WatchAndBuyResponse,
  WatchAndBuyStoryTrayData,
} from "@/types/WatchAndBuy";

import { getSeenStoryIds, toStoryGroup, toStoryTrayGroup } from "./adapters";
import {
  mergeStoredSeenStoryIds,
  mergeStoredSeenStoryGroups,
  queuePendingStorySeen,
  readPendingStorySeen,
  readStoredSeenStoryIds,
  readStoredSeenStoryGroups,
  readStoredSeenStoryStatusIds,
  removePendingStorySeen,
  type SeenStoryGroupMap,
} from "./storySeenPersistence";
import { WATCH_AND_BUY_SWR_CONFIG } from "./swrConfig";

export interface WatchAndBuyStoriesSectionProps extends Omit<
  StoriesSectionProps,
  | "groups"
  | "onStoryGroupComplete"
  | "onStorySeen"
  | "onStoryViewerClose"
  | "resolveGroup"
  | "seenStoryIds"
  | "hasMore"
  | "isLoadingMore"
  | "onLoadMore"
> {
  /** Controlled view models are useful for previews and isolated tests. */
  groups?: StoryGroup[];
  /** Restrict the shared Story tray to one store. */
  storeId?: number;
  storeSlug?: string;
  /** Temporary store-detail media used until the scoped tray responds. */
  fallbackGroups?: StoryGroup[];
  /** Number of lightweight publisher records loaded into the tray per batch. */
  perPage?: number;
  /** Maximum active Stories fetched only after a publisher is opened. */
  profilePerPage?: number;
  hideWhenEmpty?: boolean;
  emptyMessage?: string;
  emptyState?: ReactNode;
  /** Keep a supplied fallback visible while a scoped tray is unavailable. */
  showEmptyStateAsFallback?: boolean;
  onStorySeen?: (storyId: string) => void | Promise<unknown>;
  onStoryGroupComplete?: (group: StoryGroup) => void | Promise<unknown>;
  onStoryViewerClose?: () => void | Promise<unknown>;
}

const WatchAndBuyStoriesSection: FC<WatchAndBuyStoriesSectionProps> = ({
  groups: controlledGroups,
  storeId,
  storeSlug,
  fallbackGroups = [],
  perPage = 12,
  profilePerPage = 50,
  hideWhenEmpty = true,
  emptyMessage = "No Stories are available right now.",
  emptyState,
  showEmptyStateAsFallback = false,
  onStorySeen,
  onStoryGroupComplete,
  onStoryViewerClose,
  storyQueryParam = "story",
  sharedStoryId,
  storageKey: requestedStorageKey,
  ...sectionProps
}) => {
  const router = useRouter();
  const isControlled = controlledGroups !== undefined;
  const storageKey = requestedStorageKey ?? STORY_PROGRESS_STORAGE_KEY;
  const reduxAccessToken = useSelector(
    (state: RootState) => state.auth.access_token,
  );
  const accessToken =
    reduxAccessToken || getCookie<string>("access_token") || "";
  const isAuthenticated = Boolean(accessToken);
  const [hydratedGroups, setHydratedGroups] = useState<
    Record<string, StoryGroup>
  >({});
  const [seenStoryIds, setSeenStoryIds] = useState<string[]>([]);
  const [seenStoryGroups, setSeenStoryGroups] = useState<SeenStoryGroupMap>({});
  const seenStoryIdsRef = useRef(new Set<string>());
  const storyStatusIdsRef = useRef(new Map<string, number>());
  const storyGroupIdsRef = useRef(new Map<string, string>());
  const backendSeenStatusIdsRef = useRef(new Set<number>());
  const isSyncingSeenRef = useRef(false);
  const reconciledAccessTokenRef = useRef<string | null>(null);
  const { data: configResponse } = useSWR(
    isControlled ? null : "watch-and-buy-config",
    getWatchAndBuyConfig,
    WATCH_AND_BUY_SWR_CONFIG,
  );
  const isEnabled = configResponse?.success
    ? configResponse.data?.enabled !== false &&
      configResponse.data?.statusesEnabled !== false
    : true;
  const getTrayKey = (
    pageIndex: number,
    previousPageData: WatchAndBuyResponse<WatchAndBuyStoryTrayData> | null,
  ) => {
    if (isControlled || !isEnabled) return null;
    if (pageIndex > 0 && !previousPageData?.data?.meta.next_cursor) return null;

    return [
      "watch-and-buy-story-tray",
      perPage,
      storeId ?? null,
      storeSlug ?? null,
      pageIndex === 0 ? null : previousPageData?.data?.meta.next_cursor,
      pageIndex === 0 ? null : previousPageData?.data?.meta.session_id,
    ] as const;
  };
  const {
    data: trayPages,
    isLoading,
    isValidating: isTrayValidating,
    setSize: setTraySize,
    size: traySize,
  } = useSWRInfinite<WatchAndBuyResponse<WatchAndBuyStoryTrayData>>(
    getTrayKey,
    (key) => {
      const cursor = key[4] as string | null;
      const sessionId = key[5] as string | null;
      return getWatchAndBuyStoryTray({
        per_page: perPage,
        ...(storeId !== undefined ? { store_id: storeId } : {}),
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
  const trayItems = useMemo(() => {
    const uniqueItems = new Map<
      string,
      WatchAndBuyStoryTrayData["items"][number]
    >();
    trayPages?.forEach((page) => {
      if (!page?.success) return;
      page.data?.items.forEach((item) => {
        uniqueItems.set(item.profile.username, item);
      });
    });
    return [...uniqueItems.values()];
  }, [trayPages]);
  const lastTrayPage = trayPages?.[trayPages.length - 1];
  const hasMoreTrayItems = Boolean(
    !isControlled &&
    lastTrayPage?.success &&
    lastTrayPage.data?.meta.next_cursor,
  );
  const isLoadingMoreTrayItems = Boolean(
    !isControlled &&
    isTrayValidating &&
    traySize > 1 &&
    trayPages?.[traySize - 1] === undefined,
  );
  const loadMoreTrayItems = useCallback(() => {
    if (!hasMoreTrayItems || isLoadingMoreTrayItems) return;
    void setTraySize((currentSize) => currentSize + 1);
  }, [hasMoreTrayItems, isLoadingMoreTrayItems, setTraySize]);
  const trayGroups = useMemo(() => {
    if (controlledGroups) return controlledGroups;

    const locallySeenIds = new Set(seenStoryIds);
    const feedGroups = trayItems.map((item) => {
      const group = toStoryTrayGroup(item);
      const locallySeenCount = Object.entries(seenStoryGroups).filter(
        ([storyId, groupId]) =>
          groupId === group.id && locallySeenIds.has(storyId),
      ).length;
      if (!locallySeenCount || !group.unseenItemCount) return group;

      return {
        ...group,
        unseenItemCount: Math.max(0, group.unseenItemCount - locallySeenCount),
      };
    });
    return feedGroups.length > 0 ? feedGroups : fallbackGroups;
  }, [
    controlledGroups,
    fallbackGroups,
    seenStoryGroups,
    seenStoryIds,
    trayItems,
  ]);
  const groups = useMemo(
    () => trayGroups.map((group) => hydratedGroups[group.id] ?? group),
    [hydratedGroups, trayGroups],
  );

  useEffect(() => {
    if (!controlledGroups) return;

    const statusIds = new Map<string, number>();
    const groupIds = new Map<string, string>();
    const backendSeenStatusIds = new Set<number>();
    groups.forEach((group) => {
      group.items.forEach((item) => {
        groupIds.set(item.id, group.id);
        if (item.apiId === undefined) return;
        statusIds.set(item.id, item.apiId);
        if (item.seen) backendSeenStatusIds.add(item.apiId);
      });
    });
    storyStatusIdsRef.current = statusIds;
    storyGroupIdsRef.current = groupIds;
    backendSeenStatusIdsRef.current = backendSeenStatusIds;
  }, [controlledGroups, groups]);

  const mergeSeenState = useCallback(
    (storyIds: readonly string[]) => {
      if (typeof window === "undefined") return;
      const merged = mergeStoredSeenStoryIds(
        window.localStorage,
        storageKey,
        storyIds,
      );
      seenStoryIdsRef.current = new Set(merged);
      setSeenStoryIds(merged);
    },
    [storageKey],
  );

  const flushPendingSeen = useCallback(async () => {
    if (
      typeof window === "undefined" ||
      isSyncingSeenRef.current ||
      !isAuthenticated
    )
      return;

    isSyncingSeenRef.current = true;
    try {
      while (true) {
        const pending = readPendingStorySeen(window.localStorage, storageKey);
        if (pending.length === 0) break;

        const statusIds = [...new Set(pending.map((entry) => entry.statusId))];
        const response = await markWatchAndBuyStoriesSeen({
          status_ids: statusIds,
        });
        if (!response.success) break;

        statusIds.forEach((statusId) =>
          backendSeenStatusIdsRef.current.add(statusId),
        );
        removePendingStorySeen(window.localStorage, storageKey, statusIds);
      }
    } finally {
      isSyncingSeenRef.current = false;
    }
  }, [isAuthenticated, storageKey]);

  useEffect(() => {
    if (typeof window === "undefined") return;

    const storedStoryIds = readStoredSeenStoryIds(
      window.localStorage,
      storageKey,
    );
    seenStoryIdsRef.current = new Set(storedStoryIds);
    setSeenStoryIds(storedStoryIds);
    setSeenStoryGroups(
      readStoredSeenStoryGroups(window.localStorage, storageKey),
    );

    if (
      isAuthenticated &&
      readPendingStorySeen(window.localStorage, storageKey).length > 0
    ) {
      void flushPendingSeen();
    }

    const retryWhenOnline = () => void flushPendingSeen();
    window.addEventListener("online", retryWhenOnline);
    return () => {
      window.removeEventListener("online", retryWhenOnline);
    };
  }, [flushPendingSeen, isAuthenticated, storageKey]);

  const resolveGroup = useCallback(
    async (group: StoryGroup): Promise<StoryGroup | null> => {
      if (hydratedGroups[group.id]) return hydratedGroups[group.id];
      if (group.items.length > 0) return group;

      const response = await getWatchAndBuyProfileStories(group.id, {
        per_page: profilePerPage,
        ...(storeId !== undefined ? { store_id: storeId } : {}),
        ...(storeSlug ? { store_slug: storeSlug } : {}),
      });
      if (!response?.success || !response.data)
        return group.items.length ? group : null;

      const resolved = toStoryGroup(response.data);
      response.data.items.forEach((item) => {
        const storyId = item.uuid || String(item.id);
        storyStatusIdsRef.current.set(storyId, item.id);
        storyGroupIdsRef.current.set(storyId, resolved.id);
        if (item.seen_by_me) backendSeenStatusIdsRef.current.add(item.id);
      });
      setHydratedGroups((current) => ({ ...current, [resolved.id]: resolved }));
      const backendSeenStoryIds = getSeenStoryIds(response.data);
      mergeSeenState(backendSeenStoryIds);
      const knownSeenStoryIds = new Set([
        ...seenStoryIdsRef.current,
        ...backendSeenStoryIds,
      ]);
      const nextStoryGroups = mergeStoredSeenStoryGroups(
        typeof window === "undefined" ? null : window.localStorage,
        storageKey,
        response.data.items
          .filter((item) => knownSeenStoryIds.has(item.uuid || String(item.id)))
          .map((item) => ({
            storyId: item.uuid || String(item.id),
            groupId: resolved.id,
            statusId: item.id,
          })),
      );
      setSeenStoryGroups(nextStoryGroups);
      removePendingStorySeen(
        typeof window === "undefined" ? null : window.localStorage,
        storageKey,
        response.data.items
          .filter((item) => item.seen_by_me)
          .map((item) => item.id),
      );
      return resolved;
    },
    [
      hydratedGroups,
      mergeSeenState,
      profilePerPage,
      storeId,
      storeSlug,
      storageKey,
    ],
  );

  const requestedStoryId =
    sharedStoryId ??
    (() => {
      const value = router.query?.[storyQueryParam];
      return Array.isArray(value) ? value[0] : value;
    })();

  useEffect(() => {
    if (!requestedStoryId || isControlled || groups.length === 0) return;
    if (
      groups.some((group) =>
        group.items.some((item) => item.id === requestedStoryId),
      )
    )
      return;

    let cancelled = false;
    const hydrateSharedStory = async () => {
      for (const group of groups) {
        if (cancelled) return;
        const resolved = await resolveGroup(group);
        if (resolved?.items.some((item) => item.id === requestedStoryId))
          return;
      }
    };
    void hydrateSharedStory();
    return () => {
      cancelled = true;
    };
  }, [groups, isControlled, requestedStoryId, resolveGroup]);

  const reconcileStoredSeen = useCallback(async () => {
    if (typeof window === "undefined" || !isAuthenticated) return;

    const storage = window.localStorage;
    const storedStoryIds = readStoredSeenStoryIds(storage, storageKey);
    if (storedStoryIds.length === 0) {
      await flushPendingSeen();
      return;
    }

    let storedStatusIds = readStoredSeenStoryStatusIds(storage, storageKey);
    const storedGroups = readStoredSeenStoryGroups(storage, storageKey);
    const unresolvedGroupIds = new Set(
      storedStoryIds.flatMap((storyId) => {
        if (
          storyStatusIdsRef.current.has(storyId) ||
          storedStatusIds[storyId] !== undefined
        )
          return [];
        const groupId = storedGroups[storyId];
        return groupId ? [groupId] : [];
      }),
    );

    for (const groupId of unresolvedGroupIds) {
      const group = groups.find((candidate) => candidate.id === groupId);
      if (group) await resolveGroup(group);
    }

    storedStatusIds = readStoredSeenStoryStatusIds(storage, storageKey);
    storedStoryIds.forEach((storyId) => {
      const statusId =
        storyStatusIdsRef.current.get(storyId) ?? storedStatusIds[storyId];
      if (
        statusId === undefined ||
        backendSeenStatusIdsRef.current.has(statusId)
      )
        return;

      queuePendingStorySeen(storage, storageKey, {
        statusId,
        storyId,
        queuedAt: Date.now(),
      });
    });
    await flushPendingSeen();
  }, [flushPendingSeen, groups, isAuthenticated, resolveGroup, storageKey]);

  useEffect(() => {
    if (!accessToken) {
      reconciledAccessTokenRef.current = null;
      backendSeenStatusIdsRef.current.clear();
      return;
    }
    if (reconciledAccessTokenRef.current === accessToken) return;

    if (typeof window !== "undefined") {
      const storage = window.localStorage;
      const storedStoryIds = readStoredSeenStoryIds(storage, storageKey);
      const storedStatusIds = readStoredSeenStoryStatusIds(storage, storageKey);
      const storedGroups = readStoredSeenStoryGroups(storage, storageKey);
      const needsGroupResolution = storedStoryIds.some(
        (storyId) =>
          storedStatusIds[storyId] === undefined &&
          Boolean(storedGroups[storyId]),
      );
      if (needsGroupResolution && groups.length === 0) return;
    }

    reconciledAccessTokenRef.current = accessToken;
    backendSeenStatusIdsRef.current.clear();
    groups.forEach((group) => {
      group.items.forEach((item) => {
        if (item.seen && item.apiId !== undefined) {
          backendSeenStatusIdsRef.current.add(item.apiId);
        }
      });
    });
    void reconcileStoredSeen();
  }, [accessToken, groups, reconcileStoredSeen, storageKey]);

  if (!isEnabled)
    return showEmptyStateAsFallback && emptyState ? <>{emptyState}</> : null;
  if (!isControlled && isLoading && groups.length === 0) {
    if (showEmptyStateAsFallback && emptyState) return <>{emptyState}</>;
    return (
      <div
        aria-label="Loading Stories"
        className="mb-8 h-28 animate-pulse rounded-2xl bg-default-100 motion-reduce:animate-none"
        role="status"
      />
    );
  }
  if (groups.length === 0 && hideWhenEmpty) return null;

  const syncSeen = (storyId: string) => {
    if (seenStoryIdsRef.current.has(storyId)) return;

    mergeSeenState([storyId]);
    const groupId = storyGroupIdsRef.current.get(storyId);
    const statusId = storyStatusIdsRef.current.get(storyId);
    if (groupId) {
      setSeenStoryGroups(
        mergeStoredSeenStoryGroups(window.localStorage, storageKey, [
          { storyId, groupId, statusId },
        ]),
      );
    }
    void onStorySeen?.(storyId);
  };

  const syncCompletedGroup = (group: StoryGroup) => {
    void onStoryGroupComplete?.(group);
  };

  const syncViewerClose = () => {
    void onStoryViewerClose?.();
    if (typeof window === "undefined" || !isAuthenticated) return;

    const storedStatusIds = readStoredSeenStoryStatusIds(
      window.localStorage,
      storageKey,
    );
    seenStoryIdsRef.current.forEach((storyId) => {
      const statusId =
        storyStatusIdsRef.current.get(storyId) ?? storedStatusIds[storyId];
      if (
        statusId === undefined ||
        backendSeenStatusIdsRef.current.has(statusId)
      )
        return;
      queuePendingStorySeen(window.localStorage, storageKey, {
        statusId,
        storyId,
        queuedAt: Date.now(),
      });
    });
    void flushPendingSeen();
  };

  return (
    <>
      <StoriesSection
        {...sectionProps}
        groups={groups}
        hasMore={hasMoreTrayItems}
        isLoadingMore={isLoadingMoreTrayItems}
        onStoryGroupComplete={syncCompletedGroup}
        onStoryViewerClose={syncViewerClose}
        resolveGroup={resolveGroup}
        seenStoryIds={isControlled ? undefined : seenStoryIds}
        storageKey={storageKey}
        sharedStoryId={sharedStoryId}
        storyQueryParam={storyQueryParam}
        onStorySeen={syncSeen}
        onLoadMore={loadMoreTrayItems}
      />
      {groups.length === 0 &&
        (emptyState ?? (
          <p
            className="-mt-5 mb-8 text-center text-sm text-foreground/55"
            role="status"
          >
            {emptyMessage}
          </p>
        ))}
    </>
  );
};

export default WatchAndBuyStoriesSection;
