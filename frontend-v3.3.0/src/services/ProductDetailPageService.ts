import { getProductBySlug, getProducts, getSettings } from "@/routes/api";
import { getFallbackLocation } from "@/helpers/functionalHelpers";
import { Product, Settings } from "@/types/ApiResponse";

/**
 * Input parameters for fetching product detail page data
 */
export interface ProductDetailPageParams {
  slug: string;
  access_token: string;
  lat?: string;
  lng?: string;
  PER_PAGE?: number | string;
}

/**
 * Output of fetchProductDetailPageData function
 */
export interface ProductDetailPageData {
  initialProduct: Product | null; // replace `any` with your Product type
  initialSimilarProducts: Product[]; // replace `any` with your Product type
  initialSettings: Settings | null; // replace `any` with your Settings type
  errors: {
    productDetail: string | null;
    similarProducts: string | null;
    settings: string | null;
  };
}

/**
 * Fetches product detail page data concurrently:
 * - Product detail
 * - Similar products
 * - App settings
 *
 * Uses Promise.allSettled to ensure failure of one API does not block others.
 *
 * @param {ProductDetailPageParams} params - Input parameters including slug, access_token, lat, lng
 * @returns {Promise<ProductDetailPageData>} - The fetched data and any errors
 */
export async function fetchProductDetailPageData(
  params: ProductDetailPageParams
): Promise<ProductDetailPageData> {
  const { slug, access_token, lat = "", lng = "", PER_PAGE = 20 } = params;

  // Settings first — they carry the fallback coordinates, and the catalog API
  // rejects blank ones outright. Only paid for when the request has no location
  // (i.e. a crawler); a normal visitor's cookie short-circuits this.
  const settingsRes = await getSettings();
  const settings = settingsRes.success ? (settingsRes.data ?? null) : null;

  const fallback = lat && lng ? null : getFallbackLocation(settings);
  const latitude = lat || fallback?.lat || "";
  const longitude = lng || fallback?.lng || "";

  // Concurrent API calls
  const [productDetailResult, similarProductsResult] = await Promise.allSettled([
    getProductBySlug({ slug, access_token, latitude, longitude }),
    getProducts({
      exclude_product: slug,
      per_page: PER_PAGE,
      access_token,
      latitude,
      longitude,
      include_child_categories: 0,
    }),
  ]);

  return {
    // `success` is load-bearing, not belt-and-braces: on a miss this endpoint
    // returns `data: []` — an EMPTY ARRAY, which is truthy. Taking `.data`
    // alone yielded a `[]` that read as a product and produced
    // `/products/undefined` in every SEO tag.
    initialProduct:
      productDetailResult.status === "fulfilled" &&
      productDetailResult.value.success
        ? (productDetailResult.value.data ?? null)
        : null,

    initialSimilarProducts:
      similarProductsResult.status === "fulfilled" &&
      similarProductsResult.value.success
        ? (similarProductsResult.value.data?.data ?? [])
        : [],

    initialSettings: settings,

    errors: {
      productDetail:
        productDetailResult.status === "rejected"
          ? productDetailResult.reason?.message ||
            "Failed to fetch product detail"
          : null,
      similarProducts:
        similarProductsResult.status === "rejected"
          ? similarProductsResult.reason?.message ||
            "Failed to fetch similar products"
          : null,
      // getSettings never throws — it returns a fallback with success: false.
      settings: settingsRes.success ? null : (settingsRes.message ?? null),
    },
  };
}
