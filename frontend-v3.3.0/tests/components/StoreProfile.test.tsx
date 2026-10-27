import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { Store } from "@/types/ApiResponse";

vi.mock("next/router", () => ({
  useRouter: () => ({ push: vi.fn(), prefetch: vi.fn(), query: {} }),
}));

vi.mock("react-redux", () => ({
  useSelector: () => "",
}));

vi.mock("@/lib/cookies", () => ({
  getCookie: () => null,
}));

vi.mock("@/routes/api", () => ({
  notifyStoreOpening: vi.fn(),
  getWatchAndBuyConfig: vi.fn(),
  getWatchAndBuyProfileStories: vi.fn(),
  getWatchAndBuyStoryTray: vi.fn(),
  markWatchAndBuyStoriesSeen: vi.fn(),
}));

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string, options?: Record<string, unknown>) =>
        String(options?.defaultValue ?? key)
          .replace("{{store}}", String(options?.store ?? ""))
          .replace("{{state}}", String(options?.state ?? ""))
          .replace("{{count}}", String(options?.count ?? "")),
    }),
  };
});

vi.mock("yet-another-react-lightbox", () => ({
  default: () => null,
}));

vi.mock("@/components/StoreTiming", () => ({
  default: () => <div data-testid="store-timing-mock" />,
}));

vi.mock("@/components/Stories/StoryViewer", () => ({
  default: ({
    groups,
    selection,
    onSeen,
  }: {
    groups: Array<{ items: Array<{ id: string; imageSrc?: string }> }>;
    selection: { groupIndex: number; itemIndex: number } | null;
    onSeen: (storyId: string) => void;
  }) => (
    <div data-testid="store-story-viewer">
      {selection
        ? `${selection.groupIndex}:${selection.itemIndex}:${groups[0]?.items[0]?.imageSrc}`
        : "closed"}
      <button onClick={() => onSeen(groups[0].items[0].id)}>
        Mark store Story seen
      </button>
    </div>
  ),
}));

import StoreProfile from "@/components/StoreProfile";
import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";

const store: Store = {
  id: 1,
  name: "City Square Mart",
  slug: "city-square-mart-1",
  product_count: 12,
  description: "A nearby store",
  contact_number: "1234567890",
  contact_email: "store@example.com",
  address: "Market Road",
  latitude: "12.34",
  longitude: "56.78",
  distance: "1 km",
  timing: null,
  logo: "https://cdn.example.com/store-logo.webp",
  banner: "https://cdn.example.com/store-banner.webp",
  avg_products_rating: "4.5",
  avg_store_rating: "4.6",
  total_store_feedback: "20",
  created_at: "2026-09-01T00:00:00Z",
  updated_at: "2026-09-01T00:00:00Z",
  verification_status: "approved",
  visibility_status: "visible",
  status: { is_open: true, status: "online" },
};

const storeWithStories: Store = {
  ...store,
  stories: [
    {
      id: 7,
      uuid: "store-story-uuid",
      source: "seller",
      content_type: "image",
      text: "Fresh from the store",
      media_url: "https://cdn.example.com/store-story.webp",
      duration_ms: 5_000,
      seen_by_me: false,
      created_at: "2026-09-20T00:00:00Z",
      expires_at: "2026-09-21T00:00:00Z",
      profile: {
        id: 4,
        type: "seller",
        username: "city-square",
        status: "active",
      },
    },
  ],
};

describe("StoreProfile", () => {
  beforeEach(() => window.localStorage.clear());

  it("sanitizes store descriptions while preserving formatting", () => {
    const { container } = render(
      <StoreProfile
        store={{
          ...store,
          description:
            '<strong>Local produce</strong><img src="/missing.png" onerror="alert(1)"><a href="javascript:alert(1)">Offer</a>',
        }}
      />,
    );
    expect(container.querySelector("strong")).toHaveTextContent(
      "Local produce",
    );
    expect(container.querySelector("[onerror]")).toBeNull();
    expect(container.querySelector('a[href^="javascript:"]')).toBeNull();
  });

  it("uses the logo lightbox when the API has no active Stories", () => {
    render(<StoreProfile store={store} />);

    expect(
      screen.getByRole("button", { name: "View City Square Mart logo" }),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: /City Square Mart Stories/i }),
    ).not.toBeInTheDocument();
  });

  it("shows opening hours when metadata is present without legacy timing", () => {
    render(
      <StoreProfile
        store={{
          ...store,
          metadata: {
            operating_hours: {
              monday: {
                is_open: "1",
                open_time: "09:00",
                close_time: "21:00",
              },
            },
          },
        }}
      />,
    );

    expect(screen.getByTestId("store-timing-mock")).toBeInTheDocument();
  });

  it("opens and persists an API Story as seen from the main icon", async () => {
    const view = render(<StoreProfile store={storeWithStories} />);

    expect(screen.getByTestId("store-story-viewer")).toHaveTextContent(
      "closed",
    );
    fireEvent.click(
      screen.getAllByRole("button", {
        name: "View City Square Mart Stories, 1 new",
      })[0],
    );

    expect(screen.getByTestId("store-story-viewer")).toHaveTextContent(
      "0:0:https://cdn.example.com/store-story.webp",
    );

    fireEvent.click(
      screen.getByRole("button", { name: "Mark store Story seen" }),
    );

    await waitFor(() =>
      expect(
        screen.getByRole("button", {
          name: "View City Square Mart Stories, Viewed",
        }),
      ).toHaveAttribute("data-state", "seen"),
    );
    expect(
      JSON.parse(
        window.localStorage.getItem(STORY_PROGRESS_STORAGE_KEY) ?? "{}",
      ),
    ).toEqual({ "store-story-uuid": true });

    view.unmount();
    render(<StoreProfile store={storeWithStories} />);
    expect(
      await screen.findByRole("button", {
        name: "View City Square Mart Stories, Viewed",
      }),
    ).toHaveAttribute("data-state", "seen");
  });
});
