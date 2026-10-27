import { getCurrencyCode, productAmount } from "@/helpers/currency";
import { isSSR } from "@/helpers/getters";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import ProductDetailPageView from "@/views/Products/ProductDetailPageView";
import { ArrowRight, Package, ShoppingCart } from "lucide-react";
import { Product, Settings } from "@/types/ApiResponse";
import { NextPageWithLayout } from "@/types";
import { getProductBySlug, getProducts } from "@/routes/api";
import {
  getFallbackLocation,
} from "@/helpers/functionalHelpers";
import { useSettings } from "@/contexts/SettingsContext";
import useSWR from "swr";
import { useRouter } from "next/router";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { useTranslation } from "react-i18next";
import NoProductsFound from "@/components/NoProductsFound";
import { Button } from "@heroui/react";
import DynamicSEO from "@/SEO/DynamicSEO";
import {
  generateProductSchema,
  generateBreadcrumbSchema,
  generateProductMeta,
} from "@/helpers/seo";
import { useEffect, useState, useSyncExternalStore } from "react";
import { addRecentlyViewed } from "@/lib/redux/slices/recentlyViewedSlice";
import { useDispatch } from "react-redux";
export interface ProductPageProps {
  initialProduct?: Product;
  initialSettings?: Settings | null;
  initialSimilarProducts?: Product[];
  slug?: string;
  error?: string;
  clientFallback?: boolean;
}

const PER_PAGE = 20;
const subscribeToHydration = () => () => {};
const clientSnapshot = () => true;
const serverSnapshot = () => false;

// SWR fetcher for client
const fetcher = async (slug: string, settings?: Settings | null, storeId?: number) => {
  // Unguarded destructuring threw a TypeError when the cookie was absent —
  // getCookie returns undefined, not an empty object.
  const { lat = "", lng = "" } =
    (getCookie("userLocation") as UserLocation) || {};
  const fallback = lat && lng ? null : getFallbackLocation(settings);

  const res = await getProductBySlug({
    slug,
    ...(storeId ? { store_id: storeId } : {}),
    latitude: lat || fallback?.lat || "",
    longitude: lng || fallback?.lng || "",
  });
  if (!res.success || !res.data) {
    console.error(res.message || "Failed to fetch product");
  }
  return res.success && res.data && !Array.isArray(res.data) ? res.data : null;
};

// SWR fetcher for similar products
const similarProductsFetcher = async (
  slug: string,
  settings?: Settings | null,
) => {
  const { lat = "", lng = "" } =
    (getCookie("userLocation") as UserLocation) || {};
  const fallback = lat && lng ? null : getFallbackLocation(settings);

  const res = await getProducts({
    exclude_product: slug,
    per_page: PER_PAGE,
    latitude: lat || fallback?.lat || "",
    longitude: lng || fallback?.lng || "",
    include_child_categories: 0,
  });

  if (!res.success || !res.data) {
    console.error(res.message || "Failed to fetch similar products");
  }

  return res.data?.data || [];
};

const ProductPage: NextPageWithLayout<ProductPageProps> = ({
  initialProduct,
  initialSimilarProducts,
  slug,
  clientFallback = false,
}) => {
  const router = useRouter();
  const { t } = useTranslation();
  // Supplies the fallback coords when the visitor has no location cookie —
  // same source the server uses, so both paths resolve the same product.
  // `currency` feeds the Product schema: it was hardcoded "USD" while the
  // backend reports INR, which mispriced every rich result.
  const { settings, currency } = useSettings();

  // Get slug from router when SSR is false
  const hydrated = useSyncExternalStore(subscribeToHydration, clientSnapshot, serverSnapshot);
  let fallbackSlug: string | undefined;
  if (clientFallback && hydrated && router.isReady) {
    // Apache preserves the public URL when serving the shared static shell.
    const match = router.asPath.split(/[?#]/)[0].match(/^\/(?:share\/)?products\/([^/]+)\/?$/);
    try {
      fallbackSlug = match ? decodeURIComponent(match[1]) : undefined;
    } catch {
      // Malformed URL encoding is treated as an unavailable product.
    }
  }
  const productSlug = clientFallback ? fallbackSlug : slug || (router.query.slug as string);

  const [selectedStore, setSelectedStore] = useState<{ slug: string; id: number } | null>(null);
  const defaultStore = initialProduct?.variants?.find((variant) => variant.is_default)?.store_id || initialProduct?.variants?.[0]?.store_id;
  const storeId = selectedStore && selectedStore.slug === productSlug ? selectedStore.id : defaultStore;

  const {
    data: product,
    isLoading,
    mutate: refetchProduct,
  } = useSWR(
    productSlug ? [`/products/${productSlug}`, storeId ?? "all"] : null,
    () => fetcher(productSlug!, settings, storeId),
    {
      fallbackData: initialProduct,
      revalidateOnFocus: false,
      // No longer gated on the location cookie: `fetcher` falls back to the
      // settings default, so a first-time visitor (no cookie) still gets the
      // product rather than an empty page.
      revalidateOnMount: clientFallback || !isSSR(),
    },
  );

  const {
    data: similarProducts,
    isLoading: isSimilarProductsLoading,
    mutate: refetchSimilarProducts,
  } = useSWR(
    productSlug ? [`/similar-products/${productSlug}`, storeId ?? "all"] : null,
    () => similarProductsFetcher(productSlug!, settings),
    {
      fallbackData: initialSimilarProducts,
      revalidateOnFocus: false,
      revalidateOnMount: clientFallback || !isSSR(),
    },
  );

  const waitingForSlug = clientFallback && (!hydrated || !router.isReady);

  const isProductMissing =
    !product || (Array.isArray(product) && product.length === 0);

  // --- SSR SEO: use initialProduct so og:image is in the raw HTML for crawlers ---
  const ssrMeta = initialProduct ? generateProductMeta(initialProduct) : null;
  const ssrProductSchema = initialProduct
    ? generateProductSchema(initialProduct, undefined, currency)
    : null;
  const ssrBreadcrumbSchema = initialProduct
    ? generateBreadcrumbSchema([
        { name: "Home", url: "/" },
        { name: "Categories", url: "/categories" },
        {
          name: initialProduct.category_name,
          url: `/categories/${initialProduct.category}`,
        },
        {
          name: initialProduct.title,
          url: `/products/${initialProduct.slug}`,
        },
      ])
    : null;
  const ssrJsonLd = [ssrProductSchema, ssrBreadcrumbSchema].filter(Boolean);

  // --- Client SEO: update tags dynamically after SWR re-fetches ---
  const productMeta = product ? generateProductMeta(product) : null;
  const productSchema = product
    ? generateProductSchema(product, undefined, currency)
    : null;
  const breadcrumbSchema = product
    ? generateBreadcrumbSchema([
        { name: "Home", url: "/" },
        { name: "Categories", url: "/categories" },
        {
          name: product.category_name,
          url: `/categories/${product.category}`,
        },
        { name: product.title, url: `/products/${product.slug}` },
      ])
    : null;

  const jsonLdSchemas = [productSchema, breadcrumbSchema].filter(Boolean);
  const dispatch = useDispatch();

  useEffect(() => {
    if (product) {
      dispatch(addRecentlyViewed(product));
    }
  }, [product, dispatch]);

  return (
    <>
      {/* SSR SEO block — always rendered server-side so crawlers see og:image in raw HTML */}
      {ssrMeta && initialProduct ? (
        <DynamicSEO
          title={ssrMeta.title}
          description={ssrMeta.description}
          keywords={ssrMeta.keywords}
          canonical={`/products/${initialProduct.slug}`}
          ogType="product"
          ogTitle={ssrMeta.title}
          ogDescription={ssrMeta.ogDescription || ssrMeta.description}
          ogImage={ssrMeta.image}
          ogImageAlt={ssrMeta.title}
          twitterCard="summary_large_image"
          twitterTitle={ssrMeta.title}
          twitterDescription={ssrMeta.ogDescription || ssrMeta.description}
          twitterImage={ssrMeta.image}
          productPrice={
            String(productAmount(initialProduct.variants?.find((variant) => variant.is_default) || initialProduct.variants?.[0]))
          }
          productCurrency={getCurrencyCode(initialProduct.variants?.find((variant) => variant.is_default) || initialProduct.variants?.[0]) || getCurrencyCode(initialProduct) || currency}
          productAvailability={
            initialProduct.variants?.some((v) => v.stock > 0)
              ? "in stock"
              : "out of stock"
          }
          productCondition="new"
          jsonLd={ssrJsonLd}
        />
      ) : !initialProduct && !product && !isLoading && !waitingForSlug ? (
        <DynamicSEO
          title={t("not_found")}
          description={t("no_product_available")}
          robots="noindex, follow"
        />
      ) : null}

      {/* Client-side SEO update — keeps tags fresh after SWR re-fetch */}
      {product && productMeta && product !== initialProduct && (
        <DynamicSEO
          title={productMeta.title}
          description={productMeta.description}
          keywords={productMeta.keywords}
          canonical={`/products/${product.slug}`}
          ogType="product"
          ogTitle={productMeta.title}
          ogDescription={productMeta.ogDescription || productMeta.description}
          ogImage={productMeta.image}
          ogImageAlt={productMeta.title}
          twitterCard="summary_large_image"
          twitterTitle={productMeta.title}
          twitterDescription={productMeta.ogDescription || productMeta.description}
          twitterImage={productMeta.image}
          productPrice={
            String(productAmount(product.variants?.find((variant) => variant.store_id === storeId) || product.variants?.[0]))
          }
          productCurrency={currency}
          productAvailability={
            product.variants?.some((v) => v.stock > 0)
              ? "in stock"
              : "out of stock"
          }
          productCondition="new"
          jsonLd={jsonLdSchemas}
        />
      )}

      <div className="min-h-screen">
        <MyBreadcrumbs
          breadcrumbs={[
            {
              label: t("products"),
              startContent: <Package size={12} />,
            },
            { label: product?.title || "" },
          ]}
        />

        <button
          onClick={() => {
            refetchSimilarProducts();
          }}
          className="hidden"
          id="similar-products-refetch"
        />

        <button
          onClick={() => {
            refetchProduct();
          }}
          className="hidden"
          id="specific-product-refetch"
        />
        {/* Show when Main Product Is  Found */}

        {!isLoading && !waitingForSlug && isProductMissing ? (
          <NoProductsFound
            icon={ShoppingCart}
            title={t("no_product_found")}
            description={t("no_product_available")}
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
        ) : (
          <ProductDetailPageView
            key={product?.slug || "loading"}
            initialProduct={product!}
            preferredStoreId={storeId}
            onStoreChange={(id) => setSelectedStore((previous) => previous?.id === id && previous.slug === productSlug ? previous : { id, slug: productSlug! })}
            initialSimilarProducts={similarProducts || []}
            isLoading={(isLoading || waitingForSlug) && !initialProduct}
            isSimilarProductsLoading={isSimilarProductsLoading}
          />
        )}
      </div>
    </>
  );
};

export default ProductPage;
