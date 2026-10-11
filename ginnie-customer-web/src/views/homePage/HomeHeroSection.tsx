import { CSSProperties, FC, ReactNode, useEffect, useState } from "react";
import Link from "next/link";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Pagination } from "swiper/modules";
import { Skeleton } from "@heroui/react";
import { ArrowUpRight } from "lucide-react";

import {
  HomeLayoutBannerItem,
  HomeLayoutHeroItem,
  HomeLayoutSection,
} from "@/types/ApiResponse";
import {
  getSectionBanners,
  resolveSectionBannerHref,
} from "@/helpers/homeLayout";
import { useSettings } from "@/contexts/SettingsContext";
import { resolveHomeHeaderAppearance } from "@/helpers/homeHeaderAppearance";

interface HomeHeroSectionProps {
  section: HomeLayoutSection;
}

interface HeroOverlay {
  content: ReactNode;
  href: string | null;
}

interface HeroItemPresentation {
  cardStyle: CSSProperties;
  titleColor?: string;
  gap: number;
}

type CssVariableStyle = CSSProperties &
  Partial<Record<`--${string}`, string | number>>;

interface HeroPresentation {
  sectionStyle: CSSProperties;
  frameClassName: string;
  frameStyle: CssVariableStyle;
  mediaFit: Exclude<CSSProperties["objectFit"], undefined>;
  mediaPosition: string;
  autoplay: boolean;
  autoplayDelay: number;
  pagination: boolean;
  itemGap?: number;
}

const heroFrameBaseClass = "mx-auto w-full max-w-none overflow-hidden";
const heroFrameHeightClass =
  "h-[var(--home-hero-height-mobile)] sm:h-[var(--home-hero-height-tablet)] lg:h-[var(--home-hero-height-desktop)]";
const heroFrameAspectClass =
  "aspect-[var(--home-hero-aspect-ratio)] min-h-[var(--home-hero-height-mobile)] sm:min-h-[var(--home-hero-height-tablet)] lg:min-h-[var(--home-hero-height-desktop)] max-h-[var(--home-hero-max-height)]";
const DEFAULT_HERO_HEIGHTS = {
  mobile: "220px",
  tablet: "300px",
  desktop: "700px",
};
const DEFAULT_HERO_MAX_HEIGHT = "500px";
const DEFAULT_HERO_SURFACE = "hsl(var(--heroui-content1, 0 0% 100%))";
const DEFAULT_HERO_FOREGROUND = "hsl(var(--heroui-foreground, 222 47% 11%))";
const DEFAULT_HERO_MEDIA_POSITION = "center center";
const DEFAULT_HERO_MEDIA_FIT: HeroPresentation["mediaFit"] = "cover";

const encodeCssUrl = (url: string) =>
  url.replace(/\(/g, "%28").replace(/\)/g, "%29").replace(/\s/g, "%20");

const normalizeMediaUrl = (value: unknown): string | null => {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  if (!candidate) return null;

  // Be tolerant of URLs copied from markdown-rendered API viewers.
  const markdownLink = candidate.match(/^\[[^\]]+\]\((.+)\)$/);
  return markdownLink?.[1]?.trim() || candidate;
};

const useHeaderCropRatio = (
  headerImageUrl: string | null,
  heroImageUrl: string | null,
): number | null => {
  const mediaKey = `${headerImageUrl ?? ""}::${heroImageUrl ?? ""}`;
  const [resolvedCrop, setResolvedCrop] = useState<{
    mediaKey: string;
    ratio: number | null;
  }>({ mediaKey: "", ratio: null });

  useEffect(() => {
    if (!headerImageUrl || !heroImageUrl) return;

    let active = true;
    const headerImage = new window.Image();
    const heroImage = new window.Image();
    let headerLoaded = false;
    let heroLoaded = false;

    const resolveRatio = () => {
      if (!active || !headerLoaded || !heroLoaded) return;

      const isCompatibleCrop =
        headerImage.naturalWidth === heroImage.naturalWidth &&
        headerImage.naturalHeight > 0 &&
        headerImage.naturalHeight < heroImage.naturalHeight;

      setResolvedCrop({
        mediaKey,
        ratio: isCompatibleCrop
          ? headerImage.naturalHeight / headerImage.naturalWidth
          : null,
      });
    };

    headerImage.onload = () => {
      headerLoaded = true;
      resolveRatio();
    };
    heroImage.onload = () => {
      heroLoaded = true;
      resolveRatio();
    };
    const resolveFailure = () => {
      if (active) setResolvedCrop({ mediaKey, ratio: null });
    };
    headerImage.onerror = resolveFailure;
    heroImage.onerror = resolveFailure;
    headerImage.src = headerImageUrl;
    heroImage.src = heroImageUrl;

    return () => {
      active = false;
      headerImage.onload = null;
      headerImage.onerror = null;
      heroImage.onload = null;
      heroImage.onerror = null;
    };
  }, [headerImageUrl, heroImageUrl, mediaKey]);

  return resolvedCrop.mediaKey === mediaKey ? resolvedCrop.ratio : null;
};

const asRecord = (value: unknown): Record<string, unknown> =>
  value && typeof value === "object" && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : {};

const firstUsableValue = (
  records: Record<string, unknown>[],
  ...keys: string[]
): unknown => {
  for (const record of records) {
    for (const key of keys) {
      const value = record[key];
      if (value === null || value === undefined) continue;
      if (typeof value === "string" && value.trim() === "") continue;
      return value;
    }
  }
  return undefined;
};

const getHeroStyleValue = (
  section: HomeLayoutSection,
  ...keys: string[]
): unknown => {
  const media = asRecord(section.hero_media);
  const config = asRecord(section.config);
  return firstUsableValue([media, config], ...keys);
};

const normalizeCssLength = (value: unknown): string | null => {
  if (typeof value === "number" && Number.isFinite(value)) {
    return `${value}px`;
  }

  if (typeof value !== "string") return null;

  const candidate = value.trim();
  if (!candidate) return null;
  if (/^\d+(\.\d+)?$/.test(candidate)) return `${candidate}px`;
  if (/^\d+(\.\d+)?(px|rem|em|vw|vh|dvh|svh|vmin|vmax|%)$/i.test(candidate)) {
    return candidate;
  }

  return null;
};

const normalizeCssColor = (value: unknown): string | null => {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  if (!candidate || /[;"{}]/.test(candidate)) return null;
  if (/^[#(),.%\w\s-]+$/.test(candidate)) return candidate;
  return null;
};

const normalizeBorderRadius = (value: unknown): string | null => {
  if (value === "none") return "0px";
  return normalizeCssLength(value);
};

const isZeroCssLength = (value: unknown): boolean => {
  if (typeof value === "number")
    return Object.is(value, 0) || Object.is(value, -0);
  if (typeof value !== "string") return false;
  return /^-?0(?:\.0+)?(?:px|rem|em|vw|vh|dvh|svh|vmin|vmax|%)?$/i.test(
    value.trim(),
  );
};

const getBoxSpacingStyle = (
  value: unknown,
  kind: "margin" | "padding",
): CSSProperties => {
  const box = asRecord(value);
  const style: CSSProperties = {};
  const top = normalizeCssLength(box.top);
  const bottom = normalizeCssLength(box.bottom);
  const sides = normalizeCssLength(box.sides);
  const left = normalizeCssLength(box.left) ?? sides;
  const right = normalizeCssLength(box.right) ?? sides;

  if (top !== null) style[`${kind}Top`] = top;
  if (bottom !== null) style[`${kind}Bottom`] = bottom;
  if (left !== null) style[`${kind}Left`] = left;
  if (right !== null) style[`${kind}Right`] = right;
  return style;
};

const hasMeaningfulBoxSpacing = (value: unknown): boolean => {
  const box = asRecord(value);

  return [box.top, box.bottom, box.left, box.right, box.sides].some(
    (spacing) =>
      normalizeCssLength(spacing) !== null && !isZeroCssLength(spacing),
  );
};

const getHeroBorderRadiusStyle = (value: unknown): CSSProperties => {
  const scalar = normalizeBorderRadius(value);
  if (scalar !== null) {
    if (value !== "none" && isZeroCssLength(value)) return {};
    return { borderRadius: scalar };
  }

  const radius = asRecord(value);
  const configuredCorners = [
    radius.top_left,
    radius.top_right,
    radius.bottom_left,
    radius.bottom_right,
  ].filter((corner) => normalizeCssLength(corner) !== null);
  if (
    configuredCorners.length > 0 &&
    configuredCorners.every(isZeroCssLength)
  ) {
    return {};
  }

  const style: CSSProperties = {};
  const topLeft = normalizeCssLength(radius.top_left);
  const topRight = normalizeCssLength(radius.top_right);
  const bottomLeft = normalizeCssLength(radius.bottom_left);
  const bottomRight = normalizeCssLength(radius.bottom_right);
  if (topLeft !== null) style.borderTopLeftRadius = topLeft;
  if (topRight !== null) style.borderTopRightRadius = topRight;
  if (bottomLeft !== null) style.borderBottomLeftRadius = bottomLeft;
  if (bottomRight !== null) style.borderBottomRightRadius = bottomRight;
  return style;
};

const normalizeGap = (value: unknown): number | null => {
  const numeric =
    typeof value === "number"
      ? value
      : typeof value === "string" && value.trim()
        ? Number(value)
        : Number.NaN;
  return Number.isFinite(numeric) && numeric >= 0 ? numeric : null;
};

const normalizeObjectPosition = (value: unknown): string | null => {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  if (!candidate || /[;"{}()]/.test(candidate)) return null;
  if (/^[\w.%\-\s]+$/.test(candidate)) return candidate;
  return null;
};

const normalizeObjectFit = (
  value: unknown,
): HeroPresentation["mediaFit"] | null => {
  if (typeof value !== "string") return null;
  const candidate = value.trim().toLowerCase();
  if (
    candidate === "fill" ||
    candidate === "cover" ||
    candidate === "contain" ||
    candidate === "scale-down" ||
    candidate === "none"
  ) {
    return candidate;
  }
  return null;
};

const normalizeAspectRatio = (value: unknown): string | null => {
  if (typeof value === "number" && Number.isFinite(value) && value > 0) {
    return String(value);
  }

  if (typeof value !== "string") return null;

  const candidate = value.trim();
  if (!candidate) return null;
  if (/^\d+(\.\d+)?$/.test(candidate)) return candidate;
  if (/^\d+(\.\d+)?\s*\/\s*\d+(\.\d+)?$/.test(candidate)) {
    return candidate.replace(/\s*\/\s*/, " / ");
  }
  return null;
};

const normalizeBoolean = (value: unknown, fallback: boolean): boolean => {
  if (typeof value === "boolean") return value;
  if (typeof value === "number") return value !== 0;
  if (typeof value !== "string") return fallback;

  const candidate = value.trim().toLowerCase();
  if (["1", "true", "yes", "on", "enabled"].includes(candidate)) return true;
  if (["0", "false", "no", "off", "disabled"].includes(candidate)) {
    return false;
  }
  return fallback;
};

const normalizeDelay = (value: unknown, fallback: number): number => {
  const numericValue =
    typeof value === "number"
      ? value
      : typeof value === "string"
        ? Number(value.trim())
        : Number.NaN;

  return Number.isFinite(numericValue) && numericValue >= 1000
    ? numericValue
    : fallback;
};

const getHeroPresentation = (section: HomeLayoutSection): HeroPresentation => {
  const config = asRecord(section.config);
  const spacing = asRecord(config.spacing);
  const height = normalizeCssLength(
    getHeroStyleValue(section, "height", "hero_height", "banner_height"),
  );
  const configuredMobileHeight = normalizeCssLength(
    getHeroStyleValue(
      section,
      "mobile_height",
      "height_mobile",
      "mobile_banner_height",
    ),
  );
  const configuredTabletHeight = normalizeCssLength(
    getHeroStyleValue(
      section,
      "tablet_height",
      "height_tablet",
      "tablet_banner_height",
    ),
  );
  const configuredDesktopHeight = normalizeCssLength(
    getHeroStyleValue(
      section,
      "desktop_height",
      "height_desktop",
      "desktop_banner_height",
    ),
  );
  const mobileHeight =
    configuredMobileHeight ?? height ?? DEFAULT_HERO_HEIGHTS.mobile;
  const tabletHeight =
    configuredTabletHeight ?? height ?? DEFAULT_HERO_HEIGHTS.tablet;
  const desktopHeight =
    configuredDesktopHeight ?? height ?? DEFAULT_HERO_HEIGHTS.desktop;
  const maxHeight =
    normalizeCssLength(
      getHeroStyleValue(section, "max_height", "maximum_height"),
    ) ?? DEFAULT_HERO_MAX_HEIGHT;
  const aspectRatio = normalizeAspectRatio(
    getHeroStyleValue(section, "aspect_ratio", "ratio"),
  );
  const spacingBorderRadiusStyle = getHeroBorderRadiusStyle(
    firstUsableValue([spacing], "border_radius"),
  );
  const borderRadiusStyle =
    Object.keys(spacingBorderRadiusStyle).length > 0
      ? spacingBorderRadiusStyle
      : getHeroBorderRadiusStyle(
          getHeroStyleValue(section, "border_radius", "radius"),
        );
  const backgroundType = firstUsableValue([config], "background_type");
  const backgroundColor = normalizeCssColor(
    firstUsableValue([config], "background_color"),
  );
  const foregroundColor = normalizeCssColor(
    getHeroStyleValue(section, "foreground_color", "font_color", "text_color"),
  );
  const configBackgroundImage = firstUsableValue([config], "background_image");
  const backgroundImage =
    typeof configBackgroundImage === "string"
      ? configBackgroundImage
      : section.background_image || section.card_background_image;
  const hasExplicitHeight = Boolean(
    height ||
    configuredMobileHeight ||
    configuredTabletHeight ||
    configuredDesktopHeight,
  );
  const frameClassName = [
    heroFrameBaseClass,
    aspectRatio && !hasExplicitHeight
      ? heroFrameAspectClass
      : heroFrameHeightClass,
  ].join(" ");
  const frameStyle: CssVariableStyle = {
    "--home-hero-height-mobile": mobileHeight,
    "--home-hero-height-tablet": tabletHeight,
    "--home-hero-height-desktop": desktopHeight,
    "--home-hero-max-height": maxHeight,
  };
  if (aspectRatio) frameStyle["--home-hero-aspect-ratio"] = aspectRatio;
  Object.assign(
    frameStyle,
    hasMeaningfulBoxSpacing(spacing.inner)
      ? getBoxSpacingStyle(spacing.inner, "padding")
      : {},
    borderRadiusStyle,
  );

  const sectionStyle: CSSProperties = {
    backgroundColor: DEFAULT_HERO_SURFACE,
    color: foregroundColor ?? DEFAULT_HERO_FOREGROUND,
    ...(hasMeaningfulBoxSpacing(spacing.outer)
      ? getBoxSpacingStyle(spacing.outer, "margin")
      : {}),
  };

  if (backgroundType === "image" && backgroundImage) {
    sectionStyle.backgroundImage = `url("${encodeCssUrl(backgroundImage)}")`;
    sectionStyle.backgroundSize = "cover";
    sectionStyle.backgroundPosition = "center";
    sectionStyle.backgroundRepeat = "no-repeat";
  } else if (backgroundType === "color" && backgroundColor) {
    sectionStyle.backgroundColor = backgroundColor;
  }

  return {
    sectionStyle,
    frameClassName,
    frameStyle,
    mediaFit:
      normalizeObjectFit(
        getHeroStyleValue(
          section,
          "object_fit",
          "media_fit",
          "image_fit",
          "fit",
        ),
      ) ?? DEFAULT_HERO_MEDIA_FIT,
    mediaPosition:
      normalizeObjectPosition(
        getHeroStyleValue(
          section,
          "object_position",
          "image_position",
          "media_position",
        ),
      ) ?? DEFAULT_HERO_MEDIA_POSITION,
    autoplay: normalizeBoolean(
      getHeroStyleValue(section, "autoplay", "auto_play", "slider_autoplay"),
      true,
    ),
    autoplayDelay: normalizeDelay(
      getHeroStyleValue(
        section,
        "autoplay_delay",
        "autoplay_speed",
        "slide_delay",
        "interval",
      ),
      4500,
    ),
    pagination: normalizeBoolean(
      getHeroStyleValue(section, "pagination", "show_pagination"),
      true,
    ),
    itemGap:
      normalizeGap(spacing.item_gap) ??
      normalizeGap(asRecord(spacing.item_gap).horizontal) ??
      undefined,
  };
};

const defaultHeroFrameStyle: CssVariableStyle = {
  "--home-hero-height-mobile": DEFAULT_HERO_HEIGHTS.mobile,
  "--home-hero-height-tablet": DEFAULT_HERO_HEIGHTS.tablet,
  "--home-hero-height-desktop": DEFAULT_HERO_HEIGHTS.desktop,
  "--home-hero-max-height": DEFAULT_HERO_MAX_HEIGHT,
};

const getHeroOverlay = (section: HomeLayoutSection): HeroOverlay | null => {
  const media = section.hero_media;
  if (!media || typeof media !== "object" || Array.isArray(media)) return null;

  const badge = typeof media.badge === "string" ? media.badge : null;
  const title = typeof media.title === "string" ? media.title : null;
  const description =
    typeof media.description === "string" ? media.description : null;
  const ctaLabel = typeof media.cta_label === "string" ? media.cta_label : null;
  const href = typeof media.cta_url === "string" ? media.cta_url : null;

  if (!badge && !title && !description && !ctaLabel) return null;

  return {
    href,
    content: (
      <div className="max-w-[80%] sm:max-w-md">
        {badge && (
          <span className="mb-2 inline-flex rounded-full bg-white/20 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] backdrop-blur-sm sm:text-xs">
            {badge}
          </span>
        )}
        {title && (
          <h2 className="text-xl font-extrabold leading-tight sm:text-3xl lg:text-4xl">
            {title}
          </h2>
        )}
        {description && (
          <p className="mt-1.5 text-sm font-medium text-white/90 sm:mt-2 sm:text-base">
            {description}
          </p>
        )}
        {ctaLabel && (
          <span className="mt-3 inline-flex rounded-full bg-white px-4 py-2 text-xs font-bold text-black shadow-sm sm:mt-4 sm:text-sm">
            {ctaLabel}
          </span>
        )}
      </div>
    ),
  };
};

const getHeroItems = (section: HomeLayoutSection): HomeLayoutHeroItem[] => {
  const rawItems = section.content?.items;
  if (!Array.isArray(rawItems)) return [];

  return rawItems.flatMap((raw) => {
    const item = asRecord(raw);
    const id = item.id;
    if (typeof id !== "string" && typeof id !== "number") return [];

    return [
      {
        id,
        title: typeof item.title === "string" ? item.title : null,
        image: normalizeMediaUrl(item.image),
        default_image: normalizeMediaUrl(item.default_image),
        type: typeof item.type === "string" ? item.type : null,
        target_id:
          typeof item.target_id === "string" ||
          typeof item.target_id === "number"
            ? item.target_id
            : null,
        slug: typeof item.slug === "string" ? item.slug : null,
      },
    ];
  });
};

const getHeroItemPresentation = (
  section: HomeLayoutSection,
): HeroItemPresentation => {
  const config = asRecord(section.config);
  const spacing = asRecord(config.spacing);
  const cardBackgroundType = config.card_bg_type;
  const cardBackgroundColor = normalizeCssColor(config.card_bg_color);
  const titleColor = normalizeCssColor(config.card_title_color) ?? undefined;
  const cardStyle: CSSProperties = titleColor ? { color: titleColor } : {};

  if (cardBackgroundType === "image" && section.card_background_image) {
    cardStyle.backgroundImage = `url("${encodeCssUrl(section.card_background_image)}")`;
    cardStyle.backgroundPosition = "center";
    cardStyle.backgroundRepeat = "no-repeat";
    cardStyle.backgroundSize = "cover";
  } else if (cardBackgroundType === "color" && cardBackgroundColor) {
    cardStyle.backgroundColor = cardBackgroundColor;
  }

  return {
    cardStyle,
    titleColor,
    gap:
      normalizeGap(spacing.item_gap) ??
      normalizeGap(asRecord(spacing.item_gap).horizontal) ??
      12,
  };
};

const HeroItemCard: FC<{
  item: HomeLayoutHeroItem;
  itemSource: string | null;
  sectionStyle: string | null;
  presentation: HeroItemPresentation;
}> = ({ item, itemSource, sectionStyle, presentation }) => {
  const initialImage = item.image || item.default_image;
  const [imageSource, setImageSource] = useState(initialImage);
  const showImage = sectionStyle !== "title";
  const showTitle = sectionStyle !== "image";
  const isHorizontal = showImage && showTitle;
  const linkType = item.type || itemSource;
  const href = resolveSectionBannerHref({
    id: typeof item.id === "number" ? item.id : 0,
    image: imageSource,
    link_type: linkType,
    link_url: null,
    target_id: typeof item.target_id === "number" ? item.target_id : null,
    slug: item.slug,
  });
  const className = [
    "group/hero-item relative flex shrink-0 overflow-hidden rounded-xl border-none shadow-md transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-xl",
    isHorizontal
      ? "h-20 w-44 flex-row sm:h-24 sm:w-52 lg:h-28 lg:w-60"
      : "w-20 flex-col sm:w-24 lg:w-28",
    Object.keys(presentation.cardStyle).length === 0
      ? "bg-content1/95 backdrop-blur-sm"
      : "",
  ].join(" ");

  const content = (
    <>
      {showImage && (
        <div
          className={`relative overflow-hidden bg-default-100 ${
            isHorizontal
              ? "absolute inset-0 h-full w-full"
              : "aspect-square w-full"
          }`}
        >
          {imageSource ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={imageSource}
              alt={item.title || "Featured item"}
              loading="eager"
              className="h-full w-full object-cover transition-transform duration-500 group-hover/hero-item:scale-110"
              onError={() => {
                if (item.default_image && imageSource !== item.default_image) {
                  setImageSource(item.default_image);
                } else {
                  setImageSource(null);
                }
              }}
            />
          ) : (
            <div className="h-full w-full bg-default-100" />
          )}
        </div>
      )}
      {showTitle &&
        item.title &&
        (isHorizontal ? (
          <div className="absolute inset-x-0 bottom-0 z-10 flex translate-y-0 justify-end bg-linear-to-t from-black/90 via-black/55 to-transparent px-3 pb-2 pt-8 text-white opacity-100 transition-[opacity,transform] duration-300 sm:translate-y-2 sm:opacity-0 sm:group-hover/hero-item:translate-y-0 sm:group-hover/hero-item:opacity-100">
            <div className="flex max-w-full items-center overflow-hidden rounded-full bg-white/90 text-black shadow-sm">
              <span
                className="min-w-0 flex-1 truncate pl-3 py-1 text-[11px] font-semibold sm:text-xs"
                style={
                  presentation.titleColor
                    ? { color: presentation.titleColor }
                    : undefined
                }
              >
                {item.title}
              </span>
              <span className="flex h-7 w-7 shrink-0 items-center justify-center text-black">
                <ArrowUpRight size={14} strokeWidth={2.5} aria-hidden="true" />
              </span>
            </div>
          </div>
        ) : (
          <span
            className="line-clamp-2 min-h-8 px-2 py-1.5 text-center text-[10px] font-semibold leading-tight sm:text-xs"
            style={
              presentation.titleColor
                ? { color: presentation.titleColor }
                : undefined
            }
          >
            {item.title}
          </span>
        ))}
    </>
  );

  if (!href) {
    return (
      <div
        className={className}
        style={presentation.cardStyle}
        data-testid={`home-hero-item-${item.id}`}
      >
        {content}
      </div>
    );
  }

  return (
    <Link
      href={href}
      title={item.title || "Featured item"}
      className={className}
      style={presentation.cardStyle}
      data-testid={`home-hero-item-${item.id}`}
    >
      {content}
    </Link>
  );
};

const HeroItemsRail: FC<{
  section: HomeLayoutSection;
  items: HomeLayoutHeroItem[];
}> = ({ section, items }) => {
  if (items.length === 0) return null;

  const config = asRecord(section.config);
  const itemSource =
    typeof config.item_source === "string" ? config.item_source : null;
  const presentation = getHeroItemPresentation(section);

  return (
    <div className="relative z-10 mx-auto w-full max-w-none px-3 py-3 sm:px-4 lg:px-5">
      <div
        className="flex max-w-full overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        style={{ gap: `${presentation.gap}px` }}
        data-testid="home-hero-items"
      >
        {items.map((item) => (
          <HeroItemCard
            key={item.id}
            item={item}
            itemSource={itemSource}
            sectionStyle={section.style}
            presentation={presentation}
          />
        ))}
      </div>
    </div>
  );
};

/**
 * Stable first-paint placeholder. Its responsive height is exactly the one used
 * by the live hero, preventing the header/content from jumping while a
 * category's first layout response is in flight.
 */
export const HomeHeroSkeleton: FC = () => (
  <section
    aria-label="Loading promotion"
    style={{
      backgroundColor: DEFAULT_HERO_SURFACE,
      color: DEFAULT_HERO_FOREGROUND,
    }}
    className="relative left-1/2 -mt-4 mb-6 w-screen -translate-x-1/2 overflow-hidden sm:mb-8 lg:mb-10"
    data-testid="home-hero-skeleton"
  >
    <Skeleton
      className={`${heroFrameBaseClass} ${heroFrameHeightClass} rounded-none`}
      style={defaultHeroFrameStyle}
    />
  </section>
);

const HeroSlide: FC<{
  item: HomeLayoutBannerItem;
  alt: string;
  overlay?: ReactNode;
  fallbackHref?: string | null;
  fallbackStyle: CSSProperties;
  mediaFit: HeroPresentation["mediaFit"];
  mediaPosition: string;
  headerImageUrl?: string | null;
}> = ({
  item,
  alt,
  overlay,
  fallbackHref = null,
  fallbackStyle,
  mediaFit,
  mediaPosition,
  headerImageUrl = null,
}) => {
  const [mediaFailed, setMediaFailed] = useState(false);
  const href = resolveSectionBannerHref(item) || fallbackHref;
  const headerCropRatio = useHeaderCropRatio(
    headerImageUrl,
    item.media_type === "image" ? item.image : null,
  );
  const needsBackdrop =
    mediaFit === "contain" || mediaFit === "scale-down" || mediaFit === "none";

  const content = (
    <div className="relative h-full w-full overflow-hidden">
      {!mediaFailed && needsBackdrop && item.image && (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={item.image}
          alt=""
          aria-hidden="true"
          loading="eager"
          style={{ objectPosition: mediaPosition }}
          className="absolute inset-0 h-full w-full scale-105 object-cover blur-lg brightness-75"
          data-testid="home-hero-backdrop"
        />
      )}

      {!mediaFailed && item.media_type === "video" && item.video_url ? (
        <video
          autoPlay
          loop
          muted
          playsInline
          preload="metadata"
          poster={item.image || undefined}
          onError={() => setMediaFailed(true)}
          style={{ objectFit: mediaFit, objectPosition: mediaPosition }}
          className="absolute inset-0 h-full w-full"
          aria-label={alt}
        >
          <source src={item.video_url} />
        </video>
      ) : !mediaFailed && item.image ? (
        // The API owns these remote media URLs, so Next Image cannot reliably
        // reserve them without a configured domain. The enclosing aspect ratio
        // reserves the exact box before this eager image begins loading.
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={item.image}
          alt={alt}
          loading="eager"
          onError={() => setMediaFailed(true)}
          style={{ objectFit: mediaFit, objectPosition: mediaPosition }}
          className={`absolute inset-0 h-full w-full ${
            headerCropRatio !== null ? "xl:hidden" : ""
          }`}
        />
      ) : (
        <div
          style={fallbackStyle}
          className="absolute inset-0"
          role="img"
          aria-label={`${alt} unavailable`}
        />
      )}

      {!mediaFailed &&
        item.media_type === "image" &&
        item.image &&
        headerCropRatio !== null && (
          // The settings image is a top crop of this hero. At desktop widths,
          // continue the full image immediately after that crop instead of
          // independently scaling and repeating its top edge.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={item.image}
            alt={alt}
            loading="eager"
            onError={() => setMediaFailed(true)}
            className="absolute left-0 hidden h-auto w-full max-w-none xl:block"
            // style={{ top: `calc(-100vw * ${headerCropRatio})` }}
          />
        )}

      {overlay && (
        <div className="absolute inset-0 flex items-end bg-linear-to-t from-black/65 via-black/15 to-transparent px-[max(1rem,env(safe-area-inset-left))] pb-[max(1rem,env(safe-area-inset-bottom))] pt-8 text-white sm:px-8 sm:pb-7">
          {overlay}
        </div>
      )}
    </div>
  );

  if (!href) return content;

  return (
    <Link
      href={href}
      title={alt}
      className="block h-full w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-white"
    >
      {content}
    </Link>
  );
};

/**
 * The first full-width `banners` section on the home page.
 *
 * The API's current one-item payload renders as a plain, full-bleed image. A
 * multi-item payload uses the same outer box, adding only Swiper's swipe,
 * autoplay and pagination behaviour—there is no markup or size change needed
 * when the backend turns the banner into a carousel later.
 */
const HomeHeroSection: FC<HomeHeroSectionProps> = ({ section }) => {
  const { homeGeneralSettings } = useSettings();
  const headerAppearance = resolveHomeHeaderAppearance(homeGeneralSettings);
  const headerImageUrl =
    headerAppearance.type === "image"
      ? (headerAppearance.backgroundImage ?? null)
      : null;
  const itemBanners = getSectionBanners(section.content);
  const heroMedia = asRecord(section.hero_media);
  const config = asRecord(section.config);
  const usesItemLayout = config.layout === "items";
  const heroItems = usesItemLayout ? getHeroItems(section) : [];
  const heroMediaUrl =
    typeof section.hero_media === "string"
      ? normalizeMediaUrl(section.hero_media)
      : normalizeMediaUrl(section.hero_media?.url);
  const heroMediaTypeValue = firstUsableValue(
    [heroMedia, config],
    "media_type",
    "type",
  );
  const heroMediaType =
    typeof heroMediaTypeValue === "string" ? heroMediaTypeValue : "image";
  const heroPoster = normalizeMediaUrl(heroMedia.poster);
  // Current `type: "hero"` payloads usually provide content.items. Some admin
  // configurations only supply hero_media, which should still reserve and show
  // the same full-bleed slot instead of disappearing.
  let banners = usesItemLayout ? [] : itemBanners;
  if ((usesItemLayout || banners.length === 0) && heroMediaUrl) {
    banners = [
      {
        id: section.id,
        image: heroMediaType === "video" ? heroPoster : heroMediaUrl,
        media_type: heroMediaType,
        video_url: heroMediaType === "video" ? heroMediaUrl : null,
        link_type: null,
        link_url: null,
        target_id: null,
        slug: null,
      },
    ];
  } else if (usesItemLayout && heroItems.length > 0) {
    // Keep the configured hero surface visible even if its primary media is
    // temporarily unavailable; the linked item cards can still be used.
    banners = [
      {
        id: section.id,
        image: null,
        media_type: "image",
        video_url: null,
        link_type: null,
        link_url: null,
        target_id: null,
        slug: null,
      },
    ];
  }

  // An empty/malformed banner list intentionally takes no space. This avoids a
  // coloured dead area while still keeping the header's category surface valid.
  if (banners.length === 0) return null;

  const alt = section.title || "Promotional banner";
  const overlay = getHeroOverlay(section);
  const presentation = getHeroPresentation(section);
  const itemCards = usesItemLayout ? (
    <HeroItemsRail section={section} items={heroItems} />
  ) : null;
  const fallbackStyle: CSSProperties = {
    backgroundColor:
      typeof presentation.sectionStyle.backgroundColor === "string"
        ? presentation.sectionStyle.backgroundColor
        : DEFAULT_HERO_SURFACE,
  };
  const slides = banners.map((item) => (
    <HeroSlide
      key={item.id}
      item={item}
      alt={alt}
      overlay={overlay?.content}
      // Item cards are links themselves; never wrap that layout in a second
      // full-hero link, which would create invalid nested anchors.
      fallbackHref={usesItemLayout ? null : overlay?.href}
      fallbackStyle={fallbackStyle}
      mediaFit={presentation.mediaFit}
      mediaPosition={presentation.mediaPosition}
      headerImageUrl={headerImageUrl}
    />
  ));

  return (
    <section
      aria-label={alt}
      style={presentation.sectionStyle}
      className="relative left-1/2 z-0 -mt-4 mb-6 w-screen -translate-x-1/2 overflow-hidden sm:mb-8 lg:mb-10"
      data-testid={`home-hero-${section.id}`}
    >
      {banners.length === 1 ? (
        <div
          className={presentation.frameClassName}
          style={presentation.frameStyle}
          data-testid="home-hero-frame"
        >
          {slides[0]}
        </div>
      ) : (
        <Swiper
          key={`home-hero-${section.id}-${banners.map((banner) => banner.id).join("-")}`}
          modules={[Autoplay, Pagination]}
          loop
          slidesPerView={1}
          spaceBetween={presentation.itemGap}
          autoplay={
            presentation.autoplay
              ? {
                  delay: presentation.autoplayDelay,
                  disableOnInteraction: true,
                  pauseOnMouseEnter: true,
                }
              : false
          }
          pagination={
            presentation.pagination
              ? { clickable: true, dynamicBullets: banners.length > 5 }
              : false
          }
          className={`home-hero-swiper ${presentation.frameClassName}`}
          style={presentation.frameStyle}
          data-testid="home-hero-frame"
        >
          {slides.map((slide, index) => (
            <SwiperSlide key={banners[index].id} className="h-full">
              {slide}
            </SwiperSlide>
          ))}
        </Swiper>
      )}
      {itemCards}
    </section>
  );
};

export default HomeHeroSection;
