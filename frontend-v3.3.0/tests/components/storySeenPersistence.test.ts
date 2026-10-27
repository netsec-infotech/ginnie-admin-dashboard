import { beforeEach, describe, expect, it } from "vitest";

import {
  getPendingStorySeenStorageKey,
  getSeenStoryGroupsStorageKey,
  mergeStoredSeenStoryGroups,
  mergeStoredSeenStoryIds,
  queuePendingStorySeen,
  readPendingStorySeen,
  readStoredSeenStoryIds,
  readStoredSeenStoryGroups,
  readStoredSeenStoryStatusIds,
  removePendingStorySeen,
} from "@/components/WatchAndBuy/storySeenPersistence";

const STORAGE_KEY = "test-story-progress";

describe("Watch & Buy Story seen persistence", () => {
  beforeEach(() => window.localStorage.clear());

  it("merges backend and local seen IDs using the Story progress format", () => {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ "local-story": true, invalid: false }),
    );

    expect(
      mergeStoredSeenStoryIds(window.localStorage, STORAGE_KEY, [
        "backend-story",
        "local-story",
      ]),
    ).toEqual(["local-story", "backend-story"]);
    expect(readStoredSeenStoryIds(window.localStorage, STORAGE_KEY)).toEqual([
      "local-story",
      "backend-story",
    ]);
    expect(JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "{}"))
      .toEqual({ "local-story": true, "backend-story": true });
  });

  it("keeps failed database work queued and removes only confirmed IDs", () => {
    queuePendingStorySeen(window.localStorage, STORAGE_KEY, {
      statusId: 10,
      storyId: "story-10",
      queuedAt: 1,
    });
    queuePendingStorySeen(window.localStorage, STORAGE_KEY, {
      statusId: 11,
      storyId: "story-11",
      queuedAt: 2,
    });

    expect(readPendingStorySeen(window.localStorage, STORAGE_KEY)).toHaveLength(
      2,
    );
    expect(
      removePendingStorySeen(window.localStorage, STORAGE_KEY, [10]),
    ).toEqual([{ statusId: 11, storyId: "story-11", queuedAt: 2 }]);
    expect(
      JSON.parse(
        window.localStorage.getItem(
          getPendingStorySeenStorageKey(STORAGE_KEY),
        ) ?? "{}",
      ),
    ).toEqual({
      "11": { statusId: 11, storyId: "story-11", queuedAt: 2 },
    });
  });

  it("persists which merchant owns each locally seen Story", () => {
    mergeStoredSeenStoryGroups(window.localStorage, STORAGE_KEY, [
      { storyId: "story-10", groupId: "nearby-bakery", statusId: 10 },
      { storyId: "story-11", groupId: "nearby-bakery", statusId: 11 },
    ]);

    expect(readStoredSeenStoryGroups(window.localStorage, STORAGE_KEY)).toEqual(
      {
        "story-10": "nearby-bakery",
        "story-11": "nearby-bakery",
      },
    );
    expect(
      JSON.parse(
        window.localStorage.getItem(
          getSeenStoryGroupsStorageKey(STORAGE_KEY),
        ) ?? "{}",
      ),
    ).toEqual({
      "story-10": { groupId: "nearby-bakery", statusId: 10 },
      "story-11": { groupId: "nearby-bakery", statusId: 11 },
    });
    expect(
      readStoredSeenStoryStatusIds(window.localStorage, STORAGE_KEY),
    ).toEqual({ "story-10": 10, "story-11": 11 });
  });

  it("reads and upgrades the legacy UUID to merchant index", () => {
    window.localStorage.setItem(
      getSeenStoryGroupsStorageKey(STORAGE_KEY),
      JSON.stringify({ "legacy-story": "nearby-bakery" }),
    );

    expect(readStoredSeenStoryGroups(window.localStorage, STORAGE_KEY)).toEqual(
      { "legacy-story": "nearby-bakery" },
    );
    expect(
      readStoredSeenStoryStatusIds(window.localStorage, STORAGE_KEY),
    ).toEqual({});

    mergeStoredSeenStoryGroups(window.localStorage, STORAGE_KEY, [
      { storyId: "legacy-story", groupId: "nearby-bakery", statusId: 12 },
    ]);
    expect(
      JSON.parse(
        window.localStorage.getItem(
          getSeenStoryGroupsStorageKey(STORAGE_KEY),
        ) ?? "{}",
      ),
    ).toEqual({
      "legacy-story": { groupId: "nearby-bakery", statusId: 12 },
    });
  });
});
