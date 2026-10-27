import type { ReactNode } from "react";
import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { refetch, retryLoadMore, useInfiniteDataMock } = vi.hoisted(() => ({
  refetch: vi.fn(),
  retryLoadMore: vi.fn(),
  useInfiniteDataMock: vi.fn(),
}));

vi.mock("@/hooks/useInfiniteData", () => ({
  useInfiniteData: useInfiniteDataMock,
}));

vi.mock("@/routes/api", () => ({
  getBrands: vi.fn(),
  getSettings: vi.fn(),
}));

vi.mock("@/helpers/getters", () => ({
  isSSR: () => false,
}));

vi.mock("@/helpers/functionalHelpers", () => ({
  getUserLocationFromContext: vi.fn(),
}));

vi.mock("next/router", () => ({
  useRouter: () => ({ query: {} }),
}));

vi.mock("react-i18next", () => ({
  initReactI18next: { type: "3rdParty", init: vi.fn() },
  useTranslation: () => ({
    t: (key: string, options?: { defaultValue?: string }) =>
      options?.defaultValue ?? key,
  }),
}));

vi.mock("@/components/custom/MyBreadcrumbs", () => ({
  default: () => null,
}));

vi.mock("@/components/custom/PageHeader", () => ({
  default: () => null,
}));

vi.mock("@/components/Cards/BrandCard", () => ({
  default: ({ brand }: { brand: { id: number } }) => (
    <div data-testid={`brand-${brand.id}`} />
  ),
}));

vi.mock("@/components/Skeletons/BrandCardSkeleton", () => ({
  default: () => <div data-testid="brand-skeleton" />,
}));

vi.mock("@/components/Functional/InfiniteScroll", () => ({
  default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));

vi.mock("@/components/Functional/InfiniteScrollStatus", () => ({
  default: () => <div data-testid="infinite-scroll-status" />,
}));

vi.mock("@/SEO/DynamicSEO", () => ({
  default: () => null,
}));

vi.mock("@/helpers/seo", () => ({
  generateBreadcrumbSchema: vi.fn(() => ({})),
  generateCollectionSchema: vi.fn(() => ({})),
}));

import BrandsPage from "@/pages/brands";

const baseHookResult = {
  isLoading: false,
  isLoadingMore: false,
  hasMore: false,
  total: 0,
  loadMore: vi.fn(),
  loadMoreError: null,
  refetch,
  retryLoadMore,
  isValidating: false,
};

describe("BrandsPage data failure state", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    refetch.mockResolvedValue(undefined);
  });

  it("prioritizes an initial request failure and lets the customer retry", () => {
    useInfiniteDataMock.mockReturnValue({
      ...baseHookResult,
      data: [],
      error: new Error("Request failed with status code 500"),
    });

    render(<BrandsPage initialBrands={null} />);

    expect(useInfiniteDataMock).toHaveBeenCalledWith(
      expect.objectContaining({ initialRequestFailed: true }),
    );

    fireEvent.click(
      screen.getByRole("button", {
        name: "Try again: This content is taking a moment to load.",
      }),
    );

    expect(refetch).toHaveBeenCalledTimes(1);
    expect(
      screen.queryByTestId("infinite-scroll-status"),
    ).not.toBeInTheDocument();
  });

  it("keeps successful content visible during a failed background refresh", () => {
    useInfiniteDataMock.mockReturnValue({
      ...baseHookResult,
      data: [{ id: 7 }],
      total: 1,
      isLoading: true,
      isValidating: true,
      error: new Error("Background refresh failed"),
    });

    render(<BrandsPage initialBrands={null} />);

    expect(screen.getByTestId("brand-7")).toBeInTheDocument();
    expect(screen.queryByTestId("brand-skeleton")).not.toBeInTheDocument();
    expect(
      screen.queryByText("This content is taking a moment to load."),
    ).not.toBeInTheDocument();
  });
});
