import { useEffect, useId, useReducer } from "react";
import { useTranslation } from "react-i18next";
import { useSelector } from "react-redux";
import useSWR from "swr";
import ProductCard from "@/components/Cards/ProductCard";
import ProductCardSkeleton from "@/components/Skeletons/ProductCardSkeleton";
import { useSettings } from "@/contexts/SettingsContext";
import { LOCATION_CHANGED_EVENT } from "@/helpers/events";
import { getCookie, subscribeToAuthSessionChanges } from "@/lib/cookies";
import type { RootState } from "@/lib/redux/store";
import { getProductBySlug } from "@/routes/api";
import type { Product } from "@/types/ApiResponse";
import type { BlogPost } from "@/types/blog";

const canRenderProduct = (
  product: Product | null | undefined,
): product is Product =>
  Boolean(
    product &&
    product.id &&
    product.slug &&
    product.title &&
    product.store_status &&
    Array.isArray(product.variants) &&
    product.variants.length > 0,
  );

function LinkedCatalogProduct({
  slug,
  latitude,
  longitude,
  session,
  scopeVersion,
}: {
  slug: string;
  latitude: number;
  longitude: number;
  session: string;
  scopeVersion: number;
}) {
  const { t, i18n } = useTranslation();
  const { data, error, isValidating, mutate } = useSWR(
    [
      "blog-catalog-product",
      slug,
      latitude,
      longitude,
      session,
      scopeVersion,
      i18n.resolvedLanguage || i18n.language,
    ],
    async () => {
      const response = await getProductBySlug({
        slug: encodeURIComponent(slug),
        latitude,
        longitude,
      });
      if (
        !response.success ||
        !canRenderProduct(response.data) ||
        response.data.slug !== slug
      ) {
        throw new Error("Linked catalog product is unavailable");
      }
      return response.data;
    },
    {
      keepPreviousData: false,
      revalidateOnFocus: false,
      shouldRetryOnError: false,
    },
  );

  if (data) return <ProductCard product={data} />;
  if (error) {
    return (
      <div className="flex flex-col items-start justify-center gap-3 rounded-xl border border-default-200 p-4 text-sm">
        <p role="alert" className="text-default-500">
          {t("blog.productsLoadError")}
        </p>
        <button
          type="button"
          className="text-xs font-medium text-primary underline underline-offset-4 disabled:opacity-50"
          disabled={isValidating}
          onClick={() => void mutate()}
        >
          {t("blog.retry")}
        </button>
      </div>
    );
  }
  return (
    <div role="status" aria-label={t("blog.productsLoading")}>
      <ProductCardSkeleton />
    </div>
  );
}

const coordinate = (value: unknown, limit: number): number | null => {
  if (typeof value !== "number" && typeof value !== "string") return null;
  if (typeof value === "string" && !value.trim()) return null;
  const number = Number(value);
  return Number.isFinite(number) && Math.abs(number) <= limit ? number : null;
};

export default function BlogProductsSection({ post }: { post: BlogPost }) {
  const { t } = useTranslation();
  const { defaultLocation } = useSettings();
  const headingId = useId();
  const accessToken = useSelector(
    (state: RootState) => state.auth.access_token,
  );
  const [scopeVersion, refreshScope] = useReducer(
    (value: number) => value + 1,
    0,
  );
  useEffect(() => {
    window.addEventListener(LOCATION_CHANGED_EVENT, refreshScope);
    const unsubscribe = subscribeToAuthSessionChanges(refreshScope);
    return () => {
      window.removeEventListener(LOCATION_CHANGED_EVENT, refreshScope);
      unsubscribe();
    };
  }, []);

  const location = getCookie<{ lat?: number | string; lng?: number | string }>(
    "userLocation",
  );
  const cookieLatitude = coordinate(location?.lat, 90);
  const cookieLongitude = coordinate(location?.lng, 180);
  const validCookie = cookieLatitude !== null && cookieLongitude !== null;
  const latitude = validCookie
    ? cookieLatitude
    : coordinate(defaultLocation?.lat, 90);
  const longitude = validCookie
    ? cookieLongitude
    : coordinate(defaultLocation?.lng, 180);
  const session = accessToken || getCookie<string>("access_token") || "";
  const hasAssociatedProducts = post.products !== undefined;
  const products = (post.products ?? []).filter(canRenderProduct);
  const linkedSlugs = hasAssociatedProducts
    ? []
    : Array.from(
        new Set(
          (post.linkedProducts ?? [])
            .map((product) => product.slug)
            .filter(Boolean),
        ),
      );

  if (products.length === 0 && linkedSlugs.length === 0) return null;

  return (
    <section
      aria-labelledby={headingId}
      className="mt-9 border-t border-default-200/80 pt-6 sm:mt-12 sm:pt-7"
    >
      <div className="mb-6 sm:mb-8">
        <h2
          id={headingId}
          className="text-[22px] font-semibold tracking-tight sm:text-2xl"
        >
          {t("blog.productsTitle")}
        </h2>
        <p className="mt-2 max-w-xl text-sm leading-6 text-default-600">
          {t("blog.productsSubtitle")}
        </p>
      </div>
      {!hasAssociatedProducts && (latitude === null || longitude === null) ? (
        <div className="flex flex-wrap items-center gap-3 text-sm text-default-500">
          <p>{t("blog.productsLocationPrompt")}</p>
          <button
            type="button"
            className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white"
            onClick={() =>
              document.getElementById("location-modal-btn")?.click()
            }
          >
            {t("blog.productsChooseLocation")}
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          {hasAssociatedProducts
            ? products.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))
            : linkedSlugs.map((slug) => (
                <LinkedCatalogProduct
                  key={slug}
                  slug={slug}
                  latitude={latitude!}
                  longitude={longitude!}
                  session={session}
                  scopeVersion={scopeVersion}
                />
              ))}
        </div>
      )}
    </section>
  );
}
