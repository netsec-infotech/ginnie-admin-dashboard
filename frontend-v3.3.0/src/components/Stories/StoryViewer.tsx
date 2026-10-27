import {
  FC,
  MouseEvent as ReactMouseEvent,
  PointerEvent as ReactPointerEvent,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { Button, Modal, ModalBody, ModalContent } from "@heroui/react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { AlertTriangle, Play, RotateCcw } from "lucide-react";
import { useTranslation } from "react-i18next";

import {
  MediaNavigationOverlay,
  useDocumentActivity,
} from "@/components/Media";

import StoryArtwork from "./StoryArtwork";
import StoryDetailsOverlay from "./StoryDetailsOverlay";
import StoryLikeBurst from "./StoryLikeBurst";
import StoryStatusLayer from "./StoryStatusLayer";
import StoryViewerHeader from "./StoryViewerHeader";
import type { StoryGroup } from "./types";

export interface StoryViewerSelection {
  groupIndex: number;
  itemIndex: number;
}

export interface StoryViewerProps {
  groups: StoryGroup[];
  selection: StoryViewerSelection | null;
  resolveGroup?: (
    group: StoryGroup,
  ) => StoryGroup | null | Promise<StoryGroup | null>;
  onSeen: (storyId: string) => void;
  onGroupComplete?: (group: StoryGroup) => void;
  onClose: () => void;
  onNavigate: (href: string) => void | Promise<unknown>;
  shareQueryParam?: string;
}

interface PointerStart {
  x: number;
  y: number;
  at: number;
}

const STORY_MEANINGFUL_VIEW_MS = 500;
const SWIPE_DISTANCE = 52;
const HOLD_TO_PAUSE_MS = 220;
const STORY_GESTURE_HINT_KEY = "hyperlocal-story-gesture-hint-seen-v1";
const STORY_GESTURE_HINT_MS = 3200;
export const STORY_LIKES_STORAGE_KEY = "hyperlocal-story-likes-v1";
const ACCESSIBILITY_CONTROL_CLASS =
  "sr-only focus:not-sr-only focus:fixed focus:bottom-4 focus:left-1/2 focus:z-[100] focus:-translate-x-1/2 focus:rounded-full focus:bg-white focus:px-4 focus:py-3 focus:font-bold focus:text-black focus:outline-none focus:ring-4 focus:ring-primary-500";

type LikedStoryMap = Record<string, true>;
type StoryTransitionKind = "item" | "group";

interface StoryTransitionContext {
  direction: 1 | -1;
  kind: StoryTransitionKind;
}

const STORY_PANEL_VARIANTS = {
  enter: ({ direction, kind }: StoryTransitionContext) =>
    kind === "group"
      ? {
          opacity: 0,
          scale: 0.985,
          x: direction * 28,
        }
      : { opacity: 0.35, x: direction * 8 },
  active: {
    opacity: 1,
    scale: 1,
    x: 0,
  },
};

const StoryViewer: FC<StoryViewerProps> = ({
  groups,
  selection,
  resolveGroup,
  onSeen,
  onGroupComplete,
  onClose,
  onNavigate,
  shareQueryParam = "story",
}) => {
  const { t } = useTranslation();
  const prefersReducedMotion = useReducedMotion();
  const [groupIndex, setGroupIndex] = useState(selection?.groupIndex ?? 0);
  const [itemIndex, setItemIndex] = useState(selection?.itemIndex ?? 0);
  const [transitionDirection, setTransitionDirection] = useState(1);
  const [transitionKind, setTransitionKind] =
    useState<StoryTransitionKind>("item");
  const [isPaused, setIsPaused] = useState(false);
  const [showHoldIndicator, setShowHoldIndicator] = useState(false);
  const [showGestureHint, setShowGestureHint] = useState(false);
  const [isMuted, setIsMuted] = useState(true);
  const [bufferedProgress, setBufferedProgress] = useState(0);
  const [isBuffering, setIsBuffering] = useState(() => {
    const initialGroup = groups[selection?.groupIndex ?? 0];
    const initialItem = initialGroup?.items[selection?.itemIndex ?? 0];
    return initialItem?.mediaType === "video" && !initialItem.unavailable;
  });
  const [mediaFailed, setMediaFailed] = useState(false);
  const [videoAttempt, setVideoAttempt] = useState(0);
  const [likedStories, setLikedStories] = useState<LikedStoryMap>({});
  const [likeBurst, setLikeBurst] = useState<{
    storyId: string;
    key: number;
  } | null>(null);
  const [isSharing, setIsSharing] = useState(false);
  const [shareFeedback, setShareFeedback] = useState<string | null>(null);
  const [isResolvingGroup, setIsResolvingGroup] = useState(false);
  const [resolvedGroups, setResolvedGroups] = useState<
    Record<string, StoryGroup>
  >({});
  const pointerStartRef = useRef<PointerStart | null>(null);
  const shareInProgressRef = useRef(false);
  const holdIndicatorTimerRef = useRef<number | null>(null);
  const likeBurstTimerRef = useRef<number | null>(null);
  const shareFeedbackTimerRef = useRef<number | null>(null);
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const progressFillRef = useRef<HTMLDivElement | null>(null);
  const progressValueRef = useRef(0);
  const advancedRef = useRef(false);
  const navigationPendingRef = useRef(false);
  const groupTransitionPendingRef = useRef(false);
  const hasNavigatedRef = useRef(false);
  const groupRequestRef = useRef(new Map<string, Promise<StoryGroup | null>>());

  const isOpen = selection !== null;
  const isWindowInactive = useDocumentActivity(isOpen);
  const viewerGroups = useMemo(
    () =>
      groups.map((candidate) =>
        candidate.items.length > 0
          ? candidate
          : (resolvedGroups[candidate.id] ?? candidate),
      ),
    [groups, resolvedGroups],
  );
  const group = viewerGroups[groupIndex] ?? viewerGroups[0];
  const item = group?.items[itemIndex] ?? group?.items[0];
  const playbackPaused = isPaused || isSharing || isWindowInactive;

  useEffect(() => {
    try {
      const stored = JSON.parse(
        window.localStorage.getItem(STORY_LIKES_STORAGE_KEY) ?? "{}",
      ) as Record<string, unknown>;
      setLikedStories(
        Object.fromEntries(
          Object.entries(stored).filter(([, liked]) => liked === true),
        ) as LikedStoryMap,
      );
    } catch {
      setLikedStories({});
    }
  }, []);

  const displayProgress = useCallback((value: number) => {
    const next = Math.min(1, Math.max(0, value));
    progressValueRef.current = next;
    if (progressFillRef.current) {
      progressFillRef.current.style.transform = `scaleX(${next})`;
    }
  }, []);

  const clearHoldIndicator = useCallback(() => {
    if (holdIndicatorTimerRef.current !== null) {
      window.clearTimeout(holdIndicatorTimerRef.current);
      holdIndicatorTimerRef.current = null;
    }
    setShowHoldIndicator(false);
  }, []);

  const openAt = useCallback(
    (
      nextGroupIndex: number,
      nextItemIndex: number,
      direction: 1 | -1,
      resolvedGroup?: StoryGroup,
    ) => {
      videoRef.current?.pause();
      displayProgress(0);
      clearHoldIndicator();
      setShowGestureHint(false);
      setIsPaused(false);
      setBufferedProgress(0);
      const nextItem =
        resolvedGroup?.items[nextItemIndex] ??
        viewerGroups[nextGroupIndex]?.items[nextItemIndex];
      setIsBuffering(nextItem?.mediaType === "video" && !nextItem.unavailable);
      setMediaFailed(false);
      setVideoAttempt(0);
      advancedRef.current = false;
      hasNavigatedRef.current = true;
      setTransitionKind(nextGroupIndex === groupIndex ? "item" : "group");
      setTransitionDirection(direction);
      setGroupIndex(nextGroupIndex);
      setItemIndex(nextItemIndex);
    },
    [clearHoldIndicator, displayProgress, groupIndex, viewerGroups],
  );

  const goPrevious = useCallback(() => {
    if (!group) return;

    if (itemIndex > 0) {
      openAt(groupIndex, itemIndex - 1, -1);
      return;
    }

    if (groupIndex > 0) {
      const previousGroupIndex = groupIndex - 1;
      openAt(
        previousGroupIndex,
        Math.max(0, viewerGroups[previousGroupIndex].items.length - 1),
        -1,
      );
    }
  }, [group, groupIndex, itemIndex, openAt, viewerGroups]);

  const closeViewer = useCallback(() => {
    if (item && !item.unavailable) onSeen(item.id);
    onClose();
  }, [item, onClose, onSeen]);

  const navigateFromViewer = useCallback(
    (href: string) => {
      if (navigationPendingRef.current) return;

      navigationPendingRef.current = true;
      if (item && !item.unavailable) onSeen(item.id);
      clearHoldIndicator();
      onClose();

      void Promise.resolve()
        .then(() => onNavigate(href))
        .catch(() => {
          // The viewer is already dismissed; the current page remains usable.
        })
        .finally(() => {
          navigationPendingRef.current = false;
        });
    },
    [clearHoldIndicator, item, onClose, onNavigate, onSeen],
  );

  const resolveViewerGroup = useCallback(
    async (candidate: StoryGroup) => {
      const available =
        viewerGroups.find((groupEntry) => groupEntry.id === candidate.id) ??
        candidate;
      if (available.items.length > 0) return available;
      if (!resolveGroup) return null;

      let request = groupRequestRef.current.get(candidate.id);
      if (!request) {
        request = Promise.resolve(resolveGroup(candidate));
        groupRequestRef.current.set(candidate.id, request);
      }

      try {
        const resolved = await request;
        if (!resolved?.items.length) return null;
        setResolvedGroups((current) => ({
          ...current,
          [resolved.id]: resolved,
        }));
        return resolved;
      } catch {
        return null;
      } finally {
        groupRequestRef.current.delete(candidate.id);
      }
    },
    [resolveGroup, viewerGroups],
  );

  useEffect(() => {
    if (!isOpen) return;
    const nextGroup = viewerGroups[groupIndex + 1];
    if (!nextGroup || nextGroup.items.length > 0) return;
    void resolveViewerGroup(nextGroup);
  }, [groupIndex, isOpen, resolveViewerGroup, viewerGroups]);

  const advanceToNextGroup = useCallback(
    async (direction: 1 | -1, closeWhenExhausted: boolean) => {
      if (groupTransitionPendingRef.current) return;
      groupTransitionPendingRef.current = true;

      for (
        let nextGroupIndex = groupIndex + 1;
        nextGroupIndex < viewerGroups.length;
        nextGroupIndex += 1
      ) {
        const candidate = viewerGroups[nextGroupIndex];
        let nextGroup = candidate;

        if (candidate.items.length === 0) {
          setIsPaused(true);
          setIsResolvingGroup(true);
          nextGroup = (await resolveViewerGroup(candidate)) ?? candidate;
        }

        if (nextGroup.items.length === 0) continue;

        setResolvedGroups((current) => ({
          ...current,
          [nextGroup.id]: nextGroup,
        }));
        groupTransitionPendingRef.current = false;
        setIsResolvingGroup(false);
        openAt(nextGroupIndex, 0, direction, nextGroup);
        return;
      }

      groupTransitionPendingRef.current = false;
      setIsResolvingGroup(false);
      if (closeWhenExhausted) closeViewer();
      else setIsPaused(false);
    },
    [closeViewer, groupIndex, openAt, resolveViewerGroup, viewerGroups],
  );

  const goNext = useCallback(() => {
    if (!group || !item || groupTransitionPendingRef.current) return;

    if (!item.unavailable) onSeen(item.id);

    if (itemIndex < group.items.length - 1) {
      openAt(groupIndex, itemIndex + 1, 1);
      return;
    }

    onGroupComplete?.(group);

    if (groupIndex < viewerGroups.length - 1) {
      void advanceToNextGroup(1, true);
      return;
    }

    closeViewer();
  }, [
    advanceToNextGroup,
    closeViewer,
    group,
    groupIndex,
    item,
    itemIndex,
    onGroupComplete,
    onSeen,
    openAt,
    viewerGroups.length,
  ]);

  const goNextGroup = useCallback(() => {
    if (
      !group ||
      !item ||
      groupIndex >= viewerGroups.length - 1 ||
      groupTransitionPendingRef.current
    )
      return;

    if (!item.unavailable) onSeen(item.id);
    if (itemIndex === group.items.length - 1) onGroupComplete?.(group);
    void advanceToNextGroup(1, false);
  }, [
    advanceToNextGroup,
    group,
    groupIndex,
    item,
    itemIndex,
    onGroupComplete,
    onSeen,
    viewerGroups.length,
  ]);

  const goPreviousGroup = useCallback(() => {
    if (!item || groupIndex <= 0) return;

    if (!item.unavailable) onSeen(item.id);
    openAt(groupIndex - 1, 0, -1);
  }, [groupIndex, item, onSeen, openAt]);

  useEffect(() => {
    if (!isOpen || !item || item.unavailable) return;

    const timer = window.setTimeout(
      () => onSeen(item.id),
      STORY_MEANINGFUL_VIEW_MS,
    );
    return () => window.clearTimeout(timer);
  }, [isOpen, item, onSeen]);

  useEffect(() => {
    if (!isOpen) return;

    const root = document.documentElement;
    let viewerIsOpen = true;
    root.setAttribute("data-story-viewer-open", "");

    // A route transition may still be settling when the modal opens. Finish
    // that global indicator and keep it hidden so it cannot sit above Stories.
    void import("nprogress").then(({ default: NProgress }) => {
      if (viewerIsOpen) NProgress.done(true);
    });

    return () => {
      viewerIsOpen = false;
      root.removeAttribute("data-story-viewer-open");
    };
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    try {
      if (window.localStorage.getItem(STORY_GESTURE_HINT_KEY)) return;
      window.localStorage.setItem(STORY_GESTURE_HINT_KEY, "true");
    } catch {
      // The hint is optional when private browsing blocks local storage.
    }

    setShowGestureHint(true);
    const timer = window.setTimeout(
      () => setShowGestureHint(false),
      STORY_GESTURE_HINT_MS,
    );
    return () => window.clearTimeout(timer);
  }, [isOpen]);

  useEffect(
    () => () => {
      if (holdIndicatorTimerRef.current !== null) {
        window.clearTimeout(holdIndicatorTimerRef.current);
      }
      if (likeBurstTimerRef.current !== null) {
        window.clearTimeout(likeBurstTimerRef.current);
      }
      if (shareFeedbackTimerRef.current !== null) {
        window.clearTimeout(shareFeedbackTimerRef.current);
      }
    },
    [],
  );

  useEffect(() => {
    if (
      !isOpen ||
      !item ||
      item.mediaType !== "image" ||
      item.unavailable ||
      playbackPaused
    ) {
      return;
    }

    let animationFrame = 0;
    let previousTime = performance.now();

    const tick = (currentTime: number) => {
      const elapsed = currentTime - previousTime;
      previousTime = currentTime;

      const next = progressValueRef.current + elapsed / item.durationMs;
      displayProgress(next);
      if (next >= 1 && !advancedRef.current) {
        advancedRef.current = true;
        window.setTimeout(goNext, 0);
      }

      if (!advancedRef.current) {
        animationFrame = window.requestAnimationFrame(tick);
      }
    };

    animationFrame = window.requestAnimationFrame(tick);
    return () => window.cancelAnimationFrame(animationFrame);
  }, [displayProgress, goNext, isOpen, item, playbackPaused]);

  useEffect(() => {
    if (
      !isOpen ||
      !item ||
      item.mediaType !== "video" ||
      item.unavailable ||
      mediaFailed ||
      playbackPaused ||
      isBuffering
    ) {
      return;
    }

    let animationFrame = 0;
    const syncWithVideo = () => {
      const video = videoRef.current;
      if (video && Number.isFinite(video.duration) && video.duration > 0) {
        displayProgress(video.currentTime / video.duration);
      }
      animationFrame = window.requestAnimationFrame(syncWithVideo);
    };

    animationFrame = window.requestAnimationFrame(syncWithVideo);
    return () => window.cancelAnimationFrame(animationFrame);
  }, [
    displayProgress,
    isBuffering,
    isOpen,
    item,
    mediaFailed,
    playbackPaused,
    videoAttempt,
  ]);

  useEffect(() => {
    const video = videoRef.current;
    if (!video || item?.mediaType !== "video") return;

    if (playbackPaused || isBuffering) {
      video.pause();
    } else {
      void video.play().catch(() => setIsPaused(true));
    }
  }, [isBuffering, item?.id, item?.mediaType, playbackPaused, videoAttempt]);

  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      setShowGestureHint(false);
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
          "[data-story-control], input, textarea, select, [contenteditable=true]",
        )
      ) {
        return;
      }

      if (event.key === "ArrowRight") {
        event.preventDefault();
        goNext();
      } else if (event.key === "ArrowLeft") {
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

  const progressLabel = useMemo(() => {
    if (!group || !item) return "";
    return t("stories.viewer.position", {
      current: itemIndex + 1,
      total: group.items.length,
      defaultValue: "Story {{current}} of {{total}}",
    });
  }, [group, item, itemIndex, t]);

  if (!group || !item) return null;

  const isLiked = Boolean(likedStories[item.id]);
  const canLike = item.likeCount !== undefined;
  const displayedLikeCount = canLike
    ? (item.likeCount ?? 0) + (isLiked ? 1 : 0)
    : undefined;

  const showShareFeedback = (message: string) => {
    if (shareFeedbackTimerRef.current !== null) {
      window.clearTimeout(shareFeedbackTimerRef.current);
    }
    setShareFeedback(message);
    shareFeedbackTimerRef.current = window.setTimeout(() => {
      setShareFeedback(null);
      shareFeedbackTimerRef.current = null;
    }, 2200);
  };

  const toggleLike = () => {
    if (!canLike) return;
    setShowGestureHint(false);
    const willLike = !isLiked;

    setLikedStories((current) => {
      const next = { ...current };
      if (willLike) next[item.id] = true;
      else delete next[item.id];

      try {
        window.localStorage.setItem(
          STORY_LIKES_STORAGE_KEY,
          JSON.stringify(next),
        );
      } catch {
        // The optimistic like still works when storage is unavailable.
      }
      return next;
    });

    if (!willLike) {
      setLikeBurst(null);
      return;
    }

    if (likeBurstTimerRef.current !== null) {
      window.clearTimeout(likeBurstTimerRef.current);
    }
    setLikeBurst({ storyId: item.id, key: Date.now() });
    likeBurstTimerRef.current = window.setTimeout(() => {
      setLikeBurst(null);
      likeBurstTimerRef.current = null;
    }, 850);
  };

  const shareStory = async () => {
    setShowGestureHint(false);
    const resumeAfterShare = !isPaused;
    shareInProgressRef.current = true;
    setIsSharing(true);
    setIsPaused(true);

    const shareUrl = new URL(window.location.href);
    shareUrl.searchParams.set(shareQueryParam, item.id);
    shareUrl.hash = "";
    const shareData = {
      title: item.title || group.title,
      text: item.subtitle || group.subtitle,
      url: shareUrl.toString(),
    };

    try {
      if (typeof navigator.share === "function") {
        await navigator.share(shareData);
        showShareFeedback(
          t("stories.viewer.shared", { defaultValue: "Shared" }),
        );
      } else if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(shareData.url);
        showShareFeedback(
          t("stories.viewer.linkCopied", { defaultValue: "Link copied" }),
        );
      } else {
        throw new Error("Sharing is unavailable");
      }
    } catch (error) {
      if (!(error instanceof DOMException && error.name === "AbortError")) {
        showShareFeedback(
          t("stories.viewer.shareUnavailable", {
            defaultValue: "Unable to share",
          }),
        );
      }
    } finally {
      shareInProgressRef.current = false;
      setIsSharing(false);
      if (resumeAfterShare) setIsPaused(false);
    }
  };

  const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (event.button !== 0) return;
    if ((event.target as HTMLElement).closest("[data-story-control]")) return;

    setShowGestureHint(false);
    clearHoldIndicator();
    pointerStartRef.current = {
      x: event.clientX,
      y: event.clientY,
      at: performance.now(),
    };
    setIsPaused(true);
    holdIndicatorTimerRef.current = window.setTimeout(() => {
      holdIndicatorTimerRef.current = null;
      if (pointerStartRef.current) setShowHoldIndicator(true);
    }, HOLD_TO_PAUSE_MS);
  };

  const handlePointerUp = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (event.button !== 0) return;
    const start = pointerStartRef.current;
    pointerStartRef.current = null;
    clearHoldIndicator();
    if (!start) return;

    setIsPaused(false);
    const deltaX = event.clientX - start.x;
    const deltaY = event.clientY - start.y;
    const heldFor = performance.now() - start.at;

    if (
      heldFor >= HOLD_TO_PAUSE_MS &&
      Math.abs(deltaX) < 16 &&
      Math.abs(deltaY) < 16
    ) {
      return;
    }

    if (
      Math.abs(deltaY) > SWIPE_DISTANCE &&
      Math.abs(deltaY) > Math.abs(deltaX)
    ) {
      if (deltaY > 0) closeViewer();
      return;
    }

    if (Math.abs(deltaX) > SWIPE_DISTANCE) {
      if (deltaX < 0) goNextGroup();
      else goPreviousGroup();
      return;
    }

    const bounds = event.currentTarget.getBoundingClientRect();
    const clickedLeftHalf =
      bounds.width > 0 && event.clientX < bounds.left + bounds.width / 2;
    if (clickedLeftHalf) goPrevious();
    else goNext();
  };

  const handleContextMenu = (event: ReactMouseEvent<HTMLDivElement>) => {
    if ((event.target as HTMLElement).closest("[data-story-control]")) return;

    event.preventDefault();
    pointerStartRef.current = null;
    clearHoldIndicator();
    setShowGestureHint(false);
    if (!shareInProgressRef.current) setIsPaused(false);
    goPrevious();
  };

  const renderMedia = () => {
    if (item.unavailable || mediaFailed) {
      return (
        <div className="absolute inset-0 flex flex-col items-center justify-center bg-zinc-950 px-8 text-center text-white">
          <div className="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-white/10">
            <AlertTriangle aria-hidden="true" size={30} />
          </div>
          <h3 className="text-xl font-bold">
            {t("stories.viewer.unavailableTitle", {
              defaultValue: "This story is unavailable",
            })}
          </h3>
          <p className="mt-2 max-w-xs text-sm leading-relaxed text-white/80">
            {t("stories.viewer.unavailableDescription", {
              defaultValue:
                "The update may have ended or is no longer available near you.",
            })}
          </p>
          {mediaFailed && !item.unavailable && (
            <Button
              data-story-control
              className="mt-6 bg-white text-black"
              radius="full"
              startContent={<RotateCcw size={17} />}
              onPress={() => {
                setMediaFailed(false);
                setIsBuffering(true);
                setVideoAttempt((value) => value + 1);
              }}
            >
              {t("stories.viewer.retry", { defaultValue: "Try again" })}
            </Button>
          )}
          <Button
            data-story-control
            className="mt-3 text-white"
            variant="light"
            onPress={goNext}
          >
            {t("stories.viewer.next", { defaultValue: "Next story" })}
          </Button>
        </div>
      );
    }

    if (item.mediaType === "video" && item.videoSrc) {
      return (
        <>
          <StoryArtwork
            imageSrc={item.posterSrc}
            visual={item.visual}
            className="absolute inset-0"
          />
          <video
            key={`${item.id}-${videoAttempt}`}
            ref={videoRef}
            aria-hidden="true"
            autoPlay
            muted={isMuted}
            playsInline
            poster={item.posterSrc}
            preload="metadata"
            className="absolute inset-0 h-full w-full object-cover"
            src={item.videoSrc}
            tabIndex={-1}
            onCanPlay={() => setIsBuffering(false)}
            onEnded={() => {
              displayProgress(1);
              goNext();
            }}
            onError={() => {
              setIsBuffering(false);
              setMediaFailed(true);
            }}
            onLoadStart={() => setIsBuffering(true)}
            onPlaying={() => setIsBuffering(false)}
            onProgress={(event) => {
              const video = event.currentTarget;
              if (!Number.isFinite(video.duration) || video.duration <= 0)
                return;

              const ranges = video.buffered;
              const bufferedEnd = ranges.length
                ? ranges.end(ranges.length - 1)
                : 0;
              setBufferedProgress(Math.min(1, bufferedEnd / video.duration));
            }}
            onStalled={() => setIsBuffering(true)}
            onWaiting={() => setIsBuffering(true)}
          >
            {item.captions?.map((caption) => (
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
          <div className="pointer-events-none absolute inset-0 bg-linear-to-b from-black/35 via-transparent to-black/75" />
        </>
      );
    }

    return (
      <StoryArtwork
        imagePosition={item.imagePosition}
        imageSrc={item.imageSrc}
        visual={item.visual}
        className="absolute inset-0"
      />
    );
  };

  return (
    <Modal
      aria-label={progressLabel}
      backdrop="opaque"
      hideCloseButton
      isDismissable
      isKeyboardDismissDisabled
      isOpen={isOpen}
      placement="center"
      scrollBehavior="inside"
      size="full"
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
        wrapper: "pointer-events-none p-0 md:p-6",
        backdrop: "bg-black/85",
        base: "pointer-events-auto m-0 h-[100dvh] max-h-[100dvh] overflow-hidden rounded-none bg-black shadow-2xl md:h-[min(860px,calc(100dvh-3rem))] md:max-h-[860px] md:!max-w-[480px] md:rounded-[2rem]",
      }}
    >
      <ModalContent>
        <ModalBody className="sr-only">
          {t("stories.viewer.instructions", {
            defaultValue:
              "Use Left and Right Arrow to change stories, Space to pause or resume, M to mute, and Escape to close.",
          })}
        </ModalBody>
        <div
          aria-label={progressLabel}
          aria-keyshortcuts="ArrowLeft ArrowRight Space M Escape"
          data-story-paused={playbackPaused || undefined}
          data-story-sharing={isSharing || undefined}
          data-story-window-inactive={isWindowInactive || undefined}
          data-story-viewer
          className="group/storyviewer relative h-full w-full touch-none cursor-pointer select-none overflow-hidden bg-black"
          onContextMenu={handleContextMenu}
          onPointerCancel={() => {
            pointerStartRef.current = null;
            clearHoldIndicator();
            if (!shareInProgressRef.current) setIsPaused(false);
          }}
          onPointerDown={handlePointerDown}
          onPointerLeave={() => {
            pointerStartRef.current = null;
            clearHoldIndicator();
            if (!shareInProgressRef.current) setIsPaused(false);
          }}
          onPointerUp={handlePointerUp}
        >
          <p aria-atomic="true" aria-live="polite" className="sr-only">
            {`${group.title}. ${item.title ?? item.visual.headline}. ${progressLabel}`}
          </p>

          <div className="absolute inset-0">
            <motion.div
              key={`${group.id}:${item.id}`}
              animate="active"
              className="absolute inset-0"
              custom={{
                direction: transitionDirection,
                kind: transitionKind,
              }}
              data-story-media={item.id}
              data-story-transition={transitionKind}
              data-story-transition-direction={transitionDirection}
              initial={
                prefersReducedMotion || !hasNavigatedRef.current
                  ? false
                  : "enter"
              }
              transition={
                prefersReducedMotion
                  ? { duration: 0 }
                  : transitionKind === "group"
                    ? {
                        duration: 0.16,
                        ease: [0.22, 0.8, 0.24, 1],
                      }
                    : { duration: 0.08, ease: "easeOut" }
              }
              variants={STORY_PANEL_VARIANTS}
            >
              {renderMedia()}
            </motion.div>
          </div>

          <StoryViewerHeader
            bufferedProgress={bufferedProgress}
            group={group}
            isMuted={isMuted}
            item={item}
            itemIndex={itemIndex}
            progress={progressValueRef.current}
            progressFillRef={progressFillRef}
            onClose={closeViewer}
            onMutedChange={setIsMuted}
            onPublisherPress={navigateFromViewer}
          />

          {!item.unavailable && !mediaFailed && (
            <StoryDetailsOverlay
              displayedLikeCount={displayedLikeCount}
              isLiked={isLiked}
              item={item}
              shareFeedback={shareFeedback}
              onLike={toggleLike}
              onNavigate={navigateFromViewer}
              onShare={() => void shareStory()}
            />
          )}

          <StoryStatusLayer
            isBuffering={isBuffering}
            mediaFailed={mediaFailed}
            showGestureHint={showGestureHint}
            showHoldIndicator={showHoldIndicator}
            unavailable={Boolean(item.unavailable)}
          />

          <AnimatePresence>
            {likeBurst?.storyId === item.id && (
              <StoryLikeBurst key={likeBurst.key} burstKey={likeBurst.key} />
            )}
          </AnimatePresence>

          {isResolvingGroup && (
            <MediaNavigationOverlay
              label={t("stories.viewer.loadingNext", {
                defaultValue: "Loading next Story…",
              })}
            />
          )}

          <button
            data-story-control
            type="button"
            className={ACCESSIBILITY_CONTROL_CLASS}
            disabled={groupIndex === 0 && itemIndex === 0}
            onClick={goPrevious}
          >
            {t("stories.viewer.previous", { defaultValue: "Previous story" })}
          </button>
          {!item.unavailable && !mediaFailed && (
            <>
              <button
                data-story-control
                type="button"
                className={ACCESSIBILITY_CONTROL_CLASS}
                onClick={goNext}
              >
                {t("stories.viewer.next", { defaultValue: "Next story" })}
              </button>
              <button
                data-story-control
                type="button"
                className={ACCESSIBILITY_CONTROL_CLASS}
                onClick={() => setIsPaused((value) => !value)}
              >
                {isPaused
                  ? t("stories.viewer.resume", {
                      defaultValue: "Resume story",
                    })
                  : t("stories.viewer.pause", {
                      defaultValue: "Pause story",
                    })}
                {isPaused ? <Play aria-hidden="true" /> : null}
              </button>
            </>
          )}
        </div>
      </ModalContent>
    </Modal>
  );
};

export default StoryViewer;
