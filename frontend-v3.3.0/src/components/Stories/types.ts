export type StoryMediaType = "image" | "video";
export type StoryPublisherType = "seller" | "admin";

export interface StoryVisual {
  background: string;
  accent: string;
  icon: string;
  eyebrow: string;
  headline: string;
  description: string;
}

export interface StoryItem {
  id: string;
  /** Numeric backend status identifier used by seen synchronization. */
  apiId?: number;
  /** Initial server-owned seen state, before local progress is reconciled. */
  seen?: boolean;
  mediaType: StoryMediaType;
  durationMs: number;
  likeCount?: number;
  views24h?: number;
  visual: StoryVisual;
  title?: string;
  subtitle?: string;
  imageSrc?: string;
  imagePosition?: string;
  posterSrc?: string;
  ctaLabel?: string;
  href?: string;
  videoSrc?: string;
  captions?: {
    src: string;
    srcLang: string;
    label: string;
    default?: boolean;
  }[];
  unavailable?: boolean;
}

export interface StoryGroup {
  id: string;
  title: string;
  subtitle: string;
  publisherType: StoryPublisherType;
  /** Store/profile destination opened from the viewer publisher header. */
  publisherHref?: string;
  coverSrc?: string;
  visual: StoryVisual;
  items: StoryItem[];
  /** Tray metadata used before this profile's Story media is lazy-loaded. */
  itemCount?: number;
  unseenItemCount?: number;
}

export interface StorySelection {
  groupId: string;
  itemId: string;
}
