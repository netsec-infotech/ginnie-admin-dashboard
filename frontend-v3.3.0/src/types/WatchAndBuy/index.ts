import type { CurrencyContext } from "@/types/currency";
import type { ApiResponse } from "@/types/ApiResponse";

export type WatchAndBuyProfileType = "platform" | "seller";

export interface WatchAndBuyConfig {
  enabled: boolean;
  title: string;
  maxDurationSeconds: number;
  maxUploadMb: number;
  maxProductsPerReel: number;
  statusesEnabled: boolean;
  maxStatusDurationSeconds: number;
  maxProductsPerStatus: number;
  effectiveMaxUploadMb: number;
}

export interface WatchAndBuyProfile {
  id: number;
  type: WatchAndBuyProfileType;
  username: string;
  name?: string | null;
  bio?: string | null;
  photo_url?: string | null;
  status: string;
  has_active_status?: boolean;
  has_unseen_status?: boolean;
  store?: {
    id: string | number;
    name: string;
    slug: string;
    image?: string | null;
  } | null;
}

export interface WatchAndBuyCursorMeta {
  session_id?: string | null;
  next_cursor: string | null;
  per_page: number;
  slug_not_found?: boolean;
}

export interface WatchAndBuyStore extends CurrencyContext {
  id: string | number;
  slug?: string | null;
  name?: string | null;
  distance_km?: number | null;
  distance_label?: string | null;
}

export interface WatchAndBuyReelProduct extends CurrencyContext {
  id?: string | number;
  /** Compact store-detail payload aliases. */
  product_id?: string | number;
  product_variant_id?: string | number;
  store_id?: string | number;
  sort_order?: number | null;
  is_primary?: boolean | null;
  is_effective_primary?: boolean | null;
  name?: string | null;
  title?: string | null;
  price?: string | number | null;
  price_label?: string | null;
  compare_at_price?: string | number | null;
  compare_at_price_label?: string | null;
  rating?: string | number | null;
  rating_count?: number | null;
  badge?: string | null;
  image_url?: string | null;
  image?: string | null;
  image_position?: string | null;
  url?: string | null;
  href?: string | null;
  slug?: string | null;
  action_label?: string | null;
  product?: {
    id: string | number;
    slug?: string | null;
    title?: string | null;
    image_url?: string | null;
  } | null;
  variant?: CurrencyContext & {
    id: string | number;
    title?: string | null;
    sku?: string | null;
    price?: string | number | null;
    special_price?: string | number | null;
    stock?: number | null;
    availability?: boolean | null;
  } | null;
  store?: WatchAndBuyStore | null;
}

/** Live Reel fields plus backward-compatible aliases accepted by the adapter. */
export interface WatchAndBuyReelItem {
  id: string | number;
  uuid?: string | null;
  slug?: string | null;
  source?: "admin_direct" | "seller" | string | null;
  title?: string | null;
  caption?: string | null;
  text?: string | null;
  profile?: WatchAndBuyProfile | null;
  publisher?: {
    id?: string | number;
    name?: string | null;
    username?: string | null;
    type?: WatchAndBuyProfileType | "admin" | null;
    avatar_url?: string | null;
    photo_url?: string | null;
  } | null;
  poster_url?: string | null;
  thumbnail_url?: string | null;
  cover_url?: string | null;
  poster_position?: string | null;
  video_url?: string | null;
  media_url?: string | null;
  video_width?: number | null;
  video_height?: number | null;
  duration_ms?: number | null;
  duration_seconds?: number | null;
  distance?: string | number | null;
  distance_label?: string | null;
  delivery_estimate?: string | null;
  delivery_time?: string | null;
  views_count?: number | null;
  likes_count?: number | null;
  like_count?: number | null;
  is_liked?: boolean | null;
  liked_by_me?: boolean | null;
  effective_primary_variant_id?: string | number | null;
  published_at?: string | null;
  products?: WatchAndBuyReelProduct[] | null;
  cta?: {
    label?: string | null;
    url?: string | null;
    href?: string | null;
  } | null;
}

export interface WatchAndBuyReelFeedData {
  items: WatchAndBuyReelItem[];
  meta: WatchAndBuyCursorMeta;
}

export interface WatchAndBuyProfileReelsData {
  profile: WatchAndBuyProfile;
  reels: WatchAndBuyReelItem[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface WatchAndBuyStoryTrayItem {
  profile: WatchAndBuyProfile;
  status_count: number;
}

export interface WatchAndBuyStoryTrayData {
  items: WatchAndBuyStoryTrayItem[];
  meta: WatchAndBuyCursorMeta;
}

export interface WatchAndBuyStoryProduct extends WatchAndBuyReelProduct {}

export interface WatchAndBuyStoryItem {
  id: number;
  uuid: string;
  source: "admin_direct" | "seller" | string;
  content_type: "image" | "video";
  text?: string | null;
  media_url: string;
  media_width?: number | null;
  media_height?: number | null;
  duration_ms?: number | null;
  seen_by_me?: boolean;
  products?: WatchAndBuyStoryProduct[] | null;
  created_at: string;
  expires_at: string;
  likes_count?: number | null;
  views_count?: number | null;
  cta?: {
    label?: string | null;
    url?: string | null;
    href?: string | null;
  } | null;
}

/** Store-detail responses include the owning profile on each Story record. */
export interface WatchAndBuyStoreStoryItem extends WatchAndBuyStoryItem {
  profile?: WatchAndBuyProfile | null;
}

export interface WatchAndBuyProfileStoriesData {
  profile: WatchAndBuyProfile;
  items: WatchAndBuyStoryItem[];
  pagination: {
    has_more_pages: boolean;
    next_cursor: string | null;
    previous_cursor: string | null;
    per_page: number;
    total: number;
  };
}

export interface WatchAndBuyReelLikeItem {
  reel_id: string | number;
  liked: boolean;
}

export interface WatchAndBuyReelLikeParams {
  latitude: number;
  longitude: number;
  items: WatchAndBuyReelLikeItem[];
  access_token?: string | null;
}

export interface WatchAndBuyReelLikeResult {
  reel_id?: string | number;
  liked?: boolean;
  is_liked?: boolean;
  likes_count?: number;
}

export interface WatchAndBuyStorySeenParams {
  status_ids: number[];
  access_token?: string | null;
}

export interface WatchAndBuyStorySeenResult {
  status_id: number;
  seen_at: string;
}

export type WatchAndBuyResponse<T> = ApiResponse<T>;
