import { useEffect, useRef, useState } from "react";

interface ScrollImageCollapseOptions {
  collapseAt?: number;
  disabled?: boolean;
  expandAt?: number;
}

const DEFAULT_COLLAPSE_AT = 112;
const DEFAULT_EXPAND_AT = 32;

/**
 * Collapses image-heavy tab rows after a meaningful page scroll. Separate
 * collapse and expand thresholds provide hysteresis, while one animation frame
 * per scroll burst keeps the listener out of the browser's hot path.
 */
const useScrollImageCollapse = ({
  collapseAt = DEFAULT_COLLAPSE_AT,
  disabled = false,
  expandAt = DEFAULT_EXPAND_AT,
}: ScrollImageCollapseOptions = {}) => {
  const [imagesCollapsed, setImagesCollapsed] = useState(false);
  const imagesCollapsedRef = useRef(false);

  useEffect(() => {
    if (disabled) return;

    let frameId: number | null = null;

    const updateImageVisibility = () => {
      frameId = null;
      const scrollY = Math.max(window.scrollY, 0);
      const shouldCollapse = imagesCollapsedRef.current
        ? scrollY > expandAt
        : scrollY >= collapseAt;

      if (shouldCollapse === imagesCollapsedRef.current) return;
      imagesCollapsedRef.current = shouldCollapse;
      setImagesCollapsed(shouldCollapse);
    };

    const handleScroll = () => {
      if (frameId !== null) return;
      frameId = window.requestAnimationFrame(updateImageVisibility);
    };

    updateImageVisibility();
    window.addEventListener("scroll", handleScroll, { passive: true });

    return () => {
      window.removeEventListener("scroll", handleScroll);
      if (frameId !== null) window.cancelAnimationFrame(frameId);
    };
  }, [collapseAt, disabled, expandAt]);

  return imagesCollapsed;
};

export default useScrollImageCollapse;
