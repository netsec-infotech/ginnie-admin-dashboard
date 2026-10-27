import React, { useEffect, useRef, useCallback } from "react";

interface InfiniteScrollProps {
  children: React.ReactNode;
  hasMore: boolean;
  isLoading: boolean;
  onLoadMore: () => void;
  threshold?: number;
  className?: string;
}

const InfiniteScroll: React.FC<InfiniteScrollProps> = ({
  children,
  hasMore,
  isLoading,
  onLoadMore,
  threshold = 500,
  className = "",
}) => {
  const isLoadingRef = useRef(isLoading);
  const hasMoreRef = useRef(hasMore);
  const onLoadMoreRef = useRef(onLoadMore);
  const thresholdRef = useRef(threshold);
  const loadMoreTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const lastScrollTimeRef = useRef(0);

  // Update refs to avoid stale closures
  useEffect(() => {
    isLoadingRef.current = isLoading;
  }, [isLoading]);

  useEffect(() => {
    hasMoreRef.current = hasMore;
  }, [hasMore]);

  // Callers build `onLoadMore` from state (useInfiniteData rebuilds it on every
  // render), so reading it through a ref is what keeps the check below stable —
  // otherwise the scroll listener is torn down and re-bound on every render and
  // the settle check re-measures layout each time.
  useEffect(() => {
    onLoadMoreRef.current = onLoadMore;
    thresholdRef.current = threshold;
  }, [onLoadMore, threshold]);

  /** Request the next page if the viewport is within `threshold` of the end. */
  const loadMoreIfNearBottom = useCallback(() => {
    // Clear any pending timeout
    if (loadMoreTimeoutRef.current) {
      clearTimeout(loadMoreTimeoutRef.current);
    }

    // Use window scroll instead of container scroll
    const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
    const isNearBottom =
      scrollTop + clientHeight >= scrollHeight - thresholdRef.current;

    if (isNearBottom && hasMoreRef.current && !isLoadingRef.current) {
      // Debounce the loadMore call slightly to prevent rapid firing
      loadMoreTimeoutRef.current = setTimeout(() => {
        // Double-check conditions before calling loadMore
        if (hasMoreRef.current && !isLoadingRef.current) {
          onLoadMoreRef.current();
        }
      }, 50);
    }
  }, []);

  const handleScroll = useCallback(() => {
    const now = Date.now();

    // Throttle scroll events (max once per 100ms)
    if (now - lastScrollTimeRef.current < 100) {
      return;
    }
    lastScrollTimeRef.current = now;

    loadMoreIfNearBottom();
  }, [loadMoreIfNearBottom]);

  useEffect(() => {
    // Listen to window scroll instead of container scroll
    window.addEventListener("scroll", handleScroll, { passive: true });

    return () => {
      window.removeEventListener("scroll", handleScroll);
      // Clean up timeout on unmount
      if (loadMoreTimeoutRef.current) {
        clearTimeout(loadMoreTimeoutRef.current);
      }
    };
  }, [handleScroll]);

  // Re-check once a page settles, because scrolling alone can't restart the
  // feed: a page whose items all render nothing (home-layout sends sections
  // with zero products, which render null) leaves the document exactly as tall
  // as before, so no further scroll event ever fires and paging stalls one page
  // in. The same check auto-fills a first page too short to scroll at all.
  useEffect(() => {
    if (isLoading || !hasMore) return;
    loadMoreIfNearBottom();
  }, [isLoading, hasMore, loadMoreIfNearBottom]);

  // Clean up timeout when component unmounts or dependencies change
  useEffect(() => {
    return () => {
      if (loadMoreTimeoutRef.current) {
        clearTimeout(loadMoreTimeoutRef.current);
      }
    };
  }, []);

  return <div className={className}>{children}</div>;
};

export default InfiniteScroll;
