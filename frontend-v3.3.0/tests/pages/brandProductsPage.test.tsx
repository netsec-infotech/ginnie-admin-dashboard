// @vitest-environment jsdom
import type { ReactNode } from "react";
import { render } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { useInfiniteDataMock, routerMock } = vi.hoisted(() => ({
  useInfiniteDataMock: vi.fn(),
  routerMock: {
    asPath: "/brands/amul",
    query: { slug: "amul" },
    isReady: true,
    pathname: "/brands/[slug]",
    events: { on: vi.fn(), off: vi.fn() },
    push: vi.fn(),
  },
}));

vi.mock("@/hooks/useInfiniteData", () => ({
  useInfiniteData: useInfiniteDataMock,
}));

vi.mock("@/routes/api", () => ({
  getProducts: vi.fn(),
  getSettings: vi.fn(),
  getBrands: vi.fn(async () => ({ success: true, data: { data: [], total: 0 } })),
}));

vi.mock("@/helpers/getters", () => ({
  isSSR: () => false,
}));

vi.mock("next/router", () => ({
  useRouter: () => routerMock,
}));

vi.mock("react-i18next", () => ({
  initReactI18next: { type: "3rdParty", init: vi.fn() },
  useTranslation: () => ({
    t: (key: string, options?: { brand?: string; defaultValue?: string }) =>
      options?.defaultValue ?? options?.brand ?? key,
  }),
}));

vi.mock("@/components/custom/MyBreadcrumbs", () => ({ default: () => null }));
vi.mock("@/components/custom/PageHeader", () => ({ default: () => null }));
vi.mock("@/components/Products/ProductFilter", () => ({ default: () => null }));
vi.mock("@/components/Cards/ProductCard", () => ({ default: () => null }));
vi.mock("@/components/Skeletons/ProductCardSkeleton", () => ({ default: () => null }));
vi.mock("@/components/Functional/InfiniteScroll", () => ({
  default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));
vi.mock("@/components/Functional/InfiniteScrollStatus", () => ({ default: () => null }));
vi.mock("@/components/NoProductsFound", () => ({ default: () => null }));
vi.mock("@/components/Functional/InitialDataRetry", () => ({ default: () => null }));
vi.mock("@/SEO/DynamicSEO", () => ({ default: () => null }));
vi.mock("@/helpers/seo", () => ({
  generateBreadcrumbSchema: vi.fn(() => ({})),
  generateCollectionSchema: vi.fn(() => ({})),
  generateBrandMeta: vi.fn(() => ({
    brandName: "Amul",
    title: "Amul Products",
    description: "Browse Amul products.",
    keywords: "Amul, Amul products",
    image: undefined,
    ogTitle: "Amul Products",
    ogDescription: "Browse Amul products.",
    ogImage: undefined,
  })),
  stripHtmlTags: vi.fn((s: string) => s),
}));
vi.mock("@/services/staticGenerationService", () => ({
  getStaticBrandSlugs: vi.fn(async () => ["amul", "bata-1"]),
  getStaticBrand: vi.fn(async () => null),
  getStaticGenerationContext: vi.fn(async () => ({ settings: {}, latitude: "1", longitude: "2" })),
  getStaticApiResponse: vi.fn(async (_label: string, fn: () => unknown) => fn()),
}));

import BrandProductsPage, { getStaticPaths } from "@/pages/brands/[slug]/index";

describe("BrandProductsPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useInfiniteDataMock.mockReturnValue({
      data: [],
      isLoading: false,
      isLoadingMore: false,
      hasMore: false,
      total: 0,
      loadMore: vi.fn(),
      loadMoreError: null,
      refetch: vi.fn(),
      retryLoadMore: vi.fn(),
      error: null,
      isValidating: false,
    });
  });

  it("renders with a statically provided brand slug", () => {
    render(
      <BrandProductsPage
        initialProducts={{ success: true, data: { data: [], total: 0 } } as any}
        brandSlug="amul"
      />
    );

    expect(useInfiniteDataMock).toHaveBeenCalledWith(
      expect.objectContaining({
        dataKey: "/brands/amul",
        initialRequestFailed: false,
      })
    );
  });

  it("resolves slug dynamically from router when clientFallback is active", () => {
    routerMock.asPath = "/brands/amul";
    routerMock.isReady = true;

    render(
      <BrandProductsPage
        initialProducts={null}
        brandSlug=""
        clientFallback={true}
      />
    );

    expect(useInfiniteDataMock).toHaveBeenCalledWith(
      expect.objectContaining({
        dataKey: "/brands/amul",
        initialRequestFailed: false,
      })
    );
  });

  it("getStaticPaths returns mapped brand slugs with fallback false in static export", async () => {
    const pathsResult = await getStaticPaths({});
    expect(pathsResult).toEqual({
      paths: [{ params: { slug: "amul" } }, { params: { slug: "bata-1" } }],
      fallback: false,
    });
  });
});
