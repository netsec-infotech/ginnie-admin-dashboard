export type ReelPublisherType = "seller" | "admin";

export interface ReelProductStore {
  id: string;
  slug?: string;
  name?: string;
  distanceKm?: number;
  distanceLabel?: string;
  href?: string;
}

export interface ReelProduct {
  id: string;
  name: string;
  /** Compact store-detail Reel products do not include pricing. */
  price?: string;
  compareAtPrice?: string;
  rating?: number;
  ratingCount?: number;
  badge?: string;
  actionLabel?: string;
  imageSrc: string;
  imagePosition?: string;
  href?: string;
  store?: ReelProductStore;
}

export interface Reel {
  id: string;
  /** Numeric/backend identifier used by authenticated mutations. */
  apiId?: string | number;
  title: string;
  caption: string;
  publisher: string;
  publisherType: ReelPublisherType;
  posterSrc: string;
  posterPosition?: string;
  videoSrc: string;
  captions?: {
    src: string;
    srcLang: string;
    label: string;
    default?: boolean;
  }[];
  durationLabel: string;
  distance: string;
  deliveryEstimate: string;
  views: number;
  likes: number;
  isLiked?: boolean;
  products?: ReelProduct[];
  cta?: {
    label: string;
    href: string;
  };
}

/** @deprecated Use the neutral `Reel` view model for new components. */
export type HomeReel = Reel;
