import {
  act,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { HOME_STORIES } from "@/components/Stories/storyData";
import StoryViewer, {
  STORY_LIKES_STORAGE_KEY,
} from "@/components/Stories/StoryViewer";

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string, options?: Record<string, unknown>) =>
        String(options?.defaultValue ?? key)
          .replace("{{current}}", String(options?.current ?? ""))
          .replace("{{total}}", String(options?.total ?? ""))
          .replace("{{count}}", String(options?.count ?? ""))
          .replace("{{title}}", String(options?.title ?? "")),
    }),
  };
});

describe("StoryViewer", () => {
  beforeEach(() => {
    window.localStorage.clear();
    vi.spyOn(HTMLMediaElement.prototype, "play").mockResolvedValue(undefined);
    vi.spyOn(HTMLMediaElement.prototype, "pause").mockImplementation(() => {});
    vi.spyOn(HTMLMediaElement.prototype, "load").mockImplementation(() => {});
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: undefined,
    });
  });

  it("closes the viewer when Escape is pressed", async () => {
    const onClose = vi.fn();
    const onSeen = vi.fn();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={onClose}
        onNavigate={vi.fn()}
        onSeen={onSeen}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 1 of 3" });
    fireEvent.keyDown(screen.getByRole("button", { name: "Close stories" }), {
      key: "Escape",
    });

    expect(onSeen).toHaveBeenCalledWith(HOME_STORIES[0].items[0].id);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("closes when the desktop backdrop outside the story is clicked", async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={onClose}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    const dialog = await screen.findByRole("dialog", { name: "Story 1 of 3" });
    const backdrop = document.querySelector<HTMLElement>(".bg-black\\/85");

    expect(dialog).toHaveClass("pointer-events-auto", "md:!max-w-[480px]");
    expect(dialog.parentElement).toHaveClass("pointer-events-none", "md:p-6");
    expect(backdrop).not.toBeNull();
    expect(backdrop).not.toHaveClass("backdrop-blur-md");
    await user.click(backdrop!);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("exposes instructions and operable keyboard-only story controls", async () => {
    const user = userEvent.setup();
    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    const dialog = await screen.findByRole("dialog", {
      name: "Story 1 of 3",
    });
    const instructionsId = dialog.getAttribute("aria-describedby");
    expect(instructionsId).toBeTruthy();
    expect(document.getElementById(instructionsId!)).toHaveTextContent(
      "Use Left and Right Arrow to change stories",
    );
    expect(document.querySelector("[data-story-viewer]")).toHaveAttribute(
      "aria-keyshortcuts",
      "ArrowLeft ArrowRight Space M Escape",
    );

    expect(
      screen.getByRole("button", { name: "Previous story" }),
    ).toBeDisabled();
    const nextButton = screen.getByRole("button", { name: "Next story" });
    const pauseButton = screen.getByRole("button", { name: "Pause story" });
    expect(nextButton).toHaveClass("sr-only", "focus:not-sr-only");
    expect(pauseButton).toHaveClass("sr-only", "focus:not-sr-only");

    pauseButton.focus();
    await user.keyboard("{Enter}");
    expect(document.querySelector("[data-story-viewer]")).toHaveAttribute(
      "data-story-paused",
      "true",
    );
    expect(
      screen.getByRole("button", { name: "Resume story" }),
    ).toBeInTheDocument();

    nextButton.focus();
    fireEvent.keyDown(nextButton, { key: "ArrowRight" });
    expect(
      screen.getByRole("dialog", { name: "Story 1 of 3" }),
    ).toBeInTheDocument();
    await user.keyboard("{Enter}");
    expect(
      await screen.findByRole("dialog", { name: "Story 2 of 3" }),
    ).toBeInTheDocument();
    expect(document.activeElement).toBe(nextButton);
    expect(
      screen.getByText(/Fresh Finds\. Berry good mornings\. Story 2 of 3/),
    ).toHaveAttribute("aria-live", "polite");
  });

  it("marks meaningful views and opens the configured internal CTA", async () => {
    const onSeen = vi.fn();
    const onNavigate = vi.fn();
    const user = userEvent.setup();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={onNavigate}
        onSeen={onSeen}
      />,
    );

    expect(
      await screen.findByRole("dialog", { name: /Story 1 of 3/i }),
    ).toBeInTheDocument();
    expect(document.documentElement).toHaveAttribute("data-story-viewer-open");
    expect(screen.getByText("Fresh Finds")).toBeInTheDocument();
    expect(
      screen.getByText("Fresh produce, delivered fast"),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Hand-picked fruits and vegetables from nearby sellers.",
      ),
    ).toBeInTheDocument();
    const uploadedArtwork = screen.getByRole("img", {
      name: HOME_STORIES[0].items[0].visual.headline,
    });
    expect(uploadedArtwork).toBeInTheDocument();
    expect(uploadedArtwork).toHaveStyle({ background: "#09090b" });
    expect(
      document.querySelector(
        'img[src="/images/stories/seller-fresh-produce.webp"]',
      ),
    ).toBeInTheDocument();
    expect(document.querySelector(".bg-black\\/85")).toBeInTheDocument();
    expect(
      document.querySelector('[data-story-media="fresh-finds-1"]'),
    ).toBeInTheDocument();
    expect(
      document.querySelector('[data-story-progress-fill="active"]'),
    ).toHaveClass("origin-left", "will-change-transform");

    await waitFor(() => expect(onSeen).toHaveBeenCalledWith("fresh-finds-1"), {
      timeout: 1800,
    });

    await user.click(screen.getByRole("button", { name: "Browse categories" }));
    expect(onNavigate).toHaveBeenCalledWith("/categories/");
  });

  it("opens the seller page from the publisher header", async () => {
    const onNavigate = vi.fn();
    const onSeen = vi.fn();
    const user = userEvent.setup();
    const sellerGroup = {
      ...HOME_STORIES[0],
      publisherHref: "/stores/fresh-finds/",
    };

    render(
      <StoryViewer
        groups={[sellerGroup]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={onNavigate}
        onSeen={onSeen}
      />,
    );

    await user.click(
      await screen.findByRole("button", {
        name: "Open Fresh Finds store",
      }),
    );

    expect(onSeen).toHaveBeenCalledWith("fresh-finds-1");
    expect(onNavigate).toHaveBeenCalledWith("/stores/fresh-finds/");
  });

  it("closes immediately while the seller route opens in the background", async () => {
    let finishNavigation: (() => void) | undefined;
    const onNavigate = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          finishNavigation = resolve;
        }),
    );
    const sellerGroup = {
      ...HOME_STORIES[0],
      publisherHref: "/stores/fresh-finds/",
    };

    const onClose = vi.fn();
    render(
      <StoryViewer
        groups={[sellerGroup]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={onClose}
        onNavigate={onNavigate}
        onSeen={vi.fn()}
      />,
    );

    await userEvent.click(
      await screen.findByRole("button", {
        name: "Open Fresh Finds store",
      }),
    );

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(screen.queryByText("Opening…")).not.toBeInTheDocument();

    await act(async () => finishNavigation?.());
    expect(onNavigate).toHaveBeenCalledWith("/stores/fresh-finds/");
  });

  it("renders the unavailable state with a way to continue", async () => {
    const unavailableGroup = HOME_STORIES[5];

    render(
      <StoryViewer
        groups={[unavailableGroup]}
        selection={{ groupIndex: 0, itemIndex: 1 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    expect(
      await screen.findByText("This story is unavailable"),
    ).toBeInTheDocument();
    expect(screen.getAllByRole("button", { name: "Next story" })).toHaveLength(
      1,
    );
  });

  it("loads only the active video progressively with a local poster", async () => {
    const videoGroup = HOME_STORIES[3];
    render(
      <StoryViewer
        groups={[videoGroup]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 1 of 2" });
    const video = document.querySelector("video");

    expect(video).toHaveAttribute("preload", "metadata");
    expect(video).toHaveAttribute(
      "poster",
      "/images/stories/seller-florist.webp",
    );
    expect(document.querySelectorAll("video")).toHaveLength(1);
    expect(
      document.querySelector("[data-story-buffer-fill]"),
    ).toBeInTheDocument();
    expect(screen.getByText("Loading story")).toBeInTheDocument();
  });

  it("shows views, animates persisted likes, and shares the active story", async () => {
    const share = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: share,
    });
    const user = userEvent.setup();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        shareQueryParam="update"
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 1 of 3" });
    const views = screen.getByLabelText("1.3K views");
    expect(views).toHaveTextContent("1.3K");
    expect(views).not.toHaveTextContent("24h");

    const likeButton = screen.getByRole("button", { name: "Like story" });
    expect(likeButton).toHaveTextContent("184");
    await user.click(likeButton);

    expect(
      screen.getByRole("button", { name: "Unlike story" }),
    ).toHaveTextContent("185");
    expect(
      document.querySelector("[data-story-like-burst]"),
    ).toBeInTheDocument();
    expect(
      JSON.parse(window.localStorage.getItem(STORY_LIKES_STORAGE_KEY) ?? "{}"),
    ).toEqual({ "fresh-finds-1": true });
    expect(
      screen.getByRole("dialog", { name: "Story 1 of 3" }),
    ).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Share story" }));
    expect(share).toHaveBeenCalledWith(
      expect.objectContaining({
        title: "Fresh produce, delivered fast",
        url: expect.stringContaining("update=fresh-finds-1"),
      }),
    );
    expect(screen.getByText("Shared")).toBeInTheDocument();
    expect(
      screen.getByRole("dialog", { name: "Story 1 of 3" }),
    ).toBeInTheDocument();
  });

  it("keeps playback paused while the native share sheet is open", async () => {
    let finishSharing: (() => void) | undefined;
    const share = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          finishSharing = resolve;
        }),
    );
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: share,
    });
    const user = userEvent.setup();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 1 of 3" });
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();

    await user.click(screen.getByRole("button", { name: "Share story" }));
    expect(viewer).toHaveAttribute("data-story-sharing", "true");
    expect(viewer).toHaveAttribute("data-story-paused", "true");

    fireEvent.pointerLeave(viewer!);
    expect(viewer).toHaveAttribute("data-story-paused", "true");
    expect(
      screen.getByRole("dialog", { name: "Story 1 of 3" }),
    ).toBeInTheDocument();

    await act(async () => {
      finishSharing?.();
      await Promise.resolve();
    });

    await waitFor(() => {
      expect(viewer).not.toHaveAttribute("data-story-sharing");
      expect(viewer).not.toHaveAttribute("data-story-paused");
    });
  });

  it("stays paused when the native share promise resolves before its system panel closes", async () => {
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: vi.fn().mockResolvedValue(undefined),
    });
    const user = userEvent.setup();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 1 of 3" });
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();

    await user.click(screen.getByRole("button", { name: "Share story" }));
    expect(viewer).not.toHaveAttribute("data-story-sharing");

    fireEvent.blur(window);
    expect(viewer).toHaveAttribute("data-story-window-inactive", "true");
    expect(viewer).toHaveAttribute("data-story-paused", "true");

    fireEvent.pointerLeave(viewer!);
    expect(viewer).toHaveAttribute("data-story-paused", "true");

    fireEvent.focus(window);
    await waitFor(() => {
      expect(viewer).not.toHaveAttribute("data-story-window-inactive");
      expect(viewer).not.toHaveAttribute("data-story-paused");
    });
  });

  it("advances on a normal viewer surface click", async () => {
    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 1 of 3" });
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();
    expect(viewer).toHaveClass("cursor-pointer");

    fireEvent.pointerDown(viewer!, { clientX: 240, clientY: 400 });
    fireEvent.pointerUp(viewer!, { clientX: 240, clientY: 400 });

    expect(
      await screen.findByRole("dialog", { name: "Story 2 of 3" }),
    ).toBeInTheDocument();
    expect(
      document.querySelector('[data-story-transition="item"]'),
    ).toHaveAttribute("data-story-transition-direction", "1");
  });

  it("uses the left and right halves for backward and forward taps", async () => {
    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 1 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 2 of 3" });
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();
    vi.spyOn(viewer!, "getBoundingClientRect").mockReturnValue({
      bottom: 800,
      height: 800,
      left: 0,
      right: 480,
      top: 0,
      width: 480,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    });

    fireEvent.pointerDown(viewer!, { button: 0, clientX: 100, clientY: 400 });
    fireEvent.pointerUp(viewer!, { button: 0, clientX: 100, clientY: 400 });
    expect(
      await screen.findByRole("dialog", { name: "Story 1 of 3" }),
    ).toBeInTheDocument();

    fireEvent.pointerDown(viewer!, { button: 0, clientX: 380, clientY: 400 });
    fireEvent.pointerUp(viewer!, { button: 0, clientX: 380, clientY: 400 });
    expect(
      await screen.findByRole("dialog", { name: "Story 2 of 3" }),
    ).toBeInTheDocument();
  });

  it("uses right-click to return to the previous segment", async () => {
    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 2 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 3 of 3" });
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();
    expect(screen.getByRole("button", { name: "Previous story" })).toHaveClass(
      "sr-only",
      "focus:not-sr-only",
    );
    expect(screen.getByRole("button", { name: "Next story" })).toHaveClass(
      "sr-only",
      "focus:not-sr-only",
    );

    const contextMenu = new MouseEvent("contextmenu", {
      bubbles: true,
      cancelable: true,
      button: 2,
    });
    viewer!.dispatchEvent(contextMenu);

    expect(contextMenu.defaultPrevented).toBe(true);
    expect(
      await screen.findByRole("dialog", { name: "Story 2 of 3" }),
    ).toBeInTheDocument();
    expect(
      document.querySelector(
        `[data-story-media="${HOME_STORIES[0].items[1].id}"]`,
      ),
    ).toHaveAttribute("data-story-transition-direction", "-1");
  });

  it("uses right-click on the first segment to open the previous story's last segment", async () => {
    render(
      <StoryViewer
        groups={HOME_STORIES}
        selection={{ groupIndex: 1, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByText("Daily Bake");
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();

    fireEvent.contextMenu(viewer!, { button: 2 });

    expect(await screen.findByText("Fresh Finds")).toBeInTheDocument();
    expect(
      screen.getByRole("dialog", { name: "Story 3 of 3" }),
    ).toBeInTheDocument();
    expect(
      document.querySelector('[data-story-transition="group"]'),
    ).toHaveAttribute("data-story-transition-direction", "-1");
  });

  it("marks the final story viewed before a center click closes it", async () => {
    const onClose = vi.fn();
    const onGroupComplete = vi.fn();
    const onSeen = vi.fn();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 2 }}
        onClose={onClose}
        onGroupComplete={onGroupComplete}
        onNavigate={vi.fn()}
        onSeen={onSeen}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 3 of 3" });
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();

    fireEvent.pointerDown(viewer!, { clientX: 240, clientY: 400 });
    fireEvent.pointerUp(viewer!, { clientX: 240, clientY: 400 });

    expect(onSeen).toHaveBeenCalledWith(HOME_STORIES[0].items[2].id);
    expect(onGroupComplete).toHaveBeenCalledWith(HOME_STORIES[0]);
    expect(onClose).toHaveBeenCalledOnce();
  });

  it("autoplays into the next lazily loaded merchant instead of closing", async () => {
    let finishResolving:
      | ((group: (typeof HOME_STORIES)[number]) => void)
      | undefined;
    const firstGroup = {
      ...HOME_STORIES[0],
      items: [{ ...HOME_STORIES[0].items[0], durationMs: 60 }],
    };
    const nextGroup = {
      ...HOME_STORIES[1],
      items: [],
      itemCount: 1,
    };
    const resolvedNextGroup = {
      ...HOME_STORIES[1],
      items: [{ ...HOME_STORIES[1].items[0], durationMs: 5_000 }],
    };
    const resolveGroup = vi.fn(
      () =>
        new Promise<(typeof HOME_STORIES)[number]>((resolve) => {
          finishResolving = resolve;
        }),
    );
    const onClose = vi.fn();

    render(
      <StoryViewer
        groups={[firstGroup, nextGroup]}
        resolveGroup={resolveGroup}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={onClose}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    expect(await screen.findByText("Loading next Story…")).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
    expect(resolveGroup).toHaveBeenCalledWith(nextGroup);

    await act(async () => finishResolving?.(resolvedNextGroup));

    expect(await screen.findByText("Daily Bake")).toBeInTheDocument();
    expect(
      document.querySelector(
        `[data-story-media='${resolvedNextGroup.items[0].id}']`,
      ),
    ).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });

  it("marks the active Story seen before the close control exits", async () => {
    const onClose = vi.fn();
    const onSeen = vi.fn();
    const user = userEvent.setup();

    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={onClose}
        onNavigate={vi.fn()}
        onSeen={onSeen}
      />,
    );

    await user.click(
      await screen.findByRole("button", { name: "Close stories" }),
    );

    expect(onSeen).toHaveBeenCalledWith(HOME_STORIES[0].items[0].id);
    expect(onClose).toHaveBeenCalledOnce();
  });

  it("uses an Instagram-style left swipe to reach the next story source", async () => {
    const onSeen = vi.fn();
    render(
      <StoryViewer
        groups={HOME_STORIES}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={onSeen}
      />,
    );

    await screen.findByText("Fresh Finds");
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();

    fireEvent.pointerDown(viewer!, { clientX: 180, clientY: 400 });
    fireEvent.pointerUp(viewer!, { clientX: 100, clientY: 400 });

    expect(await screen.findByText("Daily Bake")).toBeInTheDocument();
    expect(onSeen).toHaveBeenCalledWith("fresh-finds-1");
    expect(
      document.querySelector('[data-story-transition="group"]'),
    ).toHaveAttribute("data-story-transition-direction", "1");
  });

  it("uses an Instagram-style right swipe to return to the previous source", async () => {
    render(
      <StoryViewer
        groups={HOME_STORIES}
        selection={{ groupIndex: 1, itemIndex: 1 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByText("Daily Bake");
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();

    fireEvent.pointerDown(viewer!, { clientX: 100, clientY: 400 });
    fireEvent.pointerUp(viewer!, { clientX: 180, clientY: 400 });

    expect(await screen.findByText("Fresh Finds")).toBeInTheDocument();
    expect(
      screen.getByRole("dialog", { name: "Story 1 of 3" }),
    ).toBeInTheDocument();
    expect(
      document.querySelector('[data-story-media="fresh-finds-1"]'),
    ).toHaveAttribute("data-story-transition-direction", "-1");
  });

  it("shows the pause indicator only after holding the story", async () => {
    render(
      <StoryViewer
        groups={[HOME_STORIES[0]]}
        selection={{ groupIndex: 0, itemIndex: 0 }}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onSeen={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Story 1 of 3" });
    const viewer = document.querySelector("[data-story-viewer]");
    expect(viewer).not.toBeNull();

    fireEvent.pointerDown(viewer!, { clientX: 240, clientY: 400 });
    expect(screen.queryByText("Paused")).not.toBeInTheDocument();

    expect(
      await screen.findByText("Paused", {}, { timeout: 600 }),
    ).toBeInTheDocument();

    fireEvent.pointerUp(viewer!, { clientX: 240, clientY: 400 });
    expect(screen.queryByText("Paused")).not.toBeInTheDocument();
  });

  it("shows the gesture guide only on the first viewer use", async () => {
    const props = {
      groups: [HOME_STORIES[0]],
      selection: { groupIndex: 0, itemIndex: 0 },
      onClose: vi.fn(),
      onNavigate: vi.fn(),
      onSeen: vi.fn(),
    };
    const { unmount } = render(<StoryViewer {...props} />);

    expect(
      await screen.findByText(
        "Tap left for previous · Tap right for next · Hold to pause · Swipe between stores",
      ),
    ).toBeInTheDocument();

    unmount();
    render(<StoryViewer {...props} />);
    await screen.findByRole("dialog", { name: "Story 1 of 3" });

    expect(
      screen.queryByText(
        "Tap left for previous · Tap right for next · Hold to pause · Swipe between stores",
      ),
    ).not.toBeInTheDocument();
  });
});
