import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { StoryGroup } from "@/components/Stories/storyData";

const push = vi.fn();
const navigate = vi.fn();

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
          .replace("{{seen}}", String(options?.seen ?? ""))
          .replace("{{total}}", String(options?.total ?? "")),
    }),
  };
});

vi.mock("@/components/Stories/StoryViewer", () => ({
  default: ({
    groups,
    selection,
    onSeen,
    onNavigate,
    onClose,
  }: {
    groups: StoryGroup[];
    selection: { groupIndex: number; itemIndex: number } | null;
    onSeen: (storyId: string) => void;
    onNavigate: (href: string) => void;
    onClose: () => void;
  }) => (
    <div data-testid="custom-story-viewer">
      <span>{groups.map((group) => group.id).join(",")}</span>
      <span>
        {selection
          ? `${selection.groupIndex}:${selection.itemIndex}`
          : "closed"}
      </span>
      <button onClick={() => onSeen(groups[0].items[1].id)}>
        Mark custom story seen
      </button>
      <button
        onClick={() => {
          onClose();
          onNavigate("/custom-destination/");
        }}
      >
        Open custom story link
      </button>
    </div>
  ),
}));

import StoriesSection from "@/components/Stories/StoriesSection";
import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";

const customVisual = {
  background: "linear-gradient(#111827, #2563eb)",
  accent: "#bfdbfe",
  icon: "C",
  eyebrow: "CUSTOM",
  headline: "Custom updates",
  description: "Updates supplied by another page.",
};

const CUSTOM_STORIES: StoryGroup[] = [
  {
    id: "custom-shop",
    title: "Custom Shop",
    subtitle: "500 m · Open now",
    publisherType: "seller",
    visual: customVisual,
    items: [
      {
        id: "custom-story-1",
        mediaType: "image",
        durationMs: 5000,
        likeCount: 1,
        views24h: 10,
        visual: customVisual,
      },
      {
        id: "custom-story-2",
        mediaType: "image",
        durationMs: 5000,
        likeCount: 2,
        views24h: 20,
        visual: customVisual,
        href: "/custom-destination/",
      },
    ],
  },
];

const OTHER_STORY_GROUP: StoryGroup = {
  id: "other-shop",
  title: "Other Shop",
  subtitle: "1 km Â· Open now",
  publisherType: "seller",
  visual: customVisual,
  items: [
    {
      id: "other-story-1",
      mediaType: "image",
      durationMs: 5000,
      likeCount: 0,
      views24h: 5,
      visual: customVisual,
    },
  ],
};

describe("StoriesSection", () => {
  beforeEach(() => {
    window.localStorage.clear();
    navigate.mockReset();
    push.mockReset();
  });

  it("renders arbitrary story data and keeps its progress namespace isolated", async () => {
    render(
      <StoriesSection
        className="custom-placement"
        description="Updates selected for this page"
        groups={CUSTOM_STORIES}
        initialSeenStoryIds={["custom-story-1", "not-in-this-feed"]}
        onNavigate={navigate}
        sectionId="custom-stories"
        seeAllHref="/custom-feed/"
        seeAllLabel="Browse all updates"
        showSeeAll
        storageKey="custom-story-progress"
        title="Custom stories"
        trayLabel="Custom seller stories"
      />,
    );

    expect(screen.getByTestId("custom-stories")).toHaveClass(
      "custom-placement",
    );
    expect(
      screen.getByRole("heading", { name: "Custom stories" }),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Updates selected for this page"),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Browse all updates" }),
    ).toHaveAttribute("href", "/custom-feed");
    expect(
      screen.getByRole("list", { name: "Custom seller stories" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Fresh Finds")).not.toBeInTheDocument();
    expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent(
      "custom-shop",
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Custom Shop, 1\/2 viewed/i }),
    );
    expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent("0:1");

    fireEvent.click(
      screen.getByRole("button", { name: "Mark custom story seen" }),
    );

    await waitFor(() =>
      expect(
        screen.getByRole("button", { name: /Custom Shop, Viewed/i }),
      ).toBeInTheDocument(),
    );
    expect(
      JSON.parse(window.localStorage.getItem("custom-story-progress") ?? "{}"),
    ).toEqual({ "custom-story-1": true, "custom-story-2": true });
    expect(window.localStorage.getItem(STORY_PROGRESS_STORAGE_KEY)).toBeNull();

    fireEvent.click(
      screen.getByRole("button", { name: "Open custom story link" }),
    );
    expect(navigate).toHaveBeenCalledWith("/custom-destination/");
    expect(push).not.toHaveBeenCalled();
  });

  it("closes the Story viewer before navigation finishes", async () => {
    let finishNavigation: (() => void) | undefined;
    const onNavigate = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          finishNavigation = resolve;
        }),
    );

    render(
      <StoriesSection
        groups={CUSTOM_STORIES}
        onNavigate={onNavigate}
        sectionId="smooth-story-navigation"
      />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Custom Shop, 2 new/i }),
    );
    expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent("0:0");

    fireEvent.click(
      screen.getByRole("button", { name: "Open custom story link" }),
    );
    expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent(
      "closed",
    );

    finishNavigation?.();
    expect(onNavigate).toHaveBeenCalledWith("/custom-destination/");
  });

  it("supports caller-selected Stories without assuming a See All link", async () => {
    render(
      <StoriesSection
        groups={CUSTOM_STORIES}
        sectionId="embedded-stories"
        sharedStoryId="custom-story-2"
        storageKey="embedded-story-progress"
      />,
    );

    await waitFor(() =>
      expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent(
        "0:1",
      ),
    );
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("lazy-loads a profile's Story media before opening it", async () => {
    const resolveGroup = vi.fn(async () => CUSTOM_STORIES[0]);
    render(
      <StoriesSection
        groups={[
          {
            ...CUSTOM_STORIES[0],
            items: [],
            itemCount: 2,
            unseenItemCount: 2,
          },
        ]}
        resolveGroup={resolveGroup}
        sectionId="lazy-stories"
      />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Custom Shop, 2 new/i }),
    );

    await waitFor(() =>
      expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent(
        "0:0",
      ),
    );
    expect(resolveGroup).toHaveBeenCalledTimes(1);
  });

  it("opens a lazy profile at its first persisted unseen Story", async () => {
    const resolvedGroup: StoryGroup = {
      ...CUSTOM_STORIES[0],
      items: [
        ...CUSTOM_STORIES[0].items,
        {
          ...CUSTOM_STORIES[0].items[0],
          id: "custom-story-3",
        },
        {
          ...CUSTOM_STORIES[0].items[0],
          id: "custom-story-4",
        },
      ],
    };
    const resolveGroup = vi.fn(async () => resolvedGroup);
    render(
      <StoriesSection
        groups={[
          {
            ...resolvedGroup,
            items: [],
            itemCount: 4,
            unseenItemCount: 1,
          },
        ]}
        resolveGroup={resolveGroup}
        sectionId="lazy-persisted-stories"
        seenStoryIds={[
          "custom-story-1",
          "custom-story-2",
          "custom-story-3",
        ]}
      />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Custom Shop, 3\/4 viewed/i }),
    );

    await waitFor(() =>
      expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent(
        "0:3",
      ),
    );
  });

  it("supports caller-owned viewed state without writing local storage", async () => {
    const onStorySeen = vi.fn();
    const { rerender } = render(
      <StoriesSection
        groups={CUSTOM_STORIES}
        onStorySeen={onStorySeen}
        sectionId="controlled-stories"
        seenStoryIds={["custom-story-1"]}
      />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Custom Shop, 1\/2 viewed/i }),
    );
    fireEvent.click(
      screen.getByRole("button", { name: "Mark custom story seen" }),
    );

    expect(onStorySeen).toHaveBeenCalledWith("custom-story-2");
    expect(window.localStorage).toHaveLength(0);
    expect(
      screen.getByRole("button", { name: /Custom Shop, 1\/2 viewed/i }),
    ).toBeInTheDocument();

    rerender(
      <StoriesSection
        groups={CUSTOM_STORIES}
        onStorySeen={onStorySeen}
        sectionId="controlled-stories"
        seenStoryIds={["custom-story-1", "custom-story-2"]}
      />,
    );
    await waitFor(() =>
      expect(
        screen.getByRole("button", { name: /Custom Shop, Viewed/i }),
      ).toBeInTheDocument(),
    );
  });

  it("keeps the colorful tray ring proportional to the unseen ratio", () => {
    const thirteenStories: StoryGroup = {
      ...CUSTOM_STORIES[0],
      items: Array.from({ length: 13 }, (_, index) => ({
        ...CUSTOM_STORIES[0].items[0],
        id: `ratio-story-${index + 1}`,
      })),
    };

    const { container } = render(
      <StoriesSection
        groups={[thirteenStories]}
        sectionId="ratio-stories"
        seenStoryIds={[
          "ratio-story-1",
          "ratio-story-2",
          "ratio-story-3",
        ]}
      />,
    );

    expect(
      screen.getByRole("button", { name: /Custom Shop, 3\/13 viewed/i }),
    ).toBeInTheDocument();
    const ring = container.querySelector("[data-story-ring]");
    expect(ring).toHaveAttribute("data-story-progress-percent", "76.92");
    expect(ring).toHaveAttribute("data-story-viewed-percent", "23.08");
    expect(ring).toHaveStyle({
      background:
        "conic-gradient(#f97316 0 76.92%, #d4d4d8 76.92% 100%)",
    });
  });

  it("keeps the selected Story by ID when groups and items are reordered", async () => {
    const { rerender } = render(
      <StoriesSection
        groups={CUSTOM_STORIES}
        initialSeenStoryIds={["custom-story-1"]}
        sectionId="live-stories"
      />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Custom Shop, 1\/2 viewed/i }),
    );
    expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent("0:1");

    rerender(
      <StoriesSection
        groups={[
          OTHER_STORY_GROUP,
          {
            ...CUSTOM_STORIES[0],
            items: [CUSTOM_STORIES[0].items[1], CUSTOM_STORIES[0].items[0]],
          },
        ]}
        initialSeenStoryIds={["custom-story-1"]}
        sectionId="live-stories"
      />,
    );
    expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent("1:0");

    rerender(
      <StoriesSection groups={[OTHER_STORY_GROUP]} sectionId="live-stories" />,
    );
    await waitFor(() =>
      expect(screen.getByTestId("custom-story-viewer")).toHaveTextContent(
        "closed",
      ),
    );
  });

  it("generates unique accessible IDs for multiple unnamed sections", () => {
    const { container } = render(
      <>
        <StoriesSection groups={CUSTOM_STORIES} />
        <StoriesSection groups={CUSTOM_STORIES} />
      </>,
    );

    const sections = Array.from(
      container.querySelectorAll<HTMLElement>("section[id^='stories-']"),
    );
    expect(sections).toHaveLength(2);
    expect(sections[0].id).not.toBe(sections[1].id);
    for (const section of sections) {
      expect(section).toHaveAttribute("aria-labelledby", `${section.id}-title`);
    }
  });
});
