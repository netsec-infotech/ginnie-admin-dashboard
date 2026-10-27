import type { ReactNode } from "react";
import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { useInfiniteDataMock, useScrollImageCollapseMock } = vi.hoisted(() => ({
  useInfiniteDataMock: vi.fn(),
  useScrollImageCollapseMock: vi.fn(),
}));

vi.mock("@/hooks/useInfiniteData", () => ({
  useInfiniteData: useInfiniteDataMock,
}));

vi.mock("@/hooks/useScrollImageCollapse", () => ({
  default: useScrollImageCollapseMock,
}));

vi.mock("@/contexts/HeaderScrollContext", () => ({
  useHeaderScroll: () => ({
    isMobileHeaderExpanded: true,
    isMobileViewport: false,
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
  default: ({
    hideIcon,
    size,
    title,
  }: {
    hideIcon?: boolean;
    size?: string;
    title: string;
  }) => (
    <button data-hide-icon={hideIcon} data-size={size}>
      {title}
    </button>
  ),
}));

vi.mock("@/components/custom/SkeletonTabButton", () => ({
  default: () => <div data-testid="subcategory-tab-skeleton" />,
}));

vi.mock("@/components/Functional/InlineDataRetry", () => ({
  default: () => <button>retry</button>,
}));

vi.mock("@/helpers/functionalHelpers", () => ({
  isRTL: () => false,
}));

vi.mock("@/routes/api", () => ({
  getCategories: vi.fn(),
}));

import SubcategoryTabs from "@/components/Functional/SubcategoryTabs";

describe("SubcategoryTabs", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useInfiniteDataMock.mockReturnValue({
      data: [
        {
          id: 1,
          title: "Body Care",
          slug: "body-care",
          image: "",
          icon: "",
          active_icon: "",
          description: null,
          status: "active",
        },
      ],
      error: null,
      hasMore: false,
      isLoading: false,
      isLoadingMore: false,
      isValidating: false,
      loadMore: vi.fn(),
      loadMoreError: null,
      refetch: vi.fn(),
      retryLoadMore: vi.fn(),
    });
  });

  it("keeps stable tab widths while collapsing the image row", () => {
    useScrollImageCollapseMock.mockReturnValue(true);

    render(<SubcategoryTabs parentSlug="beauty" />);

    const tabRail = screen.getByLabelText("subcategories");
    expect(tabRail).toHaveClass("py-1", "duration-200", "ease-in-out");
    expect(tabRail.firstElementChild).toHaveClass("items-end", "duration-200");
    for (const tab of screen.getAllByRole("button")) {
      expect(tab).toHaveAttribute("data-size", "lg");
      expect(tab).toHaveAttribute("data-hide-icon", "true");
    }
  });

  it("keeps the image tabs large before the page is scrolled", () => {
    useScrollImageCollapseMock.mockReturnValue(false);

    render(<SubcategoryTabs parentSlug="beauty" />);

    expect(screen.getByLabelText("subcategories")).toHaveClass("py-2");
    for (const tab of screen.getAllByRole("button")) {
      expect(tab).toHaveAttribute("data-size", "lg");
      expect(tab).toHaveAttribute("data-hide-icon", "false");
    }
  });
});
