import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const push = vi.fn();

vi.mock("next/router", () => ({
  useRouter: () => ({ push, query: {} }),
}));

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string, options?: Record<string, unknown>) =>
        String(options?.defaultValue ?? key)
          .replace("{{count}}", String(options?.count ?? ""))
          .replace("{{title}}", String(options?.title ?? ""))
          .replace("{{publisher}}", String(options?.publisher ?? "")),
    }),
  };
});

vi.mock("@/components/Reels/ReelViewer", () => ({
  default: ({ selection, onNavigate, onView }: any) => (
    <div data-testid="reel-viewer-stub">
      {selection === null ? "closed" : String(selection)}
      <button onClick={() => onNavigate("/custom-destination/")}>
        Open destination
      </button>
      <button onClick={() => onView("custom-first")}>Count view</button>
    </div>
  ),
}));

import ReelsSection from "@/components/Reels/ReelsSection";
import { HOME_REELS } from "@/components/Reels/reelData";
import {
  LOCAL_REEL_VIEW_INCREMENTS_STORAGE_KEY,
  SESSION_VIEWED_REELS_STORAGE_KEY,
} from "@/components/Reels/useLocalReelViews";

const CUSTOM_REELS = [
  {
    ...HOME_REELS[0],
    id: "custom-first",
    title: "Custom first Reel",
    publisher: "Custom Seller",
  },
  {
    ...HOME_REELS[1],
    id: "custom-second",
    title: "Custom second Reel",
    publisher: "Another Seller",
  },
];

describe("ReelsSection", () => {
  beforeEach(() => {
    push.mockReset();
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it("renders caller-provided data and presentation independently of Home", () => {
    const { container } = render(
      <ReelsSection
        badgeLabel="Videos"
        description="A reusable Reel collection"
        inset
        railLabel="Category seller reels"
        reels={CUSTOM_REELS}
        sectionId="category-reels"
        seeAllHref="/category/videos/"
        seeAllLabel="Browse all videos"
        showSeeAll
        title="Category videos"
      />,
    );

    expect(screen.getByTestId("category-reels")).toHaveClass("px-1", "sm:px-6");
    expect(
      screen.getByRole("heading", { name: "Category videos" }),
    ).toBeInTheDocument();
    expect(screen.getByText("A reusable Reel collection")).toBeInTheDocument();
    expect(screen.getByText("Videos")).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Browse all videos" }),
    ).toHaveAttribute("href", "/category/videos");
    expect(
      screen.getByRole("list", { name: "Category seller reels" }),
    ).toBeInTheDocument();
    expect(container.querySelectorAll("[data-reel-card-id]")).toHaveLength(2);
    expect(container.querySelector("[data-reels-section-icon]")).toHaveClass(
      "from-primary-500",
      "to-primary-400",
    );
    expect(container.querySelector("[data-reels-section-icon]")).not.toHaveClass(
      "from-fuchsia-500",
    );
    expect(screen.getByText("Custom first Reel")).toBeInTheDocument();
    expect(screen.queryByText(HOME_REELS[2].title)).not.toBeInTheDocument();
    expect(container.querySelectorAll("video")).toHaveLength(0);
  });

  it("applies builder card flags without changing the accessible reel title", () => {
    const { container } = render(
      <ReelsSection
        reels={[{ ...CUSTOM_REELS[0], likes: 3 }]}
        sectionId="configured-reels"
        showLikes
        showTitle={false}
        itemGap={12}
      />,
    );

    expect(screen.queryByText("Custom first Reel")).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", {
        name: "Open Custom first Reel by Custom Seller, reels.likesCount",
      }),
    ).toBeInTheDocument();
    expect(container.querySelector("[data-layout='rail']")).toHaveStyle({
      gap: "12px",
    });
  });

  it("supports an explicit shared Reel and caller-owned navigation", async () => {
    const onNavigate = vi.fn();

    render(
      <ReelsSection
        onNavigate={onNavigate}
        reels={CUSTOM_REELS}
        sectionId="shared-reels"
        sharedReelId="custom-second"
      />,
    );

    await waitFor(() =>
      expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("1"),
    );

    fireEvent.click(screen.getByRole("button", { name: "Open destination" }));
    expect(onNavigate).toHaveBeenCalledWith("/custom-destination/");
    expect(push).not.toHaveBeenCalled();
  });

  it("keeps the Reel viewer mounted until navigation finishes", async () => {
    let finishNavigation: (() => void) | undefined;
    const onNavigate = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          finishNavigation = resolve;
        }),
    );

    render(
      <ReelsSection
        onNavigate={onNavigate}
        reels={CUSTOM_REELS}
        sectionId="smooth-reel-navigation"
        sharedReelId="custom-second"
      />,
    );

    await waitFor(() =>
      expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("1"),
    );
    fireEvent.click(screen.getByRole("button", { name: "Open destination" }));
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("1");

    finishNavigation?.();
    await waitFor(() =>
      expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent(
        "closed",
      ),
    );
  });

  it("updates and persists a qualified local view only once per session", async () => {
    const reels = [{ ...CUSTOM_REELS[0], views: 9 }];
    render(<ReelsSection reels={reels} sectionId="view-count-reels" />);

    expect(screen.getByText("9")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Count view" }));
    await waitFor(() => expect(screen.getByText("10")).toBeInTheDocument());

    fireEvent.click(screen.getByRole("button", { name: "Count view" }));
    expect(screen.getByText("10")).toBeInTheDocument();
    expect(
      window.localStorage.getItem(
        LOCAL_REEL_VIEW_INCREMENTS_STORAGE_KEY,
      ),
    ).toContain('"custom-first":1');
    expect(
      window.sessionStorage.getItem(SESSION_VIEWED_REELS_STORAGE_KEY),
    ).toContain("custom-first");
  });

  it("keeps the selected Reel by ID when caller data is reordered", async () => {
    const { rerender } = render(
      <ReelsSection reels={CUSTOM_REELS} sectionId="live-reels" />,
    );

    fireEvent.click(
      screen.getByRole("button", {
        name: "Open Custom first Reel by Custom Seller",
      }),
    );
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("0");

    rerender(
      <ReelsSection
        reels={[CUSTOM_REELS[1], CUSTOM_REELS[0]]}
        sectionId="live-reels"
      />,
    );
    expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent("1");

    rerender(<ReelsSection reels={[CUSTOM_REELS[1]]} sectionId="live-reels" />);
    await waitFor(() =>
      expect(screen.getByTestId("reel-viewer-stub")).toHaveTextContent(
        "closed",
      ),
    );
  });

  it("generates unique accessible IDs for multiple unnamed sections", () => {
    const { container } = render(
      <>
        <ReelsSection reels={[CUSTOM_REELS[0]]} />
        <ReelsSection reels={[CUSTOM_REELS[1]]} />
      </>,
    );

    const sections = Array.from(
      container.querySelectorAll<HTMLElement>("section[id^='reels-']"),
    );
    expect(sections).toHaveLength(2);
    expect(sections[0].id).not.toBe(sections[1].id);
    for (const section of sections) {
      expect(section).toHaveAttribute("aria-labelledby", `${section.id}-title`);
    }
  });
});
