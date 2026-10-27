import {
  fireEvent,
  render,
  screen,
  waitFor,
  within,
} from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { act, StrictMode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import ReelViewer from "@/components/Reels/ReelViewer";
import { HOME_REELS } from "@/components/Reels/reelData";

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string, options?: Record<string, unknown>) =>
        String(options?.defaultValue ?? key)
          .replace("{{current}}", String(options?.current ?? ""))
          .replace("{{total}}", String(options?.total ?? ""))
          .replace("{{count}}", String(options?.count ?? "")),
    }),
  };
});

describe("ReelViewer", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
    vi.spyOn(HTMLMediaElement.prototype, "play").mockResolvedValue(undefined);
    vi.spyOn(HTMLMediaElement.prototype, "pause").mockImplementation(() => {});
    vi.spyOn(HTMLMediaElement.prototype, "load").mockImplementation(() => {});
    Object.defineProperty(navigator, "share", {
      configurable: true,
      value: undefined,
    });
  });

  it("does not mount or request an active video while the viewer is closed", () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={null}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    expect(document.querySelectorAll("video")).toHaveLength(0);
    expect(HTMLMediaElement.prototype.load).not.toHaveBeenCalled();
  });

  it("renders safely when the Reel feed is temporarily empty", () => {
    const { container } = render(
      <ReelViewer
        reels={[]}
        selection={null}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it("exposes announced Reel changes and keyboard-only playback controls", async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={onClose}
        onNavigate={vi.fn()}
      />,
    );

    const dialog = await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    const instructionsId = dialog.getAttribute("aria-describedby");
    expect(instructionsId).toBeTruthy();
    expect(document.getElementById(instructionsId!)).toHaveTextContent(
      "Use Up or Down Arrow to change reels",
    );
    expect(document.querySelector("[data-reel-viewer]")).toHaveAttribute(
      "aria-keyshortcuts",
      "ArrowUp ArrowDown ArrowLeft ArrowRight J K Space M Escape",
    );
    expect(document.querySelector("[data-reel-active-video]")).toHaveClass(
      "duration-150",
    );

    expect(
      screen.getByRole("button", { name: "Previous reel" }),
    ).toBeDisabled();
    const nextButton = screen.getByRole("button", { name: "Next reel" });
    const pauseButton = screen.getByRole("button", { name: "Pause reel" });
    expect(nextButton).toHaveClass("sr-only", "focus:not-sr-only");

    pauseButton.focus();
    await user.keyboard("{Enter}");
    expect(document.querySelector("[data-reel-viewer]")).toHaveAttribute(
      "data-reel-paused",
      "true",
    );
    expect(
      screen.getByRole("button", { name: "Play reel" }),
    ).toBeInTheDocument();

    nextButton.focus();
    fireEvent.keyDown(nextButton, { key: "ArrowDown" });
    expect(
      screen.getByRole("dialog", { name: "Reel 1 of 10" }),
    ).toBeInTheDocument();
    await user.keyboard("{Enter}");
    expect(
      await screen.findByRole("dialog", { name: "Reel 2 of 10" }),
    ).toBeInTheDocument();
    expect(document.activeElement).toBe(nextButton);
    expect(
      screen.getByText(/Daily Bake\. The first batch is out\. Reel 2 of 10/),
    ).toHaveAttribute("aria-live", "polite");

    fireEvent.keyDown(screen.getByRole("button", { name: "Close reels" }), {
      key: "Escape",
    });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("closes when the desktop backdrop outside the reel is clicked", async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={onClose}
        onNavigate={vi.fn()}
      />,
    );

    const dialog = await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    const backdrop = document.querySelector<HTMLElement>(".bg-black\\/90");

    expect(dialog).toHaveClass("pointer-events-auto", "md:!max-w-[480px]");
    expect(dialog.parentElement).toHaveClass("pointer-events-none", "md:p-4");
    expect(backdrop).not.toBeNull();
    expect(backdrop).not.toHaveClass("backdrop-blur-md");
    await user.click(backdrop!);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("mounts only the active video with metadata preload and its poster", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    const dialog = await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    expect(dialog).toHaveClass("h-[100dvh]", "overflow-hidden");
    expect(dialog).toHaveClass("md:rounded-[2rem]");
    expect(dialog.parentElement).toHaveClass("!overflow-hidden");
    expect(document.documentElement).toHaveAttribute(
      "data-reel-viewer-open",
      "true",
    );
    expect(document.querySelector("[data-reel-bottom-overlay]")).toHaveClass(
      "from-black/55",
      "via-black/30",
    );
    const videos = document.querySelectorAll("video");
    expect(videos).toHaveLength(1);
    expect(videos[0]).toHaveAttribute("preload", "metadata");
    expect(videos[0]).toHaveAttribute("poster", HOME_REELS[0].posterSrc);
    expect(videos[0]).toHaveAttribute("src", HOME_REELS[0].videoSrc);
    expect(videos[0]).toHaveAttribute("loop");
    expect(videos[0]).toHaveAttribute("autoplay");
    expect(videos[0]).toHaveProperty("muted", true);
    expect(videos[0]).toHaveAttribute("aria-hidden", "true");
    expect(videos[0]).toHaveAttribute("tabindex", "-1");

    const progress = document.querySelector("[data-reel-progress]");
    const progressFill = document.querySelector("[data-reel-progress-fill]");
    expect(progress).toHaveClass("bottom-0", "md:hidden");
    expect(progressFill).toHaveStyle({ transform: "scaleX(0)" });

    Object.defineProperty(videos[0], "duration", {
      configurable: true,
      value: 20,
    });
    Object.defineProperty(videos[0], "currentTime", {
      configurable: true,
      value: 5,
    });
    fireEvent.timeUpdate(videos[0]);
    expect(progressFill).toHaveStyle({ transform: "scaleX(0.25)" });
  });

  it("counts one view after two seconds of uninterrupted playback", async () => {
    const onView = vi.fn();
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
        onView={onView}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    const video = document.querySelector("[data-reel-active-video]");
    expect(video).not.toBeNull();

    vi.useFakeTimers();
    try {
      fireEvent.playing(video!);
      act(() => vi.advanceTimersByTime(1_500));
      fireEvent.waiting(video!);
      act(() => vi.advanceTimersByTime(1_000));
      expect(onView).not.toHaveBeenCalled();

      fireEvent.playing(video!);
      act(() => vi.advanceTimersByTime(1_999));
      expect(onView).not.toHaveBeenCalled();
      act(() => vi.advanceTimersByTime(1));
      expect(onView).toHaveBeenCalledTimes(1);
      expect(onView).toHaveBeenCalledWith(HOME_REELS[0].id);

      fireEvent.pause(video!);
      fireEvent.playing(video!);
      act(() => vi.advanceTimersByTime(2_000));
      expect(onView).toHaveBeenCalledTimes(1);
    } finally {
      vi.useRealTimers();
    }
  });

  it("keeps the first video source attached during Strict Mode effect replay", async () => {
    render(
      <StrictMode>
        <ReelViewer
          reels={HOME_REELS}
          selection={0}
          onClose={vi.fn()}
          onNavigate={vi.fn()}
        />
      </StrictMode>,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    const video = document.querySelector("[data-reel-active-video]");
    expect(video).toHaveAttribute("src", HOME_REELS[0].videoSrc);
  });

  it("retries one playback failure before showing the unavailable state", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    const firstVideo = document.querySelector("[data-reel-active-video]");
    fireEvent.error(firstVideo!);

    expect(
      screen.queryByText("This reel could not be played"),
    ).not.toBeInTheDocument();
    await waitFor(() =>
      expect(document.querySelector("[data-reel-active-video]")).not.toBe(
        firstVideo,
      ),
    );

    fireEvent.error(document.querySelector("[data-reel-active-video]")!);
    expect(
      await screen.findByText("This reel could not be played"),
    ).toBeInTheDocument();
  });

  it("provides familiar actions and a tappable product strip", async () => {
    const onNavigate = vi.fn();
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={1}
        onClose={vi.fn()}
        onNavigate={onNavigate}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 2 of 10" });
    expect(
      screen.getByRole("button", { name: "Like reel" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Share reel" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Unmute" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Unmute" })).toHaveClass(
      "border-0",
      "outline-none",
      "focus-visible:bg-black/70",
    );
    expect(screen.getByRole("button", { name: "Unmute" })).toHaveClass(
      "focus-visible:ring-2",
      "focus-visible:ring-white",
    );
    expect(
      screen.queryByRole("button", { name: /comments/i }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: /save reel/i }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("region", { name: "Products in this reel" }),
    ).toHaveClass("touch-pan-x", "overflow-x-auto");
    expect(
      screen.getByRole("region", { name: "Products in this reel" }),
    ).toHaveClass("py-2");
    expect(
      screen.getByRole("region", { name: "Products in this reel" }),
    ).not.toHaveClass("snap-x", "snap-proximity", "snap-mandatory");
    expect(screen.getByText("Featured products")).toBeInTheDocument();
    expect(screen.getByText("Popular")).toBeInTheDocument();
    expect(screen.getByText("4.6")).toBeInTheDocument();
    expect(screen.getByText("48 ratings")).toBeInTheDocument();
    expect(screen.getAllByText("View product").length).toBeGreaterThan(0);
    expect(
      screen.getByRole("button", { name: "Butter croissant, ₹75" }),
    ).toHaveClass("shadow-none");
    expect(
      screen.getByRole("button", { name: "Visit the bakery" }),
    ).toHaveClass("bg-white/95", "text-black");

    await userEvent.click(screen.getByRole("button", { name: "Like reel" }));
    expect(screen.getByRole("button", { name: "Unlike reel" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
    expect(window.localStorage.getItem("hyperlocal-liked-reels-v1")).toContain(
      HOME_REELS[1].id,
    );

    await userEvent.click(
      screen.getByRole("button", { name: "Butter croissant, ₹75" }),
    );
    expect(onNavigate).toHaveBeenCalledWith("/products/search/?q=croissant");
  });

  it("keeps the current Reel visible while a product route is opening", async () => {
    let finishNavigation: (() => void) | undefined;
    const onNavigate = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          finishNavigation = resolve;
        }),
    );

    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={1}
        onClose={vi.fn()}
        onNavigate={onNavigate}
      />,
    );

    await userEvent.click(
      await screen.findByRole("button", {
        name: /Butter croissant/,
      }),
    );

    expect(screen.getByRole("status")).toHaveTextContent("Opening…");
    expect(
      document.querySelector("[data-reel-active-video]"),
    ).toBeInTheDocument();

    await act(async () => finishNavigation?.());
    await waitFor(() =>
      expect(screen.queryByText("Opening…")).not.toBeInTheDocument(),
    );
  });

  it("shows each linked product's store and backend distance", async () => {
    const reelWithStoreDistance = {
      ...HOME_REELS[1],
      products: [
        {
          ...HOME_REELS[1].products![0],
          store: {
            id: "1",
            slug: "city-square-mart-1",
            name: "City Square Mart",
            distanceKm: 4.61,
            distanceLabel: "4.6 km",
            href: "/stores/city-square-mart-1/",
          },
        },
      ],
    };

    render(
      <ReelViewer
        reels={[reelWithStoreDistance]}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    expect(
      await screen.findByText("City Square Mart · 4.6 km"),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", {
        name: /Butter croissant.*City Square Mart.*4\.6 km/i,
      }),
    ).toBeInTheDocument();
  });

  it("drags the product strip without opening a product", async () => {
    const onNavigate = vi.fn();
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={1}
        onClose={vi.fn()}
        onNavigate={onNavigate}
      />,
    );

    const rail = await screen.findByRole("region", {
      name: "Products in this reel",
    });
    const productButton = screen.getByRole("button", {
      name: "Butter croissant, ₹75",
    });
    const productImage = productButton.querySelector("img");
    expect(productImage).not.toBeNull();
    expect(productImage).toHaveAttribute("draggable", "false");

    fireEvent.pointerDown(productImage!, {
      button: 0,
      clientX: 240,
      isPrimary: true,
      pointerId: 7,
      pointerType: "mouse",
    });
    fireEvent.pointerMove(productImage!, {
      clientX: 120,
      isPrimary: true,
      pointerId: 7,
      pointerType: "mouse",
    });
    expect(rail.scrollLeft).toBe(120);
    fireEvent.pointerUp(productImage!, {
      clientX: 120,
      isPrimary: true,
      pointerId: 7,
      pointerType: "mouse",
    });
    fireEvent.wheel(rail, { deltaX: 0, deltaY: 48 });
    expect(rail.scrollLeft).toBe(168);
    expect(
      screen.getByRole("dialog", { name: "Reel 2 of 10" }),
    ).toBeInTheDocument();
    fireEvent.click(
      screen.getByRole("button", { name: "Butter croissant, ₹75" }),
    );
    expect(onNavigate).not.toHaveBeenCalled();
  });

  it("omits optional CTA and unavailable product links", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={2}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 3 of 10" });
    expect(
      screen.queryByRole("button", { name: "Explore cafés" }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "See all" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", {
        name: "Coffee cookie, ₹59, Unavailable",
      }),
    ).toBeDisabled();
  });

  it("supports Reels with zero, one, or many products", async () => {
    const noProducts = render(
      <ReelViewer
        reels={HOME_REELS}
        selection={3}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );
    await screen.findByRole("dialog", { name: "Reel 4 of 10" });
    expect(
      screen.queryByRole("region", { name: "Products in this reel" }),
    ).not.toBeInTheDocument();
    noProducts.unmount();

    const oneProduct = render(
      <ReelViewer
        reels={HOME_REELS}
        selection={8}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );
    await screen.findByRole("dialog", { name: "Reel 9 of 10" });
    expect(
      within(
        screen.getByRole("region", { name: "Products in this reel" }),
      ).getAllByRole("button"),
    ).toHaveLength(1);
    oneProduct.unmount();

    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={5}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );
    await screen.findByRole("dialog", { name: "Reel 6 of 10" });
    expect(
      within(
        screen.getByRole("region", { name: "Products in this reel" }),
      ).getAllByRole("button"),
    ).toHaveLength(10);
  });

  it("navigates vertically and releases the previous active media", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    fireEvent.keyDown(window, { key: "ArrowDown" });

    await screen.findByRole("dialog", { name: "Reel 2 of 10" });
    const videos = document.querySelectorAll("video");
    expect(videos).toHaveLength(1);
    expect(videos[0]).toHaveAttribute("src", HOME_REELS[1].videoSrc);
    expect(HTMLMediaElement.prototype.pause).toHaveBeenCalled();
  });

  it("preloads and advances into an appended cursor page", async () => {
    const onLoadMore = vi.fn();
    const firstPage = [HOME_REELS[0]];
    const sharedProps = {
      selection: 0,
      onClose: vi.fn(),
      onNavigate: vi.fn(),
      onLoadMore,
    };
    const { rerender } = render(
      <ReelViewer {...sharedProps} hasMore reels={firstPage} />,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 1" });
    await waitFor(() => expect(onLoadMore).toHaveBeenCalledTimes(1));

    rerender(
      <ReelViewer {...sharedProps} hasMore isLoadingMore reels={firstPage} />,
    );
    fireEvent.keyDown(window, { key: "ArrowDown" });
    expect(screen.getByRole("status")).toHaveTextContent("Loading more Reels");

    rerender(
      <ReelViewer
        {...sharedProps}
        hasMore={false}
        reels={[HOME_REELS[0], HOME_REELS[1]]}
      />,
    );
    await screen.findByRole("dialog", { name: "Reel 2 of 2" });
    expect(document.querySelector("[data-reel-active-video]")).toHaveAttribute(
      "src",
      HOME_REELS[1].videoSrc,
    );
  });

  it("stays on the final Reel when no next cursor exists", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS.slice(0, 2)}
        selection={1}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 2 of 2" });
    fireEvent.keyDown(window, { key: "ArrowDown" });
    expect(
      screen.getByRole("dialog", { name: "Reel 2 of 2" }),
    ).toBeInTheDocument();
  });

  it("moves one Reel at a time from wheel gestures anywhere in the viewer", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    fireEvent.wheel(document.body, { deltaX: 0, deltaY: 90 });
    expect(
      screen.getByRole("dialog", { name: "Reel 1 of 10" }),
    ).toBeInTheDocument();
    fireEvent.wheel(document.body, { deltaX: 0, deltaY: 90 });
    await screen.findByRole("dialog", { name: "Reel 2 of 10" });

    fireEvent.wheel(document.querySelector("[data-reel-viewer]")!, {
      deltaX: 0,
      deltaY: 140,
    });
    expect(
      screen.getByRole("dialog", { name: "Reel 2 of 10" }),
    ).toBeInTheDocument();
  });

  it("supports an upward swipe and tap-to-pause", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    let viewer = document.querySelector("[data-reel-viewer]");
    expect(viewer).not.toBeNull();
    fireEvent.pointerDown(viewer!, { clientX: 220, clientY: 500 });
    fireEvent.pointerUp(viewer!, { clientX: 220, clientY: 420 });
    await screen.findByRole("dialog", { name: "Reel 2 of 10" });

    viewer = document.querySelector("[data-reel-viewer]");
    fireEvent.pointerDown(viewer!, { clientX: 220, clientY: 420 });
    fireEvent.pointerUp(viewer!, { clientX: 220, clientY: 420 });
    await waitFor(() =>
      expect(viewer).toHaveAttribute("data-reel-paused", "true"),
    );
  });

  it("likes with a familiar double-tap heart burst without pausing", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 1 of 10" });
    const viewer = document.querySelector("[data-reel-viewer]");
    expect(viewer).not.toBeNull();

    fireEvent.pointerDown(viewer!, { clientX: 220, clientY: 360 });
    fireEvent.pointerUp(viewer!, { clientX: 220, clientY: 360 });
    fireEvent.pointerDown(viewer!, { clientX: 222, clientY: 362 });
    fireEvent.pointerUp(viewer!, { clientX: 222, clientY: 362 });

    expect(screen.getByRole("button", { name: "Unlike reel" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
    expect(
      document.querySelector("[data-reel-like-burst]"),
    ).toBeInTheDocument();
    expect(viewer).not.toHaveAttribute("data-reel-paused");
  });

  it("shows only the Reel media while the viewer is held", async () => {
    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={1}
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );

    await screen.findByRole("dialog", { name: "Reel 2 of 10" });
    const viewer = document.querySelector("[data-reel-viewer]");
    const video = document.querySelector("[data-reel-active-video]");
    expect(viewer).not.toBeNull();
    expect(video).toBeInTheDocument();

    vi.useFakeTimers();
    try {
      fireEvent.pointerDown(viewer!, {
        button: 0,
        clientX: 220,
        clientY: 360,
        isPrimary: true,
        pointerId: 12,
      });
      act(() => vi.advanceTimersByTime(260));

      expect(viewer).toHaveAttribute("data-reel-clean-view", "true");
      expect(viewer).not.toHaveAttribute("data-reel-paused");
      expect(document.querySelector('[data-reel-chrome="header"]')).toHaveClass(
        "opacity-0",
      );
      expect(
        document.querySelector('[data-reel-chrome="actions"]'),
      ).toHaveClass("opacity-0");
      expect(
        document.querySelector('[data-reel-chrome="details"]'),
      ).toHaveClass("opacity-0");
      expect(
        document.querySelector("[data-reel-progress]"),
      ).not.toBeInTheDocument();
      expect(video).toBeInTheDocument();

      fireEvent.pointerUp(viewer!, {
        button: 0,
        clientX: 220,
        clientY: 360,
        isPrimary: true,
        pointerId: 12,
      });
      expect(viewer).not.toHaveAttribute("data-reel-clean-view");
      expect(document.querySelector('[data-reel-chrome="header"]')).toHaveClass(
        "opacity-100",
      );
    } finally {
      vi.useRealTimers();
    }
  });

  it("pauses while sharing and creates a direct Reel URL", async () => {
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

    render(
      <ReelViewer
        reels={HOME_REELS}
        selection={0}
        shareQueryParam="clip"
        onClose={vi.fn()}
        onNavigate={vi.fn()}
      />,
    );
    await screen.findByRole("dialog", { name: "Reel 1 of 10" });

    await userEvent.click(screen.getByRole("button", { name: "Share reel" }));
    const viewer = document.querySelector("[data-reel-viewer]");
    expect(viewer).toHaveAttribute("data-reel-sharing", "true");
    expect(viewer).toHaveAttribute("data-reel-paused", "true");
    expect(share).toHaveBeenCalledWith(
      expect.objectContaining({
        title: HOME_REELS[0].title,
        url: expect.stringContaining("clip=fresh-market-morning"),
      }),
    );

    finishSharing?.();
    await waitFor(() =>
      expect(viewer).not.toHaveAttribute("data-reel-sharing"),
    );
  });
});
