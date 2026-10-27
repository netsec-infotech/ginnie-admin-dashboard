import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";
import {
  HOME_STORIES,
  INITIAL_SEEN_STORY_IDS,
} from "@/components/Stories/storyData";

vi.mock("react-redux", () => ({ useSelector: (select: (state: { auth: { access_token: string } }) => unknown) => select({ auth: { access_token: "" } }) }));

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
          .replace("{{count}}", String(options?.count ?? ""))
          .replace("{{seen}}", String(options?.seen ?? ""))
          .replace("{{total}}", String(options?.total ?? "")),
    }),
  };
});

vi.mock("@/components/Stories/StoryViewer", () => ({
  default: ({ selection, onSeen, onNavigate, onClose }: any) => (
    <div data-testid="story-viewer-stub">
      {selection ? `${selection.groupIndex}:${selection.itemIndex}` : "closed"}
      <button onClick={() => onSeen("fresh-finds-1")}>Mark story seen</button>
      <button onClick={() => { onClose(); onNavigate("/categories/"); }}>
        Open story link
      </button>
    </div>
  ),
}));

import HomeStories from "@/views/homePage/HomeStories";

describe("HomeStories", () => {
  beforeEach(() => {
    window.localStorage.clear();
    routerQuery = {};
    push.mockReset();
  });

  const storyOrder = () =>
    Array.from(document.querySelectorAll("[data-story-group-id]")).map((item) =>
      item.getAttribute("data-story-group-id"),
    );

  it("renders seeded unseen, partial, and viewed source states", () => {
    const { container } = render(
      <HomeStories
        groups={HOME_STORIES}
        initialSeenStoryIds={INITIAL_SEEN_STORY_IDS}
      />,
    );

    const storiesSection = screen.getByTestId("home-stories");
    expect(storiesSection).toBeInTheDocument();
    expect(storiesSection).not.toHaveClass(
      "bg-content1/80",
      "border-y",
      "backdrop-blur-sm",
    );
    expect(storiesSection).toHaveClass("mb-8");
    expect(storiesSection).toHaveClass(
      "min-w-0",
      "max-w-full",
      "overflow-hidden",
    );
    expect(storiesSection).not.toHaveClass("px-1", "sm:px-6");
    expect(storiesSection).not.toHaveClass("w-page", "left-1/2");
    expect(storiesSection).not.toHaveClass("py-4", "sm:py-5");
    const storiesTitle = screen.getByRole("heading", { name: "Watch & Buy" });
    expect(
      screen.getByText("Fresh updates from stores you can shop"),
    ).toBeInTheDocument();
    expect(storiesTitle).toHaveClass(
      "text-sm",
      "sm:text-lg",
      "tracking-wide",
      "sm:tracking-wider",
    );
    expect(storiesTitle).not.toHaveClass("text-lg", "sm:text-xl");
    expect(screen.getByRole("link", { name: "see_all" })).toHaveAttribute(
      "href",
      "/play",
    );
    expect(
      screen.queryByRole("button", { name: "Scroll stories backward" }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Scroll stories forward" }),
    ).not.toBeInTheDocument();
    expect(screen.getByRole("list", { name: "Store stories" })).toHaveClass(
      "gap-3",
      "pt-2",
    );
    expect(screen.getByRole("list", { name: "Store stories" })).not.toHaveClass(
      "-mx-4",
      "px-4",
    );
    expect(container.querySelectorAll('[data-state="unseen"]').length).toBe(4);
    expect(container.querySelectorAll('[data-state="partial"]').length).toBe(1);
    expect(container.querySelectorAll('[data-state="seen"]').length).toBe(1);
    expect(
      screen.getByRole("button", { name: /Fresh Finds, 3 new/i }),
    ).toHaveClass("cursor-pointer");
    const storyRing = container.querySelector("[data-story-ring]");
    expect(storyRing).toHaveClass(
      "transform-gpu",
      "will-change-transform",
      "group-hover/story:scale-[1.035]",
      "group-active/story:scale-95",
    );
    expect(
      screen.getByRole("button", { name: /Daily Bake, 1\/3 viewed/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Care Close By, Viewed/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Fresh Finds, 3 new, Seller/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", {
        name: /Weekend Value, 2 new, Official/i,
      }),
    ).toBeInTheDocument();
    const sellerCover = container.querySelector(
      'img[src="/images/stories/seller-fresh-produce.webp"]',
    );
    expect(sellerCover).toBeInTheDocument();
    expect(sellerCover).toHaveAttribute("loading", "eager");
    expect(sellerCover).toHaveClass(
      "transform-gpu",
      "[backface-visibility:hidden]",
    );
    expect(sellerCover).not.toHaveClass("scale-[1.01]");
    const sellerCovers = container.querySelectorAll(
      'img[src^="/images/stories/seller-"]',
    );
    expect(sellerCovers).toHaveLength(5);
    sellerCovers.forEach((cover) =>
      expect(cover).toHaveAttribute("loading", "eager"),
    );
    expect(screen.queryByText("1/3 viewed")).not.toBeInTheDocument();
    expect(screen.queryByText("3 new")).not.toBeInTheDocument();
    expect(storyOrder()).toEqual([
      "fresh-finds",
      "local-blooms",
      "coffee-break",
      "weekend-value",
      "daily-bake",
      "care-close-by",
    ]);
  });

  it("opens a partial source at the first unseen story", () => {
    render(
      <HomeStories
        groups={HOME_STORIES}
        initialSeenStoryIds={INITIAL_SEEN_STORY_IDS}
      />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Daily Bake, 1\/3 viewed/i }),
    );

    expect(screen.getByTestId("story-viewer-stub")).toHaveTextContent("1:1");
  });

  it("opens the exact story identified by a shared URL", async () => {
    routerQuery = { story: "coffee-break-2" };
    render(
      <HomeStories
        groups={HOME_STORIES}
        initialSeenStoryIds={INITIAL_SEEN_STORY_IDS}
      />,
    );

    await waitFor(() =>
      expect(screen.getByTestId("story-viewer-stub")).toHaveTextContent("4:1"),
    );
  });

  it("preserves a normal story click when the belt is not dragged", () => {
    render(
      <HomeStories
        groups={HOME_STORIES}
        initialSeenStoryIds={INITIAL_SEEN_STORY_IDS}
      />,
    );
    const story = screen.getByRole("button", {
      name: /Daily Bake, 1\/3 viewed/i,
    });

    fireEvent.pointerDown(story, {
      button: 0,
      clientX: 180,
      pointerId: 1,
      pointerType: "mouse",
    });
    fireEvent.pointerUp(story, {
      clientX: 180,
      pointerId: 1,
      pointerType: "mouse",
    });
    fireEvent.click(story);

    expect(screen.getByTestId("story-viewer-stub")).toHaveTextContent("1:1");
  });

  it("supports mouse dragging without opening a story", () => {
    render(
      <HomeStories
        groups={HOME_STORIES}
        initialSeenStoryIds={INITIAL_SEEN_STORY_IDS}
      />,
    );
    const tray = screen.getByRole("list", { name: "Store stories" });
    Object.defineProperty(tray, "scrollLeft", { value: 40, writable: true });

    fireEvent.pointerDown(tray, {
      button: 0,
      clientX: 300,
      pointerId: 1,
      pointerType: "mouse",
    });
    fireEvent.pointerMove(tray, {
      clientX: 200,
      pointerId: 1,
      pointerType: "mouse",
    });

    expect(tray.scrollLeft).toBe(140);
    expect(tray).toHaveAttribute("data-dragging", "true");

    fireEvent.pointerUp(tray, {
      clientX: 200,
      pointerId: 1,
      pointerType: "mouse",
    });
    fireEvent.click(
      screen.getByRole("button", { name: /Fresh Finds, 3 new/i }),
    );

    expect(screen.getByTestId("story-viewer-stub")).toHaveTextContent("closed");
  });

  it("updates and persists viewed state", async () => {
    render(
      <HomeStories
        groups={HOME_STORIES}
        initialSeenStoryIds={INITIAL_SEEN_STORY_IDS}
      />,
    );
    fireEvent.click(screen.getByRole("button", { name: "Mark story seen" }));

    await waitFor(() =>
      expect(
        screen.getByRole("button", { name: /Fresh Finds, 1\/3 viewed/i }),
      ).toBeInTheDocument(),
    );

    expect(
      JSON.parse(
        window.localStorage.getItem(STORY_PROGRESS_STORAGE_KEY) ?? "{}",
      ),
    ).toMatchObject({ "fresh-finds-1": true });
    expect(storyOrder()).toEqual([
      "local-blooms",
      "coffee-break",
      "weekend-value",
      "fresh-finds",
      "daily-bake",
      "care-close-by",
    ]);
  });

  it("closes the viewer state and follows internal story links", async () => {
    render(
      <HomeStories
        groups={HOME_STORIES}
        initialSeenStoryIds={INITIAL_SEEN_STORY_IDS}
      />,
    );
    fireEvent.click(
      screen.getByRole("button", { name: /Fresh Finds, 3 new/i }),
    );
    expect(screen.getByTestId("story-viewer-stub")).toHaveTextContent("0:0");

    fireEvent.click(screen.getByRole("button", { name: "Open story link" }));
    await waitFor(() => expect(screen.getByTestId("story-viewer-stub")).toHaveTextContent("closed"));
    expect(push).toHaveBeenCalledWith("/categories/");
  });
});
