import type { ReactNode } from "react";
import { act, fireEvent, render, screen } from "@testing-library/react";
import { hydrateRoot } from "react-dom/client";
import { renderToString } from "react-dom/server";
import { beforeEach, describe, expect, it, vi } from "vitest";

const {
  loadMore,
  refetch,
  replace,
  retryLoadMore,
  routerState,
  useInfiniteDataMock,
} = vi.hoisted(() => ({
  loadMore: vi.fn(),
  refetch: vi.fn(),
  replace: vi.fn(),
  retryLoadMore: vi.fn(),
  routerState: {
    isReady: true,
    pathname: "/",
    query: { category: "beauty" },
  },
  useInfiniteDataMock: vi.fn(),
}));

vi.mock("@/hooks/useInfiniteData", () => ({
  useInfiniteData: useInfiniteDataMock,
}));

vi.mock("next/router", () => ({
  useRouter: () => ({
    ...routerState,
    replace,
  }),
}));

vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    homeGeneralSettings: {
      activeIcon: "",
      icon: "",
      title: "All",
    },
    webSettings: { primaryColor: "#7c3aed" },
  }),
}));

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    i18n: { language: "en", resolvedLanguage: "en" },
    t: (key: string, options?: { defaultValue?: string }) =>
      options?.defaultValue ?? key,
  }),
}));

vi.mock("swiper/react", () => ({
  Swiper: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  SwiperSlide: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));

vi.mock("swiper/modules", () => ({ Mousewheel: {} }));

vi.mock("@/components/custom/TabButton", () => ({
  default: ({ onClick, title }: { onClick?: () => void; title: string }) => (
    <button onClick={onClick}>{title}</button>
  ),
}));

vi.mock("@/components/custom/SkeletonTabButton", () => ({
  default: () => <div data-testid="category-tab-skeleton" />,
}));

vi.mock("@/components/Functional/InlineDataRetry", () => ({
  default: ({
    onRetry,
    retryLabel,
    variant = "inline",
  }: {
    onRetry: () => void;
    retryLabel: string;
    variant?: "inline" | "panel";
  }) => (
    <button data-retry-variant={variant} onClick={onRetry}>
      {retryLabel}
    </button>
  ),
}));

vi.mock("@/hooks/useScrollImageCollapse", () => ({
  default: () => false,
}));

vi.mock("@/lib/cookies", () => ({
  getCookie: vi.fn(),
  setCookie: vi.fn(),
}));

vi.mock("@/helpers/events", () => ({
  onHomeCategoryChange: vi.fn(),
}));

vi.mock("@/helpers/homeLayout", () => ({
  prefetchHomeLayout: vi.fn(),
}));

vi.mock("@/helpers/functionalHelpers", () => ({
  isRTL: () => false,
}));

vi.mock("@/helpers/homeCategoryTheme", () => ({
  applyHomeCategoryTheme: vi.fn(),
  resolveHomeCategoryTheme: vi.fn(() => ({ color: "#7c3aed" })),
}));

vi.mock("@/routes/api", () => ({
  getCategories: vi.fn(),
}));

import CategoryTabs from "@/components/Functional/CategoryTabs";

describe("CategoryTabs", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    routerState.isReady = true;
    routerState.pathname = "/";
    routerState.query = { category: "beauty" };
    refetch.mockResolvedValue(undefined);
    replace.mockResolvedValue(true);
    useInfiniteDataMock.mockReturnValue({
      data: [],
      error: new Error("Categories unavailable"),
      hasLoadedSuccessfully: false,
      hasMore: false,
      isLoading: false,
      isLoadingMore: false,
      isValidating: false,
      loadMore,
      loadMoreError: null,
      refetch,
      retryLoadMore,
    });
  });

  it("replaces the expanded category row with a balanced retry panel", () => {
    render(<CategoryTabs hideImages />);

    expect(replace).not.toHaveBeenCalled();
    expect(
      screen.queryByRole("button", { name: "All" }),
    ).not.toBeInTheDocument();

    const retryControl = screen.getByRole("button", {
      name: "Try again",
    });
    expect(retryControl).toHaveAttribute("data-retry-variant", "panel");

    fireEvent.click(retryControl);

    expect(refetch).toHaveBeenCalledTimes(1);
    expect(replace).not.toHaveBeenCalled();
  });

  it("keeps the compact initial-error treatment as an inline pill", () => {
    render(<CategoryTabs hideImages isCompact />);

    const retryControl = screen.getByRole("button", {
      name: "Try again",
    });

    expect(retryControl).toHaveAttribute("data-retry-variant", "inline");

    fireEvent.click(retryControl);

    expect(refetch).toHaveBeenCalledTimes(1);
  });

  it("keeps a background-refresh error at the end of a populated row", () => {
    useInfiniteDataMock.mockReturnValue({
      data: [
        {
          id: 1,
          title: "Beauty",
          slug: "beauty",
          image: "",
          icon: "",
          active_icon: "",
          description: null,
          status: "active",
        },
      ],
      error: new Error("Refresh failed"),
      hasLoadedSuccessfully: true,
      hasMore: false,
      isLoading: false,
      isLoadingMore: false,
      isValidating: false,
      loadMore,
      loadMoreError: null,
      refetch,
      retryLoadMore,
    });

    render(<CategoryTabs hideImages />);

    expect(screen.getByRole("button", { name: "All" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Beauty" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Try again" })).toHaveAttribute(
      "data-retry-variant",
      "inline",
    );
  });

  it("hydrates the router-ready category row without replacing server HTML", async () => {
    useInfiniteDataMock.mockReturnValue({
      data: [
        {
          id: 1,
          title: "Beauty",
          slug: "beauty",
          image: "",
          icon: "",
          active_icon: "",
          description: null,
          status: "active",
        },
      ],
      error: null,
      hasLoadedSuccessfully: true,
      hasMore: false,
      isLoading: false,
      isLoadingMore: false,
      isValidating: false,
      loadMore,
      loadMoreError: null,
      refetch,
      retryLoadMore,
    });
    routerState.isReady = false;

    const serverHtml = renderToString(<CategoryTabs hideImages />);
    const container = document.createElement("div");
    container.innerHTML = serverHtml;
    document.body.appendChild(container);
    routerState.isReady = true;
    const recoverableErrors: unknown[] = [];
    let root: ReturnType<typeof hydrateRoot> | undefined;

    try {
      await act(async () => {
        root = hydrateRoot(container, <CategoryTabs hideImages />, {
          onRecoverableError: (error) => recoverableErrors.push(error),
        });
      });

      expect(recoverableErrors).toEqual([]);
      expect(
        container.querySelector("#home-category-tabs"),
      ).toBeInTheDocument();
      expect(container).toHaveTextContent("Beauty");
    } finally {
      await act(async () => root?.unmount());
      container.remove();
    }
  });
});
