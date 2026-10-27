import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const push = vi.fn();
let routerQuery: Record<string, string> = {};

vi.mock("next/router", () => ({
  useRouter: () => ({ push, query: routerQuery }),
}));

vi.mock("react-redux", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-redux")>();
  return {
    ...actual,
    useSelector: (selector: (state: unknown) => unknown) =>
      selector({
        auth: { isLoggedIn: false, access_token: "", user: null },
      }),
  };
});

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string, options?: Record<string, unknown>) =>
        String(options?.defaultValue ?? key)
          .replace("{{count}}", String(options?.count ?? ""))
          .replace("{{title}}", String(options?.title ?? ""))
          .replace("{{publisher}}", String(options?.publisher ?? ""))
          .replace("{{seen}}", String(options?.seen ?? ""))
          .replace("{{total}}", String(options?.total ?? "")),
    }),
  };
});

vi.mock("@/components/Reels/ReelViewer", () => ({
  default: ({ selection }: { selection: number | null }) => (
    <div data-testid="reel-viewer-stub">
      {selection === null ? "closed" : String(selection)}
    </div>
  ),
}));

vi.mock("@/components/Stories/StoryViewer", () => ({
  default: ({ selection }: { selection: unknown }) => (
    <div data-testid="story-viewer-stub">
      {selection === null ? "closed" : "open"}
    </div>
  ),
}));

import PlayPageView from "@/views/PlayPageView";
import { HOME_REELS } from "@/components/Reels/reelData";
import { HOME_STORIES } from "@/components/Stories/storyData";

describe("PlayPageView", () => {
  beforeEach(() => {
    window.localStorage.clear();
    routerQuery = {};
    push.mockReset();
  });

  it("renders Stories before a responsive, poster-only Reel grid", () => {
    const { container } = render(
      <PlayPageView reels={HOME_REELS} storyGroups={HOME_STORIES} />,
    );

    expect(screen.getByTestId("play-page")).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { level: 1, name: "Play" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("heading", { level: 1, name: "Play" })).toHaveClass(
      "xl:text-xl",
      "font-bold",
      "text-foreground",
    );
    expect(
      screen.getByRole("navigation", { name: "Breadcrumbs" }),
    ).toBeInTheDocument();
    expect(container.querySelector("a a")).not.toBeInTheDocument();
    expect(
      screen.getAllByRole("heading").map((heading) => heading.textContent),
    ).toEqual([
      "Play",
      expect.stringContaining("Watch & Buy"),
      "Explore shoppable videos",
    ]);
    expect(screen.getByTestId("play-stories")).toBeInTheDocument();
    expect(screen.getByTestId("play-reels")).toBeInTheDocument();
    expect(screen.getByTestId("play-stories")).not.toHaveClass(
      "px-1",
      "sm:px-6",
    );
    expect(screen.getByTestId("play-reels")).not.toHaveClass("px-1", "sm:px-6");
    expect(
      screen.queryByRole("link", { name: "see_all" }),
    ).not.toBeInTheDocument();

    const grid = screen.getByRole("list", { name: "Nearby store reels" });
    expect(grid).toHaveAttribute("data-layout", "grid");
    expect(grid).toHaveClass(
      "grid",
      "grid-cols-2",
      "md:grid-cols-3",
      "min-[1100px]:grid-cols-4",
      "lg:grid-cols-5",
    );
    expect(grid).not.toHaveClass("cursor-grab", "snap-x", "overflow-x-auto");
    expect(container.querySelectorAll("[data-reel-card-id]")).toHaveLength(10);
    expect(container.querySelectorAll("video")).toHaveLength(0);
    expect(container.querySelectorAll("[data-reel-featured]")).toHaveLength(0);
    expect(container.querySelectorAll(".col-span-2")).toHaveLength(0);
    expect(container.querySelectorAll("[data-reel-media-frame]")).toHaveLength(
      10,
    );
    for (const frame of container.querySelectorAll("[data-reel-media-frame]")) {
      expect(frame).toHaveClass("aspect-9/16");
      expect(frame).not.toHaveClass("shadow-sm", "group-hover/reel:shadow-xl");
    }
  });

  it("shows local discovery signals and opens the selected Reel", () => {
    render(<PlayPageView reels={HOME_REELS} storyGroups={HOME_STORIES} />);

    expect(
      screen.getAllByLabelText("Linked products: 4").length,
    ).toBeGreaterThan(0);
    expect(screen.getAllByText("Fresh Finds").length).toBeGreaterThan(0);
    expect(screen.getByText("1.2 km · 18–25 min")).toBeInTheDocument();

    fireEvent.click(
      screen.getByRole("button", {
        name: "Open Your afternoon reset by Coffee Break",
      }),
    );
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("2");
  });

  it("opens a Reel from a shared Play URL", async () => {
    routerQuery = { reel: "flowers-made-local" };
    render(<PlayPageView reels={HOME_REELS} storyGroups={HOME_STORIES} />);

    await waitFor(() =>
      expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("3"),
    );
  });

  it("shows distinct empty states without an end-of-feed message", () => {
    render(<PlayPageView reels={[]} storyGroups={[]} />);

    expect(screen.getByTestId("play-stories-empty")).toHaveClass(
      "py-20",
      "gap-4",
    );
    expect(screen.getByTestId("play-stories-empty")).not.toHaveClass(
      "border",
      "border-dashed",
      "bg-default-50/70",
    );
    expect(screen.getByText("No Stories yet")).toBeInTheDocument();
    expect(
      screen.getByText(
        "Nearby sellers haven't posted any active Stories. Check back soon for fresh updates.",
      ),
    ).toBeInTheDocument();

    expect(screen.getByTestId("play-reels-empty")).toBeInTheDocument();
    expect(screen.getByText("No Reels nearby")).toBeInTheDocument();
    expect(
      screen.getByText(
        "There are no nearby Reels to watch right now. New seller videos will appear here.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.queryByText("You're all caught up for now."),
    ).not.toBeInTheDocument();
  });
});
