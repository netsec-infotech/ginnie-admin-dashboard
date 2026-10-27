import type { SeenStoryMap } from "@/components/Stories/storyProgress";

type StorageReader = Pick<Storage, "getItem"> | null | undefined;
type StorageWriter = Pick<Storage, "getItem" | "setItem"> | null | undefined;

export interface PendingStorySeen {
  statusId: number;
  storyId: string;
  queuedAt: number;
}

type PendingStorySeenMap = Record<string, PendingStorySeen>;
export type SeenStoryGroupMap = Record<string, string>;
export type SeenStoryStatusIdMap = Record<string, number>;

interface SeenStoryMetadata {
  groupId: string;
  statusId?: number;
}

type SeenStoryMetadataMap = Record<string, SeenStoryMetadata>;

export const getPendingStorySeenStorageKey = (storageKey: string) =>
  `${storageKey}:pending-api-v1`;

export const getSeenStoryGroupsStorageKey = (storageKey: string) =>
  `${storageKey}:groups-v1`;

const parseObject = (value: string | null): Record<string, unknown> => {
  if (!value) return {};

  try {
    const parsed: unknown = JSON.parse(value);
    return parsed && typeof parsed === "object" && !Array.isArray(parsed)
      ? (parsed as Record<string, unknown>)
      : {};
  } catch {
    return {};
  }
};

export const readStoredSeenStoryIds = (
  storage: StorageReader,
  storageKey: string,
): string[] => {
  if (!storage) return [];

  const progress = parseObject(storage.getItem(storageKey));
  return Object.entries(progress)
    .filter(([, isSeen]) => isSeen === true)
    .map(([storyId]) => storyId);
};

export const mergeStoredSeenStoryIds = (
  storage: StorageWriter,
  storageKey: string,
  storyIds: readonly string[],
): string[] => {
  const merged = [
    ...new Set([...readStoredSeenStoryIds(storage, storageKey), ...storyIds]),
  ];
  if (!storage) return merged;

  const progress: SeenStoryMap = Object.fromEntries(
    merged.map((storyId) => [storyId, true]),
  );
  try {
    storage.setItem(storageKey, JSON.stringify(progress));
  } catch {
    // Seen state still remains in React state when storage is unavailable/full.
  }
  return merged;
};

export const readStoredSeenStoryGroups = (
  storage: StorageReader,
  storageKey: string,
): SeenStoryGroupMap => {
  return Object.fromEntries(
    Object.entries(readStoredSeenStoryMetadata(storage, storageKey)).map(
      ([storyId, metadata]) => [storyId, metadata.groupId],
    ),
  );
};

const readStoredSeenStoryMetadata = (
  storage: StorageReader,
  storageKey: string,
): SeenStoryMetadataMap => {
  if (!storage) return {};

  return Object.fromEntries(
    Object.entries(
      parseObject(storage.getItem(getSeenStoryGroupsStorageKey(storageKey))),
    ).flatMap(([storyId, value]) => {
      if (!storyId) return [];

      // Backward compatibility for the original UUID -> merchant ID index.
      if (typeof value === "string" && value.length > 0) {
        return [[storyId, { groupId: value }]];
      }
      if (!value || typeof value !== "object" || Array.isArray(value)) return [];

      const candidate = value as Record<string, unknown>;
      if (typeof candidate.groupId !== "string" || !candidate.groupId) return [];

      const statusId = candidate.statusId;
      return [[
        storyId,
        {
          groupId: candidate.groupId,
          ...(Number.isInteger(statusId) && Number(statusId) > 0
            ? { statusId: Number(statusId) }
            : {}),
        },
      ]];
    }),
  );
};

export const readStoredSeenStoryStatusIds = (
  storage: StorageReader,
  storageKey: string,
): SeenStoryStatusIdMap =>
  Object.fromEntries(
    Object.entries(readStoredSeenStoryMetadata(storage, storageKey)).flatMap(
      ([storyId, metadata]) =>
        metadata.statusId === undefined
          ? []
          : [[storyId, metadata.statusId]],
    ),
  );

export const mergeStoredSeenStoryGroups = (
  storage: StorageWriter,
  storageKey: string,
  entries: readonly {
    storyId: string;
    groupId: string;
    statusId?: number;
  }[],
): SeenStoryGroupMap => {
  const merged = { ...readStoredSeenStoryMetadata(storage, storageKey) };
  entries.forEach(({ storyId, groupId, statusId }) => {
    if (!storyId || !groupId) return;
    const existingStatusId = merged[storyId]?.statusId;
    merged[storyId] = {
      groupId,
      ...(Number.isInteger(statusId) && Number(statusId) > 0
        ? { statusId }
        : existingStatusId === undefined
          ? {}
          : { statusId: existingStatusId }),
    };
  });

  if (storage) {
    try {
      storage.setItem(
        getSeenStoryGroupsStorageKey(storageKey),
        JSON.stringify(merged),
      );
    } catch {
      // The flat seen map still preserves progress if this index cannot be saved.
    }
  }

  return Object.fromEntries(
    Object.entries(merged).map(([storyId, metadata]) => [
      storyId,
      metadata.groupId,
    ]),
  );
};

export const readPendingStorySeen = (
  storage: StorageReader,
  storageKey: string,
): PendingStorySeen[] => {
  if (!storage) return [];

  const pending = parseObject(
    storage.getItem(getPendingStorySeenStorageKey(storageKey)),
  );
  return Object.values(pending).filter(
    (entry): entry is PendingStorySeen =>
      Boolean(entry) &&
      typeof entry === "object" &&
      Number.isInteger((entry as PendingStorySeen).statusId) &&
      (entry as PendingStorySeen).statusId > 0 &&
      typeof (entry as PendingStorySeen).storyId === "string" &&
      typeof (entry as PendingStorySeen).queuedAt === "number",
  );
};

const writePendingStorySeen = (
  storage: StorageWriter,
  storageKey: string,
  pending: readonly PendingStorySeen[],
) => {
  if (!storage) return;

  const value: PendingStorySeenMap = Object.fromEntries(
    pending.map((entry) => [String(entry.statusId), entry]),
  );
  try {
    storage.setItem(
      getPendingStorySeenStorageKey(storageKey),
      JSON.stringify(value),
    );
  } catch {
    // Database synchronization can be retried only when storage is available.
  }
};

export const queuePendingStorySeen = (
  storage: StorageWriter,
  storageKey: string,
  entry: PendingStorySeen,
): PendingStorySeen[] => {
  const pending = readPendingStorySeen(storage, storageKey).filter(
    (candidate) => candidate.statusId !== entry.statusId,
  );
  const next = [...pending, entry];
  writePendingStorySeen(storage, storageKey, next);
  return next;
};

export const removePendingStorySeen = (
  storage: StorageWriter,
  storageKey: string,
  statusIds: readonly number[],
): PendingStorySeen[] => {
  const completed = new Set(statusIds);
  const remaining = readPendingStorySeen(storage, storageKey).filter(
    (entry) => !completed.has(entry.statusId),
  );
  writePendingStorySeen(storage, storageKey, remaining);
  return remaining;
};
