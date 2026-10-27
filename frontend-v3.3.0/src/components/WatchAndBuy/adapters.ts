import { getCurrencyCode, money, productPrice } from "@/helpers/currency";
import type { Reel, ReelProduct } from "@/components/Reels/types";
import type {
  StoryGroup,
  StoryItem,
  StoryPublisherType,
  StoryVisual,
} from "@/components/Stories/types";
import type {
  WatchAndBuyProfile,
  WatchAndBuyProfileStoriesData,
  WatchAndBuyReelItem,
  WatchAndBuyReelProduct,
  WatchAndBuyStoreStoryItem,
  WatchAndBuyStoryItem,
  WatchAndBuyStoryTrayItem,
} from "@/types/WatchAndBuy";
import { siteConfig } from "@/config/site";

const DEFAULT_STORY_DURATION_MS = 5_000;

const asNonEmptyString = (value: unknown): string | undefined => {
  if (typeof value !== "string") return undefined;
  const trimmed = value.trim();
  return trimmed || undefined;
};

const asCount = (value: unknown): number => {
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed >= 0 ? Math.floor(parsed) : 0;
};

const asOptionalCount = (value: unknown): number | undefined =>
  value === null || value === undefined ? undefined : asCount(value);

const profileTitle = (profile: WatchAndBuyProfile): string =>
  asNonEmptyString(profile.name) ?? profile.username;

const storyProfileTitle = (profile: WatchAndBuyProfile): string => {
  if (profile.type === "seller") {
    return asNonEmptyString(profile.store?.name) ?? "Nearby seller";
  }
  return profileTitle(profile);
};

const profileStoreHref = (profile: WatchAndBuyProfile): string | undefined =>
  profile.type === "seller"
    ? (() => {
        const slug = asNonEmptyString(profile.store?.slug);
        return slug ? `/stores/${encodeURIComponent(slug)}/` : undefined;
      })()
    : undefined;

const profileCover = (profile: WatchAndBuyProfile): string | undefined =>
  asNonEmptyString(profile.photo_url) ??
  (profile.type === "seller"
    ? asNonEmptyString(profile.store?.image)
    : undefined);

const publisherType = (
  type: WatchAndBuyProfile["type"] | "admin" | null | undefined,
): StoryPublisherType => (type === "seller" ? "seller" : "admin");

const profileVisual = (profile: WatchAndBuyProfile): StoryVisual => {
  const title = storyProfileTitle(profile);
  return {
    background:
      "linear-gradient(145deg, hsl(var(--heroui-primary-600)), hsl(var(--heroui-secondary-600)))",
    accent: "hsl(var(--heroui-primary-400))",
    icon: profile.type === "seller" ? "🏪" : "▶",
    eyebrow: profile.type === "seller" ? "Nearby seller" : siteConfig.name,
    headline: title,
    description:
      asNonEmptyString(profile.bio) ?? "Fresh updates from around you.",
  };
};

const formatDuration = (seconds: number): string => {
  const safeSeconds = Math.max(0, Math.round(seconds));
  const minutes = Math.floor(safeSeconds / 60);
  return `${minutes}:${String(safeSeconds % 60).padStart(2, "0")}`;
};

const formatPrice = (
  value: string | number | null | undefined,
  currencySymbol: string,
): string | undefined => {
  if (typeof value === "number" && Number.isFinite(value)) {
    return `${currencySymbol}${value.toLocaleString()}`;
  }
  const label = asNonEmptyString(value);
  if (!label) return undefined;
  return /^\D/.test(label) ? label : `${currencySymbol}${label}`;
};

const formatDistanceKm = (value: number | null | undefined) => {
  if (typeof value !== "number" || !Number.isFinite(value)) return undefined;
  const rounded = Math.round(value * 10) / 10;
  return `${rounded.toFixed(1).replace(/\.0$/, "")} km`;
};

const toProduct = (
  product: WatchAndBuyReelProduct,
  currencySymbol: string,
): ReelProduct | null => {
  const nestedProduct = product.product;
  const variant = product.variant;
  const name =
    asNonEmptyString(product.name) ??
    asNonEmptyString(product.title) ??
    asNonEmptyString(nestedProduct?.title) ??
    asNonEmptyString(variant?.title);
  const imageSrc =
    asNonEmptyString(product.image_url) ??
    asNonEmptyString(product.image) ??
    asNonEmptyString(nestedProduct?.image_url);
  const hasNestedSpecialPrice =
    Number(variant?.special_price) > 0;
  const currencyContext = getCurrencyCode(variant) ? variant : getCurrencyCode(product) ? product : product.store;
  const contextualPrice = getCurrencyCode(currencyContext) || product.formatted_price || variant?.formatted_price || variant?.formatted_display_price;
  const price = contextualPrice
    ? variant ? productPrice(variant, hasNestedSpecialPrice ? "special_price" : "price", currencySymbol, currencyContext) : money(product, "price", currencySymbol, currencyContext)
    : asNonEmptyString(product.price_label) ??
    formatPrice(
      product.price ??
        (hasNestedSpecialPrice ? variant?.special_price : variant?.price),
      currencySymbol,
    );
  const productId = nestedProduct?.id ?? product.product_id ?? product.id;
  if (!name || !imageSrc || productId === null || productId === undefined)
    return null;

  const numericRating = Number(product.rating);
  const slug =
    asNonEmptyString(product.slug) ?? asNonEmptyString(nestedProduct?.slug);
  const nestedCompareAtPrice = hasNestedSpecialPrice
    ? contextualPrice ? productPrice(variant, "price", currencySymbol, currencyContext) : formatPrice(variant?.price, currencySymbol)
    : undefined;
  const store = product.store;
  const storeSlug = asNonEmptyString(store?.slug);
  const storeDistanceKm =
    typeof store?.distance_km === "number" && Number.isFinite(store.distance_km)
      ? store.distance_km
      : undefined;
  return {
    id: String(productId),
    name,
    price,
    compareAtPrice:
      asNonEmptyString(product.compare_at_price_label) ??
      formatPrice(product.compare_at_price, currencySymbol) ??
      nestedCompareAtPrice,
    rating: Number.isFinite(numericRating) ? numericRating : undefined,
    ratingCount: asOptionalCount(product.rating_count),
    badge:
      asNonEmptyString(product.badge) ??
      (product.is_effective_primary ? "Featured" : undefined),
    actionLabel: asNonEmptyString(product.action_label),
    imageSrc,
    imagePosition: asNonEmptyString(product.image_position),
    href:
      asNonEmptyString(product.url) ??
      asNonEmptyString(product.href) ??
      (slug ? `/products/${slug}/` : undefined),
    store: store
      ? {
          id: String(store.id),
          slug: storeSlug,
          name: asNonEmptyString(store.name),
          distanceKm: storeDistanceKm,
          distanceLabel:
            asNonEmptyString(store.distance_label) ??
            formatDistanceKm(storeDistanceKm),
          href: storeSlug
            ? `/stores/${encodeURIComponent(storeSlug)}/`
            : undefined,
        }
      : undefined,
  };
};

export const toReel = (
  item: WatchAndBuyReelItem,
  currencySymbol = "",
): Reel | null => {
  const profile = item.profile;
  const publisher = item.publisher;
  const id = asNonEmptyString(item.uuid) ?? String(item.id);
  const videoSrc =
    asNonEmptyString(item.video_url) ?? asNonEmptyString(item.media_url);
  const posterSrc =
    asNonEmptyString(item.poster_url) ??
    asNonEmptyString(item.thumbnail_url) ??
    asNonEmptyString(item.cover_url) ??
    asNonEmptyString(profile?.photo_url) ??
    asNonEmptyString(publisher?.avatar_url) ??
    asNonEmptyString(publisher?.photo_url);
  if (!videoSrc || !posterSrc) return null;

  const title =
    asNonEmptyString(item.title) ??
    asNonEmptyString(item.caption) ??
    asNonEmptyString(item.text) ??
    asNonEmptyString(publisher?.name) ??
    asNonEmptyString(publisher?.username) ??
    (profile ? profileTitle(profile) : siteConfig.name);
  const caption =
    asNonEmptyString(item.caption) ?? asNonEmptyString(item.text) ?? "";
  const durationSeconds =
    typeof item.duration_seconds === "number"
      ? item.duration_seconds
      : typeof item.duration_ms === "number"
        ? item.duration_ms / 1_000
        : 0;
  const liked = (item.is_liked ?? item.liked_by_me) === true;
  const rawLikes = asCount(item.likes_count ?? item.like_count);
  const ctaHref =
    asNonEmptyString(item.cta?.url) ?? asNonEmptyString(item.cta?.href);
  const ctaLabel = asNonEmptyString(item.cta?.label);
  const numericId = Number(item.id);

  return {
    id,
    apiId: Number.isInteger(numericId) ? numericId : item.id,
    title,
    caption,
    publisher:
      asNonEmptyString(publisher?.name) ??
      asNonEmptyString(publisher?.username) ??
      (profile ? profileTitle(profile) : siteConfig.name),
    publisherType: publisherType(publisher?.type ?? profile?.type),
    posterSrc,
    posterPosition: asNonEmptyString(item.poster_position),
    videoSrc,
    durationLabel: formatDuration(durationSeconds),
    distance:
      asNonEmptyString(item.distance_label) ??
      (item.distance === null || item.distance === undefined
        ? ""
        : String(item.distance)),
    deliveryEstimate:
      asNonEmptyString(item.delivery_estimate) ??
      asNonEmptyString(item.delivery_time) ??
      "",
    views: asCount(item.views_count),
    likes: Math.max(0, rawLikes - (liked ? 1 : 0)),
    isLiked: liked,
    products: (item.products ?? [])
      .map((product) => toProduct(product, currencySymbol))
      .filter((product): product is ReelProduct => product !== null),
    cta: ctaHref && ctaLabel ? { label: ctaLabel, href: ctaHref } : undefined,
  };
};

export const toReels = (
  items: readonly WatchAndBuyReelItem[],
  currencySymbol = "",
): Reel[] =>
  items
    .map((item) => toReel(item, currencySymbol))
    .filter((reel): reel is Reel => reel !== null);

const reelIdentity = (item: WatchAndBuyReelItem) =>
  asNonEmptyString(item.uuid) ?? String(item.id);

/**
 * The store-detail endpoint is authoritative for which Reels belong to a
 * store, while the profile endpoint carries richer shoppable product fields.
 */
export const enrichStoreReelItems = (
  storeItems: readonly WatchAndBuyReelItem[],
  profileItems: readonly WatchAndBuyReelItem[],
): WatchAndBuyReelItem[] => {
  const profileItemsById = new Map(
    profileItems.map((item) => [reelIdentity(item), item]),
  );

  return storeItems.map((item) => ({
    ...item,
    ...profileItemsById.get(reelIdentity(item)),
  }));
};

export interface StoreWatchAndBuySource {
  id: string | number;
  name: string;
  slug: string;
  logo?: string | null;
  reels?: readonly WatchAndBuyReelItem[] | null;
  stories?: readonly WatchAndBuyStoreStoryItem[] | null;
}

const toStoreProfile = (store: StoreWatchAndBuySource): WatchAndBuyProfile => {
  const stories = store.stories ?? [];
  const reels = store.reels ?? [];
  const sourceProfile =
    stories.find((item) => item.profile)?.profile ??
    reels.find((item) => item.profile)?.profile;
  const numericStoreId = Number(store.id);

  return {
    ...sourceProfile,
    id:
      sourceProfile?.id ??
      (Number.isFinite(numericStoreId) ? numericStoreId : 0),
    type: "seller",
    username: asNonEmptyString(sourceProfile?.username) ?? store.slug,
    name: store.name,
    photo_url:
      asNonEmptyString(sourceProfile?.photo_url) ??
      asNonEmptyString(store.logo),
    status: asNonEmptyString(sourceProfile?.status) ?? "active",
    has_active_status: stories.length > 0,
    has_unseen_status: stories.some((item) => item.seen_by_me !== true),
    store: {
      id: store.id,
      name: store.name,
      slug: store.slug,
      image: asNonEmptyString(store.logo),
    },
  };
};

/** Maps the compact media embedded in `/stores/{slug}` to the shared viewer. */
export const toStoreReels = (
  store: StoreWatchAndBuySource,
  currencySymbol = "",
): Reel[] => {
  const profile = toStoreProfile(store);
  const storeReference = {
    id: store.id,
    slug: store.slug,
    name: store.name,
  };

  return toReels(
    (store.reels ?? []).map((item) => ({
      ...item,
      profile,
      products: (item.products ?? []).map((product) => ({
        ...product,
        id: product.id ?? product.product_id,
        product:
          product.product ??
          (product.product_id === null || product.product_id === undefined
            ? null
            : {
                id: product.product_id,
                title: product.name ?? product.title,
                image_url: product.image_url ?? product.image,
              }),
        store: product.store ?? {
          ...storeReference,
          id: product.store_id ?? store.id,
        },
      })),
    })),
    currencySymbol,
  );
};

/** Returns no launcher when a store has no active API-backed Stories. */
export const toStoreStoryGroup = (
  store: StoreWatchAndBuySource,
): StoryGroup | null => {
  const stories = store.stories ?? [];
  if (stories.length === 0) return null;

  const profile = toStoreProfile(store);
  return toStoryGroup({
    profile,
    items: stories.map((item) => ({
      ...item,
      seen_by_me: item.seen_by_me === true,
    })),
    pagination: {
      has_more_pages: false,
      next_cursor: null,
      previous_cursor: null,
      per_page: stories.length,
      total: stories.length,
    },
  });
};

export const toStoryTrayGroup = (
  item: WatchAndBuyStoryTrayItem,
): StoryGroup => ({
  id: item.profile.username,
  title: storyProfileTitle(item.profile),
  subtitle:
    asNonEmptyString(item.profile.bio) ?? "Fresh updates from around you",
  publisherType: publisherType(item.profile.type),
  publisherHref: profileStoreHref(item.profile),
  coverSrc: profileCover(item.profile),
  visual: profileVisual(item.profile),
  items: [],
  itemCount: asCount(item.status_count),
  unseenItemCount: item.profile.has_unseen_status
    ? asCount(item.status_count)
    : 0,
});

const toStoryItem = (
  item: WatchAndBuyStoryItem,
  visual: StoryVisual,
): StoryItem => {
  const ctaHref =
    asNonEmptyString(item.cta?.url) ?? asNonEmptyString(item.cta?.href);
  const ctaLabel = asNonEmptyString(item.cta?.label);
  return {
    id: item.uuid || String(item.id),
    apiId: item.id,
    seen: item.seen_by_me,
    mediaType: item.content_type,
    durationMs:
      typeof item.duration_ms === "number" && item.duration_ms > 0
        ? item.duration_ms
        : DEFAULT_STORY_DURATION_MS,
    likeCount: asOptionalCount(item.likes_count),
    views24h: asOptionalCount(item.views_count),
    visual,
    title: asNonEmptyString(item.text),
    imageSrc: item.content_type === "image" ? item.media_url : undefined,
    videoSrc: item.content_type === "video" ? item.media_url : undefined,
    ctaLabel: ctaHref && ctaLabel ? ctaLabel : undefined,
    href: ctaHref && ctaLabel ? ctaHref : undefined,
  };
};

export const toStoryGroup = (
  data: WatchAndBuyProfileStoriesData,
): StoryGroup => {
  const visual = profileVisual(data.profile);
  return {
    id: data.profile.username,
    title: storyProfileTitle(data.profile),
    subtitle:
      asNonEmptyString(data.profile.bio) ?? "Fresh updates from around you",
    publisherType: publisherType(data.profile.type),
    publisherHref: profileStoreHref(data.profile),
    coverSrc: profileCover(data.profile),
    visual,
    items: data.items.map((item) => toStoryItem(item, visual)),
    itemCount: data.pagination.total,
    unseenItemCount: data.items.filter((item) => !item.seen_by_me).length,
  };
};

export const getSeenStoryIds = (
  data: WatchAndBuyProfileStoriesData,
): string[] =>
  data.items
    .filter((item) => item.seen_by_me)
    .map((item) => item.uuid || String(item.id));
