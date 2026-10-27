import { beforeEach, describe, expect, it } from "vitest";

import { HOME_STORIES } from "@/components/Stories/storyData";
import {
  createInitialStoryProgress,
  createStoryProgressHelpers,
  getFirstUnseenStoryIndex,
  getStoryGroupProgress,
  getStorySummary,
  markStorySeen,
  readStoryProgress,
  STORY_PROGRESS_STORAGE_KEY,
  writeStoryProgress,
} from "@/components/Stories/storyProgress";

describe("story progress", () => {
  beforeEach(() => window.localStorage.clear());

  it("seeds unseen, partial, and seen groups for the static UI", () => {
    const progress = createInitialStoryProgress();

    expect(getStoryGroupProgress(HOME_STORIES[0], progress).state).toBe(
      "unseen",
    );
    expect(getStoryGroupProgress(HOME_STORIES[1], progress)).toMatchObject({
      state: "partial",
      seen: 1,
      unseen: 2,
    });
    expect(getStoryGroupProgress(HOME_STORIES[2], progress)).toMatchObject({
      state: "seen",
      seen: 2,
      unseen: 0,
    });
  });

  it("opens a source at its first unseen item and replays from the start when fully seen", () => {
    const progress = createInitialStoryProgress();

    expect(getFirstUnseenStoryIndex(HOME_STORIES[1], progress)).toBe(1);
    expect(getFirstUnseenStoryIndex(HOME_STORIES[2], progress)).toBe(0);
  });

  it("moves group and overall counts as stories are seen", () => {
    const initial = createInitialStoryProgress();
    const next = markStorySeen(initial, "fresh-finds-1");

    expect(next).not.toBe(initial);
    expect(getStoryGroupProgress(HOME_STORIES[0], next)).toMatchObject({
      state: "partial",
      seen: 1,
      unseen: 2,
    });
    expect(getStorySummary(HOME_STORIES, next).seen).toBe(
      getStorySummary(HOME_STORIES, initial).seen + 1,
    );
    expect(markStorySeen(next, "fresh-finds-1")).toBe(next);
  });

  it("persists valid progress and drops stale or malformed entries", () => {
    const progress = markStorySeen(
      createInitialStoryProgress(),
      "fresh-finds-1",
    );
    writeStoryProgress(window.localStorage, progress);

    expect(readStoryProgress(window.localStorage)).toEqual(progress);

    window.localStorage.setItem(
      STORY_PROGRESS_STORAGE_KEY,
      JSON.stringify({
        "fresh-finds-2": true,
        "removed-story": true,
        "daily-bake-2": false,
      }),
    );
    expect(readStoryProgress(window.localStorage)).toEqual({
      "fresh-finds-2": true,
    });

    window.localStorage.setItem(STORY_PROGRESS_STORAGE_KEY, "not-json");
    expect(readStoryProgress(window.localStorage)).toEqual(
      createInitialStoryProgress(),
    );
  });

  it("isolates factory progress by feed data, seed, and storage key", () => {
    const freshHelpers = createStoryProgressHelpers({
      groups: [HOME_STORIES[0]],
      initialSeenStoryIds: ["fresh-finds-1", "daily-bake-1"],
      storageKey: "fresh-story-progress",
    });
    const bakeryHelpers = createStoryProgressHelpers({
      groups: [HOME_STORIES[1]],
      initialSeenStoryIds: ["daily-bake-1"],
      storageKey: "bakery-story-progress",
    });

    expect(freshHelpers.createInitialStoryProgress()).toEqual({
      "fresh-finds-1": true,
    });
    expect(bakeryHelpers.createInitialStoryProgress()).toEqual({
      "daily-bake-1": true,
    });

    const freshProgress = freshHelpers.markStorySeen(
      freshHelpers.createInitialStoryProgress(),
      "fresh-finds-2",
    );
    freshHelpers.writeStoryProgress(window.localStorage, freshProgress);
    bakeryHelpers.writeStoryProgress(
      window.localStorage,
      bakeryHelpers.createInitialStoryProgress(),
    );

    expect(
      JSON.parse(window.localStorage.getItem("fresh-story-progress") ?? "{}"),
    ).toEqual({ "fresh-finds-1": true, "fresh-finds-2": true });
    expect(
      JSON.parse(window.localStorage.getItem("bakery-story-progress") ?? "{}"),
    ).toEqual({ "daily-bake-1": true });

    const unchanged = freshHelpers.markStorySeen(freshProgress, "daily-bake-2");
    expect(unchanged).toBe(freshProgress);

    window.localStorage.setItem(
      "fresh-story-progress",
      JSON.stringify({
        "fresh-finds-3": true,
        "daily-bake-2": true,
      }),
    );
    expect(freshHelpers.readStoryProgress(window.localStorage)).toEqual({
      "fresh-finds-3": true,
    });
  });

  it("supports in-memory progress when no storage key is supplied", () => {
    const helpers = createStoryProgressHelpers({
      groups: [HOME_STORIES[0]],
      initialSeenStoryIds: ["fresh-finds-1"],
    });
    const progress = helpers.markStorySeen(
      helpers.createInitialStoryProgress(),
      "fresh-finds-2",
    );

    helpers.writeStoryProgress(window.localStorage, progress);

    expect(window.localStorage).toHaveLength(0);
    expect(helpers.readStoryProgress(window.localStorage)).toEqual({
      "fresh-finds-1": true,
    });
  });
});
