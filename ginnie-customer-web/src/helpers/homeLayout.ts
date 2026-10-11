import {
  ApiResponse,
  Brand,
  Category,
  HomeLayoutBannerItem,
  HomeLayoutData,
  HomeLayoutSection,
  Product,
  ProductVariant,
  Store,
} from "@/types/ApiResponse";
import { getActiveCategory } from "@/helpers/getters";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { getHomeLayout } from "@/routes/api";

/**
 * Adapters for the /home-layout API.
 *
 * The home-layout product payload is structurally close to `Product` but not
 * identical: `metadata` arrives as an object (the type expects `string | null`),
 * a few fields are absent, and variant `attributes` can come back as `[]`.
 * These adapters normalise the raw payload into a `Product` that `ProductCard`,
 * `addToCart` and the offline-cart slice can consume unchanged.
 */

// Raw payload is an API boundary — intentionally loose.
type RawRecord = Record<string, any>;

/**
 * `attributes` must be a Record for ProductCard's Object.entries() tooltip.
 * The API sends `[]` when there are none, which isn't assignable to the type.
 */
const toAttributeRecord = (value: unknown): Record<string, string> =>
  value && typeof value === "object" && !Array.isArray(value)
    ? (value as Record<string, string>)
    : {};

const adaptVariant = (raw: RawRecord): ProductVariant => {
  const stock = Number(raw?.stock) || 0;

  return {
    id: raw?.id,
    title: raw?.title ?? "",
    slug: raw?.slug ?? "",
    image: raw?.image || raw?.main_image || "",
    weight: Number(raw?.weight) || 0,
    height: Number(raw?.height) || 0,
    breadth: Number(raw?.breadth) || 0,
    length: Number(raw?.length) || 0,
    // Must not fall through to `undefined`: ProductCard renders OUT OF STOCK on
    // `!availability`, so an absent flag would wrongly hide every product.
    availability:
      typeof raw?.availability === "boolean" ? raw.availability : stock > 0,
    cart_item: raw?.cart_item,
    barcode: raw?.barcode ?? "",
    is_default: Boolean(raw?.is_default),
    price: Number(raw?.price) || 0,
    special_price: Number(raw?.special_price) || 0,
    store_id: raw?.store_id,
    store_slug: raw?.store_slug ?? "",
    store_name: raw?.store_name ?? "",
    stock,
    sku: raw?.sku ?? "",
    attributes: toAttributeRecord(raw?.attributes),
    addon_groups: raw?.addon_groups,
    is_addons: raw?.is_addons,
  };
};

/**
 * Normalise one raw home-layout product into a `Product`.
 * Returns `null` when the product has no variant — ProductCard renders nothing
 * without one, so such items are dropped rather than rendered blank.
 */
export const adaptHomeLayoutProduct = (raw: RawRecord): Product | null => {
  if (!raw || !Array.isArray(raw.variants) || raw.variants.length === 0) {
    return null;
  }

  // ProductCard reads variants[0] for the store link but resolves the cart
  // variant via find(is_default) — if those disagree the displayed store won't
  // match the store added to cart. Emitting the default first keeps them aligned.
  const variants = raw.variants
    .map(adaptVariant)
    .sort((a, b) => Number(b.is_default) - Number(a.is_default));

  if (!variants.some((v) => v.is_default)) {
    variants[0] = { ...variants[0], is_default: true };
  }

  return {
    id: raw.id,
    uuid: raw.uuid ?? "",
    category_id: raw.category_id,
    brand_id: raw.brand_id ?? null,
    brand_name: raw.brand_name ?? null,
    seller_id: raw.seller_id,
    title: raw.title ?? "",
    slug: raw.slug ?? "",
    type: raw.type ?? (variants.length === 1 ? "simple" : "variant"),
    short_description: raw.short_description ?? "",
    description: raw.description ?? "",
    category: raw.category ?? "",
    category_name: raw.category_name ?? "",
    brand: raw.brand ?? "",
    seller: raw.seller ?? null,
    indicator: raw.indicator ?? null,
    favorite: raw.favorite ?? null,
    estimated_delivery_time: raw.estimated_delivery_time ?? null,
    ratings: Number(raw.ratings) || 0,
    rating_count: Number(raw.rating_count) || 0,
    main_image: raw.main_image ?? "",
    image_fit: raw.image_fit ?? "cover",
    additional_images: raw.additional_images ?? [],
    minimum_order_quantity: Number(raw.minimum_order_quantity) || 1,
    quantity_step_size: Number(raw.quantity_step_size) || 1,
    total_allowed_quantity:
      Number(raw.total_allowed_quantity) || variants[0].stock || 1,
    is_returnable: raw.is_returnable ?? 0,
    returnable_days: raw.returnable_days ?? null,
    is_cancelable: raw.is_cancelable ?? 0,
    cancelable_till: raw.cancelable_till ?? null,
    tags: raw.tags ?? [],
    warranty_period: raw.warranty_period ?? null,
    guarantee_period: raw.guarantee_period ?? null,
    made_in: raw.made_in ?? null,
    is_inclusive_tax: raw.is_inclusive_tax ?? "0",
    video_type: raw.video_type ?? null,
    video_link: raw.video_link ?? null,
    status: raw.status ?? "active",
    featured: raw.featured ?? null,
    // The API sends an SEO object here; the Product contract is `string | null`.
    metadata: typeof raw.metadata === "string" ? raw.metadata : null,
    item_count_in_cart: raw.item_count_in_cart,
    custom_fields: raw.custom_fields ?? {},
    created_at: raw.created_at ?? "",
    updated_at: raw.updated_at ?? "",
    // Absent store_status would crash ProductCard's `store_status.is_open` read.
    store_status: raw.store_status ?? { is_open: true, status: "open" },
    variants,
    attributes: raw.attributes ?? [],
    custom_product_sections: raw.custom_product_sections ?? [],
  };
};

/** Adapt a raw home-layout product list, dropping items that can't render. */
export const adaptHomeLayoutProducts = (raw: unknown[] = []): Product[] =>
  (raw ?? [])
    .map((item) => adaptHomeLayoutProduct(item as RawRecord))
    .filter((item): item is Product => item !== null);

/** Pull the products out of a section's `content`, adapted and render-ready. */
export const getSectionProducts = (content: Record<string, unknown[]>): Product[] =>
  adaptHomeLayoutProducts(content?.products ?? []);

/**
 * Normalise a raw home-layout category into a `Category`.
 * Same drift as products: `metadata` arrives as an SEO object and `banner`
 * isn't sent at all, but CategoryCard needs a valid Category.
 * Returns `null` when there's no slug — CategoryCard's link would be broken.
 */
export const adaptHomeLayoutCategory = (raw: RawRecord): Category | null => {
  if (!raw || !raw.slug) return null;

  return {
    id: raw.id,
    title: raw.title ?? "",
    slug: raw.slug,
    image: raw.image ?? "",
    banner: raw.banner ?? "", // not returned by /home-layout
    icon: raw.icon ?? "",
    active_icon: raw.active_icon ?? "",
    background_type: raw.background_type ?? null,
    background_color: raw.background_color ?? "",
    background_image: raw.background_image ?? "",
    font_color: raw.font_color ?? "",
    parent_id: raw.parent_id ?? null,
    parent_slug: raw.parent_slug ?? null,
    description: raw.description ?? null,
    status: raw.status ?? "active",
    requires_approval: Boolean(raw.requires_approval),
    // The API sends an SEO object here; the Category contract is `string | null`.
    metadata: typeof raw.metadata === "string" ? raw.metadata : null,
    subcategory_count: Number(raw.subcategory_count) || 0,
    product_count: Number(raw.product_count) || 0,
    enabled: raw.enabled,
    custom_image: raw.custom_image ?? raw.banner ?? null,
  };
};

/** Adapt a raw home-layout category list, dropping items that can't render. */
export const adaptHomeLayoutCategories = (raw: unknown[] = []): Category[] =>
  (raw ?? [])
    .map((item) => adaptHomeLayoutCategory(item as RawRecord))
    .filter((item): item is Category => item !== null);

/** Pull the categories out of a section's `content`, adapted and render-ready. */
export const getSectionCategories = (
  content: Record<string, unknown[]>,
): Category[] => adaptHomeLayoutCategories(content?.categories ?? []);

/**
 * First non-empty list among `keys`.
 *
 * Only `products` / `categories` / `items` are confirmed against the live API;
 * the remaining section types are keyed on the builder's naming, so each getter
 * below accepts its likely aliases rather than rendering nothing on a near-miss.
 */
const pickContentList = (
  content: Record<string, unknown[]>,
  ...keys: string[]
): unknown[] => {
  for (const key of keys) {
    const value = content?.[key];
    if (Array.isArray(value) && value.length > 0) return value;
  }
  return [];
};

/**
 * Normalise one raw banner/slider item.
 * Returns `null` without an image — the slide is nothing but its image, so an
 * item missing one would render an empty box.
 */
export const adaptHomeLayoutBannerItem = (
  raw: RawRecord,
): HomeLayoutBannerItem | null => {
  if (!raw) return null;

  const media = raw?.media && typeof raw.media === "object" ? raw.media : {};
  const mediaType = raw?.media_type ?? media.type ?? raw?.type ?? "image";
  const videoUrl =
    raw?.video_url ??
    raw?.video?.url ??
    (mediaType === "video" ? media.url : null);
  const image =
    raw?.image ??
    raw?.banner_image ??
    raw?.poster ??
    media.poster ??
    (mediaType === "video" ? null : raw?.url ?? media.url ?? null);

  // Image slides need an image; video slides can run without a poster because
  // the hero's fixed aspect-ratio surface is their graceful loading fallback.
  if (!image && !videoUrl) return null;

  return {
    id: raw.id,
    image,
    media_type: mediaType,
    video_url: videoUrl,
    link_type: raw.link_type ?? raw.type ?? null,
    link_url: raw.link_url ?? raw.custom_url ?? null,
    target_id: raw.target_id ?? null,
    slug: raw.slug ?? null,
  };
};

/** Adapt a raw banner/slider list, dropping items that can't render. */
export const adaptHomeLayoutBannerItems = (
  raw: unknown[] = [],
): HomeLayoutBannerItem[] =>
  (raw ?? [])
    .map((item) => adaptHomeLayoutBannerItem(item as RawRecord))
    .filter((item): item is HomeLayoutBannerItem => item !== null);

/** Pull the banner/slider items out of a section's `content`. */
export const getSectionBanners = (
  content: Record<string, unknown[]>,
): HomeLayoutBannerItem[] =>
  adaptHomeLayoutBannerItems(
    pickContentList(content, "items", "banners", "slides", "sliders"),
  );

/**
 * Where a banner slide points, or `null` when it points nowhere.
 *
 * `null` matters: the legacy sliders fall back to `href="#"`, which renders a
 * focusable link that scrolls to the top when clicked. An unlinked banner is
 * rendered as a plain image instead.
 */
export const resolveSectionBannerHref = (
  item: HomeLayoutBannerItem,
): string | null => {
  const slug = item?.slug;

  switch (item?.link_type) {
    case "product":
    case "products":
      return slug || item.target_id ? `/products/${slug ?? item.target_id}` : null;
    case "category":
    case "categories":
      return slug || item.target_id
        ? `/categories/${slug ?? item.target_id}`
        : null;
    case "brand":
    case "brands":
      return slug || item.target_id ? `/brands/${slug ?? item.target_id}` : null;
    case "store":
    case "stores":
    case "seller":
      return slug || item.target_id ? `/stores/${slug ?? item.target_id}` : null;
    case "none":
      return null;
    // "custom" and anything unrecognised: a URL is the only thing that can be
    // meant, so use it if one was sent.
    default:
      return item?.link_url || null;
  }
};

/**
 * Normalise a raw home-layout brand into a `Brand`.
 * Returns `null` without a slug — BrandCard's link would be broken.
 */
export const adaptHomeLayoutBrand = (raw: RawRecord): Brand | null => {
  if (!raw || !raw.slug) return null;

  return {
    id: raw.id,
    title: raw.title ?? raw.name ?? "",
    slug: raw.slug,
    logo: raw.logo ?? raw.image ?? "",
    description: raw.description ?? "",
    enabled: raw.enabled,
  };
};

/** Adapt a raw home-layout brand list, dropping items that can't render. */
export const adaptHomeLayoutBrands = (raw: unknown[] = []): Brand[] =>
  (raw ?? [])
    .map((item) => adaptHomeLayoutBrand(item as RawRecord))
    .filter((item): item is Brand => item !== null);

/** Pull the brands out of a section's `content`, adapted and render-ready. */
export const getSectionBrands = (content: Record<string, unknown[]>): Brand[] =>
  adaptHomeLayoutBrands(pickContentList(content, "brands", "items"));

/**
 * Normalise a raw home-layout store into a `Store`.
 * Returns `null` without a slug — StoreCard's link would be broken.
 *
 * StoreCard reads `status.is_open` and `parseFloat(avg_store_rating)`
 * unguarded, so both are defaulted here rather than crashing or printing NaN.
 */
export const adaptHomeLayoutStore = (raw: RawRecord): Store | null => {
  if (!raw || !raw.slug) return null;

  const rawStatus = raw.status;
  const status =
    rawStatus && typeof rawStatus === "object"
      ? {
          is_open: rawStatus.is_open ?? true,
          status: rawStatus.status ?? "online",
        }
      : // Some list endpoints send a plain string ("online") instead of the object.
        { is_open: rawStatus !== "offline", status: rawStatus ?? "online" };

  return {
    id: raw.id,
    name: raw.name ?? raw.title ?? "",
    slug: raw.slug,
    product_count: Number(raw.product_count) || 0,
    description: raw.description ?? "",
    contact_number: raw.contact_number ?? "",
    contact_email: raw.contact_email ?? "",
    address: raw.address ?? "",
    latitude: String(raw.latitude ?? ""),
    longitude: String(raw.longitude ?? ""),
    distance: raw.distance ?? 0,
    timing: raw.timing ?? null,
    logo: raw.logo ?? raw.image ?? "",
    banner: raw.banner ?? "",
    avg_products_rating: String(raw.avg_products_rating ?? "0"),
    avg_store_rating: String(raw.avg_store_rating ?? "0"),
    total_store_feedback: String(raw.total_store_feedback ?? "0"),
    created_at: raw.created_at ?? "",
    updated_at: raw.updated_at ?? "",
    verification_status: raw.verification_status ?? "approved",
    visibility_status: raw.visibility_status ?? "visible",
    status,
  };
};

/** Adapt a raw home-layout store list, dropping items that can't render. */
export const adaptHomeLayoutStores = (raw: unknown[] = []): Store[] =>
  (raw ?? [])
    .map((item) => adaptHomeLayoutStore(item as RawRecord))
    .filter((item): item is Store => item !== null);

/** Pull the stores out of a section's `content`, adapted and render-ready. */
export const getSectionStores = (content: Record<string, unknown[]>): Store[] =>
  adaptHomeLayoutStores(pickContentList(content, "stores", "sellers", "items"));

/**
 * Build the /home-layout request params for the section list.
 *
 * MUST be called fresh on every fetch. `getActiveCategory()` reads
 * window.location.search and the location cookie changes at runtime, so
 * capturing either in a `useMemo` pins every later request to the mount-time
 * category — the homepage then stays stuck on the category it first loaded with.
 */
export const buildHomeLayoutParams = (
  params: Record<string, unknown> = {},
): Record<string, unknown> => {
  const { lat = "", lng = "" } =
    (getCookie("userLocation") as UserLocation) || {};

  return {
    platform: "web",
    category_slug: getActiveCategory(), // undefined for "all"/unset
    ...params,
    latitude: (params.latitude ?? lat) || undefined,
    longitude: (params.longitude ?? lng) || undefined,
  };
};

export interface HomeLayoutRequestOptions {
  categorySlug?: string;
  latitude?: string | number;
  longitude?: string | number;
  page?: string | number;
  perPage?: string | number;
}

/**
 * Keep one in-flight/fulfilled request per category and location. This warms
 * the same request that the homepage will need after a tab click, preventing
 * hover followed by click from creating two network requests.
 */
const homeLayoutRequestCache = new Map<
  string,
  Promise<ApiResponse<HomeLayoutData>>
>();

const homeLayoutRequestKey = ({
  categorySlug = "all",
  latitude = "",
  longitude = "",
  page = 1,
  perPage = 5,
}: HomeLayoutRequestOptions) =>
  [categorySlug || "all", latitude, longitude, page, perPage].join(":");

const requestHomeLayout = (
  options: HomeLayoutRequestOptions = {},
  retainResult = false,
): Promise<ApiResponse<HomeLayoutData>> => {
  const key = homeLayoutRequestKey(options);
  const cachedRequest = homeLayoutRequestCache.get(key);
  if (cachedRequest) return cachedRequest;

  const {
    categorySlug = "all",
    latitude,
    longitude,
    page = 1,
    perPage = 5,
  } = options;

  const request = getHomeLayout(
    buildHomeLayoutParams({
      category_slug:
        categorySlug && categorySlug !== "all" ? categorySlug : undefined,
      latitude,
      longitude,
      page,
      per_page: perPage,
    }) as Parameters<typeof getHomeLayout>[0],
  ).catch((error) => {
    homeLayoutRequestCache.delete(key);
    throw error;
  });

  // Ordinary page fetches are cached by SWR. Only retain completed prefetches
  // here, so a normal refresh can never be served by an old module-level
  // promise while a hover prefetch still gets reused by the click.
  if (!retainResult) return request;

  const retainedRequest = request.then((response) => {
    // API helpers return fallback responses instead of throwing, so failed
    // prefetches must not poison this in-memory cache forever.
    if (!response?.success) homeLayoutRequestCache.delete(key);
    return response;
  });
  homeLayoutRequestCache.set(key, retainedRequest);
  return retainedRequest;
};

/** Start loading a category layout before the user clicks it. */
export const prefetchHomeLayout = (
  options: HomeLayoutRequestOptions,
): void => {
  void requestHomeLayout(options, true);
};

/** Use a prefetched category request when the active tab needs it. */
export const fetchHomeLayout = (
  options: HomeLayoutRequestOptions,
): Promise<ApiResponse<HomeLayoutData>> => requestHomeLayout(options);

/**
 * Re-shape a /home-layout response for `useInfiniteData`.
 *
 * The hook reads its page array from `data.data` and its count from `data.total`,
 * but /home-layout is a non-standard envelope that puts the array at
 * `data.sections`. Without this bridge the hook silently renders zero sections.
 */
export const toSectionListPage = (
  res: ApiResponse<HomeLayoutData>,
): { success: boolean; data: { data: HomeLayoutSection[]; total: number } } => ({
  success: Boolean(res?.success),
  data: {
    data: res?.data?.sections ?? [],
    total: res?.data?.total ?? 0,
  },
});
