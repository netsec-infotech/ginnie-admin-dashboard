import {
  FC,
  PointerEvent as ReactPointerEvent,
  useCallback,
  useEffect,
  useRef,
  useState,
} from "react";
import { Modal, ModalBody, ModalContent } from "@heroui/react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import Image from "next/image";
import { useTranslation } from "react-i18next";

import {
  MediaNavigationOverlay,
  useDocumentActivity,
} from "@/components/Media";

import ReelActionRail from "./ReelActionRail";
import ReelDetailsOverlay from "./ReelDetailsOverlay";
import ReelLikeBurst, { type ReelLikeBurstState } from "./ReelLikeBurst";
import ReelPlaybackProgress from "./ReelPlaybackProgress";
import ReelPublisherHeader from "./ReelPublisherHeader";
import ReelStatusLayer from "./ReelStatusLayer";
import type { Reel } from "./types";

export interface ReelViewerProps {
  reels: Reel[];
  selection: number | null;
  onClose: () => void;
  onNavigate: (href: string) => void | Promise<unknown>;
  onView?: (reelId: string) => void;
  viewCounts?: Readonly<Record<string, number>>;
  shareQueryParam?: string;
  hasMore?: boolean;
  isLoadingMore?: boolean;
  onLoadMore?: () => void;
  onLikeChange?: (reel: Reel, liked: boolean) => boolean | Promise<boolean>;
}

interface PointerStart {
  x: number;
  y: number;
}

interface ReelTap {
  time: number;
  x: number;
  y: number;
}

const REEL_SWIPE_DISTANCE = 52;
const REEL_DOUBLE_TAP_DELAY_MS = 280;
const REEL_DOUBLE_TAP_DISTANCE = 48;
const REEL_CLEAN_VIEW_HOLD_DELAY_MS = 260;
const REEL_CLEAN_VIEW_MOVE_TOLERANCE = 10;
const REEL_WHEEL_THRESHOLD = 160;
const REEL_WHEEL_LOCK_MS = 750;
const REEL_WHEEL_RESET_MS = 240;
const BUFFER_SPINNER_DELAY_MS = 320;
const REEL_VIEW_QUALIFICATION_MS = 2_000;
const MEDIA_ERROR_ABORTED = 1;
const LIKED_REELS_STORAGE_KEY = "hyperlocal-liked-reels-v1";
const ACCESSIBILITY_CONTROL_CLASS =
  "sr-only focus:not-sr-only focus:fixed focus:bottom-4 focus:left-1/2 focus:z-[100] focus:-translate-x-1/2 focus:rounded-full focus:bg-white focus:px-4 focus:py-3 focus:font-bold focus:text-black focus:outline-none focus:ring-4 focus:ring-primary-500";

const readStoredReelIds = (storageKey: string) => {
  if (typeof window === "undefined") return new Set<string>();
  try {
    const ids = JSON.parse(window.localStorage.getItem(storageKey) ?? "[]");
    return new Set<string>(Array.isArray(ids) ? ids : []);
  } catch {
    return new Set<string>();
  }
};

const createInitialLikedReelIds = (reels: readonly Reel[]) => {
  const likedIds = readStoredReelIds(LIKED_REELS_STORAGE_KEY);
  for (const reel of reels) {
    if (reel.isLiked) likedIds.add(reel.id);
  }
  return likedIds;
};

const ReelViewer: FC<ReelViewerProps> = ({
  reels,
  selection,
  onClose,
  onNavigate,
  onView,
  viewCounts,
  shareQueryParam = "reel",
  hasMore = false,
  isLoadingMore = false,
  onLoadMore,
  onLikeChange,
}) => {
  const { t } = useTranslation();
  const prefersReducedMotion = useReducedMotion();
  const [activeIndex, setActiveIndex] = useState(selection ?? 0);
  const [direction, setDirection] = useState<1 | -1>(1);
  const [isPaused, setIsPaused] = useState(false);
  const [isMuted, setIsMuted] = useState(true);
  const [isBuffering, setIsBuffering] = useState(true);
  const [showBufferSpinner, setShowBufferSpinner] = useState(false);
  const [mediaFailed, setMediaFailed] = useState(false);
  const [videoAttempt, setVideoAttempt] = useState(0);
  const [isSharing, setIsSharing] = useState(false);
  const [shareFeedback, setShareFeedback] = useState<string | null>(null);
  const [likedReels, setLikedReels] = useState(() =>
    createInitialLikedReelIds(reels),
  );
  const [likeBurst, setLikeBurst] = useState<ReelLikeBurstState | null>(null);
  const [isCleanView, setIsCleanView] = useState(false);
  const [hasNavigated, setHasNavigated] = useState(false);
  const [isNavigating, setIsNavigating] = useState(false);
  const [isWaitingForNextPage, setIsWaitingForNextPage] = useState(false);
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const playbackProgressFillRef = useRef<HTMLDivElement | null>(null);
  const pointerStartRef = useRef<PointerStart | null>(null);
  const lastTapRef = useRef<ReelTap | null>(null);
  const singleTapTimerRef = useRef<number | null>(null);
  const wheelDeltaRef = useRef(0);
  const wheelLockedUntilRef = useRef(0);
  const wheelResetTimerRef = useRef<number | null>(null);
  const shareFeedbackTimerRef = useRef<number | null>(null);
  const automaticRetryRef = useRef(0);
  const retryPositionRef = useRef(0);
  const cleanViewHoldTimerRef = useRef<number | null>(null);
  const cleanViewActiveRef = useRef(false);
  const viewQualificationTimerRef = useRef<number | null>(null);
  const reportedViewIdsRef = useRef(new Set<string>());
  const navigationPendingRef = useRef(false);
  const pendingAdvanceFromLengthRef = useRef<number | null>(null);

  const isOpen = selection !== null;
  const isWindowInactive = useDocumentActivity(isOpen);
  const reel = reels[activeIndex] ?? reels[0];
  const nextReel = reels[activeIndex + 1];
  const playbackPaused = isPaused || isSharing || isWindowInactive;
  const isLiked = reel ? likedReels.has(reel.id) : false;

  const stopViewQualification = useCallback(() => {
    if (viewQualificationTimerRef.current === null) return;
    window.clearTimeout(viewQualificationTimerRef.current);
    viewQualificationTimerRef.current = null;
  }, []);

  const startViewQualification = useCallback(() => {
    stopViewQualification();
    if (!reel || !onView || reportedViewIdsRef.current.has(reel.id)) return;

    const reelId = reel.id;
    viewQualificationTimerRef.current = window.setTimeout(() => {
      viewQualificationTimerRef.current = null;
      reportedViewIdsRef.current.add(reelId);
      onView(reelId);
    }, REEL_VIEW_QUALIFICATION_MS);
  }, [onView, reel, stopViewQualification]);

  const stopCleanViewHold = useCallback(() => {
    if (cleanViewHoldTimerRef.current !== null) {
      window.clearTimeout(cleanViewHoldTimerRef.current);
      cleanViewHoldTimerRef.current = null;
    }
    if (cleanViewActiveRef.current) {
      cleanViewActiveRef.current = false;
      setIsCleanView(false);
    }
  }, []);

  const releaseActiveVideo = useCallback(() => {
    stopViewQualification();
    const video = videoRef.current;
    if (!video) return;
    video.pause();
    video.removeAttribute("src");
    video.load();
  }, [stopViewQualification]);

  const updatePlaybackProgress = useCallback((video: HTMLVideoElement) => {
    const fill = playbackProgressFillRef.current;
    if (!fill) return;

    const progress =
      Number.isFinite(video.duration) && video.duration > 0
        ? Math.min(1, Math.max(0, video.currentTime / video.duration))
        : 0;
    fill.style.transform = `scaleX(${progress})`;
  }, []);

  const openAt = useCallback(
    (index: number, nextDirection: 1 | -1) => {
      if (index < 0 || index >= reels.length) return;
      releaseActiveVideo();
      setHasNavigated(true);
      setDirection(nextDirection);
      setActiveIndex(index);
      setIsPaused(false);
      setIsBuffering(true);
      setShowBufferSpinner(false);
      setMediaFailed(false);
      setLikeBurst(null);
      stopCleanViewHold();
      setVideoAttempt(0);
      automaticRetryRef.current = 0;
      retryPositionRef.current = 0;
      lastTapRef.current = null;
      if (singleTapTimerRef.current !== null) {
        window.clearTimeout(singleTapTimerRef.current);
        singleTapTimerRef.current = null;
      }
    },
    [reels.length, releaseActiveVideo, stopCleanViewHold],
  );

  const closeViewer = useCallback(() => {
    stopCleanViewHold();
    releaseActiveVideo();
    onClose();
  }, [onClose, releaseActiveVideo, stopCleanViewHold]);

  const navigateFromViewer = useCallback(
    async (href: string) => {
      if (navigationPendingRef.current) return;

      navigationPendingRef.current = true;
      stopCleanViewHold();
      stopViewQualification();
      videoRef.current?.pause();
      setIsPaused(true);
      setIsNavigating(true);

      try {
        await onNavigate(href);
      } catch {
        // Keep the viewer usable when a client-side route cannot be opened.
      }

      navigationPendingRef.current = false;
      setIsNavigating(false);
      setIsPaused(false);
    },
    [onNavigate, stopCleanViewHold, stopViewQualification],
  );

  const goNext = useCallback(() => {
    if (activeIndex < reels.length - 1) {
      openAt(activeIndex + 1, 1);
      return;
    }
    if (!hasMore || !onLoadMore) return;

    pendingAdvanceFromLengthRef.current = reels.length;
    setIsWaitingForNextPage(true);
    if (!isLoadingMore) onLoadMore();
  }, [activeIndex, hasMore, isLoadingMore, onLoadMore, openAt, reels.length]);
  const goPrevious = useCallback(() => {
    if (activeIndex > 0) openAt(activeIndex - 1, -1);
  }, [activeIndex, openAt]);

  useEffect(() => {
    if (
      !isOpen ||
      !hasMore ||
      isLoadingMore ||
      !onLoadMore ||
      activeIndex < Math.max(0, reels.length - 2)
    )
      return;

    onLoadMore();
  }, [activeIndex, hasMore, isLoadingMore, isOpen, onLoadMore, reels.length]);

  useEffect(() => {
    const previousLength = pendingAdvanceFromLengthRef.current;
    if (previousLength === null) return;

    if (reels.length > previousLength) {
      pendingAdvanceFromLengthRef.current = null;
      setIsWaitingForNextPage(false);
      openAt(previousLength, 1);
      return;
    }

    if (!hasMore && !isLoadingMore) {
      pendingAdvanceFromLengthRef.current = null;
      setIsWaitingForNextPage(false);
    }
  }, [hasMore, isLoadingMore, openAt, reels.length]);

  const navigateWithWheel = useCallback(
    (deltaY: number, deltaMode: number) => {
      const multiplier =
        deltaMode === WheelEvent.DOM_DELTA_LINE
          ? 16
          : deltaMode === WheelEvent.DOM_DELTA_PAGE
            ? window.innerHeight
            : 1;
      const normalizedDelta = deltaY * multiplier;
      const now = Date.now();
      if (now < wheelLockedUntilRef.current) return;

      wheelDeltaRef.current += normalizedDelta;
      if (wheelResetTimerRef.current !== null) {
        window.clearTimeout(wheelResetTimerRef.current);
      }

      if (Math.abs(wheelDeltaRef.current) >= REEL_WHEEL_THRESHOLD) {
        const shouldGoNext = wheelDeltaRef.current > 0;
        wheelDeltaRef.current = 0;
        wheelLockedUntilRef.current = now + REEL_WHEEL_LOCK_MS;
        if (shouldGoNext) goNext();
        else goPrevious();
        return;
      }

      wheelResetTimerRef.current = window.setTimeout(() => {
        wheelDeltaRef.current = 0;
        wheelResetTimerRef.current = null;
      }, REEL_WHEEL_RESET_MS);
    },
    [goNext, goPrevious],
  );

  useEffect(() => {
    if (!isOpen) return;
    const root = document.documentElement;
    root.setAttribute("data-reel-viewer-open", "true");
    return () => root.removeAttribute("data-reel-viewer-open");
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    const handleWheel = (event: WheelEvent) => {
      const target = event.target;
      if (target instanceof Element && target.closest("[data-reel-control]")) {
        return;
      }
      if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;

      event.preventDefault();
      navigateWithWheel(event.deltaY, event.deltaMode);
    };

    window.addEventListener("wheel", handleWheel, { passive: false });
    return () => window.removeEventListener("wheel", handleWheel);
  }, [isOpen, navigateWithWheel]);

  useEffect(() => {
    if (!isOpen || !isBuffering || mediaFailed) {
      setShowBufferSpinner(false);
      return;
    }
    const timer = window.setTimeout(
      () => setShowBufferSpinner(true),
      BUFFER_SPINNER_DELAY_MS,
    );
    return () => window.clearTimeout(timer);
  }, [isBuffering, isOpen, mediaFailed]);

  useEffect(() => {
    if (!isOpen || !reel || !nextReel || nextReel.id === reel.id) return;

    // Warm the adjacent poster immediately, but never let next-Reel metadata
    // compete with the active Reel's first playable buffer.
    const poster = new window.Image();
    poster.src = nextReel.posterSrc;
    let preloader: HTMLVideoElement | null = null;
    const preloadTimer = !isBuffering
      ? window.setTimeout(() => {
          preloader = document.createElement("video");
          preloader.preload = "metadata";
          preloader.muted = true;
          preloader.src = nextReel.videoSrc;
          preloader.load();
        }, 650)
      : null;

    return () => {
      poster.src = "";
      if (preloadTimer !== null) window.clearTimeout(preloadTimer);
      if (preloader) {
        preloader.pause();
        preloader.removeAttribute("src");
        preloader.load();
      }
    };
  }, [isBuffering, isOpen, nextReel, reel]);

  useEffect(() => {
    const video = videoRef.current;
    if (!isOpen || !video || mediaFailed) return;

    video.muted = isMuted;
    if (playbackPaused) {
      video.pause();
      return;
    }

    const playPromise = video.play();
    if (video.readyState >= HTMLMediaElement.HAVE_FUTURE_DATA) {
      setIsBuffering(false);
    }
    if (playPromise) {
      void playPromise.then(() => setIsBuffering(false)).catch(() => undefined);
    }
  }, [activeIndex, isMuted, isOpen, mediaFailed, playbackPaused, videoAttempt]);

  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        closeViewer();
        return;
      }

      const target = event.target;
      if (
        target instanceof Element &&
        target.closest(
          "[data-reel-control], input, textarea, select, [contenteditable=true]",
        )
      ) {
        return;
      }

      if (
        event.key === "ArrowDown" ||
        event.key === "ArrowRight" ||
        event.key.toLowerCase() === "j"
      ) {
        event.preventDefault();
        goNext();
      } else if (
        event.key === "ArrowUp" ||
        event.key === "ArrowLeft" ||
        event.key.toLowerCase() === "k"
      ) {
        event.preventDefault();
        goPrevious();
      } else if (event.key === " ") {
        event.preventDefault();
        setIsPaused((value) => !value);
      } else if (event.key.toLowerCase() === "m") {
        setIsMuted((value) => !value);
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [closeViewer, goNext, goPrevious, isOpen]);

  useEffect(
    () => () => {
      if (shareFeedbackTimerRef.current !== null) {
        window.clearTimeout(shareFeedbackTimerRef.current);
      }
      if (singleTapTimerRef.current !== null) {
        window.clearTimeout(singleTapTimerRef.current);
      }
      if (wheelResetTimerRef.current !== null) {
        window.clearTimeout(wheelResetTimerRef.current);
      }
      if (cleanViewHoldTimerRef.current !== null) {
        window.clearTimeout(cleanViewHoldTimerRef.current);
      }
      stopViewQualification();
    },
    [stopViewQualification],
  );

  if (!reel) return null;
  const products = reel.products ?? [];
  const hasProducts = products.length > 0;
  const actionStackPosition = hasProducts
    ? "bottom-[clamp(14rem,31dvh,17rem)]"
    : reel.cta
      ? "bottom-[132px]"
      : "bottom-[128px]";

  const setTemporaryShareFeedback = (message: string) => {
    setShareFeedback(message);
    if (shareFeedbackTimerRef.current !== null) {
      window.clearTimeout(shareFeedbackTimerRef.current);
    }
    shareFeedbackTimerRef.current = window.setTimeout(() => {
      setShareFeedback(null);
      shareFeedbackTimerRef.current = null;
    }, 1800);
  };

  const persistLiked = (liked: boolean) => {
    setLikedReels((current) => {
      const next = new Set(current);
      if (liked) next.add(reel.id);
      else next.delete(reel.id);
      try {
        window.localStorage.setItem(
          LIKED_REELS_STORAGE_KEY,
          JSON.stringify([...next]),
        );
      } catch {
        // The interaction remains usable when storage is unavailable.
      }
      return next;
    });
  };

  const updateLiked = (mode: "toggle" | "like") => {
    const nextLiked = mode === "toggle" ? !isLiked : true;
    if (nextLiked === isLiked) return;

    persistLiked(nextLiked);
    if (!onLikeChange) return;

    void Promise.resolve(onLikeChange(reel, nextLiked))
      .then((accepted) => {
        if (!accepted) persistLiked(isLiked);
      })
      .catch(() => persistLiked(isLiked));
  };

  const toggleLiked = () => updateLiked("toggle");

  const showLikeBurst = (x: number, y: number) => {
    updateLiked("like");
    setLikeBurst({ id: Date.now(), x, y });
  };

  const shareReel = async () => {
    const shareUrl = new URL(window.location.href);
    shareUrl.searchParams.set(shareQueryParam, reel.id);
    const shareData = {
      title: reel.title,
      text: reel.caption,
      url: shareUrl.toString(),
    };

    setIsSharing(true);
    try {
      if (typeof navigator.share === "function") {
        await navigator.share(shareData);
        setTemporaryShareFeedback(
          t("reels.viewer.shared", { defaultValue: "Shared" }),
        );
      } else if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(shareData.url);
        setTemporaryShareFeedback(
          t("reels.viewer.linkCopied", { defaultValue: "Link copied" }),
        );
      }
    } catch (error) {
      if ((error as DOMException)?.name !== "AbortError") {
        setTemporaryShareFeedback(
          t("reels.viewer.shareFailed", { defaultValue: "Unable to share" }),
        );
      }
    } finally {
      setIsSharing(false);
    }
  };

  const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (event.button !== 0) return;
    if ((event.target as HTMLElement).closest("[data-reel-control]")) return;
    if (mediaFailed) return;
    stopCleanViewHold();
    pointerStartRef.current = { x: event.clientX, y: event.clientY };
    cleanViewHoldTimerRef.current = window.setTimeout(() => {
      cleanViewHoldTimerRef.current = null;
      cleanViewActiveRef.current = true;
      lastTapRef.current = null;
      if (singleTapTimerRef.current !== null) {
        window.clearTimeout(singleTapTimerRef.current);
        singleTapTimerRef.current = null;
      }
      setLikeBurst(null);
      setIsCleanView(true);
    }, REEL_CLEAN_VIEW_HOLD_DELAY_MS);
  };

  const handlePointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
    const start = pointerStartRef.current;
    if (!start) return;
    if (
      Math.hypot(event.clientX - start.x, event.clientY - start.y) >=
      REEL_CLEAN_VIEW_MOVE_TOLERANCE
    ) {
      stopCleanViewHold();
    }
  };

  const handlePointerUp = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (event.button !== 0) return;
    const wasCleanView = cleanViewActiveRef.current;
    const start = pointerStartRef.current;
    pointerStartRef.current = null;
    stopCleanViewHold();
    if ((event.target as HTMLElement).closest("[data-reel-control]")) return;
    if (!start) return;
    if (wasCleanView) return;

    const deltaX = event.clientX - start.x;
    const deltaY = event.clientY - start.y;
    if (
      Math.abs(deltaY) >= REEL_SWIPE_DISTANCE &&
      Math.abs(deltaY) > Math.abs(deltaX)
    ) {
      lastTapRef.current = null;
      if (singleTapTimerRef.current !== null) {
        window.clearTimeout(singleTapTimerRef.current);
        singleTapTimerRef.current = null;
      }
      if (deltaY < 0) goNext();
      else goPrevious();
      return;
    }

    if (Math.abs(deltaX) < 8 && Math.abs(deltaY) < 8) {
      const now = Date.now();
      const lastTap = lastTapRef.current;
      const isDoubleTap =
        lastTap !== null &&
        now - lastTap.time <= REEL_DOUBLE_TAP_DELAY_MS &&
        Math.hypot(event.clientX - lastTap.x, event.clientY - lastTap.y) <=
          REEL_DOUBLE_TAP_DISTANCE;

      if (isDoubleTap) {
        if (singleTapTimerRef.current !== null) {
          window.clearTimeout(singleTapTimerRef.current);
          singleTapTimerRef.current = null;
        }
        lastTapRef.current = null;
        const bounds = event.currentTarget.getBoundingClientRect();
        showLikeBurst(
          Math.max(
            44,
            Math.min(bounds.width - 44, event.clientX - bounds.left),
          ),
          Math.max(
            44,
            Math.min(bounds.height - 44, event.clientY - bounds.top),
          ),
        );
        return;
      }

      lastTapRef.current = {
        time: now,
        x: event.clientX,
        y: event.clientY,
      };
      singleTapTimerRef.current = window.setTimeout(() => {
        setIsPaused((value) => !value);
        lastTapRef.current = null;
        singleTapTimerRef.current = null;
      }, REEL_DOUBLE_TAP_DELAY_MS);
    }
  };

  return (
    <Modal
      aria-label={t("reels.viewer.position", {
        current: activeIndex + 1,
        total: reels.length,
        defaultValue: "Reel {{current}} of {{total}}",
      })}
      backdrop="opaque"
      hideCloseButton
      isDismissable
      isKeyboardDismissDisabled
      isOpen={isOpen}
      placement="center"
      scrollBehavior="normal"
      size="md"
      onClose={closeViewer}
      motionProps={{
        variants: {
          enter: {
            opacity: 1,
            transition: {
              duration: prefersReducedMotion ? 0 : 0.16,
              ease: [0.22, 1, 0.36, 1],
            },
          },
          exit: {
            opacity: 0,
            transition: {
              duration: prefersReducedMotion ? 0 : 0.12,
              ease: [0.4, 0, 1, 1],
            },
          },
        },
      }}
      classNames={{
        wrapper: "pointer-events-none !overflow-hidden p-0 md:p-4",
        backdrop: "bg-black/90",
        base: "pointer-events-auto m-0 h-[100dvh] max-h-[100dvh] max-w-none overflow-hidden rounded-none border-0 bg-black shadow-none outline-none ring-0 sm:m-0 md:h-[min(860px,calc(100dvh-2rem))] md:max-h-[860px] md:!max-w-[480px] md:rounded-[2rem]",
      }}
    >
      <ModalContent>
        <ModalBody className="sr-only">
          {t("reels.viewer.instructions", {
            defaultValue:
              "Use Up or Down Arrow to change reels, Space to pause or play, M to mute, and Escape to close.",
          })}
        </ModalBody>
        <div
          aria-keyshortcuts="ArrowUp ArrowDown ArrowLeft ArrowRight J K Space M Escape"
          data-reel-buffering={isBuffering || undefined}
          data-reel-clean-view={isCleanView || undefined}
          data-reel-paused={playbackPaused || undefined}
          data-reel-sharing={isSharing || undefined}
          data-reel-viewer
          className="group/reelviewer relative h-full min-h-0 w-full touch-pan-x cursor-pointer select-none overflow-hidden bg-black"
          onPointerCancel={() => {
            stopCleanViewHold();
            pointerStartRef.current = null;
          }}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
        >
          <p aria-atomic="true" aria-live="polite" className="sr-only">
            {`${reel.publisher}. ${reel.title}. ${t("reels.viewer.position", {
              current: activeIndex + 1,
              total: reels.length,
              defaultValue: "Reel {{current}} of {{total}}",
            })}`}
          </p>

          <motion.div
            key={`${reel.id}-${videoAttempt}`}
            data-reel-media={reel.id}
            className="absolute inset-0 bg-black"
            initial={
              prefersReducedMotion || !hasNavigated
                ? false
                : { opacity: 0, y: direction * 18, scale: 1.01 }
            }
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={
              prefersReducedMotion
                ? { duration: 0 }
                : { duration: 0.28, ease: [0.22, 1, 0.36, 1] }
            }
          >
            <Image
              fill
              priority
              unoptimized
              alt=""
              aria-hidden="true"
              className="object-cover"
              sizes="(max-width: 767px) 100vw, 480px"
              src={reel.posterSrc}
              style={{ objectPosition: reel.posterPosition ?? "center" }}
            />

            {!mediaFailed && (
              <video
                key={`${reel.id}-${videoAttempt}`}
                ref={videoRef}
                autoPlay
                loop
                muted={isMuted}
                aria-hidden="true"
                playsInline
                poster={reel.posterSrc}
                preload="metadata"
                src={reel.videoSrc}
                tabIndex={-1}
                className={`absolute inset-0 h-full w-full object-cover transition-opacity duration-150 motion-reduce:transition-none ${
                  isBuffering ? "opacity-0" : "opacity-100"
                }`}
                data-reel-active-video={reel.id}
                onCanPlay={() => setIsBuffering(false)}
                onError={(event) => {
                  stopViewQualification();
                  const video = event.currentTarget;
                  if (video.error?.code === MEDIA_ERROR_ABORTED) {
                    return;
                  }
                  if (automaticRetryRef.current === 0) {
                    automaticRetryRef.current = 1;
                    retryPositionRef.current = video.currentTime;
                    setIsBuffering(true);
                    setShowBufferSpinner(false);
                    setVideoAttempt((value) => value + 1);
                    return;
                  }
                  setIsBuffering(false);
                  setMediaFailed(true);
                }}
                onLoadedData={() => setIsBuffering(false)}
                onLoadedMetadata={(event) => {
                  const resumeAt = retryPositionRef.current;
                  const video = event.currentTarget;
                  if (resumeAt > 0) {
                    video.currentTime = Math.min(
                      resumeAt,
                      Math.max(0, video.duration - 0.1),
                    );
                    retryPositionRef.current = 0;
                  }
                  updatePlaybackProgress(video);
                }}
                onPause={stopViewQualification}
                onPlaying={() => {
                  setIsBuffering(false);
                  if (!playbackPaused) startViewQualification();
                }}
                onSeeked={(event) =>
                  updatePlaybackProgress(event.currentTarget)
                }
                onStalled={() => {
                  stopViewQualification();
                  setIsBuffering(true);
                }}
                onTimeUpdate={(event) =>
                  updatePlaybackProgress(event.currentTarget)
                }
                onWaiting={() => {
                  stopViewQualification();
                  setIsBuffering(true);
                }}
              >
                {reel.captions?.map((caption) => (
                  <track
                    key={`${caption.srcLang}:${caption.src}`}
                    default={caption.default}
                    kind="captions"
                    label={caption.label}
                    src={caption.src}
                    srcLang={caption.srcLang}
                  />
                ))}
              </video>
            )}

            <div
              className={`pointer-events-none absolute inset-0 bg-linear-to-b from-black/55 via-transparent to-black/35 transition-opacity duration-150 motion-reduce:transition-none ${
                isCleanView ? "opacity-0" : "opacity-100"
              }`}
            />
          </motion.div>

          <ReelPublisherHeader
            isHidden={isCleanView}
            reel={reel}
            onClose={closeViewer}
          />

          <ReelPlaybackProgress
            fillRef={playbackProgressFillRef}
            isHidden={isCleanView || mediaFailed}
            resetKey={`${reel.id}-${videoAttempt}`}
          />

          <ReelStatusLayer
            isBuffering={isBuffering}
            isHidden={isCleanView}
            isPaused={isPaused}
            mediaFailed={mediaFailed}
            showBufferSpinner={showBufferSpinner}
            onNext={goNext}
            onRetry={() => {
              automaticRetryRef.current = 0;
              retryPositionRef.current = 0;
              setMediaFailed(false);
              setIsBuffering(true);
              setVideoAttempt((value) => value + 1);
            }}
          />

          <AnimatePresence>
            {likeBurst && !isCleanView && (
              <ReelLikeBurst
                key={likeBurst.id}
                burst={likeBurst}
                onComplete={() => setLikeBurst(null)}
              />
            )}
          </AnimatePresence>

          {!mediaFailed && (
            <>
              <ReelActionRail
                isHidden={isCleanView}
                isLiked={isLiked}
                isMuted={isMuted}
                likeCount={reel.likes + (isLiked ? 1 : 0)}
                positionClassName={actionStackPosition}
                onLike={toggleLiked}
                onMutedChange={setIsMuted}
                onShare={() => void shareReel()}
              />

              <ReelDetailsOverlay
                isHidden={isCleanView}
                reel={reel}
                shareFeedback={shareFeedback}
                viewCount={viewCounts?.[reel.id]}
                onNavigate={navigateFromViewer}
              />
            </>
          )}

          {(isNavigating || isWaitingForNextPage) && (
            <MediaNavigationOverlay
              label={
                isWaitingForNextPage
                  ? t("reels.viewer.loadingMore", {
                      defaultValue: "Loading more Reels…",
                    })
                  : t("reels.viewer.openingDestination", {
                      defaultValue: "Opening…",
                    })
              }
            />
          )}

          <button
            data-reel-control
            type="button"
            className={ACCESSIBILITY_CONTROL_CLASS}
            disabled={activeIndex === 0}
            onClick={goPrevious}
          >
            {t("reels.viewer.previous", { defaultValue: "Previous reel" })}
          </button>
          {!mediaFailed && (
            <>
              <button
                data-reel-control
                type="button"
                className={ACCESSIBILITY_CONTROL_CLASS}
                disabled={activeIndex === reels.length - 1 && !hasMore}
                onClick={goNext}
              >
                {t("reels.viewer.next", { defaultValue: "Next reel" })}
              </button>
              <button
                data-reel-control
                type="button"
                className={ACCESSIBILITY_CONTROL_CLASS}
                onClick={() => setIsPaused((value) => !value)}
              >
                {isPaused
                  ? t("reels.viewer.play", { defaultValue: "Play reel" })
                  : t("reels.viewer.pause", {
                      defaultValue: "Pause reel",
                    })}
              </button>
            </>
          )}
        </div>
      </ModalContent>
    </Modal>
  );
};

export default ReelViewer;
