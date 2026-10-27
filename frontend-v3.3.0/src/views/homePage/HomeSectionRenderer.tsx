import { CSSProperties, FC, ReactNode, useRef } from "react";
import { useTranslation } from "react-i18next";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Navigation } from "swiper/modules";
import Link from "next/link";
import { Skeleton } from "@heroui/react";
import { AppWindow, Boxes, Sparkles, Store as StoreIcon } from "lucide-react";

import ProductCard from "@/components/Cards/ProductCard";
import ProductCardSkeleton from "@/components/Skeletons/ProductCardSkeleton";
import CategoryCard from "@/components/Cards/CategoryCard";
import CategoryCardSkeleton from "@/components/Skeletons/CategoryCardSkeleton";
import BrandCard from "@/components/Cards/BrandCard";
import BrandCardSkeleton from "@/components/Skeletons/BrandCardSkeleton";
import StoreCard from "@/components/Cards/StoreCard";
import StoreCardSkeleton from "@/components/Skeletons/StoreCardSkeleton";
import SectionHeading from "@/components/SectionHeading";
import SwiperNavigation from "@/components/SwiperNavigation";
import { STORY_PROGRESS_STORAGE_KEY } from "@/components/Stories/storyProgress";
import {
  WatchAndBuyReelsSection,
  WatchAndBuyStoriesSection,
} from "@/components/WatchAndBuy";
import { toReels, toStoryTrayGroup } from "@/components/WatchAndBuy/adapters";
import { HomeLayoutBannerItem, HomeLayoutSection } from "@/types/ApiResponse";
import type {
  WatchAndBuyReelItem,
  WatchAndBuyStoryTrayItem,
} from "@/types/WatchAndBuy";
import {
  adaptHomeLayoutBrands,
  adaptHomeLayoutStores,
  getPlayImageRows,
  getSectionBanners,
  getSectionBrands,
  getSectionCategories,
  getSectionStores,
  resolveSectionBannerHref,
} from "@/helpers/homeLayout";
import { isRTL } from "@/helpers/functionalHelpers";
import { useHomeSectionProducts } from "@/hooks/useHomeSectionProducts";
import { useHomeSectionItems } from "@/hooks/useHomeSectionItems";
import { useSettings } from "@/contexts/SettingsContext";
import HomeHeroSection, { HomeHeroSkeleton } from "./HomeHeroSection";
import PlayImageSection from "./playImage/PlayImageSection";
import {
  isLegacyBannerSection,
  resolvePlayImageRow,
} from "./playImage/playImageConfig";

const encodeUrl = (url: string) =>
  url.replace(/\(/g, "%28").replace(/\)/g, "%29").replace(/\s/g, "%20");

const asRecord = (value: unknown): Record<string, unknown> =>
  value && typeof value === "object" && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : {};

const normalizeCssLength = (value: unknown): string | null => {
  if (typeof value === "number" && Number.isFinite(value)) return `${value}px`;
  if (typeof value !== "string") return null;

  const candidate = value.trim();
  if (!candidate) return null;
  if (/^-?\d+(\.\d+)?$/.test(candidate)) return `${candidate}px`;
  if (/^-?\d+(\.\d+)?(px|rem|em|vw|vh|dvh|svh|vmin|vmax|%)$/i.test(candidate)) {
    return candidate;
  }
  return null;
};

const normalizeCssColor = (value: unknown): string | null => {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  if (!candidate || /[;"{}]/.test(candidate)) return null;
  return /^[#(),.%\w\s-]+$/.test(candidate) ? candidate : null;
};

interface ThemeColorPair {
  light: string;
  dark: string;
}

const normalizeThemeColorPair = (value: unknown): ThemeColorPair | null => {
  const colors = asRecord(value);
  const light = normalizeCssColor(colors.light);
  const dark = normalizeCssColor(colors.dark);

  if (!light && !dark) return null;

  return {
    light: light ?? dark!,
    dark: dark ?? light!,
  };
};

const themeColorVariables = (
  colors: ThemeColorPair,
  target: "background" | "text",
): CSSProperties =>
  ({
    [`--home-section-${target}-light`]: colors.light,
    [`--home-section-${target}-dark`]: colors.dark,
  }) as CSSProperties;

const normalizeGap = (value: unknown): number | null => {
  const numeric =
    typeof value === "number"
      ? value
      : typeof value === "string" && value.trim()
        ? Number(value)
        : Number.NaN;
  return Number.isFinite(numeric) && numeric >= 0 ? numeric : null;
};

const builderFlag = (value: unknown, fallback: boolean): boolean => {
  if (value === true || value === 1 || value === "1") return true;
  if (value === false || value === 0 || value === "0") return false;
  return fallback;
};

const isZeroCssLength = (value: unknown): boolean => {
  if (typeof value === "number")
    return Object.is(value, 0) || Object.is(value, -0);
  if (typeof value !== "string") return false;
  return /^-?0(?:\.0+)?(?:px|rem|em|vw|vh|dvh|svh|vmin|vmax|%)?$/i.test(
    value.trim(),
  );
};

const getSpacing = (section: HomeLayoutSection) =>
  asRecord(asRecord(section.config).spacing);

const boxStyle = (
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

const getBorderRadiusStyle = (value: unknown): CSSProperties => {
  if (value === "none") return { borderRadius: "0px" };

  const scalar = normalizeCssLength(value);
  if (scalar !== null) {
    return isZeroCssLength(value) ? {} : { borderRadius: scalar };
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

interface SectionItemGap {
  horizontal?: number;
  vertical?: number;
}

export interface HomeSectionPresentation {
  outerStyle: CSSProperties;
  innerStyle: CSSProperties;
  itemGap: SectionItemGap;
  borderRadiusStyle: CSSProperties;
  textColor?: string;
}

/**
 * Background now comes from `config.background_type` + `config.background_color`
 * plus the section-level image fields (the old per-breakpoint image set is gone).
 * Unknown/`none` background types fall through to no styling.
 */
export const getSectionBackgroundStyle = (
  section: HomeLayoutSection,
): CSSProperties => {
  const config = asRecord(section.config);
  const backgroundType = config.background_type;
  const backgroundColor = normalizeCssColor(config.background_color);
  const themedBackgroundColor = normalizeThemeColorPair(
    config.background_color,
  );

  if (backgroundType === "image") {
    const configuredImage =
      typeof config.background_image === "string" &&
      config.background_image.trim()
        ? config.background_image
        : null;
    const image =
      configuredImage ||
      section.background_image ||
      section.card_background_image;
    if (image) {
      return {
        backgroundImage: `url("${encodeUrl(image)}")`,
        backgroundSize: "cover",
        backgroundPosition: "top",
        backgroundRepeat: "no-repeat",
      };
    }
  }

  if (backgroundType === "color") {
    if (themedBackgroundColor) {
      return themeColorVariables(themedBackgroundColor, "background");
    }
    if (backgroundColor) return { backgroundColor };
  }

  return {};
};

const hasSectionImageBackground = (section: HomeLayoutSection): boolean =>
  asRecord(section.config).background_type === "image" &&
  Boolean(getSectionBackgroundStyle(section).backgroundImage);

const getSectionImageBackgroundStyle = (
  section: HomeLayoutSection,
): "half" | "full" =>
  (asRecord(section.config).background_image_style ??
    section.background_image_style) === "full"
    ? "full"
    : "half";

const getImageContentStyle = (style: CSSProperties): CSSProperties => {
  const contentStyle = { ...style };

  delete contentStyle.backgroundImage;
  delete contentStyle.backgroundPosition;
  delete contentStyle.backgroundRepeat;
  delete contentStyle.backgroundSize;
  delete contentStyle.borderRadius;
  delete contentStyle.borderTopLeftRadius;
  delete contentStyle.borderTopRightRadius;
  delete contentStyle.borderBottomLeftRadius;
  delete contentStyle.borderBottomRightRadius;

  return contentStyle;
};

const getImageSurfaceStyle = (style: CSSProperties): CSSProperties => {
  const surfaceStyle = { ...style };

  delete surfaceStyle.padding;
  delete surfaceStyle.paddingTop;
  delete surfaceStyle.paddingBottom;
  delete surfaceStyle.paddingLeft;
  delete surfaceStyle.paddingRight;

  return surfaceStyle;
};

export const getSectionPresentation = (
  section: HomeLayoutSection,
): HomeSectionPresentation => {
  const config = asRecord(section.config);
  const spacing = getSpacing(section);
  const spacingBorderRadiusStyle = getBorderRadiusStyle(spacing.border_radius);
  const borderRadiusStyle =
    Object.keys(spacingBorderRadiusStyle).length > 0
      ? spacingBorderRadiusStyle
      : getBorderRadiusStyle(config.border_radius ?? config.radius);
  const themedTextColor = normalizeThemeColorPair(config.text_color);
  const textColor =
    normalizeCssColor(config.text_color) ??
    (themedTextColor ? "var(--home-section-text-color)" : undefined);
  const rawGap = spacing.item_gap;
  const scalarGap = normalizeGap(rawGap);
  const gap = asRecord(rawGap);
  const horizontal = scalarGap ?? normalizeGap(gap.horizontal) ?? undefined;
  const vertical = scalarGap ?? normalizeGap(gap.vertical) ?? undefined;
  const outerStyle = hasMeaningfulBoxSpacing(spacing.outer)
    ? boxStyle(spacing.outer, "margin")
    : {};
  const innerSpacingStyle = hasMeaningfulBoxSpacing(spacing.inner)
    ? boxStyle(spacing.inner, "padding")
    : {};

  return {
    // The builder serializes untouched spacing controls as zeroes. Ignore only
    // all-zero boxes so meaningful spacing remains independent of the radius.
    outerStyle,
    innerStyle: {
      ...getSectionBackgroundStyle(section),
      ...innerSpacingStyle,
      ...borderRadiusStyle,
      ...(themedTextColor ? themeColorVariables(themedTextColor, "text") : {}),
    },
    itemGap: { horizontal, vertical },
    borderRadiusStyle,
    textColor,
  };
};

interface SectionProps {
  section: HomeLayoutSection;
  isLoading?: boolean;
  /** Eager-load the first image when this is the leading home section. */
  priority?: boolean;
}

/** Outer wrapper every section type shares: test hook + optional background. */
const SectionShell: FC<{
  section: HomeLayoutSection;
  children: ReactNode;
  /** Lets builder rows escape the capped page container for true full bleed. */
  allowFullBleedChildren?: boolean;
}> = ({ section, children, allowFullBleedChildren = false }) => {
  const presentation = getSectionPresentation(section);
  const backgroundStyle = getSectionBackgroundStyle(section);
  const hasBackground = Object.keys(backgroundStyle).length > 0;
  const configuredBackgroundType = asRecord(section.config).background_type;
  const hasImageBackground = hasSectionImageBackground(section);
  const imageBackgroundStyle = getSectionImageBackgroundStyle(section);
  const hasSurfaceIntent =
    hasBackground ||
    configuredBackgroundType === "image" ||
    configuredBackgroundType === "color";
  const hasRadius = Object.keys(presentation.borderRadiusStyle).length > 0;
  const hasThemedBackground =
    "--home-section-background-light" in backgroundStyle;
  const hasThemedTextColor =
    "--home-section-text-light" in presentation.innerStyle;
  const shouldClipSurface = hasRadius && !allowFullBleedChildren;
  const shellClassName = hasSurfaceIntent
    ? `py-4 px-1 sm:px-6 ${
        shouldClipSurface ? "overflow-hidden" : hasRadius ? "" : "rounded-xl"
      }`
    : shouldClipSurface
      ? "overflow-hidden"
      : "";
  const themeClassName = [
    hasThemedBackground
      ? "bg-[var(--home-section-background-light)] dark:bg-[var(--home-section-background-dark)]"
      : "",
    hasThemedTextColor
      ? "[--home-section-text-color:var(--home-section-text-light)] dark:[--home-section-text-color:var(--home-section-text-dark)]"
      : "",
  ]
    .filter(Boolean)
    .join(" ");

  if (hasImageBackground) {
    if (imageBackgroundStyle === "full") {
      return (
        <div
          className="mb-8"
          style={presentation.outerStyle}
          data-testid={`home-section-${section.id}`}
        >
          <div
            className={`relative ${allowFullBleedChildren ? "" : "overflow-hidden"} ${hasRadius ? "" : "rounded-xl"} ${themeClassName}`.trim()}
            style={{
              ...getImageSurfaceStyle(presentation.innerStyle),
              backgroundPosition: "center top",
            }}
            data-testid={`home-section-inner-${section.id}`}
          >
            <div
              className="relative px-1 pt-[2%] pb-2 sm:px-6"
              style={getImageContentStyle(presentation.innerStyle)}
              data-testid={`home-section-content-${section.id}`}
            >
              {children}
            </div>
          </div>
        </div>
      );
    }

    return (
      <div
        className="mb-8"
        style={presentation.outerStyle}
        data-testid={`home-section-${section.id}`}
      >
        <div
          className="relative pt-5"
          data-testid={`home-section-inner-${section.id}`}
        >
          <div
            aria-hidden="true"
            className="absolute top-0 right-0 left-0 aspect-2/1 overflow-hidden rounded-xl md:aspect-5/1"
            style={{
              ...backgroundStyle,
              ...presentation.borderRadiusStyle,
              backgroundPosition: "center top",
            }}
            data-testid={`home-section-backdrop-${section.id}`}
          />
          <div
            className={`relative px-1 pt-[2%] pb-2 sm:px-6 ${themeClassName}`.trim()}
            style={getImageContentStyle(presentation.innerStyle)}
            data-testid={`home-section-content-${section.id}`}
          >
            {children}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div
      className=""
      style={presentation.outerStyle}
      data-testid={`home-section-${section.id}`}
    >
      <div
        className={`${shellClassName} ${themeClassName}`.trim() || undefined}
        style={presentation.innerStyle}
        data-testid={`home-section-inner-${section.id}`}
      >
        {children}
      </div>
    </div>
  );
};

/** Title + "see all" bar above a rail. */
const RailHeading: FC<{
  title: string;
  icon: ReactNode;
  seeAllHref: Parameters<typeof Link>[0]["href"];
  /** Product/store rails sit on mb-6, the denser tile rails on mb-4. */
  spacing?: "tight" | "loose";
  color?: string;
  imageBackground?: boolean;
}> = ({
  title,
  icon,
  seeAllHref,
  spacing = "loose",
  color,
  imageBackground = false,
}) => {
  const { t } = useTranslation();

  return (
    <div
      className={`flex justify-between w-full items-center ${
        spacing === "loose" ? "mb-6" : "mb-4"
      }`}
    >
      {imageBackground ? (
        <div />
      ) : (
        <SectionHeading
          title={title}
          description=""
          icon={icon}
          color={color}
        />
      )}
      <Link
        href={seeAllHref}
        className={
          imageBackground
            ? "inline-flex items-center justify-center rounded-xl border-gray-100 bg-gray-100 px-2 py-1 text-xs font-medium transition hover:opacity-80 dark:border-default-100 dark:bg-content1"
            : "text-xs sm:text-sm"
        }
        title={t("see_all")}
        style={!imageBackground && color ? { color } : undefined}
      >
        {t("see_all")}
      </Link>
    </div>
  );
};

/** How many slides from the end of a rail the next page is requested. */
const RAIL_PREFETCH_SLIDES = 3;
/** Placeholder slides appended while the next page is in flight. */
const RAIL_LOADING_SLIDES = 3;

const applySwiperGap = (
  breakpoints: Record<
    number,
    { slidesPerView: number | "auto"; spaceBetween?: number }
  >,
  configuredGap?: number,
) => {
  if (configuredGap === undefined) return breakpoints;
  return Object.fromEntries(
    Object.entries(breakpoints).map(([breakpoint, value]) => [
      breakpoint,
      { ...value, spaceBetween: configuredGap },
    ]),
  );
};

/**
 * Swipe handler that pages a rail once the user nears its end.
 *
 * Paging is driven by movement rather than Swiper's onReachEnd: a rail whose
 * slides all fit the viewport counts as "at the end" during init, which would
 * fetch every section on page load.
 */
const railSlideChange =
  (loadMore: () => void) =>
  (swiper: {
    activeIndex: number;
    slides: unknown[];
    params: { slidesPerView?: unknown };
  }) => {
    const perView = Math.ceil(Number(swiper.params.slidesPerView)) || 1;

    if (
      swiper.activeIndex + perView >=
      swiper.slides.length - RAIL_PREFETCH_SLIDES
    ) {
      loadMore();
    }
  };

/** A `type: "products"` section — title + horizontal ProductCard rail. */
const HomeProductsSection: FC<SectionProps> = ({
  section,
  isLoading = false,
}) => {
  const { t, i18n } = useTranslation();
  const prevRef = useRef(null);
  const nextRef = useRef(null);

  // The layout response only inlines `config.limit` products; the rest stream in
  // from /home-layout/sections/{id} as the user swipes toward the end.
  const { products, isLoadingMore, loadMore } = useHomeSectionProducts(section);
  const rtl = isRTL(i18n.resolvedLanguage || i18n.language);
  const presentation = getSectionPresentation(section);
  const horizontalGap = presentation.itemGap.horizontal;
  const verticalGap = presentation.itemGap.vertical ?? horizontalGap;
  const isVertical = asRecord(section.config).orientation === "vertical";

  // Nothing to show — render nothing rather than an empty heading.
  if (!isLoading && products.length === 0) return null;

  return (
    <SectionShell section={section}>
      {/* title is nullable — fall back rather than render an empty heading.
          The section feed endpoint returns no title, so carry it across for the
          page header; the page falls back if it's absent. */}
      <RailHeading
        title={section.title || t("pageTitle.products")}
        icon={<Boxes size={16} className="text-white" />}
        seeAllHref={{
          pathname: `/section/${section.id}`,
          query: section.title ? { title: section.title } : undefined,
        }}
        color={presentation.textColor}
        imageBackground={hasSectionImageBackground(section)}
      />

      {isVertical ? (
        <div
          className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-7"
          style={{
            ...(horizontalGap !== undefined
              ? { columnGap: `${horizontalGap}px` }
              : {}),
            ...(verticalGap !== undefined
              ? { rowGap: `${verticalGap}px` }
              : {}),
          }}
          data-testid="home-products-grid"
        >
          {isLoading
            ? Array.from({ length: 7 }).map((_, index) => (
                <ProductCardSkeleton key={`grid-skeleton-${index}`} />
              ))
            : products.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
        </div>
      ) : (
        <div className="relative group">
          <SwiperNavigation prevRef={prevRef} nextRef={nextRef} rtl={rtl} />

          <Swiper
            key={rtl ? `rtl-hls-${section.id}` : `ltr-hls-${section.id}`}
            dir={rtl ? "rtl" : "ltr"}
            slidesPerView={2}
            spaceBetween={horizontalGap ?? 4}
            breakpoints={applySwiperGap(
              {
                640: { slidesPerView: 3, spaceBetween: 12 },
                1024: { slidesPerView: 4, spaceBetween: 12 },
                1280: { slidesPerView: 5, spaceBetween: 12 },
                1440: { slidesPerView: 7, spaceBetween: 12 },
              },
              horizontalGap,
            )}
            lazyPreloadPrevNext={0}
            modules={[Navigation]}
            onBeforeInit={(swiper) => {
              const nav = swiper.params.navigation;
              if (nav && typeof nav !== "boolean") {
                nav.prevEl = prevRef.current;
                nav.nextEl = nextRef.current;
              }
            }}
            navigation={true}
            onSlideChange={railSlideChange(loadMore)}
          >
            {isLoading
              ? Array.from({ length: 7 }).map((_, index) => (
                  <SwiperSlide key={`skeleton-${index}`}>
                    <div className="py-0.5">
                      <ProductCardSkeleton />
                    </div>
                  </SwiperSlide>
                ))
              : products.map((product) => (
                  <SwiperSlide key={product.id}>
                    <div className="py-0.5">
                      <ProductCard product={product} />
                    </div>
                  </SwiperSlide>
                ))}

            {/* Kept inside the Swiper so the user can swipe straight into the
              incoming page instead of hitting a dead stop at the last card. */}
            {isLoadingMore &&
              Array.from({ length: RAIL_LOADING_SLIDES }).map((_, index) => (
                <SwiperSlide key={`load-more-skeleton-${index}`}>
                  <div className="py-0.5">
                    <ProductCardSkeleton />
                  </div>
                </SwiperSlide>
              ))}
          </Swiper>
        </div>
      )}
    </SectionShell>
  );
};

// Swiper configurations for different category layouts on the homepage.
// Easily customizable to adjust columns or margins per layout style.
const CATEGORY_SWIPER_CONFIGS = {
  custom_image: {
    slidesPerView: "auto" as const,
    spaceBetween: 12,
    breakpoints: {
      315: { slidesPerView: "auto" as const, spaceBetween: 12 },
      640: { slidesPerView: "auto" as const, spaceBetween: 16 },
      1024: { slidesPerView: "auto" as const, spaceBetween: 16 },
      1280: { slidesPerView: "auto" as const, spaceBetween: 16 },
    },
  },
  overlay: {
    slidesPerView: 2.2,
    spaceBetween: 12,
    breakpoints: {
      315: { slidesPerView: 2.2, spaceBetween: 12 },
      425: { slidesPerView: 2.5, spaceBetween: 12 },
      640: { slidesPerView: 3.5, spaceBetween: 16 },
      1024: { slidesPerView: 4.5, spaceBetween: 16 },
      1280: { slidesPerView: 5.5, spaceBetween: 16 },
    },
  },
  default: {
    slidesPerView: 3,
    spaceBetween: 1,
    breakpoints: {
      315: { slidesPerView: 3, spaceBetween: 1 },
      424: { slidesPerView: 4, spaceBetween: 1 },
      426: { slidesPerView: 5, spaceBetween: 1 },
      640: { slidesPerView: 7, spaceBetween: 12 },
      1024: { slidesPerView: 9, spaceBetween: 12 },
      1280: { slidesPerView: 10, spaceBetween: 12 },
      1440: { slidesPerView: 12, spaceBetween: 12 },
    },
  },
};

const CUSTOM_IMAGE_GRID_CLASSES: Record<number, string> = {
  1: "grid-cols-1",
  2: "grid-cols-1 md:grid-cols-2",
  3: "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3",
  4: "grid-cols-1 sm:grid-cols-2 lg:grid-cols-4",
  5: "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5",
  6: "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6",
};

const getCustomImageGridClasses = (value: unknown): string => {
  if (value === null || value === undefined || value === "") {
    return CUSTOM_IMAGE_GRID_CLASSES[2];
  }

  const requestedColumns = Number(value);
  const columnCount = Number.isFinite(requestedColumns)
    ? Math.min(6, Math.max(1, Math.round(requestedColumns)))
    : 2;

  return CUSTOM_IMAGE_GRID_CLASSES[columnCount];
};

/** A `type: "categories"` section — title + horizontal CategoryCard rail. */
const HomeCategoriesSection: FC<SectionProps> = ({
  section,
  isLoading = false,
}) => {
  const { t, i18n } = useTranslation();

  const categories = getSectionCategories(section.content);
  const rtl = isRTL(i18n.resolvedLanguage || i18n.language);

  if (!isLoading && categories.length === 0) return null;

  const style = section.style;
  const isOverlay = style === "overlay";
  const isCustomImage = style === "custom_image";
  const cardVariant = isOverlay
    ? "tile"
    : isCustomImage
      ? "image-only"
      : "icon";

  const configKey = isCustomImage
    ? "custom_image"
    : isOverlay
      ? "overlay"
      : "default";
  const swiperConfig = CATEGORY_SWIPER_CONFIGS[configKey];
  const presentation = getSectionPresentation(section);
  const horizontalGap = presentation.itemGap.horizontal;
  const verticalGap = presentation.itemGap.vertical;
  const usesGrid = section.config?.rows_layout === "grid";
  const customImageGridClasses = getCustomImageGridClasses(
    section.config?.grid_columns,
  );

  return (
    <SectionShell section={section}>
      <RailHeading
        title={section.title || t("home.categories.title")}
        icon={<AppWindow size={16} className="text-white" />}
        seeAllHref="/categories"
        spacing="tight"
        color={presentation.textColor}
        imageBackground={hasSectionImageBackground(section)}
      />

      {usesGrid ? (
        <div
          className={
            isCustomImage
              ? `grid ${customImageGridClasses} items-start justify-items-start w-full py-1`
              : "grid grid-cols-4 xs:grid-cols-5 sm:grid-cols-7 md:grid-cols-9 lg:grid-cols-11 xl:grid-cols-12 gap-y-3 gap-x-1 sm:gap-4 justify-items-center w-full py-1"
          }
          style={{
            ...(horizontalGap !== undefined
              ? { columnGap: `${horizontalGap}px` }
              : {}),
            ...(verticalGap !== undefined
              ? { rowGap: `${verticalGap}px` }
              : {}),
          }}
          data-testid={
            isCustomImage
              ? "home-categories-image-grid"
              : "home-categories-grid"
          }
        >
          {isLoading
            ? Array.from({ length: 12 }).map((_, index) => (
                <CategoryCardSkeleton
                  key={`cat-grid-skeleton-${index}`}
                  variant={cardVariant}
                />
              ))
            : categories.map((category) => (
                <CategoryCard
                  key={category.id}
                  category={category}
                  variant={cardVariant}
                  className={
                    isCustomImage ? "max-w-full" : "w-full justify-center"
                  }
                  imageStyle={presentation.borderRadiusStyle}
                />
              ))}
        </div>
      ) : (
        <Swiper
          key={rtl ? `rtl-hlc-${section.id}` : `ltr-hlc-${section.id}`}
          className={isCustomImage ? "home-category-image-swiper" : undefined}
          dir={rtl ? "rtl" : "ltr"}
          slidesPerView={swiperConfig.slidesPerView}
          spaceBetween={horizontalGap ?? swiperConfig.spaceBetween}
          breakpoints={applySwiperGap(swiperConfig.breakpoints, horizontalGap)}
          lazyPreloadPrevNext={0}
          modules={[Navigation]}
        >
          {isLoading
            ? Array.from({ length: 12 }).map((_, index) => (
                <SwiperSlide key={`cat-skeleton-${index}`}>
                  <CategoryCardSkeleton variant={cardVariant} />
                </SwiperSlide>
              ))
            : categories.map((category) => (
                <SwiperSlide
                  key={category.id}
                  style={
                    isCustomImage
                      ? {
                          width: "fit-content",
                          maxWidth: "min(100%, 280px)",
                        }
                      : undefined
                  }
                >
                  <CategoryCard
                    category={category}
                    variant={cardVariant}
                    imageStyle={presentation.borderRadiusStyle}
                  />
                </SwiperSlide>
              ))}
        </Swiper>
      )}
    </SectionShell>
  );
};

/** Placeholder shaped like a banner slide (the legacy sliders' aspect ratio). */
const BannerSkeleton: FC = () => (
  <Skeleton
    className="w-full aspect-409/240 rounded-lg"
    data-testid="banner-skeleton"
  />
);

/**
 * One banner image, wrapped in a link only when it actually points somewhere.
 * An unlinked slide stays a plain image rather than a focusable `href="#"`.
 */
const BannerSlide: FC<{
  item: HomeLayoutBannerItem;
  alt: string;
  borderRadiusStyle?: CSSProperties;
}> = ({ item, alt, borderRadiusStyle }) => {
  const href = resolveSectionBannerHref(item);
  const hasConfiguredRadius = Boolean(
    borderRadiusStyle && Object.keys(borderRadiusStyle).length > 0,
  );

  // Video-only slides are reserved for the full-page hero. A non-hero banner
  // rail keeps a stable empty fallback instead of passing an invalid source to
  // HeroUI's Image component.
  if (!item.image) {
    return (
      <div
        className={`w-full aspect-409/240 overflow-hidden bg-default-100 ${hasConfiguredRadius ? "" : "rounded-lg"}`}
        style={borderRadiusStyle}
      />
    );
  }

  const image = (
    <picture className="block h-full w-full">
      {item.image_web_mobile && (
        <source
          data-home-banner-mobile-source
          media="(max-width: 767px)"
          srcSet={item.image_web_mobile}
        />
      )}
      <img
        data-home-banner-desktop-image
        src={item.image}
        alt={alt}
        loading="eager"
        style={borderRadiusStyle}
        className={`block h-full w-full ${
          item.image_web_mobile ? "object-fill md:object-cover" : "object-cover"
        }`}
      />
    </picture>
  );

  if (!href) {
    return (
      <div
        className={`block w-full aspect-409/240 overflow-hidden ${hasConfiguredRadius ? "" : "rounded-lg"}`}
        style={borderRadiusStyle}
      >
        {image}
      </div>
    );
  }

  return (
    <Link
      href={href}
      title={alt}
      className={`w-full aspect-409/240 overflow-hidden ${hasConfiguredRadius ? "" : "rounded-lg"}`}
      style={borderRadiusStyle}
    >
      {image}
    </Link>
  );
};

const HomePlayImageSection: FC<SectionProps> = ({
  section,
  isLoading = false,
  priority = false,
}) => {
  const rows = getPlayImageRows(section.content);
  if (!isLoading && rows.length === 0) return null;
  const configuredRows = Array.isArray(section.config?.rows)
    ? section.config.rows
    : [];
  // The current API nests this compatibility marker under `config`, while
  // older/example payloads may expose it beside `type`. Support both shapes.
  const isLegacyBanner = isLegacyBannerSection(section);
  const compactLegacyBannerOnDesktop =
    isLegacyBanner &&
    typeof section.config?.position === "string" &&
    section.config.position.trim().toLowerCase() === "top";
  const hasFullBleedRow =
    rows.some(
      (row) =>
        resolvePlayImageRow(section, row).config.container_width === "full",
    ) || configuredRows.some((row) => asRecord(row).container_width === "full");

  return (
    <SectionShell section={section} allowFullBleedChildren={hasFullBleedRow}>
      <PlayImageSection
        section={section}
        rows={rows}
        isLoading={isLoading}
        priority={priority}
        compactLegacyBannerOnDesktop={compactLegacyBannerOnDesktop}
      />
    </SectionShell>
  );
};

/**
 * A `type: "banners"` or `type: "sliders"` section.
 *
 * `style` picks the arrangement: "grid" is a static responsive grid, "full" is
 * one edge-to-edge image per view, anything else is the legacy two-up rail.
 * Sliders are always full-width and autoplay whatever the style.
 *
 * Unlike the product/brand/store rails these do NOT page through
 * /home-layout/sections/{id}: a promo strip is a fixed, curated set, and
 * appending slides underneath Swiper's loop mode reshuffles the ones on screen.
 */
const HomeBannersSection: FC<
  SectionProps & { variant: "banners" | "sliders" }
> = ({ section, isLoading = false, variant }) => {
  const { t, i18n } = useTranslation();

  const banners = getSectionBanners(section.content);
  const rtl = isRTL(i18n.resolvedLanguage || i18n.language);

  if (!isLoading && banners.length === 0) return null;

  const alt = section.title || t("home.banners.alt");
  const isFullWidth = variant === "sliders" || section.style === "full";
  const isGrid = variant === "banners" && section.style === "grid";
  const isTwoColumn = !isFullWidth && !isGrid;
  const perView = isFullWidth ? 1 : 2;
  const presentation = getSectionPresentation(section);
  const horizontalGap = presentation.itemGap.horizontal;
  const verticalGap = presentation.itemGap.vertical;

  return (
    <SectionShell section={section}>
      {/* Banners are usually untitled; only show a heading when one was set.
          There is no "see all" — a promo strip has no listing page. */}
      {section.title && !hasSectionImageBackground(section) && (
        <div className="flex justify-between w-full items-center mb-4">
          <SectionHeading
            title={section.title}
            description=""
            icon={<Sparkles size={16} className="text-white" />}
            color={presentation.textColor}
          />
        </div>
      )}

      {isGrid ? (
        <div
          className="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2"
          style={{
            ...(horizontalGap !== undefined
              ? { columnGap: `${horizontalGap}px` }
              : {}),
            ...(verticalGap !== undefined
              ? { rowGap: `${verticalGap}px` }
              : {}),
          }}
        >
          {isLoading
            ? Array.from({ length: 2 }).map((_, index) => (
                <BannerSkeleton key={`banner-skeleton-${index}`} />
              ))
            : banners.map((item) => (
                <BannerSlide
                  key={item.id}
                  item={item}
                  alt={alt}
                  borderRadiusStyle={presentation.borderRadiusStyle}
                />
              ))}
        </div>
      ) : (
        <Swiper
          key={rtl ? `rtl-hlb-${section.id}` : `ltr-hlb-${section.id}`}
          dir={rtl ? "rtl" : "ltr"}
          modules={[Autoplay]}
          autoplay={{ delay: 4000, disableOnInteraction: false }}
          // Looping needs more slides than are on screen, otherwise Swiper
          // duplicates the set and the rail visibly jumps on the wrap.
          loop={banners.length > perView}
          spaceBetween={horizontalGap || 12}
          slidesPerView={1}
          breakpoints={
            isFullWidth
              ? { 315: { slidesPerView: 1 } }
              : isTwoColumn
                ? { 315: { slidesPerView: 1 }, 640: { slidesPerView: 2 } }
                : { 315: { slidesPerView: 1 } }
          }
          className={
            Object.keys(presentation.borderRadiusStyle).length > 0
              ? "shadow-none"
              : "rounded-lg shadow-none"
          }
          style={presentation.borderRadiusStyle}
        >
          {isLoading
            ? Array.from({ length: 2 }).map((_, index) => (
                <SwiperSlide key={`banner-skeleton-${index}`}>
                  <BannerSkeleton />
                </SwiperSlide>
              ))
            : banners.map((item) => (
                <SwiperSlide key={item.id}>
                  <BannerSlide
                    item={item}
                    alt={alt}
                    borderRadiusStyle={presentation.borderRadiusStyle}
                  />
                </SwiperSlide>
              ))}
        </Swiper>
      )}
    </SectionShell>
  );
};

/** A `type: "brands"` section — title + horizontal BrandCard rail. */
const HomeBrandsSection: FC<SectionProps> = ({
  section,
  isLoading = false,
}) => {
  const { t, i18n } = useTranslation();

  const {
    items: brands,
    isLoadingMore,
    loadMore,
  } = useHomeSectionItems(section, {
    getInline: getSectionBrands,
    adaptPage: adaptHomeLayoutBrands,
  });
  const rtl = isRTL(i18n.resolvedLanguage || i18n.language);
  const presentation = getSectionPresentation(section);
  const horizontalGap = presentation.itemGap.horizontal;

  if (!isLoading && brands.length === 0) return null;

  return (
    <SectionShell section={section}>
      <RailHeading
        title={section.title || t("home.brands.title")}
        icon={<Sparkles size={16} className="text-white" />}
        seeAllHref="/brands"
        spacing="tight"
        color={presentation.textColor}
        imageBackground={hasSectionImageBackground(section)}
      />

      <Swiper
        key={rtl ? `rtl-hlbr-${section.id}` : `ltr-hlbr-${section.id}`}
        dir={rtl ? "rtl" : "ltr"}
        slidesPerView={3}
        spaceBetween={horizontalGap ?? 4}
        breakpoints={applySwiperGap(
          {
            315: { slidesPerView: 3, spaceBetween: 4 },
            424: { slidesPerView: 4, spaceBetween: 4 },
            426: { slidesPerView: 5, spaceBetween: 4 },
            640: { slidesPerView: 7, spaceBetween: 12 },
            1024: { slidesPerView: 9, spaceBetween: 12 },
            1280: { slidesPerView: 10, spaceBetween: 12 },
            1440: { slidesPerView: 12, spaceBetween: 12 },
          },
          horizontalGap,
        )}
        lazyPreloadPrevNext={0}
        modules={[Navigation]}
        onSlideChange={railSlideChange(loadMore)}
      >
        {isLoading
          ? Array.from({ length: 8 }).map((_, index) => (
              <SwiperSlide key={`brand-skeleton-${index}`}>
                <BrandCardSkeleton />
              </SwiperSlide>
            ))
          : brands.map((brand) => (
              <SwiperSlide key={brand.id}>
                <div className="flex flex-col items-center pt-1 aspect-square">
                  <BrandCard brand={brand} />
                </div>
              </SwiperSlide>
            ))}

        {isLoadingMore &&
          Array.from({ length: RAIL_LOADING_SLIDES }).map((_, index) => (
            <SwiperSlide key={`brand-load-more-${index}`}>
              <BrandCardSkeleton />
            </SwiperSlide>
          ))}
      </Swiper>
    </SectionShell>
  );
};

/** A `type: "stores"` section — title + horizontal StoreCard rail. */
const HomeStoresSection: FC<SectionProps> = ({
  section,
  isLoading = false,
}) => {
  const { t, i18n } = useTranslation();
  const { isSingleVendor } = useSettings();
  const prevRef = useRef(null);
  const nextRef = useRef(null);

  const {
    items: stores,
    isLoadingMore,
    loadMore,
  } = useHomeSectionItems(section, {
    getInline: getSectionStores,
    adaptPage: adaptHomeLayoutStores,
  });
  const rtl = isRTL(i18n.resolvedLanguage || i18n.language);
  const presentation = getSectionPresentation(section);
  const horizontalGap = presentation.itemGap.horizontal;

  // A single-vendor site has no store list worth browsing — the legacy rail
  // hides itself the same way.
  if (isSingleVendor) return null;
  if (!isLoading && stores.length === 0) return null;

  return (
    <SectionShell section={section}>
      <RailHeading
        title={section.title || t("home.stores.title")}
        icon={<StoreIcon size={16} className="text-white" />}
        seeAllHref="/stores"
        color={presentation.textColor}
        imageBackground={hasSectionImageBackground(section)}
      />

      <div className="relative group">
        <SwiperNavigation prevRef={prevRef} nextRef={nextRef} rtl={rtl} />

        <Swiper
          key={rtl ? `rtl-hlst-${section.id}` : `ltr-hlst-${section.id}`}
          dir={rtl ? "rtl" : "ltr"}
          slidesPerView={2}
          spaceBetween={horizontalGap ?? 4}
          breakpoints={applySwiperGap(
            {
              640: { slidesPerView: 3, spaceBetween: 12 },
              1024: { slidesPerView: 4, spaceBetween: 12 },
              1280: { slidesPerView: 5, spaceBetween: 12 },
              1440: { slidesPerView: 7, spaceBetween: 12 },
            },
            horizontalGap,
          )}
          lazyPreloadPrevNext={0}
          modules={[Navigation]}
          onBeforeInit={(swiper) => {
            const nav = swiper.params.navigation;
            if (nav && typeof nav !== "boolean") {
              nav.prevEl = prevRef.current;
              nav.nextEl = nextRef.current;
            }
          }}
          navigation={true}
          onSlideChange={railSlideChange(loadMore)}
        >
          {isLoading
            ? Array.from({ length: 7 }).map((_, index) => (
                <SwiperSlide key={`store-skeleton-${index}`}>
                  <div className="py-0.5">
                    <StoreCardSkeleton />
                  </div>
                </SwiperSlide>
              ))
            : stores.map((store) => (
                <SwiperSlide key={store.id}>
                  <div className="py-0.5">
                    <StoreCard store={store} />
                  </div>
                </SwiperSlide>
              ))}

          {isLoadingMore &&
            Array.from({ length: RAIL_LOADING_SLIDES }).map((_, index) => (
              <SwiperSlide key={`store-load-more-${index}`}>
                <div className="py-0.5">
                  <StoreCardSkeleton />
                </div>
              </SwiperSlide>
            ))}
        </Swiper>
      </div>
    </SectionShell>
  );
};

const HomeStoriesSection: FC<SectionProps> = ({ section, isLoading }) => {
  const { t } = useTranslation();
  const stories = Array.isArray(section.content?.stories)
    ? section.content.stories.filter(
        (item): item is WatchAndBuyStoryTrayItem =>
          !!item &&
          typeof item === "object" &&
          !!(item as WatchAndBuyStoryTrayItem).profile?.username,
      )
    : [];

  if (!isLoading && stories.length === 0) return null;

  return (
    <SectionShell section={section}>
      {isLoading ? (
        <Skeleton className="mb-8 h-28 w-full rounded-xl" />
      ) : (
        <WatchAndBuyStoriesSection
          groups={stories.map(toStoryTrayGroup)}
          title={section.title || t("pages.play.storiesTitle")}
          description={t("pages.play.storiesDescription")}
          sectionId={`home-stories-${section.id}`}
          storageKey={STORY_PROGRESS_STORAGE_KEY}
          inset={false}
          showSeeAll
          ringColor={normalizeCssColor(section.config?.ring_color) ?? undefined}
          showProfileName={builderFlag(section.config?.show_profile_name, true)}
          trayStyle={
            section.style === "story_card" ? "story_card" : "avatar_circle"
          }
          itemGap={getSectionPresentation(section).itemGap.horizontal}
        />
      )}
    </SectionShell>
  );
};

const HomeReelsSection: FC<SectionProps> = ({ section, isLoading }) => {
  const { t } = useTranslation();
  const { currencySymbol } = useSettings();
  const reels = Array.isArray(section.content?.reels)
    ? section.content.reels.filter(
        (item): item is WatchAndBuyReelItem =>
          !!item && typeof item === "object" && "id" in item,
      )
    : [];
  const mappedReels = toReels(reels, currencySymbol);

  if (!isLoading && mappedReels.length === 0) return null;

  return (
    <SectionShell section={section}>
      {isLoading ? (
        <Skeleton className="mb-8 h-64 w-full rounded-xl" />
      ) : (
        <WatchAndBuyReelsSection
          reels={mappedReels}
          title={section.title || t("pages.play.reelsTitle")}
          description={t("pages.play.reelsDescription")}
          sectionId={`home-reels-${section.id}`}
          layout={section.style === "grid" ? "grid" : "rail"}
          badgeLabel={null}
          inset={false}
          showSeeAll
          showLikes={builderFlag(section.config?.show_likes, false)}
          showTitle={builderFlag(section.config?.show_title, true)}
          itemGap={getSectionPresentation(section).itemGap.horizontal}
        />
      )}
    </SectionShell>
  );
};

/**
 * Renders one home-layout section by `type`.
 *
 * Unknown types render nothing (with a dev-only warning) so new backend
 * section types can never break the homepage — add a case here as each
 * one comes online.
 */
const HomeSectionRenderer: FC<SectionProps> = ({
  section,
  isLoading,
  priority,
}) => {
  if (!section) return null;

  switch (section.type) {
    case "products":
      return <HomeProductsSection section={section} isLoading={isLoading} />;

    case "categories":
      return <HomeCategoriesSection section={section} isLoading={isLoading} />;

    case "banners":
    case "sliders":
      return (
        <HomeBannersSection
          section={section}
          isLoading={isLoading}
          variant={section.type === "sliders" ? "sliders" : "banners"}
        />
      );

    case "play_image":
      return (
        <HomePlayImageSection
          section={section}
          isLoading={isLoading}
          priority={priority}
        />
      );

    case "hero":
      return isLoading ? (
        <HomeHeroSkeleton />
      ) : (
        <HomeHeroSection section={section} />
      );

    case "brands":
      return <HomeBrandsSection section={section} isLoading={isLoading} />;

    case "stores":
      return <HomeStoresSection section={section} isLoading={isLoading} />;

    case "stories":
      return <HomeStoriesSection section={section} isLoading={isLoading} />;

    case "reels":
      return <HomeReelsSection section={section} isLoading={isLoading} />;

    default:
      if (process.env.NODE_ENV !== "production") {
        console.warn(
          `[home-layout] Unsupported section type "${section.type}" (id=${section.id}) — skipped.`,
        );
      }
      return null;
  }
};

export default HomeSectionRenderer;
