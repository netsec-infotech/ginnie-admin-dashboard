import {
  type FC,
  type ReactNode,
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
} from "react";
import { CirclePlay } from "lucide-react";
import { useRouter } from "next/router";
import { useTranslation } from "react-i18next";

import { MediaSectionHeader } from "@/components/Media";
import StoryTray from "@/components/Stories/StoryTray";
import type { StoryTrayStyle } from "@/components/Stories/StoryTrayItem";
import StoryViewer from "@/components/Stories/StoryViewer";
import type { StoryGroup, StorySelection } from "@/components/Stories/types";
import {
  createStoryProgressHelpers,
  type SeenStoryMap,
  type StoryGroupProgress,
} from "@/components/Stories/storyProgress";

const STORY_STATE_PRIORITY = {
  unseen: 0,
  partial: 1,
  seen: 2,
} as const;

const EMPTY_STORY_IDS: readonly string[] = [];

export interface StoryTriggerRenderProps {
  group: StoryGroup;
  progress: StoryGroupProgress;
  onOpen: () => void;
}

export interface StoriesSectionProps {
  groups: StoryGroup[];
  storageKey?: string;
  initialSeenStoryIds?: readonly string[];
  seenStoryIds?: readonly string[];
  sectionId?: string;
  inset?: boolean;
  className?: string;
  title?: ReactNode;
  description?: ReactNode;
  trayLabel?: string;
  ringColor?: string;
  showProfileName?: boolean;
  trayStyle?: StoryTrayStyle;
  itemGap?: number;
  showSeeAll?: boolean;
  seeAllHref?: string;
  seeAllLabel?: ReactNode;
  storyQueryParam?: string;
  sharedStoryId?: string | null;
  onStorySeen?: (storyId: string) => void | Promise<unknown>;
  onStoryGroupComplete?: (group: StoryGroup) => void | Promise<unknown>;
  onStoryViewerClose?: () => void | Promise<unknown>;
  resolveGroup?: (
    group: StoryGroup,
  ) => StoryGroup | null | Promise<StoryGroup | null>;
  onNavigate?: (href: string) => void | Promise<unknown>;
  hasMore?: boolean;
  isLoadingMore?: boolean;
  onLoadMore?: () => void;
  /** Replace the standard header/tray with a caller-owned Story launcher. */
  renderTrigger?: (props: StoryTriggerRenderProps) => ReactNode;
}

/**
 * A data-driven Stories belt and viewer that can be mounted on any page.
 * Each feed receives its own progress seed and storage key so viewed state is
 * isolated even when multiple Stories sections are used in the same app.
 */
const StoriesSection: FC<StoriesSectionProps> = ({
  groups,
  storageKey,
  initialSeenStoryIds = EMPTY_STORY_IDS,
  seenStoryIds,
  sectionId,
  inset = false,
  className = "",
  title,
  description,
  trayLabel,
  ringColor,
  showProfileName = true,
  trayStyle = "avatar_circle",
  itemGap,
  showSeeAll = false,
  seeAllHref = "/play/",
  seeAllLabel,
  storyQueryParam = "story",
  sharedStoryId,
  onStorySeen,
  onStoryGroupComplete,
  onStoryViewerClose,
  resolveGroup,
  onNavigate,
  hasMore = false,
  isLoadingMore = false,
  onLoadMore,
  renderTrigger,
}) => {
  const { t } = useTranslation();
  const router = useRouter();
  const generatedSectionId = useId();
  const [resolvedGroups, setResolvedGroups] = useState(groups);
  const groupRequestRef = useRef(new Map<string, Promise<StoryGroup | null>>());
  const effectiveGroups = useMemo(
    () =>
      groups.map((group) => {
        if (group.items.length > 0) return group;
        const resolved = resolvedGroups.find(
          (candidate) => candidate.id === group.id,
        );
        return resolved?.items.length
          ? {
              ...group,
              items: resolved.items,
              itemCount: resolved.itemCount ?? group.itemCount,
              unseenItemCount:
                resolved.unseenItemCount ?? group.unseenItemCount,
            }
          : group;
      }),
    [groups, resolvedGroups],
  );
  const progressHelpers = useMemo(
    () =>
      createStoryProgressHelpers({
        groups: effectiveGroups,
        initialSeenStoryIds,
        storageKey,
      }),
    [effectiveGroups, initialSeenStoryIds, storageKey],
  );
  const [seenStories, setSeenStories] = useState<SeenStoryMap>(() =>
    progressHelpers.createInitialStoryProgress(),
  );
  const controlledSeenStories = useMemo(
    () =>
      seenStoryIds === undefined
        ? null
        : progressHelpers.sanitizeStoryProgress(
            Object.fromEntries(seenStoryIds.map((id) => [id, true])),
          ),
    [progressHelpers, seenStoryIds],
  );
  const effectiveSeenStories = controlledSeenStories ?? seenStories;
  const [selection, setSelection] = useState<StorySelection | null>(null);
  const routerStoryQuery = router.query?.[storyQueryParam];
  const routerStoryId = Array.isArray(routerStoryQuery)
    ? routerStoryQuery[0]
    : routerStoryQuery;
  const requestedStoryId =
    sharedStoryId === undefined ? routerStoryId : sharedStoryId;
  const resolvedSectionId =
    sectionId ?? `stories-${generatedSectionId.replace(/:/g, "")}`;
  const titleId = `${resolvedSectionId}-title`;
  const selectedGroupIndex =
    selection === null
      ? -1
      : effectiveGroups.findIndex((group) => group.id === selection.groupId);
  const selectedGroup =
    selectedGroupIndex < 0
      ? null
      : (effectiveGroups[selectedGroupIndex] ?? null);
  const selectedItemIndex =
    selection === null || selectedGroup === null
      ? -1
      : selectedGroup.items.findIndex((item) => item.id === selection.itemId);
  const viewerSelection =
    selectedGroupIndex >= 0 && selectedItemIndex >= 0
      ? { groupIndex: selectedGroupIndex, itemIndex: selectedItemIndex }
      : null;

  useEffect(() => {
    if (seenStoryIds !== undefined) return;

    const timer = window.setTimeout(() => {
      const next = storageKey
        ? progressHelpers.readStoryProgress(window.localStorage)
        : progressHelpers.createInitialStoryProgress();
      setSeenStories(next);
    }, 0);
    return () => window.clearTimeout(timer);
  }, [progressHelpers, seenStoryIds, storageKey]);

  useEffect(() => {
    if (!requestedStoryId) return;

    for (const group of effectiveGroups) {
      const story = group.items.find((item) => item.id === requestedStoryId);
      if (story) {
        const timer = window.setTimeout(
          () =>
            setSelection({
              groupId: group.id,
              itemId: story.id,
            }),
          0,
        );
        return () => window.clearTimeout(timer);
      }
    }
  }, [effectiveGroups, requestedStoryId]);

  useEffect(() => {
    if (
      selection === null ||
      (selectedGroupIndex >= 0 && selectedItemIndex >= 0)
    )
      return;

    const timer = window.setTimeout(() => setSelection(null), 0);
    return () => window.clearTimeout(timer);
  }, [selection, selectedGroupIndex, selectedItemIndex]);

  useEffect(() => {
    if (!selectedGroup || selectedItemIndex < 0 || onNavigate) return;

    const selectedItem = selectedGroup.items[selectedItemIndex];
    const destinations = [
      selectedGroup.publisherHref,
      selectedItem?.href,
    ].filter((href): href is string => Boolean(href));

    for (const href of new Set(destinations)) {
      void router.prefetch?.(href);
    }
  }, [onNavigate, router, selectedGroup, selectedItemIndex]);

  const summary = useMemo(
    () => progressHelpers.getStorySummary(effectiveSeenStories),
    [effectiveSeenStories, progressHelpers],
  );

  const orderedStories = useMemo(
    () =>
      effectiveGroups
        .map((group, groupIndex) => ({
          group,
          groupIndex,
          progress: progressHelpers.getStoryGroupProgress(
            group,
            effectiveSeenStories,
          ),
        }))
        .sort(
          (a, b) =>
            STORY_STATE_PRIORITY[a.progress.state] -
              STORY_STATE_PRIORITY[b.progress.state] ||
            a.groupIndex - b.groupIndex,
        ),
    [effectiveGroups, effectiveSeenStories, progressHelpers],
  );

  const markSeen = useCallback(
    (storyId: string) => {
      const controlledNext = progressHelpers.markStorySeen(
        effectiveSeenStories,
        storyId,
      );
      if (controlledNext === effectiveSeenStories) return;

      if (onStorySeen) void onStorySeen(storyId);
      if (seenStoryIds !== undefined) return;

      setSeenStories((current) => {
        const next = progressHelpers.markStorySeen(current, storyId);
        if (next !== current) {
          progressHelpers.writeStoryProgress(
            storageKey ? window.localStorage : null,
            next,
          );
        }
        return next;
      });
    },
    [
      effectiveSeenStories,
      onStorySeen,
      progressHelpers,
      seenStoryIds,
      storageKey,
    ],
  );

  const openStory = async (group: StoryGroup) => {
    let groupToOpen =
      effectiveGroups.find((candidate) => candidate.id === group.id) ?? group;

    if (groupToOpen.items.length === 0 && resolveGroup) {
      let request = groupRequestRef.current.get(group.id);
      if (!request) {
        request = Promise.resolve(resolveGroup(group));
        groupRequestRef.current.set(group.id, request);
      }

      try {
        const resolved = await request;
        if (!resolved?.items.length) return;
        groupToOpen = resolved;
        setResolvedGroups((current) => {
          const exists = current.some(
            (candidate) => candidate.id === resolved.id,
          );
          return exists
            ? current.map((candidate) =>
                candidate.id === resolved.id ? resolved : candidate,
              )
            : [...current, resolved];
        });
      } finally {
        groupRequestRef.current.delete(group.id);
      }
    }

    // A lazy group resolves inside this click, before React can rebuild the
    // scoped progress helpers with its item IDs. Use the caller-owned IDs
    // directly so persisted progress still selects the first unseen Story.
    const progressAtOpen =
      seenStoryIds === undefined
        ? effectiveSeenStories
        : Object.fromEntries(
            seenStoryIds.map((storyId) => [storyId, true] as const),
          );
    const itemIndex = progressHelpers.getFirstUnseenStoryIndex(
      groupToOpen,
      progressAtOpen,
    );
    const item = groupToOpen.items[itemIndex];
    if (!item) return;

    setSelection({
      groupId: groupToOpen.id,
      itemId: item.id,
    });
  };

  const navigate = useCallback(
    (href: string) => (onNavigate ? onNavigate(href) : router.push(href)),
    [onNavigate, router],
  );

  const viewer = (
    <StoryViewer
      key={
        viewerSelection && selection
          ? `${selection.groupId}:${selection.itemId}:${viewerSelection.groupIndex}:${viewerSelection.itemIndex}`
          : "closed"
      }
      groups={effectiveGroups}
      resolveGroup={resolveGroup}
      selection={viewerSelection}
      onClose={() => {
        setSelection(null);
        void onStoryViewerClose?.();
      }}
      onNavigate={navigate}
      onSeen={markSeen}
      onGroupComplete={(group) => void onStoryGroupComplete?.(group)}
      shareQueryParam={storyQueryParam}
    />
  );

  if (renderTrigger) {
    const entry = orderedStories[0];
    return (
      <>
        {entry
          ? renderTrigger({
              group: entry.group,
              progress: entry.progress,
              onOpen: () => void openStory(entry.group),
            })
          : null}
        {viewer}
      </>
    );
  }

  return (
    <section
      aria-labelledby={titleId}
      className={`mb-8 min-w-0 max-w-full overflow-hidden ${
        inset ? "px-1 sm:px-6" : ""
      } ${className}`.trim()}
      data-testid={resolvedSectionId}
      id={resolvedSectionId}
    >
      <div className="min-w-0 max-w-full">
        <MediaSectionHeader
          badge={
            summary.unseen > 0
              ? t("stories.newCount", {
                  count: summary.unseen,
                  defaultValue: "{{count}} new",
                })
              : null
          }
          description={
            description ??
            t("stories.description", {
              defaultValue: "Fresh updates from stores around you",
            })
          }
          icon={
            <span className="rounded-full bg-linear-to-r from-primary-500 to-primary-400 p-2">
              <CirclePlay aria-hidden="true" className="text-white" size={16} />
            </span>
          }
          seeAllHref={seeAllHref}
          seeAllLabel={seeAllLabel ?? t("see_all")}
          showSeeAll={showSeeAll}
          title={
            title ??
            t("stories.title", {
              defaultValue: "Stories",
            })
          }
          titleId={titleId}
        />

        <StoryTray
          entries={orderedStories}
          ringColor={ringColor}
          showProfileName={showProfileName}
          trayStyle={trayStyle}
          itemGap={itemGap}
          hasMore={hasMore}
          isLoadingMore={isLoadingMore}
          label={
            trayLabel ??
            t("stories.trayLabel", {
              defaultValue: "Store stories",
            })
          }
          onLoadMore={onLoadMore}
          onOpen={(group) => void openStory(group)}
        />
      </div>

      {viewer}
    </section>
  );
};

export default StoriesSection;
