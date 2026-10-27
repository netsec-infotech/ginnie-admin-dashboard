import { GetServerSideProps, GetStaticPaths, GetStaticProps } from "next";
import { getProducts, getSettings } from "@/routes/api";
import React, {
  useState,
  useEffect,
  useCallback,
  useMemo,
  useRef,
  useSyncExternalStore,
} from "react";
import { isSSR } from "@/helpers/getters";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import PageHeader from "@/components/custom/PageHeader";
import ProductCard from "@/components/Cards/ProductCard";
import ProductCardSkeleton from "@/components/Skeletons/ProductCardSkeleton";
import StoreProfileSkeleton from "@/components/Skeletons/StoreProfileSkeleton";
import InfiniteScroll from "@/components/Functional/InfiniteScroll";
import { useInfiniteData } from "@/hooks/useInfiniteData";
import ProductFilter from "@/components/Products/ProductFilter";
import { Product, PaginatedResponse, Store } from "@/types/ApiResponse";
import { NextPageWithLayout } from "@/types";
import { getAccessTokenFromContext } from "@/helpers/auth";
import InfiniteScrollStatus from "@/components/Functional/InfiniteScrollStatus";
import InitialDataRetry from "@/components/Functional/InitialDataRetry";
import { useRouter } from "next/router";
import { formatString } from "@/helpers/validator";
import NoProductsFound from "@/components/NoProductsFound";
import { ArrowRight, PackageSearch, ShoppingCart } from "lucide-react";
import { loadTranslations } from "../../../../i18n";
import DynamicSEO from "@/SEO/DynamicSEO";
import {
  generateStoreSchema,
  generateBreadcrumbSchema,
  generateMetaDescription,
} from "@/helpers/seo";
import { useTranslation } from "react-i18next";
import { Button, Tab, Tabs } from "@heroui/react";
import { getUserLocationFromContext } from "@/helpers/functionalHelpers";
import StoreProfile from "@/components/StoreProfile";
import useSWR from "swr";
import { trackStoreView } from "@/lib/analytics";
import {
  SelectedFilters,
  SortOption,
} from "@/components/Products/ProductFilter";
import { useSettings } from "@/contexts/SettingsContext";
import {
  getStaticApiResponse,
  getStaticGenerationContext,
  getStaticStoreSlugs,
  getStaticStore,
} from "@/services/staticGenerationService";
import { WatchAndBuyReelsSection } from "@/components/WatchAndBuy";
import { toStoreReels } from "@/components/WatchAndBuy/adapters";
import { getStoreWithListingFallback } from "@/services/storeDetailService";
import { getCookie } from "@/lib/cookies";
import type { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { getConfiguredDefaultLocation, getWebSettings } from "@/helpers/getters";

interface StoreProductsPageProps {
  initialProducts: PaginatedResponse<Product[]> | null;
  initialFilters?: SelectedFilters;
  error?: string;
  storeSlug: string;
  initialStore: Store | null;
  clientFallback?: boolean;
}

const PER_PAGE = 24;
const subscribeToHydration = () => () => {};
const clientSnapshot = () => true;
const serverSnapshot = () => false;

const StoreReelsIcon = ({
  size = 16,
  ...props
}: React.SVGProps<SVGSVGElement> & { size?: number }) => (
  <svg
    aria-hidden="true"
    fill="none"
    height={size}
    viewBox="0 0 24 24"
    width={size}
    {...props}
  >
    <rect
      height="18"
      rx="4"
      stroke="currentColor"
      strokeWidth="1.8"
      width="18"
      x="3"
      y="3"
    />
    <path
      d="M3.5 9h17M7 3l4 6M13 3l4 6"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.8"
    />
    <path
      d="m10 13 5 3-5 3v-6Z"
      fill="currentColor"
      stroke="currentColor"
      strokeLinejoin="round"
      strokeWidth="1.2"
    />
  </svg>
);

// Helper function to parse query parameters into filters
const parseFiltersFromQuery = (query: {
  [key: string]: string | string[] | undefined;
}): SelectedFilters => {
  const parseQueryParam = (param: string | string[] | undefined): string[] => {
    if (!param) return [];
    if (Array.isArray(param)) return param;
    return param.split(",");
  };

  const parseSingleParam = (param: string | string[] | undefined): string => {
    if (!param) return "";
    if (Array.isArray(param)) return param[0] || "";
    return param;
  };

  return {
    categories: parseQueryParam(query.categories),
    brands: parseQueryParam(query.brands),
    colors: parseQueryParam(query.colors),
    attribute_values: parseQueryParam(query.attribute_values),
    sort: query.sort ? (query.sort as SortOption) : "relevance",
    search: parseSingleParam(query.search),
  };
};

// Helper function to convert filters to query parameters
const filtersToQueryParams = (
  filters: SelectedFilters,
): Record<string, string> => {
  const params: Record<string, string> = {};

  if (filters.categories.length > 0) {
    params.categories = filters.categories.join(",");
  }
  if (filters.brands.length > 0) {
    params.brands = filters.brands.join(",");
  }
  if (filters.colors.length > 0) {
    params.colors = filters.colors.join(",");
  }
  if (filters.attribute_values.length > 0) {
    params.attribute_values = filters.attribute_values.join(",");
  }
  if (filters.sort) {
    params.sort = filters.sort;
  }
  // Include search as a query param (single string)
  if (filters.search && filters.search.trim() !== "") {
    params.search = filters.search.trim();
  }

  return params;
};

const StoreProductsPage: NextPageWithLayout<StoreProductsPageProps> = ({
  initialProducts,
  initialFilters,
  storeSlug,
  initialStore,
  clientFallback = false,
}) => {
  const router = useRouter();
  const { t } = useTranslation();
  const { currencySymbol, isSingleVendor, defaultLocation } = useSettings();
  const hydrated = useSyncExternalStore(subscribeToHydration, clientSnapshot, serverSnapshot);
  let fallbackSlug = "";
  if (clientFallback && hydrated && router.isReady) {
    const match = router.asPath.split(/[?#]/)[0].match(/^\/stores\/([^/]+)\/?$/);
    try {
      fallbackSlug = match ? decodeURIComponent(match[1]) : "";
    } catch {
      // Malformed URL encoding cannot identify a store.
    }
  }
  const slug = clientFallback ? fallbackSlug : storeSlug || (router.query.slug as string) || "";

  // Initialize filters from URL query params when SSR is false
  const computedInitialFilters = useMemo(() => {
    if (initialFilters) {
      return initialFilters;
    }
    if (router.isReady) {
      return parseFiltersFromQuery(router.query);
    }
    return {
      categories: [],
      brands: [],
      colors: [],
      attribute_values: [],
      sort: "relevance" as SortOption,
      search: "",
    };
  }, [initialFilters, router.isReady, router.query]);

  const [selectedFilters, setSelectedFilters] = useState<SelectedFilters>(
    computedInitialFilters,
  );
  const isInternalRouteUpdate = useRef(false);

  // Use SWR for client-side store fetching when SSR is false
  const { data: storeData, isLoading: isStoreLoading } = useSWR<
    Store | undefined
  >(
    slug ? `/stores/${slug}` : null,
    async () => {
      if (!slug) {
        console.error("Slug is required");
        return undefined;
      }
      const userLocation = getCookie<UserLocation>("userLocation");
      const store = await getStoreWithListingFallback(slug, {
        latitude: userLocation?.lat ?? defaultLocation?.lat,
        longitude: userLocation?.lng ?? defaultLocation?.lng,
      });
      if (!store) {
        console.error("Store data not found");
        return undefined;
      }
      return store;
    },
    {
      fallbackData: initialStore ?? undefined,
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      revalidateOnMount: clientFallback || !isSSR(),
    },
  );

  // Determine which store data to use
  const store = storeData ?? initialStore;
  const storeReels = useMemo(
    () => (store ? toStoreReels(store, currencySymbol) : []),
    [currencySymbol, store],
  );

  // Track store view analytics
  useEffect(() => {
    if (store) {
      trackStoreView(store.slug, store.name);
    }
  }, [store]);

  const {
    data: products,
    isLoading,
    isLoadingMore,
    hasMore,
    total,
    loadMore,
    loadMoreError,
    refetch,
    retryLoadMore,
    error,
    isValidating,
  } = useInfiniteData<Product>({
    fetcher: (params) => slug
      ? getProducts({ ...params, store: slug })
      : Promise.resolve({
          success: true,
          message: "",
          data: { current_page: 1, data: [], per_page: PER_PAGE, total: 0 },
        } as PaginatedResponse<Product[]>),
    perPage: PER_PAGE,
    initialData: initialProducts?.data?.data || [],
    initialTotal: initialProducts?.data?.total || 0,
    initialRequestFailed: !clientFallback && initialProducts?.success !== true,
    passLocation: true,
    dataKey: slug ? `/stores/${slug}` : null,
    extraParams: {
      categories:
        selectedFilters?.categories?.length > 0
          ? selectedFilters.categories.join(",")
          : undefined,
      brands:
        selectedFilters?.brands?.length > 0
          ? selectedFilters.brands.join(",")
          : undefined,
      colors:
        selectedFilters?.colors?.length > 0
          ? selectedFilters.colors.join(",")
          : undefined,
      attribute_values:
        selectedFilters?.attribute_values?.length > 0
          ? selectedFilters.attribute_values.join(",")
          : undefined,
      sort: selectedFilters?.sort ? selectedFilters.sort : undefined,
      search:
        typeof selectedFilters?.search === "string" &&
        selectedFilters.search.trim() !== ""
          ? selectedFilters.search.trim()
          : undefined,
      include_child_categories: 0,
    },
  });

  // Update URL when filters change
  const updateURL = useCallback(
    async (filters: SelectedFilters) => {
      const queryParams = filtersToQueryParams(filters);

      const filteredParams = Object.fromEntries(
        Object.entries(queryParams).filter(([, value]) => value),
      );

      // Check if all filters are empty (clear all case)
      const isFilterCleared =
        filters.categories.length === 0 &&
        filters.brands.length === 0 &&
        filters.colors.length === 0 &&
        filters.attribute_values.length === 0 &&
        filters.sort === "relevance" &&
        (!filters.search || filters.search.trim() === "");

      // Preserve any non-filter query parameters
      const preservedQuery = Object.fromEntries(
        Object.entries(router.query || {}).filter(
          ([key]) =>
            ![
              "categories",
              "brands",
              "colors",
              "sort",
              "search",
              "attribute_values",
            ].includes(key),
        ),
      );

      if (slug) {
        preservedQuery.slug = slug;
      }

      const nextQuery = isFilterCleared
        ? preservedQuery
        : { ...preservedQuery, ...filteredParams };
      const publicQuery = { ...nextQuery };
      delete publicQuery.slug;
      await router.push(
        {
          pathname: router.pathname,
          query: nextQuery,
        },
        clientFallback
          ? { pathname: `/stores/${encodeURIComponent(slug)}`, query: publicQuery }
          : undefined,
        { shallow: true },
      );
    },
    [clientFallback, router, slug],
  );

  const onApplyFilters = useCallback(
    async (filters: SelectedFilters) => {
      setSelectedFilters(filters);
      isInternalRouteUpdate.current = true;
      try {
        await updateURL(filters);
      } finally {
        isInternalRouteUpdate.current = false;
      }
    },
    [updateURL],
  );

  // Listen for browser back/forward navigation
  useEffect(() => {
    const handleRouteChange = () => {
      if (isInternalRouteUpdate.current) return;

      const newFilters = parseFiltersFromQuery(router.query);
      setSelectedFilters((prev) => {
        if (JSON.stringify(prev) === JSON.stringify(newFilters)) return prev;
        return newFilters;
      });
    };

    router.events.on("routeChangeComplete", handleRouteChange);
    return () => {
      router.events.off("routeChangeComplete", handleRouteChange);
    };
  }, [router.events, router.query]);

  useEffect(() => {
    if (isSingleVendor) {
      router.replace("/");
    }
  }, [isSingleVendor, router]);

  // Don't render any content if single vendor – avoids flash before redirect
  if (isSingleVendor) return null;

  // Generate SEO data for store
  const storeSchema = store ? generateStoreSchema(store) : null;
  const breadcrumbSchema = generateBreadcrumbSchema([
    { name: "Home", url: "/" },
    { name: t("pages.storeProductsPage.breadcrumbs.stores"), url: "/stores" },
    { name: store?.name || formatString(slug), url: `/stores/${slug}` },
  ]);

  const metaDescription = store
    ? generateMetaDescription(
        store.description ||
          `${store.name} - Shop quality products from this store`,
        160,
      )
    : t("pages.storeProductsPage.subtitle");

  return (
    <>
      {store ? (
        <DynamicSEO
          title={store.name}
          description={metaDescription}
          keywords={`${store.name}, store, shop, products`}
          canonical={`/stores/${slug}`}
          ogType="website"
          ogTitle={store.name}
          ogDescription={metaDescription}
          ogImage={store.logo}
          ogImageAlt={store.name}
          jsonLd={[storeSchema, breadcrumbSchema].filter(Boolean)}
        />
      ) : (
        <DynamicSEO
          title={t("pageTitle.store-products")}
          description={t("pages.storeProductsPage.subtitle")}
          canonical={`/stores/${slug}`}
        />
      )}

      <div className="min-h-screen">
        <MyBreadcrumbs
          breadcrumbs={[
            {
              href: "/stores",
              label: t("pages.storeProductsPage.breadcrumbs.stores"),
            },
            { href: `/stores/${slug}`, label: formatString(slug) },
          ]}
        />

        <button
          id="refetch-store-products"
          className="hidden"
          onClick={() => {
            refetch();
          }}
        />

        <PageHeader
          headingLevel={2}
          title={t("pages.storeProductsPage.title")}
          subtitle={t("pages.storeProductsPage.subtitle")}
          highlightText={
            total
              ? t("pages.storeProductsPage.highlight", { count: total })
              : ""
          }
        />
        <div className="flex flex-col items-center justify-center gap-3 sm:gap-4">
          {/* Show skeleton while loading store on client-side */}
          {isStoreLoading && !store ? (
            <StoreProfileSkeleton />
          ) : store ? (
            <StoreProfile store={store} />
          ) : null}

          <Tabs
            aria-label={t("pages.storeProductsPage.tabs.label", {
              defaultValue: "Store content",
            })}
            className="w-full"
            classNames={{
              base: "w-full",
              tabList:
                "w-full justify-center gap-8 rounded-none bg-transparent p-0 shadow-none sm:gap-14",
              tab: "h-10 w-auto flex-none rounded-none border-b-2 border-transparent px-1 text-[11px] font-semibold uppercase tracking-[0.08em] data-[selected=true]:border-foreground sm:h-11",
              cursor: "hidden",
              tabContent:
                "text-default-500 transition-colors group-data-[selected=true]:text-foreground",
              panel: "w-full px-0 pt-2 sm:pt-4",
            }}
            color="default"
            defaultSelectedKey="products"
            variant="underlined"
          >
            <Tab
              key="products"
              title={
                <span className="flex items-center gap-2">
                  <PackageSearch aria-hidden="true" size={14} />
                  {t("pages.storeProductsPage.tabs.products", {
                    defaultValue: "Products",
                  })}
                </span>
              }
            >
              <div className="flex w-full gap-2 flex-col md:flex-row">
                <div className="flex-none h-full">
                  <ProductFilter
                    selectedFilters={selectedFilters}
                    setSelectedFilters={setSelectedFilters}
                    onApplyFilters={onApplyFilters}
                    totalProducts={total}
                    searchComponent={true}
                    sidebarType="store"
                    sidebarValue={slug}
                  />
                </div>

                <div className="min-w-0 flex-1">
                  <InfiniteScroll
                    hasMore={hasMore}
                    isLoading={isLoadingMore}
                    onLoadMore={loadMore}
                  >
                    <div className="grid grid-cols-2 xs:grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2">
                      {isLoading && !error && products.length === 0
                        ? Array.from({ length: PER_PAGE }).map((_, i) => (
                            <ProductCardSkeleton key={i} />
                          ))
                        : products.map((product) => (
                            <ProductCard
                              key={product.id}
                              product={product}
                              hideStoreName={true}
                            />
                          ))}
                    </div>

                    {isLoadingMore && (
                      <div className="grid grid-cols-2 xs:grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2 mt-6">
                        {Array.from({ length: PER_PAGE }).map((_, i) => (
                          <ProductCardSkeleton key={`loading-${i}`} />
                        ))}
                      </div>
                    )}

                    {error && products.length === 0 ? (
                      <InitialDataRetry
                        isRetrying={isValidating}
                        onRetry={() => void refetch()}
                      />
                    ) : products.length > 0 ? (
                      <InfiniteScrollStatus
                        entityType="product"
                        total={total}
                        hasMore={hasMore}
                        error={loadMoreError}
                        isRetrying={isLoadingMore}
                        onRetry={retryLoadMore}
                      />
                    ) : (
                      <NoProductsFound
                        icon={ShoppingCart}
                        title={t("pages.storeProductsPage.noProducts.title")}
                        description={t(
                          "pages.storeProductsPage.noProducts.description",
                        )}
                        customActions={
                          <div className="flex w-full justify-center items-center">
                            <Button
                              color="primary"
                              className="h-8"
                              variant="solid"
                              onPress={() => {
                                router.push("/");
                              }}
                              endContent={<ArrowRight size={16} />}
                            >
                              {t("home_title")}
                            </Button>
                          </div>
                        }
                      />
                    )}
                  </InfiniteScroll>
                </div>
              </div>
            </Tab>
            <Tab
              key="reels"
              title={
                <span className="flex items-center gap-2">
                  <StoreReelsIcon size={14} />
                  {t("pages.storeProductsPage.tabs.reels", {
                    defaultValue: "Reels",
                  })}
                </span>
              }
            >
              {store ? (
                <WatchAndBuyReelsSection
                  className="mb-0"
                  emptyState={
                    <div className="flex min-h-80 w-full items-center justify-center rounded-3xl border border-dashed border-default-300 bg-linear-to-b from-content1 to-default-50 px-6 py-12 text-center">
                      <div className="max-w-md">
                        <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                          <StoreReelsIcon size={30} />
                        </span>
                        <h2 className="mt-5 text-xl font-bold text-foreground">
                          {t("pages.storeProductsPage.reels.emptyTitle", {
                            defaultValue: "No Store Reels yet",
                          })}
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-foreground/60">
                          {t("pages.storeProductsPage.reels.emptyDescription", {
                            defaultValue:
                              "Shoppable videos from {{store}} will appear here when they become available.",
                            store: store.name,
                          })}
                        </p>
                      </div>
                    </div>
                  }
                  fallbackReels={storeReels}
                  hideWhenEmpty={false}
                  infiniteScroll
                  layout="grid"
                  sectionId={`store-${store.id}-reels`}
                  showHeader={false}
                  showSeeAll={false}
                  storeSlug={store.slug}
                />
              ) : null}
            </Tab>
          </Tabs>
        </div>
      </div>
    </>
  );
};

export const getRequestTimeStoreProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      try {
        const { slug } = context.params || {};
        const access_token = (await getAccessTokenFromContext(context)) || "";
        const { lat = "", lng = "" } =
          (await getUserLocationFromContext(context)) || {};
        await loadTranslations(context);

        if (!slug || Array.isArray(slug)) {
          return {
            notFound: true,
          };
        }

        // Parse initial filters from query parameters
        const initialFilters = parseFiltersFromQuery(context.query);

        // Build API parameters with filters
        const apiParams: any = {
          page: 1,
          per_page: PER_PAGE,
          store: slug,
          latitude: lat,
          longitude: lng,
          access_token,
          include_child_categories: 0,
        };

        if (initialFilters.categories.length > 0) {
          apiParams.categories = initialFilters.categories.join(",");
        }
        if (initialFilters.brands.length > 0) {
          apiParams.brands = initialFilters.brands.join(",");
        }
        if (initialFilters.colors.length > 0) {
          apiParams.colors = initialFilters.colors.join(",");
        }
        if (initialFilters.attribute_values.length > 0) {
          apiParams.attribute_values =
            initialFilters.attribute_values.join(",");
        }
        if (initialFilters.sort) {
          apiParams.sort = initialFilters.sort;
        }
        if (initialFilters.search) {
          apiParams.search = initialFilters.search;
        }

        const products = await getProducts(apiParams);
        const settings = await getSettings();
        const fallbackLocation = getConfiguredDefaultLocation(getWebSettings(settings.data));
        const store = await getStoreWithListingFallback(slug, {
          latitude: lat || fallbackLocation?.lat,
          longitude: lng || fallbackLocation?.lng,
        });

        // Server-side redirect for single vendor mode
        const systemSettings = settings?.data?.find(
          (s: { variable: string }) => s.variable === "system",
        )?.value as { systemVendorType?: string } | undefined;
        if (systemSettings?.systemVendorType === "single") {
          return { redirect: { destination: "/", permanent: false } };
        }

        return {
          props: {
            initialProducts: products,
            initialFilters,
            initialSettings: settings.data,
            storeSlug: slug,
            initialStore: store,
          },
        };
      } catch (err) {
        console.error("Error in getServerSideProps:", err);
        return {
          props: {
            initialProducts: null,
            initialFilters: {
              categories: [],
              brands: [],
              colors: [],
              attribute_values: [],
              sort: "relevance",
            },
            initialSettings: null,
            initialStore: null,
            storeSlug: "",
            error:
              err instanceof Error
                ? err.message
                : "An error occurred during SSR",
          },
        };
      }
    }
  : undefined;

export const getStaticPaths: GetStaticPaths = async () => ({
  paths: (await getStaticStoreSlugs()).map((slug) => ({ params: { slug } })),
  fallback: isSSR() ? "blocking" : false,
});

export const getStaticProps: GetStaticProps = async (context) => {
  const slug = context.params?.slug;
  if (!slug || Array.isArray(slug)) return { notFound: true };

  const { settings, latitude, longitude } = await getStaticGenerationContext();
  const initialFilters = parseFiltersFromQuery({});
  const [products, store] = await Promise.all([
    getStaticApiResponse(`store products for ${slug}`, () =>
      getProducts({
        page: 1,
        per_page: PER_PAGE,
        store: slug,
        latitude,
        longitude,
        include_child_categories: 0,
      }),
    ),
    // Export the same listed stores as the sitemap, even when the optional
    // detail endpoint fails. Runtime requests retain the live detail fallback.
    !isSSR() && process.env.NODE_ENV === "production"
      ? getStaticStore(slug)
      : getStoreWithListingFallback(slug, { latitude, longitude }),
  ]);

  if (!store) return { notFound: true };

  return {
    props: {
      initialProducts: products,
      initialFilters,
      initialSettings: settings,
      storeSlug: slug,
      initialStore: store,
    },
    ...(isSSR() ? { revalidate: 300 } : {}),
  };
};

export default StoreProductsPage;
