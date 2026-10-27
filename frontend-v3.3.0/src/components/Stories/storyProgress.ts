import { HOME_STORIES, INITIAL_SEEN_STORY_IDS } from "./storyData";
import type { StoryGroup } from "./types";

export const STORY_PROGRESS_STORAGE_KEY = "hyperlocal-story-progress-v1";

export type SeenStoryMap = Record<string, true>;
export type StoryGroupState = "unseen" | "partial" | "seen";

export interface StoryGroupProgress {
  total: number;
  seen: number;
  unseen: number;
  state: StoryGroupState;
}

type StoryProgressReader = Pick<Storage, "getItem"> | null | undefined;
type StoryProgressWriter = Pick<Storage, "setItem"> | null | undefined;

export interface StoryProgressHelpersOptions {
  groups: readonly StoryGroup[];
  initialSeenStoryIds?: readonly string[];
  storageKey?: string;
}

export interface StoryProgressHelpers {
  storageKey?: string;
  createInitialStoryProgress: () => SeenStoryMap;
  sanitizeStoryProgress: (value: unknown) => SeenStoryMap;
  readStoryProgress: (storage: StoryProgressReader) => SeenStoryMap;
  writeStoryProgress: (
    storage: StoryProgressWriter,
    progress: SeenStoryMap,
  ) => void;
  markStorySeen: (progress: SeenStoryMap, storyId: string) => SeenStoryMap;
  getStoryGroupProgress: (
    group: StoryGroup,
    progress: SeenStoryMap,
  ) => StoryGroupProgress;
  getFirstUnseenStoryIndex: (
    group: StoryGroup,
    progress: SeenStoryMap,
  ) => number;
  getStorySummary: (progress: SeenStoryMap) => {
    total: number;
    seen: number;
    unseen: number;
  };
}

export const markStorySeen = (
  progress: SeenStoryMap,
  storyId: string,
): SeenStoryMap =>
  progress[storyId] ? progress : { ...progress, [storyId]: true };

export const getStoryGroupProgress = (
  group: StoryGroup,
  progress: SeenStoryMap,
): StoryGroupProgress => {
  const isHydrated = group.items.length > 0;
  const total = isHydrated ? group.items.length : (group.itemCount ?? 0);
  const hydratedSeen = group.items.filter(
    (item) => progress[item.id] || item.seen,
  ).length;
  const metadataUnseen = Math.min(
    total,
    Math.max(0, group.unseenItemCount ?? total),
  );
  const seen = isHydrated ? hydratedSeen : total - metadataUnseen;
  const unseen = Math.max(0, total - seen);

  return {
    total,
    seen,
    unseen,
    state: seen === 0 ? "unseen" : seen === total ? "seen" : "partial",
  };
};

export const getFirstUnseenStoryIndex = (
  group: StoryGroup,
  progress: SeenStoryMap,
) => {
  const index = group.items.findIndex(
    (item) => !progress[item.id] && !item.seen,
  );
  return index === -1 ? 0 : index;
};

export const getStorySummary = (
  groups: readonly StoryGroup[],
  progress: SeenStoryMap,
) => {
  const total = groups.reduce(
    (sum, group) => sum + (group.items.length || group.itemCount || 0),
    0,
  );
  const seen = groups.reduce(
    (sum, group) => sum + getStoryGroupProgress(group, progress).seen,
    0,
  );

  return { total, seen, unseen: total - seen };
};

/**
 * Creates progress helpers scoped to one Stories feed. Scoping the valid IDs,
 * seed, and storage key prevents independently rendered feeds from leaking
 * viewed state into one another.
 */
export const createStoryProgressHelpers = ({
  groups,
  initialSeenStoryIds = [],
  storageKey,
}: StoryProgressHelpersOptions): StoryProgressHelpers => {
  const validStoryIds = new Set(
    groups.flatMap((group) => group.items.map((item) => item.id)),
  );
  const validInitialSeenStoryIds = initialSeenStoryIds.filter((id) =>
    validStoryIds.has(id),
  );

  const createInitialProgress = (): SeenStoryMap =>
    Object.fromEntries(validInitialSeenStoryIds.map((id) => [id, true]));

  const sanitizeProgress = (value: unknown): SeenStoryMap => {
    if (!value || typeof value !== "object" || Array.isArray(value)) {
      return createInitialProgress();
    }

    const entries = Object.entries(value).filter(
      ([id, isSeen]) => validStoryIds.has(id) && isSeen === true,
    );

    return Object.fromEntries(entries);
  };

  const readProgress = (storage: StoryProgressReader): SeenStoryMap => {
    if (!storage || !storageKey) return createInitialProgress();

    try {
      const stored = storage.getItem(storageKey);
      return stored
        ? sanitizeProgress(JSON.parse(stored))
        : createInitialProgress();
    } catch {
      return createInitialProgress();
    }
  };

  const writeProgress = (
    storage: StoryProgressWriter,
    progress: SeenStoryMap,
  ) => {
    if (!storage || !storageKey) return;

    try {
      storage.setItem(storageKey, JSON.stringify(progress));
    } catch {
      // Story state is an enhancement. Browsing remains usable when storage is
      // unavailable or full.
    }
  };

  return {
    storageKey,
    createInitialStoryProgress: createInitialProgress,
    sanitizeStoryProgress: sanitizeProgress,
    readStoryProgress: readProgress,
    writeStoryProgress: writeProgress,
    markStorySeen: (progress, storyId) =>
      validStoryIds.has(storyId) ? markStorySeen(progress, storyId) : progress,
    getStoryGroupProgress,
    getFirstUnseenStoryIndex,
    getStorySummary: (progress) => getStorySummary(groups, progress),
  };
};

const legacyStoryProgressHelpers = createStoryProgressHelpers({
  groups: HOME_STORIES,
  initialSeenStoryIds: INITIAL_SEEN_STORY_IDS,
  storageKey: STORY_PROGRESS_STORAGE_KEY,
});

/** Backward-compatible helpers for the original home Stories feed. */
export const createInitialStoryProgress =
  legacyStoryProgressHelpers.createInitialStoryProgress;
export const sanitizeStoryProgress =
  legacyStoryProgressHelpers.sanitizeStoryProgress;
export const readStoryProgress = legacyStoryProgressHelpers.readStoryProgress;
export const writeStoryProgress = legacyStoryProgressHelpers.writeStoryProgress;
