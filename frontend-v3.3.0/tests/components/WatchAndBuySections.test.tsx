import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { SWRConfig } from "swr";
import { beforeEach, describe, expect, it, vi } from "vitest";

const apiMocks = vi.hoisted(() => ({
  getWatchAndBuyConfig: vi.fn(),
  getWatchAndBuyReels: vi.fn(),
  getWatchAndBuyStoryTray: vi.fn(),
  getWatchAndBuyProfileStories: vi.fn(),
  markWatchAndBuyStoriesSeen: vi.fn(),
  updateWatchAndBuyReelLikes: vi.fn(),
}));
const authState = vi.hoisted(() => ({
  accessToken: "test-customer-token" as string | null,
}));

vi.mock("@/routes/api", () => apiMocks);
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    currencySymbol: "₹",
    defaultLocation: { lat: 23.242, lng: 69.6669 },
  }),
}));
vi.mock("@/lib/cookies", () => ({
  getCookie: (name: string) =>
    name === "access_token"
      ? authState.accessToken
      : name === "userLocation"
        ? { lat: 23.242, lng: 69.6669 }
        : null,
}));
vi.mock("react-redux", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-redux")>();
  return {
    ...actual,
    useSelector: (selector: (state: unknown) => unknown) =>
      selector({
        auth: {
          isLoggedIn: Boolean(authState.accessToken),
          access_token: authState.accessToken ?? "",
          user: null,
        },
      }),
  };
});
vi.mock("next/router", () => ({
  useRouter: () => ({ push: vi.fn(), query: {} }),
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
          .replace("{{publisher}}", String(options?.publisher ?? ""))
          .replace("{{seen}}", String(options?.seen ?? ""))
          .replace("{{total}}", String(options?.total ?? "")),
    }),
  };
});
vi.mock("@/components/Stories/StoryViewer", () => ({
  default: ({ groups, selection, onClose, onGroupComplete, onSeen }: any) => {
    const group = selection ? groups[selection.groupIndex] : null;
    const item = selection
      ? groups[selection.groupIndex]?.items[selection.itemIndex]
      : null;
    return (
      <div data-testid="live-story-viewer">
        {item?.id ?? "closed"}
        {item && <button onClick={() => onSeen(item.id)}>Record seen</button>}
        {group && (
          <>
            <button
              onClick={() =>
                group.items.forEach((story: { id: string }) => onSeen(story.id))
              }
            >
              Record merchant Stories
            </button>
            <button onClick={() => onGroupComplete?.(group)}>
              Complete merchant Stories
            </button>
            <button onClick={() => onClose?.()}>Close Story viewer</button>
          </>
        )}
      </div>
    );
  },
}));
vi.mock("@/components/Reels/ReelViewer", () => ({
  default: ({
    reels,
    selection,
    hasMore,
    isLoadingMore,
    onLikeChange,
    onLoadMore,
  }: any) => {
    const reel = selection === null ? null : reels[selection];
    return (
      <div data-testid="live-reel-viewer">
        {reel?.id ?? "closed"}
        {reel && (
          <>
            <button onClick={() => onLikeChange(reel, true)}>Sync like</button>
            {hasMore && (
              <button disabled={isLoadingMore} onClick={onLoadMore}>
                Load next Reel in viewer
              </button>
            )}
          </>
        )}
      </div>
    );
  },
}));
vi.mock("@/components/Functional/LoadMoreSentinel", () => ({
  default: ({
    disabled,
    onVisible,
  }: {
    disabled?: boolean;
    onVisible: () => void;
  }) => (
    <button disabled={disabled} onClick={onVisible}>
      Load next Reel page
    </button>
  ),
}));

import {
  WatchAndBuyReelsSection,
  WatchAndBuyStoriesSection,
} from "@/components/WatchAndBuy";
import { toStoryTrayGroup } from "@/components/WatchAndBuy/adapters";
import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";
import {
  getPendingStorySeenStorageKey,
  getSeenStoryGroupsStorageKey,
  queuePendingStorySeen,
} from "@/components/WatchAndBuy/storySeenPersistence";

const renderWithFreshSWR = (ui: React.ReactNode) =>
  render(
    <SWRConfig value={{ dedupingInterval: 0, provider: () => new Map() }}>
      {ui}
    </SWRConfig>,
  );

describe("Watch & Buy data controllers", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    authState.accessToken = "test-customer-token";
    window.localStorage.clear();
    apiMocks.getWatchAndBuyConfig.mockResolvedValue({
      success: true,
      message: "ok",
      data: { enabled: true, statusesEnabled: true },
    });
    apiMocks.markWatchAndBuyStoriesSeen.mockResolvedValue({
      success: true,
      message: "seen",
      data: [{ status_id: 7, seen_at: "2026-08-29T09:44:12Z" }],
    });
    apiMocks.updateWatchAndBuyReelLikes.mockResolvedValue({
      success: true,
      message: "liked",
      data: [{ reel_id: 42, liked: true }],
    });
  });

  it("opens a story publisher supplied by the home layout", async () => {
    const profile = {
      id: 1,
      type: "platform" as const,
      username: "hyperlocal",
      status: "active",
      photo_url: "https://cdn.example.com/profile.webp",
      has_active_status: true,
      has_unseen_status: true,
    };
    apiMocks.getWatchAndBuyProfileStories.mockResolvedValue({
      success: true,
      data: {
        profile,
        items: [
          {
            id: 7,
            uuid: "layout-story-7",
            source: "admin_direct",
            content_type: "image",
            media_url: "https://cdn.example.com/story.webp",
            created_at: "2026-09-29T08:00:00Z",
            expires_at: "2026-10-01T08:00:00Z",
          },
        ],
        pagination: {
          has_more_pages: false,
          next_cursor: null,
          previous_cursor: null,
          per_page: 50,
          total: 1,
        },
      },
    });

    renderWithFreshSWR(
      <WatchAndBuyStoriesSection
        groups={[toStoryTrayGroup({ profile, status_count: 1 })]}
        sectionId="layout-stories"
        ringColor="#E1306C"
        showProfileName={false}
        itemGap={12}
      />,
    );

    expect(apiMocks.getWatchAndBuyStoryTray).not.toHaveBeenCalled();
    expect(screen.queryByText("hyperlocal")).not.toBeInTheDocument();
    expect(screen.getByRole("list", { name: /stor/i })).toHaveStyle({
      gap: "12px",
    });
    expect(document.querySelector("[data-story-ring]")).toHaveStyle({
      backgroundColor: "#E1306C",
    });
    fireEvent.click(screen.getByRole("button", { name: /hyperlocal, 1 new/i }));
    await waitFor(() =>
      expect(screen.getByTestId("live-story-viewer")).toHaveTextContent(
        "layout-story-7",
      ),
    );
    expect(apiMocks.getWatchAndBuyProfileStories).toHaveBeenCalledWith(
      "hyperlocal",
      { per_page: 50 },
    );
  });

  it("renders portrait story cards with image previews and a static video fallback", () => {
    const profile = {
      id: 1,
      type: "platform" as const,
      username: "hyperlocal",
      status: "active",
      photo_url: "https://cdn.example.com/profile.webp",
      has_active_status: true,
      has_unseen_status: true,
    };
    const imageGroup = toStoryTrayGroup({ profile, status_count: 1 });
    const videoGroup = {
      ...imageGroup,
      id: "video-publisher",
      title: "Video publisher",
      items: [
        {
          id: "video-story",
          mediaType: "video" as const,
          durationMs: 5000,
          visual: imageGroup.visual,
          videoSrc: "https://cdn.example.com/story.mp4",
        },
      ],
    };

    renderWithFreshSWR(
      <WatchAndBuyStoriesSection
        groups={[
          {
            ...imageGroup,
            items: [
              {
                id: "image-story",
                mediaType: "image",
                durationMs: 5000,
                visual: imageGroup.visual,
                imageSrc: "https://cdn.example.com/story.webp",
              },
            ],
          },
          videoGroup,
        ]}
        sectionId="portrait-stories"
        trayStyle="story_card"
      />,
    );

    const imageCard = document.querySelector(
      '[data-story-group-id="hyperlocal"]',
    );
    const videoCard = document.querySelector(
      '[data-story-group-id="video-publisher"]',
    );
    expect(imageCard).toHaveAttribute("data-story-style", "story_card");
    expect(imageCard?.querySelector("[data-story-card-scrim]")).toHaveClass(
      "z-20",
      "from-black/75",
    );
    const previewImage = imageCard?.querySelector(
      "img[src='https://cdn.example.com/story.webp']",
    );
    expect(previewImage).toHaveClass("saturate-50");
    expect(previewImage).toHaveStyle({ opacity: "0.42" });
    expect(
      videoCard?.querySelectorAll(
        "img[src='https://cdn.example.com/profile.webp']",
      ),
    ).toHaveLength(2);
    expect(
      videoCard?.querySelectorAll(
        "img[src='https://cdn.example.com/profile.webp']",
      )[1],
    ).toHaveAttribute("loading", "eager");
    const fallbackImage = videoCard?.querySelector(
      "img[src='https://cdn.example.com/profile.webp']",
    );
    expect(fallbackImage).toHaveClass("saturate-50");
    expect(fallbackImage).toHaveStyle({ opacity: "0.18" });
    expect(imageCard?.querySelector("button > span")).toHaveStyle({
      background: "linear-gradient(155deg, #425768 0%, #263843 52%, #171e25 100%)",
    });
    expect(videoCard?.querySelector("button > span")).toHaveStyle({
      background: "linear-gradient(155deg, #52634f 0%, #304337 52%, #19231d 100%)",
    });
    expect(videoCard?.querySelector("video")).not.toBeInTheDocument();
    expect(screen.getByText("Video publisher")).toHaveClass(
      "z-30",
      "text-white",
    );
  });

  it("persists and synchronizes API-backed controlled Stories", async () => {
    const visual = {
      background: "#111",
      accent: "#fff",
      icon: "S",
      eyebrow: "Seller",
      headline: "Store",
      description: "Store update",
    };

    const view = renderWithFreshSWR(
      <WatchAndBuyStoriesSection
        groups={[
          {
            id: "seller-profile",
            title: "Seller Store",
            subtitle: "Latest update",
            publisherType: "seller",
            visual,
            items: [
              {
                id: "controlled-story-uuid",
                apiId: 27,
                mediaType: "image",
                durationMs: 5_000,
                visual,
                imageSrc: "https://cdn.example.com/controlled-story.webp",
              },
            ],
          },
        ]}
        sectionId="controlled-stories"
      />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: /Seller Store, 1 new/i }),
    );
    fireEvent.click(screen.getByRole("button", { name: "Record seen" }));
    fireEvent.click(screen.getByRole("button", { name: "Close Story viewer" }));

    await waitFor(() =>
      expect(apiMocks.markWatchAndBuyStoriesSeen).toHaveBeenCalledWith({
        status_ids: [27],
      }),
    );
    expect(
      JSON.parse(
        window.localStorage.getItem(STORY_PROGRESS_STORAGE_KEY) ?? "{}",
      ),
    ).toEqual({ "controlled-story-uuid": true });

    view.unmount();
    renderWithFreshSWR(
      <WatchAndBuyStoriesSection
        groups={[
          {
            id: "seller-profile",
            title: "Seller Store",
            subtitle: "Latest update",
            publisherType: "seller",
            visual,
            items: [
              {
                id: "controlled-story-uuid",
                apiId: 27,
                mediaType: "image",
                durationMs: 5_000,
                visual,
                imageSrc: "https://cdn.example.com/controlled-story.webp",
              },
            ],
          },
        ]}
        sectionId="controlled-stories-reloaded"
      />,
    );
    expect(
      await screen.findByRole("button", {
        name: /Seller Store, Viewed/i,
      }),
    ).toBeInTheDocument();
  });

  it("loads the Story tray first, then profile media and seen state", async () => {
    apiMocks.getWatchAndBuyStoryTray.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [
          {
            profile: {
              id: 1,
              type: "platform",
              username: "hyperlocal",
              bio: "Official updates",
              photo_url: "https://cdn.example.com/profile.webp",
              status: "active",
              has_active_status: true,
              has_unseen_status: true,
            },
            status_count: 2,
          },
        ],
        meta: { next_cursor: null, per_page: 50 },
      },
    });
    apiMocks.getWatchAndBuyProfileStories.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        profile: {
          id: 1,
          type: "platform",
          username: "hyperlocal",
          bio: "Official updates",
          photo_url: "https://cdn.example.com/profile.webp",
          status: "active",
        },
        items: [
          {
            id: 7,
            uuid: "story-uuid",
            source: "admin_direct",
            content_type: "image",
            text: "Nearby update",
            media_url: "https://cdn.example.com/story.webp",
            duration_ms: null,
            seen_by_me: false,
            products: [],
            created_at: "2026-08-29T08:00:00Z",
            expires_at: "2026-08-30T08:00:00Z",
          },
          {
            id: 8,
            uuid: "story-uuid-2",
            source: "admin_direct",
            content_type: "image",
            text: "Another nearby update",
            media_url: "https://cdn.example.com/story-2.webp",
            duration_ms: null,
            seen_by_me: false,
            products: [],
            created_at: "2026-08-29T08:05:00Z",
            expires_at: "2026-08-30T08:05:00Z",
          },
        ],
        pagination: {
          has_more_pages: false,
          next_cursor: null,
          previous_cursor: null,
          per_page: 50,
          total: 2,
        },
      },
    });

    renderWithFreshSWR(<WatchAndBuyStoriesSection sectionId="live-stories" />);

    const trayItem = await screen.findByRole("button", {
      name: /hyperlocal, 2 new/i,
    });
    expect(apiMocks.getWatchAndBuyProfileStories).not.toHaveBeenCalled();
    fireEvent.click(trayItem);

    await waitFor(() =>
      expect(screen.getByTestId("live-story-viewer")).toHaveTextContent(
        "story-uuid",
      ),
    );
    expect(apiMocks.getWatchAndBuyProfileStories).toHaveBeenCalledWith(
      "hyperlocal",
      { per_page: 50 },
    );

    fireEvent.click(
      screen.getByRole("button", { name: "Record merchant Stories" }),
    );
    expect(apiMocks.markWatchAndBuyStoriesSeen).not.toHaveBeenCalled();
    fireEvent.click(
      screen.getByRole("button", { name: "Complete merchant Stories" }),
    );
    expect(apiMocks.markWatchAndBuyStoriesSeen).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "Close Story viewer" }));
    await waitFor(() =>
      expect(apiMocks.markWatchAndBuyStoriesSeen).toHaveBeenCalledWith({
        status_ids: [7, 8],
      }),
    );
    expect(
      JSON.parse(
        window.localStorage.getItem(STORY_PROGRESS_STORAGE_KEY) ?? "{}",
      ),
    ).toEqual({ "story-uuid": true, "story-uuid-2": true });
    expect(
      JSON.parse(
        window.localStorage.getItem(
          getPendingStorySeenStorageKey(STORY_PROGRESS_STORAGE_KEY),
        ) ?? "{}",
      ),
    ).toEqual({});
  });

  it("retries queued seen updates as one database batch after reload", async () => {
    queuePendingStorySeen(window.localStorage, STORY_PROGRESS_STORAGE_KEY, {
      statusId: 10,
      storyId: "story-10",
      queuedAt: 1,
    });
    queuePendingStorySeen(window.localStorage, STORY_PROGRESS_STORAGE_KEY, {
      statusId: 11,
      storyId: "story-11",
      queuedAt: 2,
    });
    queuePendingStorySeen(window.localStorage, STORY_PROGRESS_STORAGE_KEY, {
      statusId: 12,
      storyId: "story-12",
      queuedAt: 3,
    });
    apiMocks.getWatchAndBuyStoryTray.mockResolvedValue({
      success: true,
      message: "ok",
      data: { items: [], meta: { next_cursor: null, per_page: 50 } },
    });

    renderWithFreshSWR(
      <WatchAndBuyStoriesSection
        hideWhenEmpty={false}
        sectionId="live-stories"
      />,
    );

    await waitFor(() =>
      expect(apiMocks.markWatchAndBuyStoriesSeen).toHaveBeenCalledWith({
        status_ids: [10, 11, 12],
      }),
    );
    await waitFor(() =>
      expect(
        JSON.parse(
          window.localStorage.getItem(
            getPendingStorySeenStorageKey(STORY_PROGRESS_STORAGE_KEY),
          ) ?? "{}",
        ),
      ).toEqual({}),
    );
  });

  it("reconciles locally seen Stories that the database still reports unseen", async () => {
    window.localStorage.setItem(
      STORY_PROGRESS_STORAGE_KEY,
      JSON.stringify({ "story-uuid": true }),
    );
    apiMocks.getWatchAndBuyStoryTray.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [
          {
            profile: {
              id: 1,
              type: "platform",
              username: "hyperlocal",
              photo_url: "https://cdn.example.com/profile.webp",
              status: "active",
              has_active_status: true,
              has_unseen_status: true,
            },
            status_count: 1,
          },
        ],
        meta: { next_cursor: null, per_page: 50 },
      },
    });
    apiMocks.getWatchAndBuyProfileStories.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        profile: {
          id: 1,
          type: "platform",
          username: "hyperlocal",
          photo_url: "https://cdn.example.com/profile.webp",
          status: "active",
        },
        items: [
          {
            id: 7,
            uuid: "story-uuid",
            source: "admin_direct",
            content_type: "image",
            media_url: "https://cdn.example.com/story.webp",
            duration_ms: null,
            seen_by_me: false,
            products: [],
            created_at: "2026-08-29T08:00:00Z",
            expires_at: "2026-08-30T08:00:00Z",
          },
        ],
        pagination: {
          has_more_pages: false,
          next_cursor: null,
          previous_cursor: null,
          per_page: 50,
          total: 1,
        },
      },
    });

    renderWithFreshSWR(<WatchAndBuyStoriesSection sectionId="live-stories" />);
    fireEvent.click(
      await screen.findByRole("button", { name: /hyperlocal, 1 new/i }),
    );
    fireEvent.click(
      await screen.findByRole("button", {
        name: "Complete merchant Stories",
      }),
    );
    expect(apiMocks.markWatchAndBuyStoriesSeen).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "Close Story viewer" }));

    await waitFor(() =>
      expect(apiMocks.markWatchAndBuyStoriesSeen).toHaveBeenCalledWith({
        status_ids: [7],
      }),
    );
  });

  it("keeps guest progress after a fresh unauthenticated tray request", async () => {
    authState.accessToken = null;
    apiMocks.getWatchAndBuyStoryTray.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [
          {
            profile: {
              id: 1,
              type: "platform",
              username: "hyperlocal",
              photo_url: "https://cdn.example.com/profile.webp",
              status: "active",
              has_active_status: true,
              has_unseen_status: true,
            },
            status_count: 2,
          },
        ],
        meta: { next_cursor: null, per_page: 50 },
      },
    });
    apiMocks.getWatchAndBuyProfileStories.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        profile: {
          id: 1,
          type: "platform",
          username: "hyperlocal",
          photo_url: "https://cdn.example.com/profile.webp",
          status: "active",
        },
        items: [
          {
            id: 7,
            uuid: "guest-story-1",
            source: "admin_direct",
            content_type: "image",
            media_url: "https://cdn.example.com/guest-story-1.webp",
            duration_ms: null,
            seen_by_me: false,
            products: [],
            created_at: "2026-08-29T08:00:00Z",
            expires_at: "2026-08-30T08:00:00Z",
          },
          {
            id: 8,
            uuid: "guest-story-2",
            source: "admin_direct",
            content_type: "image",
            media_url: "https://cdn.example.com/guest-story-2.webp",
            duration_ms: null,
            seen_by_me: false,
            products: [],
            created_at: "2026-08-29T08:05:00Z",
            expires_at: "2026-08-30T08:05:00Z",
          },
        ],
        pagination: {
          has_more_pages: false,
          next_cursor: null,
          previous_cursor: null,
          per_page: 50,
          total: 2,
        },
      },
    });

    const firstRender = renderWithFreshSWR(
      <WatchAndBuyStoriesSection sectionId="live-stories" />,
    );
    fireEvent.click(
      await screen.findByRole("button", { name: /hyperlocal, 2 new/i }),
    );
    fireEvent.click(await screen.findByRole("button", { name: "Record seen" }));
    fireEvent.click(screen.getByRole("button", { name: "Close Story viewer" }));

    expect(apiMocks.markWatchAndBuyStoriesSeen).not.toHaveBeenCalled();
    expect(
      JSON.parse(
        window.localStorage.getItem(STORY_PROGRESS_STORAGE_KEY) ?? "{}",
      ),
    ).toEqual({ "guest-story-1": true });
    expect(
      JSON.parse(
        window.localStorage.getItem(
          getSeenStoryGroupsStorageKey(STORY_PROGRESS_STORAGE_KEY),
        ) ?? "{}",
      ),
    ).toEqual({
      "guest-story-1": { groupId: "hyperlocal", statusId: 7 },
    });

    firstRender.unmount();
    renderWithFreshSWR(
      <WatchAndBuyStoriesSection sectionId="live-stories-reloaded" />,
    );

    expect(
      await screen.findByRole("button", {
        name: /hyperlocal, 1\/2 viewed/i,
      }),
    ).toBeInTheDocument();
    expect(apiMocks.markWatchAndBuyStoriesSeen).not.toHaveBeenCalled();
  });

  it("synchronizes locally viewed guest Stories when the customer logs in", async () => {
    authState.accessToken = null;
    apiMocks.getWatchAndBuyStoryTray.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [
          {
            profile: {
              id: 1,
              type: "platform",
              username: "hyperlocal",
              photo_url: "https://cdn.example.com/profile.webp",
              status: "active",
              has_active_status: true,
              has_unseen_status: true,
            },
            status_count: 1,
          },
        ],
        meta: { next_cursor: null, per_page: 50 },
      },
    });
    apiMocks.getWatchAndBuyProfileStories.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        profile: {
          id: 1,
          type: "platform",
          username: "hyperlocal",
          photo_url: "https://cdn.example.com/profile.webp",
          status: "active",
        },
        items: [
          {
            id: 7,
            uuid: "guest-login-story",
            source: "admin_direct",
            content_type: "image",
            media_url: "https://cdn.example.com/guest-login-story.webp",
            duration_ms: null,
            seen_by_me: false,
            products: [],
            created_at: "2026-08-29T08:00:00Z",
            expires_at: "2026-08-30T08:00:00Z",
          },
        ],
        pagination: {
          has_more_pages: false,
          next_cursor: null,
          previous_cursor: null,
          per_page: 50,
          total: 1,
        },
      },
    });

    const view = renderWithFreshSWR(
      <WatchAndBuyStoriesSection sectionId="guest-login-stories" />,
    );
    fireEvent.click(
      await screen.findByRole("button", { name: /hyperlocal, 1 new/i }),
    );
    fireEvent.click(await screen.findByRole("button", { name: "Record seen" }));
    fireEvent.click(screen.getByRole("button", { name: "Close Story viewer" }));
    expect(apiMocks.markWatchAndBuyStoriesSeen).not.toHaveBeenCalled();

    authState.accessToken = "new-customer-token";
    view.rerender(
      <SWRConfig value={{ dedupingInterval: 0, provider: () => new Map() }}>
        <WatchAndBuyStoriesSection sectionId="guest-login-stories" />
      </SWRConfig>,
    );

    await waitFor(() =>
      expect(apiMocks.markWatchAndBuyStoriesSeen).toHaveBeenCalledWith({
        status_ids: [7],
      }),
    );
    expect(
      JSON.parse(
        window.localStorage.getItem(
          getPendingStorySeenStorageKey(STORY_PROGRESS_STORAGE_KEY),
        ) ?? "{}",
      ),
    ).toEqual({});
  });

  it("reconciles legacy guest UUID records after an authenticated reload", async () => {
    window.localStorage.setItem(
      STORY_PROGRESS_STORAGE_KEY,
      JSON.stringify({ "legacy-guest-story": true }),
    );
    window.localStorage.setItem(
      getSeenStoryGroupsStorageKey(STORY_PROGRESS_STORAGE_KEY),
      JSON.stringify({ "legacy-guest-story": "hyperlocal" }),
    );
    apiMocks.getWatchAndBuyStoryTray.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [
          {
            profile: {
              id: 1,
              type: "platform",
              username: "hyperlocal",
              photo_url: "https://cdn.example.com/profile.webp",
              status: "active",
              has_active_status: true,
              has_unseen_status: true,
            },
            status_count: 1,
          },
        ],
        meta: { next_cursor: null, per_page: 50 },
      },
    });
    apiMocks.getWatchAndBuyProfileStories.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        profile: {
          id: 1,
          type: "platform",
          username: "hyperlocal",
          photo_url: "https://cdn.example.com/profile.webp",
          status: "active",
        },
        items: [
          {
            id: 17,
            uuid: "legacy-guest-story",
            source: "admin_direct",
            content_type: "image",
            media_url: "https://cdn.example.com/legacy-guest-story.webp",
            duration_ms: null,
            seen_by_me: false,
            products: [],
            created_at: "2026-08-29T08:00:00Z",
            expires_at: "2026-08-30T08:00:00Z",
          },
        ],
        pagination: {
          has_more_pages: false,
          next_cursor: null,
          previous_cursor: null,
          per_page: 50,
          total: 1,
        },
      },
    });

    renderWithFreshSWR(
      <WatchAndBuyStoriesSection sectionId="legacy-guest-login-stories" />,
    );

    await waitFor(() =>
      expect(apiMocks.markWatchAndBuyStoriesSeen).toHaveBeenCalledWith({
        status_ids: [17],
      }),
    );
    expect(
      JSON.parse(
        window.localStorage.getItem(
          getSeenStoryGroupsStorageKey(STORY_PROGRESS_STORAGE_KEY),
        ) ?? "{}",
      ),
    ).toEqual({
      "legacy-guest-story": { groupId: "hyperlocal", statusId: 17 },
    });
  });

  it("appends Reel cursor pages with the original session ID", async () => {
    apiMocks.getWatchAndBuyReels.mockImplementation(
      async (params: {
        cursor?: string;
        session_id?: string;
        store_slug?: string;
      }) => {
        const isSecondPage = params.cursor === "reel-cursor-2";
        return {
          success: true,
          message: "ok",
          data: {
            items: [
              {
                id: isSecondPage ? 2 : 1,
                uuid: isSecondPage ? "reel-page-2" : "reel-page-1",
                caption: isSecondPage ? "Second Reel" : "First Reel",
                profile: {
                  id: 1,
                  type: "platform",
                  username: "hyperlocal",
                  photo_url: "https://cdn.example.com/profile.webp",
                  status: "active",
                },
                video_url: `https://cdn.example.com/reel-${isSecondPage ? 2 : 1}.mp4`,
                cover_url: `https://cdn.example.com/reel-${isSecondPage ? 2 : 1}.webp`,
                duration_ms: 10_000,
                like_count: 0,
                liked_by_me: false,
                products: [],
              },
            ],
            meta: {
              session_id: "reel-session",
              next_cursor: isSecondPage ? null : "reel-cursor-2",
              per_page: 1,
            },
          },
        };
      },
    );

    renderWithFreshSWR(
      <WatchAndBuyReelsSection
        infiniteScroll
        layout="grid"
        perPage={1}
        sectionId="paginated-reels"
        storeSlug="amits-beauty-essentials"
      />,
    );

    fireEvent.click(
      await screen.findByRole("button", {
        name: "Open First Reel by hyperlocal",
      }),
    );
    fireEvent.click(
      await screen.findByRole("button", {
        name: "Load next Reel in viewer",
      }),
    );

    expect(
      await screen.findByRole("button", {
        name: "Open Second Reel by hyperlocal",
      }),
    ).toBeInTheDocument();
    expect(apiMocks.getWatchAndBuyReels).toHaveBeenLastCalledWith({
      latitude: 23.242,
      longitude: 69.6669,
      per_page: 1,
      store_slug: "amits-beauty-essentials",
      cursor: "reel-cursor-2",
      session_id: "reel-session",
    });
  });

  it("appends Story tray cursor pages near the end of the rail", async () => {
    apiMocks.getWatchAndBuyStoryTray.mockImplementation(
      async (params: {
        cursor?: string;
        session_id?: string;
        store_slug?: string;
      }) => {
        const isSecondPage = params.cursor === "story-cursor-2";
        const username = isSecondPage ? "seller-two" : "seller-one";
        return {
          success: true,
          message: "ok",
          data: {
            items: [
              {
                profile: {
                  id: isSecondPage ? 2 : 1,
                  type: "seller",
                  username,
                  name: isSecondPage ? "Seller Two" : "Seller One",
                  status: "active",
                  has_active_status: true,
                  has_unseen_status: true,
                  store: {
                    id: isSecondPage ? 2 : 1,
                    name: isSecondPage ? "Seller Two" : "Seller One",
                    slug: username,
                    image: `https://cdn.example.com/${username}.webp`,
                  },
                },
                status_count: 1,
              },
            ],
            meta: {
              session_id: "story-session",
              next_cursor: isSecondPage ? null : "story-cursor-2",
              per_page: 1,
            },
          },
        };
      },
    );

    renderWithFreshSWR(
      <WatchAndBuyStoriesSection
        perPage={1}
        sectionId="paginated-stories"
        storeSlug="amits-beauty-essentials"
      />,
    );

    fireEvent.click(
      await screen.findByRole("button", { name: "Load more Stories" }),
    );

    expect(
      await screen.findByRole("button", { name: /Seller Two, 1 new/i }),
    ).toBeInTheDocument();
    expect(apiMocks.getWatchAndBuyStoryTray).toHaveBeenLastCalledWith({
      per_page: 1,
      store_slug: "amits-beauty-essentials",
      cursor: "story-cursor-2",
      session_id: "story-session",
    });
  });

  it("scopes Reels by store slug and trusts the scoped API result", async () => {
    const reelForStore = (storeId: number, title: string) => ({
      id: storeId,
      uuid: `store-${storeId}-reel`,
      title,
      profile: {
        id: storeId,
        type: "seller" as const,
        username: `seller-${storeId}`,
        name: `Seller ${storeId}`,
        status: "active",
        store: {
          id: storeId,
          name: `Store ${storeId}`,
          slug: `store-${storeId}`,
        },
      },
      poster_url: `https://cdn.example.com/store-${storeId}.webp`,
      video_url: `https://cdn.example.com/store-${storeId}.mp4`,
      products: [],
    });
    apiMocks.getWatchAndBuyReels.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [reelForStore(8, "Requested Store Reel")],
        meta: { next_cursor: null, per_page: 10 },
      },
    });

    renderWithFreshSWR(
      <WatchAndBuyReelsSection
        layout="grid"
        sectionId="store-reels"
        storeSlug="requested-store"
      />,
    );

    expect(
      await screen.findByRole("button", {
        name: "Open Requested Store Reel by Seller 8",
      }),
    ).toBeInTheDocument();
    expect(apiMocks.getWatchAndBuyReels).toHaveBeenCalledWith({
      latitude: 23.242,
      longitude: 69.6669,
      per_page: 10,
      store_slug: "requested-store",
    });
  });

  it("scopes the Story tray and profile Stories to a store ID and slug", async () => {
    const trayItemForStore = (storeId: number) => ({
      profile: {
        id: storeId,
        type: "seller" as const,
        username: `seller-${storeId}`,
        name: `Seller ${storeId}`,
        status: "active",
        has_active_status: true,
        has_unseen_status: true,
        store: {
          id: storeId,
          name: `Store ${storeId}`,
          slug: `store-${storeId}`,
        },
      },
      status_count: 1,
    });
    apiMocks.getWatchAndBuyStoryTray.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [trayItemForStore(7)],
        meta: { next_cursor: null, per_page: 12 },
      },
    });

    renderWithFreshSWR(
      <WatchAndBuyStoriesSection
        sectionId="store-stories"
        storeId={7}
        storeSlug="store-7"
      />,
    );

    const scopedStory = await screen.findByRole("button", {
      name: /Store 7, 1 new/i,
    });
    expect(scopedStory).toBeInTheDocument();
    expect(apiMocks.getWatchAndBuyStoryTray).toHaveBeenCalledWith({
      per_page: 12,
      store_id: 7,
      store_slug: "store-7",
    });
    fireEvent.click(scopedStory);
    await waitFor(() =>
      expect(apiMocks.getWatchAndBuyProfileStories).toHaveBeenCalledWith(
        "seller-7",
        { per_page: 50, store_id: 7, store_slug: "store-7" },
      ),
    );
  });

  it("maps a live Reel and synchronizes authenticated likes", async () => {
    apiMocks.getWatchAndBuyReels.mockResolvedValue({
      success: true,
      message: "ok",
      data: {
        items: [
          {
            id: 42,
            uuid: "reel-uuid",
            title: "Fresh nearby",
            caption: "Available now",
            profile: {
              id: 2,
              type: "seller",
              username: "fresh-finds",
              status: "active",
            },
            poster_url: "https://cdn.example.com/reel.webp",
            video_url: "https://cdn.example.com/reel.mp4",
            duration_seconds: 18,
            views_count: 100,
            likes_count: 10,
            is_liked: false,
            products: [],
          },
        ],
        meta: { next_cursor: null, per_page: 10 },
      },
    });

    renderWithFreshSWR(<WatchAndBuyReelsSection sectionId="live-reels" />);

    fireEvent.click(
      await screen.findByRole("button", {
        name: "Open Fresh nearby by fresh-finds",
      }),
    );
    fireEvent.click(screen.getByRole("button", { name: "Sync like" }));

    await waitFor(() =>
      expect(apiMocks.updateWatchAndBuyReelLikes).toHaveBeenCalledWith({
        latitude: 23.242,
        longitude: 69.6669,
        items: [{ reel_id: 42, liked: true }],
      }),
    );
  });
});
