import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { getCookie } from "@/lib/cookies";
import { getHomeLayoutSection } from "@/routes/api";
import { HomeLayoutSection } from "@/types/ApiResponse";

/** Every home-layout content kind carries an `id`; it's the dedupe key. */
type Identifiable = { id: string | number };

interface UseHomeSectionItemsOptions<T> {
  /**
   * Reads the inlined first page out of `section.content`.
   * MUST be a stable reference (a module-level function, not an inline arrow) —
   * it's a dependency of the memo that detects a new payload, and an unstable
   * one resets the appended pages on every render.
   */
  getInline: (content: Record<string, unknown[]>) => T[];
  /** Adapts one raw page from /home-layout/sections/{id}. Also must be stable. */
  adaptPage: (raw: unknown[]) => T[];
}

/**
 * Infinite scroll for the items inside ONE /home-layout section rail.
 *
 * The homepage payload inlines at most `config.limit` items per section, so a
 * rail that filled its limit is only a window onto a longer list (a section can
 * hold 19 products but ship 12 with the layout). This hook keeps the inline
 * items as the instant first page and pulls the rest from
 * `/home-layout/sections/{id}` as the user swipes toward the end of the rail.
 *
 * The feed endpoint is generic — it returns whatever kind the section holds —
 * so products, banners, brands and stores all page through it; only the two
 * adapters differ. Sections that send no `config.limit` (categories, and
 * banners today, whose `config` comes back as `[]`) never request a page.
 *
 * Deliberately NOT `useInfiniteData`: that hook owns page 1 and re-fetches it
 * via SWR on mount, which would discard the items already embedded in the
 * layout response and fire one request per rail on every homepage load.
 */
export const useHomeSectionItems = <T extends Identifiable>(
  section: HomeLayoutSection,
  { getInline, adaptPage }: UseHomeSectionItemsOptions<T>,
) => {
  const inline = useMemo(
    () => getInline(section?.content ?? {}),
    [section?.content, getInline],
  );

  // `config.limit` is the page size the backend inlined with, so resuming at
  // page 2 with the same per_page picks up exactly where the rail left off.
  const perPage = Number(section?.config?.limit) || 0;

  // Only a rail that actually filled its limit can have more behind it — a
  // shorter one is already the whole list, and paging it wastes a request.
  // Sections that send no limit at all arrive complete.
  const canPaginate = perPage > 0 && inline.length >= perPage;

  const [more, setMore] = useState<T[]>([]);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(canPaginate);

  // Mirrors of the paging state. Swiper binds its event callbacks at init, so a
  // loadMore closing over state could page from a stale cursor and refetch the
  // same page; the refs keep the cursor correct however the callback is bound.
  const moreRef = useRef<T[]>([]);
  const hasMoreRef = useRef(canPaginate);
  const isLoadingRef = useRef(false);
  const nextPageRef = useRef(2);

  // Reset whenever this rail gets a new payload. The homepage keys sections by
  // id, so switching category re-uses the same component instance — without
  // this the previous category's appended pages leak into the new one.
  useEffect(() => {
    moreRef.current = [];
    hasMoreRef.current = canPaginate;
    isLoadingRef.current = false;
    nextPageRef.current = 2;
    setMore([]);
    setHasMore(canPaginate);
    setIsLoadingMore(false);
  }, [inline, canPaginate]);

  const loadMore = useCallback(async () => {
    if (isLoadingRef.current || !hasMoreRef.current) return;

    isLoadingRef.current = true;
    setIsLoadingMore(true);

    const { lat = "", lng = "" } =
      (getCookie("userLocation") as UserLocation) || {};

    try {
      const res = await getHomeLayoutSection(section.id, {
        page: nextPageRef.current,
        per_page: perPage,
        latitude: lat || undefined,
        longitude: lng || undefined,
      });

      if (!res.success) {
        hasMoreRef.current = false;
        setHasMore(false);
        return;
      }

      const fetched = adaptPage((res.data?.data as unknown[]) ?? []);

      // The feed orders a page slightly differently from the inline payload, so
      // a page boundary can hand back something already on screen. Dropping the
      // repeats keeps React keys unique and the rail free of duplicates.
      const seen = new Set([
        ...inline.map((item) => item.id),
        ...moreRef.current.map((item) => item.id),
      ]);
      const merged = [
        ...moreRef.current,
        ...fetched.filter((item) => !seen.has(item.id)),
      ];

      moreRef.current = merged;
      nextPageRef.current += 1;
      setMore(merged);

      // Stop on an empty page as well as on the total: a page that dedupes down
      // to nothing would otherwise keep hasMore true and re-request on every
      // further swipe without the rail ever growing.
      const exhausted =
        fetched.length === 0 ||
        inline.length + merged.length >= (res.data?.total ?? 0);

      hasMoreRef.current = !exhausted;
      setHasMore(!exhausted);
    } catch (error) {
      // getHomeLayoutSection swallows API errors already; this guards the
      // adapters against a malformed payload rather than blanking the rail.
      console.error("Home section load-more failed:", error);
      hasMoreRef.current = false;
      setHasMore(false);
    } finally {
      isLoadingRef.current = false;
      setIsLoadingMore(false);
    }
  }, [inline, perPage, section?.id, adaptPage]);

  const items = useMemo(() => [...inline, ...more], [inline, more]);

  return { items, hasMore, isLoadingMore, loadMore };
};

export default useHomeSectionItems;
