import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const push = vi.fn();
let routerQuery: Record<string, string> = {};

vi.mock("next/router", () => ({
  useRouter: () => ({ push, query: routerQuery }),
}));

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string, options?: Record<string, unknown>) =>
        String(options?.defaultValue ?? key)
          .replace("{{title}}", String(options?.title ?? ""))
          .replace("{{publisher}}", String(options?.publisher ?? "")),
    }),
  };
});

vi.mock("@/components/Reels/ReelViewer", () => ({
  default: ({ selection, onNavigate }: any) => (
    <div data-testid="reel-viewer-stub">
      {selection === null ? "closed" : String(selection)}
      <button onClick={() => onNavigate("/stores/")}>Open reel link</button>
    </div>
  ),
}));

import HomeReels from "@/views/homePage/HomeReels";
import { HOME_REELS } from "@/components/Reels/reelData";

describe("HomeReels", () => {
  beforeEach(() => {
    routerQuery = {};
    push.mockReset();
  });

  it("renders ten poster-only Reel cards below the shoppable videos heading", () => {
    const { container } = render(<HomeReels reels={HOME_REELS} />);

    expect(screen.getByTestId("home-reels")).toBeInTheDocument();
    expect(screen.getByTestId("home-reels")).toHaveClass(
      "min-w-0",
      "max-w-full",
      "overflow-hidden",
    );
    expect(screen.getByTestId("home-reels")).not.toHaveClass("px-1", "sm:px-6");
    expect(screen.getByTestId("home-reels")).not.toHaveClass("w-page");
    expect(
      screen.getByRole("heading", { name: "Explore shoppable videos" }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Tap a video to watch, shop, and keep scrolling through the feed.",
      ),
    ).toBeInTheDocument();
    expect(screen.queryByText("Reels")).not.toBeInTheDocument();
    expect(screen.getByRole("link", { name: "see_all" })).toHaveAttribute(
      "href",
      "/play",
    );
    expect(container.querySelectorAll("[data-reel-card-id]")).toHaveLength(10);
    expect(container.querySelectorAll("video")).toHaveLength(0);
    expect(container.querySelectorAll("img")).toHaveLength(10);
    expect(screen.queryByLabelText("Official")).not.toBeInTheDocument();
    expect(
      screen.getByRole("list", { name: "Nearby store reels" }),
    ).toHaveClass("touch-auto", "gap-3", "pt-4", "pb-6");
    expect(
      screen.getByRole("list", { name: "Nearby store reels" }),
    ).not.toHaveClass("touch-pan-x", "snap-x", "snap-proximity");
    expect(container.querySelector("[data-reel-card-id]")?.parentElement).toHaveClass(
      "w-[calc((100%-0.75rem)/2)]",
    );
    expect(
      screen.getByRole("button", {
        name: "Open Market-fresh picks this morning by Fresh Finds",
      }),
    ).toHaveClass("cursor-pointer");
  });

  it("uses a varied set of byte-range demo videos", () => {
    const sources = new Set(HOME_REELS.map((reel) => reel.videoSrc));

    expect(sources.size).toBeGreaterThanOrEqual(6);
    expect(
      [...sources].every((source) =>
        source.startsWith("https://res.cloudinary.com/demo/video/upload/"),
      ),
    ).toBe(true);
  });

  it("opens a selected Reel and follows its internal destination", async () => {
    render(<HomeReels reels={HOME_REELS} />);

    fireEvent.click(
      screen.getByRole("button", {
        name: "Open Your afternoon reset by Coffee Break",
      }),
    );
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("2");

    fireEvent.click(screen.getByRole("button", { name: "Open reel link" }));
    await waitFor(() => expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("closed"));
    expect(push).toHaveBeenCalledWith("/stores/");
  });

  it("opens the exact Reel identified by a shared URL", async () => {
    routerQuery = { reel: "flowers-made-local" };
    render(<HomeReels reels={HOME_REELS} />);

    await waitFor(() =>
      expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("3"),
    );
  });

  it.each([
    { startX: 280, endX: 180, scrollLeft: 130 },
    { startX: 180, endX: 200, scrollLeft: 10 },
  ])("drags from a Reel poster to $endX without opening it", ({ startX, endX, scrollLeft }) => {
    render(<HomeReels reels={HOME_REELS} />);
    const rail = screen.getByRole("list", { name: "Nearby store reels" });
    const card = screen.getByRole("button", {
      name: "Open Market-fresh picks this morning by Fresh Finds",
    });
    const poster = card.querySelector("img")!;
    expect(poster).toHaveAttribute("draggable", "false");
    Object.defineProperty(rail, "scrollLeft", { value: 30, writable: true });

    fireEvent.pointerDown(poster, {
      button: 0,
      clientX: startX,
      pointerId: 4,
      pointerType: "mouse",
    });
    fireEvent.pointerMove(poster, {
      clientX: endX,
      pointerId: 4,
      pointerType: "mouse",
    });
    expect(rail.scrollLeft).toBe(scrollLeft);

    fireEvent.pointerUp(rail, {
      clientX: endX,
      pointerId: 4,
      pointerType: "mouse",
    });
    fireEvent.click(
      screen.getByRole("button", {
        name: "Open Market-fresh picks this morning by Fresh Finds",
      }),
    );
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("closed");
  });

  it("prevents poster image dragging from interrupting a card drag", () => {
    render(<HomeReels reels={HOME_REELS} />);
    const rail = screen.getByRole("list", { name: "Nearby store reels" });
    const card = screen.getByRole("button", {
      name: "Open Market-fresh picks this morning by Fresh Finds",
    });
    const poster = card.querySelector("img")!;
    expect(poster).toHaveAttribute("draggable", "false");
    expect(fireEvent.dragStart(poster)).toBe(false);

    Object.defineProperty(rail, "scrollLeft", { value: 150, writable: true });
    fireEvent.pointerDown(poster, {
      button: 0,
      clientX: 280,
      pointerId: 4,
      pointerType: "mouse",
    });
    fireEvent.pointerMove(card, {
      clientX: 180,
      pointerId: 4,
      pointerType: "mouse",
    });
    expect(rail.scrollLeft).toBe(250);
    fireEvent.pointerMove(card, {
      clientX: 320,
      pointerId: 4,
      pointerType: "mouse",
    });
    expect(rail.scrollLeft).toBe(110);
    fireEvent.pointerUp(card, {
      pointerId: 4,
      pointerType: "mouse",
    });
    fireEvent.click(card);
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("closed");
    fireEvent.click(card);
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("0");
  });
});
