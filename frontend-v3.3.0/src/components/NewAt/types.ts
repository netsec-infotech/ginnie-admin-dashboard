/** Single-row and banner-style distributions. */
export type NewAtHorizontalVariant =
  | "grid"
  | "horizontal-scroll"
  | "featured"
  | "compact-grid"
  | "mosaic"
  | "spotlight"
  | "stacked-deck"
  | "staggered"
  | "alternating"
  | "gallery-strip";

/** Multi-row distributions for larger, mixed-category catalogues. */
export type NewAtVerticalVariant =
  | "vertical-grid"
  | "vertical-compact"
  | "vertical-featured"
  | "vertical-mosaic";

export type NewAtVariant = NewAtHorizontalVariant | NewAtVerticalVariant;

export type NewAtBackground =
  | {
      type: "color";
      /** Any valid CSS solid color or gradient. */
      value?: string;
    }
  | {
      type: "image";
      imageUrl: string;
      position?: string;
    }
  | {
      type: "plain";
      value?: string;
    };

export interface NewAtItem {
  id: string | number;
  brand: string;
  title: string;
  description: string;
  imageUrl: string;
  deepLink: string;
}

export type NewAtCardProps = NewAtItem;

export interface NewAtSectionProps {
  title?: string;
  subtitle?: string;
  variant?: NewAtVariant;
  background?: NewAtBackground;
  items: readonly NewAtItem[];
  className?: string;
}
