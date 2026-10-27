import { FC, useEffect, useRef, useState } from "react";

interface LoadMoreSentinelProps {
  /** Called whenever the marker scrolls into view. Must be idempotent — the
   *  observer can fire again on the next scroll while a page is still loading. */
  onVisible: () => void;
  /** Stop observing (e.g. once the feed is exhausted). */
  disabled?: boolean;
  /** Grows the viewport for the check, so the page starts loading even earlier. */
  rootMargin?: string;
  className?: string;
}

/**
 * A zero-height marker that asks for the next page when it scrolls into view.
 *
 * Drop it *among* the items rather than after them: placing it before the last
 * couple of entries starts the next page while the user is still reading those,
 * so the feed extends before they ever reach the end. A bottom-of-document
 * scroll check can't express that — by the time it fires the user is already
 * looking at the footer.
 */
const LoadMoreSentinel: FC<LoadMoreSentinelProps> = ({
  onVisible,
  disabled = false,
  rootMargin = "0px",
  className,
}) => {
  // A callback ref, not useRef: the marker is rendered between list items, so
  // appending a page re-mounts it at a new position. A plain ref would leave the
  // observer watching a detached node and the feed would stop after one page.
  const [node, setNode] = useState<HTMLDivElement | null>(null);

  // Callers rebuild onVisible on every render (useInfiniteData does); reading it
  // through a ref keeps the observer from being torn down and re-created.
  const onVisibleRef = useRef(onVisible);

  useEffect(() => {
    onVisibleRef.current = onVisible;
  }, [onVisible]);

  useEffect(() => {
    if (!node || disabled) return;

    // Static export runs this in browsers only, but jsdom and older Safari can
    // both lack the observer — the caller's scroll fallback still covers them.
    if (typeof IntersectionObserver === "undefined") return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          onVisibleRef.current();
        }
      },
      { rootMargin },
    );

    observer.observe(node);

    return () => observer.disconnect();
  }, [node, disabled, rootMargin]);

  return (
    <div
      ref={setNode}
      aria-hidden="true"
      data-testid="load-more-sentinel"
      className={className}
    />
  );
};

export default LoadMoreSentinel;
