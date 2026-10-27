import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import type { Reel } from "@/components/Reels/types";

export const LOCAL_REEL_VIEW_INCREMENTS_STORAGE_KEY =
  "hyperlocal-reel-view-increments-v1";
export const SESSION_VIEWED_REELS_STORAGE_KEY =
  "hyperlocal-viewed-reels-v1";

const VIEW_COUNTS_CHANGED_EVENT = "hyperlocal:reel-view-counts-changed";

type ReelViewIncrements = Record<string, number>;

const readViewIncrements = (): ReelViewIncrements => {
  if (typeof window === "undefined") return {};
  try {
    const stored = JSON.parse(
      window.localStorage.getItem(LOCAL_REEL_VIEW_INCREMENTS_STORAGE_KEY) ??
        "{}",
    );
    if (!stored || typeof stored !== "object" || Array.isArray(stored)) return {};

    return Object.fromEntries(
      Object.entries(stored).filter(
        (entry): entry is [string, number] =>
          typeof entry[1] === "number" &&
          Number.isFinite(entry[1]) &&
          entry[1] >= 0,
      ),
    );
  } catch {
    return {};
  }
};

const readViewedReelIds = () => {
  if (typeof window === "undefined") return new Set<string>();
  try {
    const stored = JSON.parse(
      window.sessionStorage.getItem(SESSION_VIEWED_REELS_STORAGE_KEY) ?? "[]",
    );
    return new Set<string>(
      Array.isArray(stored)
        ? stored.filter((value): value is string => typeof value === "string")
        : [],
    );
  } catch {
    return new Set<string>();
  }
};

/** Frontend-only Reel views, persisted locally and deduplicated per tab session. */
export const useLocalReelViews = (reels: readonly Reel[]) => {
  const [increments, setIncrements] = useState<ReelViewIncrements>({});
  const viewedInMemoryRef = useRef(new Set<string>());

  useEffect(() => {
    const syncStoredCounts = () => setIncrements(readViewIncrements());
    viewedInMemoryRef.current = readViewedReelIds();
    syncStoredCounts();
    window.addEventListener(VIEW_COUNTS_CHANGED_EVENT, syncStoredCounts);
    return () =>
      window.removeEventListener(VIEW_COUNTS_CHANGED_EVENT, syncStoredCounts);
  }, []);

  const recordView = useCallback((reelId: string) => {
    const viewedInSession = readViewedReelIds();
    if (
      viewedInMemoryRef.current.has(reelId) ||
      viewedInSession.has(reelId)
    ) {
      viewedInMemoryRef.current.add(reelId);
      return;
    }

    viewedInMemoryRef.current.add(reelId);
    viewedInSession.add(reelId);

    try {
      window.sessionStorage.setItem(
        SESSION_VIEWED_REELS_STORAGE_KEY,
        JSON.stringify([...viewedInSession]),
      );
    } catch {
      // In-memory deduplication still protects the current component instance.
    }

    const current = readViewIncrements();
    const next = { ...current, [reelId]: (current[reelId] ?? 0) + 1 };
    try {
      window.localStorage.setItem(
        LOCAL_REEL_VIEW_INCREMENTS_STORAGE_KEY,
        JSON.stringify(next),
      );
    } catch {
      // The count remains functional for the current page when storage is blocked.
    }
    setIncrements(next);
    window.dispatchEvent(new Event(VIEW_COUNTS_CHANGED_EVENT));
  }, []);

  const viewCounts = useMemo(
    () =>
      Object.fromEntries(
        reels.map((reel) => [
          reel.id,
          reel.views + (increments[reel.id] ?? 0),
        ]),
      ),
    [increments, reels],
  );

  return { recordView, viewCounts };
};
