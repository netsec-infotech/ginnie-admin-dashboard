import {
  type MouseEventHandler,
  type PointerEventHandler,
  useCallback,
  useEffect,
  useRef,
  useState,
} from "react";

interface DragState {
  pointerId: number;
  startX: number;
  startScrollLeft: number;
  moved: boolean;
  lastX: number;
  lastTime: number;
  velocity: number;
}

export interface HorizontalDragScrollOptions {
  enabled?: boolean;
  threshold?: number;
  mouseOnly?: boolean;
  stopPropagation?: boolean;
  momentum?: boolean;
}

export interface HorizontalDragScrollResult {
  isDragging: boolean;
  dragHandlers: {
    onClickCapture: MouseEventHandler<HTMLDivElement>;
    onPointerCancel: PointerEventHandler<HTMLDivElement>;
    onPointerDown: PointerEventHandler<HTMLDivElement>;
    onPointerMove: PointerEventHandler<HTMLDivElement>;
    onPointerUp: PointerEventHandler<HTMLDivElement>;
  };
}

/**
 * Adds desktop drag-to-scroll behavior without interfering with native touch
 * momentum. A drag suppresses the following click so rail items do not open
 * accidentally when the pointer is released.
 */
export const useHorizontalDragScroll = ({
  enabled = true,
  threshold = 6,
  mouseOnly = true,
  stopPropagation = false,
  momentum = false,
}: HorizontalDragScrollOptions = {}): HorizontalDragScrollResult => {
  const dragRef = useRef<DragState | null>(null);
  const suppressClickRef = useRef(false);
  const suppressClickTimerRef = useRef<number | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const momentumFrameRef = useRef<number | null>(null);

  const stopMomentum = useCallback(() => {
    if (momentumFrameRef.current !== null) {
      window.cancelAnimationFrame(momentumFrameRef.current);
      momentumFrameRef.current = null;
    }
  }, []);

  useEffect(
    () => () => {
      stopMomentum();
      if (suppressClickTimerRef.current !== null) {
        window.clearTimeout(suppressClickTimerRef.current);
      }
    },
    [enabled, stopMomentum],
  );

  const onPointerDown = useCallback<PointerEventHandler<HTMLDivElement>>(
    (event) => {
      if (stopPropagation) event.stopPropagation();
      if (!enabled || event.button !== 0) return;
      stopMomentum();
      if (mouseOnly && event.pointerType === "touch") return;

      dragRef.current = {
        pointerId: event.pointerId,
        startX: event.clientX,
        startScrollLeft: event.currentTarget.scrollLeft,
        moved: false,
        lastX: event.clientX,
        lastTime: performance.now(),
        velocity: 0,
      };
    },
    [enabled, mouseOnly, stopMomentum, stopPropagation],
  );

  const onPointerMove = useCallback<PointerEventHandler<HTMLDivElement>>(
    (event) => {
      if (stopPropagation) event.stopPropagation();
      const drag = dragRef.current;
      if (!enabled || !drag || drag.pointerId !== event.pointerId) return;

      const distance = event.clientX - drag.startX;
      if (!drag.moved && Math.abs(distance) < threshold) return;

      if (!drag.moved) {
        drag.moved = true;
        event.currentTarget.setPointerCapture?.(event.pointerId);
        setIsDragging(true);
      }

      event.preventDefault();
      event.currentTarget.scrollLeft = drag.startScrollLeft - distance;
      const now = performance.now();
      const elapsed = Math.max(1, now - drag.lastTime);
      drag.velocity = Math.max(-2.5, Math.min(2.5, (drag.lastX - event.clientX) / elapsed));
      drag.lastX = event.clientX;
      drag.lastTime = now;
    },
    [enabled, stopPropagation, threshold],
  );

  const finishDrag = useCallback<PointerEventHandler<HTMLDivElement>>(
    (event) => {
      if (stopPropagation) event.stopPropagation();
      const drag = dragRef.current;
      if (!drag || drag.pointerId !== event.pointerId) return;

      dragRef.current = null;
      setIsDragging(false);
      if (event.currentTarget.hasPointerCapture?.(event.pointerId)) {
        event.currentTarget.releasePointerCapture(event.pointerId);
      }

      if (!drag.moved) return;

      if (
        momentum &&
        event.type === "pointerup" &&
        performance.now() - drag.lastTime < 80 &&
        Math.abs(drag.velocity) > 0.05 &&
        !window.matchMedia?.("(prefers-reduced-motion: reduce)").matches
      ) {
        const rail = event.currentTarget;
        let velocity = drag.velocity;
        let lastTime = performance.now();
        const glide = (now: number) => {
          const elapsed = Math.min(32, Math.max(0, now - lastTime));
          lastTime = now;
          const previous = rail.scrollLeft;
          rail.scrollLeft += velocity * elapsed;
          velocity *= Math.exp(-elapsed / 180);
          if (Math.abs(velocity) < 0.02 || (elapsed > 0 && rail.scrollLeft === previous)) {
            momentumFrameRef.current = null;
            return;
          }
          momentumFrameRef.current = window.requestAnimationFrame(glide);
        };
        momentumFrameRef.current = window.requestAnimationFrame(glide);
      }

      suppressClickRef.current = true;
      if (suppressClickTimerRef.current !== null) {
        window.clearTimeout(suppressClickTimerRef.current);
      }
      suppressClickTimerRef.current = window.setTimeout(() => {
        suppressClickRef.current = false;
        suppressClickTimerRef.current = null;
      }, 0);
    },
    [momentum, stopPropagation],
  );

  const onClickCapture = useCallback<MouseEventHandler<HTMLDivElement>>(
    (event) => {
      if (!enabled || !suppressClickRef.current) return;

      suppressClickRef.current = false;
      event.preventDefault();
      event.stopPropagation();
    },
    [enabled],
  );

  return {
    isDragging,
    dragHandlers: {
      onClickCapture,
      onPointerCancel: finishDrag,
      onPointerDown,
      onPointerMove,
      onPointerUp: finishDrag,
    },
  };
};
