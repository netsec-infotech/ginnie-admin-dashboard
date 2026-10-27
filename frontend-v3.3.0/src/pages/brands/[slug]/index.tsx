import { GetServerSideProps, GetStaticPaths, GetStaticProps } from "next";
import { getBrands, getProducts, getSettings } from "@/routes/api";
import React, {
  useCallback,
  useMemo,
  useState,
  useEffect,
  useRef,
  useSyncExternalStore,
} from "react";
import { isSSR } from "@/helpers/getters";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import PageHeader from "@/components/custom/PageHeader";
import ProductCard from "@/components/Cards/ProductCard";
import ProductCardSkeleton from "@/components/Skeletons/ProductCardSkeleton";
import InfiniteScroll from "@/components/Functional/InfiniteScroll";
import { useInfiniteData } from "@/hooks/useInfiniteData";
import { Product, Brand, PaginatedResponse } from "@/types/ApiResponse";
import { NextPageWithLayout } from "@/types";
import { getAccessTokenFromContext } from "@/helpers/auth";
import InfiniteScrollStatus from "@/components/Functional/InfiniteScrollStatus";
import InitialDataRetry from "@/components/Functional/InitialDataRetry";
import { useRouter } from "next/router";
import { getUserLocationFromContext } from "@/helpers/functionalHelpers";
import NoProductsFound from "@/components/NoProductsFound";
import { ArrowRight, ShoppingCart } from "lucide-react";
import { loadTranslations } from "../../../../i18n";
import { formatString } from "@/helpers/validator";
import { useTranslation } from "react-i18next";
import { Button } from "@heroui/react";
import ProductFilter, {
  SelectedFilters,
  SortOption,
} from "@/components/Products/ProductFilter";
import DynamicSEO from "@/SEO/DynamicSEO";
import {
  generateBreadcrumbSchema,
  generateCollectionSchema,
  generateBrandMeta,
  stripHtmlTags,
} from "@/helpers/seo";
import {
  getStaticApiResponse,
  getStaticBrand,
  getStaticBrandSlugs,
  getStaticGenerationContext,
} from "@/services/staticGenerationService";

interface BrandProductsPageProps {
  initialProducts: PaginatedResponse<Product[]> | null;
  initialBrand?: Brand | null;
  initialFilters?: SelectedFilters;
  error?: string;
  brandSlug: string;
  clientFallback?: boolean;
}

const subscribeToHydration = () => () => {};
const clientSnapshot = () => true;
const serverSnapshot = () => false;

const PER_PAGE = 24;

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
  if (filters.search && filters.search.trim() !== "") {
    params.search = filters.search.trim();
  }

  return params;
};

const BrandProductsPage: NextPageWithLayout<BrandProductsPageProps> = ({
  initialProducts,
  initialBrand,
  initialFilters,
  brandSlug,
  clientFallback = false,
}) => {
  const router = useRouter();
  const { t } = useTranslation();
  const hydrated = useSyncExternalStore(
    subscribeToHydration,
    clientSnapshot,
    serverSnapshot,
  );

  let fallbackSlug: string | undefined;
  if (clientFallback && hydrated && router.isReady) {
    const match = router.asPath
      .split(/[?#]/)[0]
      .match(/^\/brands\/([^/]+)\/?$/);
    try {
      fallbackSlug = match ? decodeURIComponent(match[1]) : undefined;
    } catch {
      // Malformed URL encoding is treated as an unavailable brand.
    }
  }

  const slug = clientFallback
    ? fallbackSlug || ""
    : brandSlug || (router.query.slug as string) || "";

  const [brand, setBrand] = useState<Brand | null>(initialBrand || null);

  useEffect(() => {
    if (initialBrand) {
      setBrand(initialBrand);
      return;
    }
    if (!slug) return;
    getBrands({ scope_category_slug: "all" })
      .then((res) => {
        const found = res?.data?.data?.find((b) => b.slug === slug);
        if (found) setBrand(found);
      })
      .catch(() => {});
  }, [initialBrand, slug]);

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
    fetcher: (params) =>
      slug
        ? getProducts({ ...params, brands: slug })
        : Promise.resolve({
            success: true,
            data: { data: [], total: 0 },
          } as any),
    perPage: PER_PAGE,
    initialData: initialProducts?.data?.data || [],
    initialTotal: initialProducts?.data?.total || 0,
    initialRequestFailed:
      !clientFallback && initialProducts?.success !== true,
    passLocation: true,
    dataKey: slug ? `/brands/${slug}` : null,
    extraParams: {
      categories:
        selectedFilters?.categories?.length > 0
          ? selectedFilters.categories.join(",")
          : undefined,
      attribute_values:
        selectedFilters?.attribute_values?.length > 0
          ? selectedFilters.attribute_values.join(",")
          : undefined,
      colors:
        selectedFilters?.colors?.length > 0
          ? selectedFilters.colors.join(",")
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

  const brandName = brand?.title?.trim() || formatString(slug || "");
  const fallbackDescription = brandName
    ? t("pages.brandProducts.metaDescription", {
        brand: brandName,
        count: total,
        defaultValue: `Browse ${total} products from ${brandName}.`,
      })
    : t("pages.brandProducts.defaultMetaDescription", {
        defaultValue:
          "Shop quality brand products available for fast local delivery.",
      });

  const meta = generateBrandMeta({
    brand,
    slug,
    total,
    fallbackTitle: brandName ? `${brandName} ${t("products")}` : t("products"),
    fallbackDescription,
  });

  const updateURL = useCallback(
    async (filters: SelectedFilters) => {
      const queryParams = filtersToQueryParams(filters);

      const filteredParams = Object.fromEntries(
        Object.entries(queryParams).filter(([, value]) => value),
      );

      const isFilterCleared =
        filters.categories.length === 0 &&
        filters.colors.length === 0 &&
        filters.attribute_values.length === 0 &&
        filters.sort === "relevance" &&
        (!filters.search || filters.search.trim() === "");

      // Preserve the brand slug but exclude other filter params from being duplicated
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

      await router.push(
        {
          pathname: router.pathname,
          query: isFilterCleared
            ? preservedQuery
            : {
                ...preservedQuery,
                ...filteredParams,
              },
        },
        undefined,
        { shallow: true },
      );
    },
    [router, slug],
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

  // Sync filters when they change in URL (for browser back/forward)
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

  return (
    <>
      <DynamicSEO
        title={meta.title}
        description={meta.description}
        keywords={meta.keywords}
        canonical={`/brands/${slug}`}
        ogType="website"
        ogTitle={meta.ogTitle}
        ogDescription={meta.ogDescription}
        ogImage={meta.ogImage}
        jsonLd={[
          generateCollectionSchema(
            meta.title,
            meta.description,
            `/brands/${slug}`,
          ),
          generateBreadcrumbSchema([
            { name: "Home", url: "/" },
            { name: t("pageTitle.brands"), url: "/brands" },
            { name: meta.brandName, url: `/brands/${slug}` },
          ]),
        ]}
      />

      <div className="min-h-screen">
        <MyBreadcrumbs
          breadcrumbs={[
            {
              href: "/brands",
              label: t("pages.brandProducts.breadcrumb.brands"),
            },
            { href: `/brands/${slug}`, label: meta.brandName },
          ]}
        />

        <button
          id="refetch-brand-products"
          className="hidden"
          onClick={() => {
            refetch();
          }}
        />

        <PageHeader
          title={t("pages.brandProducts.title", {
            brand: meta.brandName,
          })}
          subtitle={
            brand?.description
              ? stripHtmlTags(brand.description).trim()
              : t("pages.brandProducts.subtitle", {
                  brand: meta.brandName,
                })
          }
          highlightText={
            total ? t("pages.brandProducts.highlight", { count: total }) : ""
          }
        />

        <div className="flex w-full gap-2 flex-col md:flex-row">
          <div className="flex-none h-full">
            <ProductFilter
              selectedFilters={selectedFilters}
              setSelectedFilters={setSelectedFilters}
              onApplyFilters={onApplyFilters}
              totalProducts={total}
              sidebarType="brand"
              sidebarValue={slug}
              searchComponent={true}
              hideBrandFilter={true}
            />
          </div>

          <div className="flex-1">
            <InfiniteScroll
              hasMore={hasMore}
              isLoading={isLoadingMore}
              onLoadMore={loadMore}
            >
              <div className="grid grid-cols-2 xs:grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                {isLoading && !error && products.length === 0
                  ? Array.from({ length: PER_PAGE }).map((_, i) => (
                      <ProductCardSkeleton key={i} />
                    ))
                  : products.map((product) => (
                      <ProductCard key={product.id} product={product} />
                    ))}
              </div>

              {isLoadingMore && (
                <div className="grid grid-cols-2 xs:grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2 mt-6">
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
                  title={t("pages.brandProducts.noProducts.title")}
                  description={t("pages.brandProducts.noProducts.description")}
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
      </div>
    </>
  );
};

export const getRequestTimeBrandProps: GetServerSideProps | undefined = isSSR()
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

        const initialFilters = parseFiltersFromQuery(context.query);

        const apiParams: any = {
          page: 1,
          per_page: PER_PAGE,
          brands: slug,
          access_token,
          latitude: lat,
          longitude: lng,
          include_child_categories: 0,
        };

        if (initialFilters.categories.length > 0) {
          apiParams.categories = initialFilters.categories.join(",");
        }
        if (initialFilters.attribute_values.length > 0) {
          apiParams.attribute_values =
            initialFilters.attribute_values.join(",");
        }
        if (initialFilters.colors.length > 0) {
          apiParams.colors = initialFilters.colors.join(",");
        }
        if (initialFilters.sort) {
          apiParams.sort = initialFilters.sort;
        }
        if (initialFilters.search) {
          apiParams.search = initialFilters.search;
        }

        const [products, settings, brandsRes] = await Promise.all([
          getProducts(apiParams),
          getSettings(),
          getBrands({ scope_category_slug: "all" }),
        ]);

        const initialBrand =
          brandsRes?.data?.data?.find((b) => b.slug === slug) ?? null;

        return {
          props: {
            initialProducts: products,
            initialSettings: settings.data,
            brandSlug: slug,
            initialFilters,
            initialBrand,
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
              search: "",
            },
            initialSettings: null,
            brandSlug: "",
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
      paths: (await getStaticBrandSlugs()).map((slug) => ({ params: { slug } })),
      fallback: isSSR() ? "blocking" : false,
    });

export const getStaticProps: GetStaticProps = async (context) => {
      const slug = context.params?.slug;
      if (!slug || Array.isArray(slug)) return { notFound: true };

      try {
        const { settings, latitude, longitude } =
          await getStaticGenerationContext();
        const initialFilters = parseFiltersFromQuery({});

        const [products, initialBrand] = await Promise.all([
          getStaticApiResponse(
            `brand products for ${slug}`,
            () =>
              getProducts({
                page: 1,
                per_page: PER_PAGE,
                brands: slug,
                latitude,
                longitude,
                include_child_categories: 0,
              }),
          ).catch(() => null),
          getStaticBrand(slug).catch(() => null),
        ]);

        return {
          props: {
            initialProducts: products,
            initialSettings: settings,
            brandSlug: slug,
            initialFilters,
            initialBrand: initialBrand ?? null,
          },
          ...(isSSR() ? { revalidate: 300 } : {}),
        };
      } catch (err) {
        console.error(`Error generating static page for brand ${slug}:`, err);
        return {
          props: {
            initialProducts: null,
            initialSettings: null,
            brandSlug: slug,
            initialFilters: parseFiltersFromQuery({}),
            initialBrand: null,
          },
          ...(isSSR() ? { revalidate: 300 } : {}),
        };
      }
    };

export default BrandProductsPage;
