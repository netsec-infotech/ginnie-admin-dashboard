import { GetServerSideProps } from "next";
import { useMemo } from "react";
import { useRouter } from "next/router";
import { useTranslation } from "react-i18next";
import { ArrowRight, ShoppingCart } from "lucide-react";
import { Button } from "@heroui/react";

import { getHomeLayoutSection, getSettings } from "@/routes/api";
import { isSSR } from "@/helpers/getters";
import { adaptHomeLayoutProducts } from "@/helpers/homeLayout";
import { useInfiniteData } from "@/hooks/useInfiniteData";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { PaginatedResponse, Product } from "@/types/ApiResponse";
import { NextPageWithLayout } from "@/types";
import { getAccessTokenFromContext } from "@/helpers/auth";
import { getUserLocationFromContext } from "@/helpers/functionalHelpers";
import { loadTranslations } from "../../../../i18n";

import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import PageHeader from "@/components/custom/PageHeader";
import DynamicSEO from "@/SEO/DynamicSEO";
import {
  generateBreadcrumbSchema,
  generateCollectionSchema,
} from "@/helpers/seo";
import ProductCard from "@/components/Cards/ProductCard";
import ProductCardSkeleton from "@/components/Skeletons/ProductCardSkeleton";
import InfiniteScroll from "@/components/Functional/InfiniteScroll";
import InfiniteScrollStatus from "@/components/Functional/InfiniteScrollStatus";
import NoProductsFound from "@/components/NoProductsFound";

const PER_PAGE = 24;

interface SectionPageProps {
  initialProducts: PaginatedResponse<Product[]> | null;
  sectionId: string;
  sectionTitle?: string;
  error?: string;
}

/**
 * "View all" / load-more feed for a single /home-layout section.
 * The section is addressed by its numeric id (the new endpoint is id-keyed,
 * unlike the slug-based legacy /feature-sections pages).
 */
const SectionPage: NextPageWithLayout<SectionPageProps> = ({
  initialProducts,
  sectionId,
  sectionTitle,
}) => {
  const router = useRouter();
  const { t } = useTranslation();

  const id = sectionId || (router.query.id as string) || "";
  const title =
    sectionTitle || (router.query.title as string) || t("pageTitle.products");

  // The feed returns the raw home-layout product shape — adapt before render.
  const initialData = useMemo(
    () => adaptHomeLayoutProducts((initialProducts?.data?.data as unknown[]) ?? []),
    [initialProducts],
  );

  const {
    data: products,
    isLoading,
    isLoadingMore,
    hasMore,
    total,
    loadMore,
    refetch,
  } = useInfiniteData<Product>({
    fetcher: async (params) => {
      // Guard the pre-hydration render where router.query.id isn't resolved yet;
      // dataKey below carries the id, so this refetches once it is.
      if (!id) return { success: false, data: { data: [], total: 0 } };

      const { lat = "", lng = "" } =
        (getCookie("userLocation") as UserLocation) || {};

      const res = await getHomeLayoutSection(id, {
        ...params,
        latitude: lat || undefined,
        longitude: lng || undefined,
      });

      if (!res.success || !res.data) return res;

      return {
        ...res,
        data: {
          ...res.data,
          data: adaptHomeLayoutProducts(res.data.data as unknown[]),
        },
      };
    },
    perPage: PER_PAGE,
    initialData,
    initialTotal: initialProducts?.data?.total ?? 0,
    dataKey: `home-layout-section-${id}`,
  });

  return (
    <>
      {/* Canonical is deliberately the bare /section/{id} — `title` can arrive
          as a ?title= query param, and every variant of it is the same page.
          Pointing them all at one URL stops those from being indexed separately. */}
      <DynamicSEO
        title={title}
        description={t("pages.featureSection.metaDescription", {
          title,
          count: total,
        })}
        keywords={`${title}, ${title} products`}
        canonical={`/section/${id}`}
        ogType="website"
        ogTitle={title}
        ogDescription={t("pages.featureSection.metaDescription", {
          title,
          count: total,
        })}
        jsonLd={[
          generateCollectionSchema(
            title,
            t("pages.featureSection.metaDescription", {
              title,
              count: total,
            }),
            `/section/${id}`,
          ),
          generateBreadcrumbSchema([
            { name: "Home", url: "/" },
            { name: title, url: `/section/${id}` },
          ]),
        ]}
      />

      <div className="min-h-screen">
        <MyBreadcrumbs
          breadcrumbs={[{ href: `/section/${id}`, label: title }]}
        />

        <button
          id="refetch-section-products"
          className="hidden"
          onClick={() => refetch()}
        />

        <PageHeader
          title={title}
          subtitle={t("pages.featureSection.subtitle")}
          highlightText={
            total ? ` ${total} ${t("pages.featureSection.products")}` : title
          }
        />

        <InfiniteScroll
          hasMore={hasMore}
          isLoading={isLoadingMore}
          onLoadMore={loadMore}
        >
          <div className="grid grid-cols-2 xs:grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
            {isLoading
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

          {!isLoading && products.length === 0 && (
            <NoProductsFound
              icon={ShoppingCart}
              title={t("no_products_found")}
              description={t("no_products_available")}
              customActions={
                <div className="flex w-full justify-center items-center">
                  <Button
                    color="primary"
                    className="h-8"
                    variant="solid"
                    onPress={() => router.push("/")}
                    endContent={<ArrowRight size={16} />}
                  >
                    {t("home_title")}
                  </Button>
                </div>
              }
            />
          )}

          {products.length > 0 && (
            <InfiniteScrollStatus
              entityType="product"
              total={total}
              hasMore={hasMore}
            />
          )}
        </InfiniteScroll>
      </div>
    </>
  );
};

export const getServerSideProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      try {
        const { id } = context.params || {};
        await loadTranslations(context);

        if (!id || Array.isArray(id)) {
          return { notFound: true };
        }

        const access_token = (await getAccessTokenFromContext(context)) || "";
        const { lat = "", lng = "" } =
          (await getUserLocationFromContext(context)) || {};

        const [products, settings] = await Promise.all([
          getHomeLayoutSection(id, {
            page: 1,
            per_page: PER_PAGE,
            latitude: lat || undefined,
            longitude: lng || undefined,
            access_token,
          }),
          getSettings({ access_token }),
        ]);

        return {
          props: {
            initialProducts: products,
            initialSettings: settings.data ?? null,
            sectionId: id,
            sectionTitle: (context.query.title as string) || "",
          },
        };
      } catch (err) {
        console.error("Error in getServerSideProps:", err);
        return {
          props: {
            initialProducts: null,
            initialSettings: null,
            sectionId: "",
            error:
              err instanceof Error ? err.message : "An error occurred during SSR",
          },
        };
      }
    }
  : undefined;

export default SectionPage;
