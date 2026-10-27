import { useRef, type CSSProperties, type FC } from "react";
import { Skeleton } from "@heroui/react";
import { useTranslation } from "react-i18next";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Navigation, Pagination } from "swiper/modules";

import SwiperNavigation from "@/components/SwiperNavigation";
import { isRTL } from "@/helpers/functionalHelpers";
import PlayImageItemCard from "@/views/homePage/playImage/PlayImageItemCard";
import {
  getPlayImageBorderRadiusStyle,
  getPlayImageGridSpan,
  getPlayImageItemConfig,
  getPlayImageRowSurfaceStyle,
  getPlayImageSlidesPerView,
  normalizeBuilderBoolean,
  normalizePlayImageGap,
  type ResolvedPlayImageRow,
} from "@/views/homePage/playImage/playImageConfig";

interface PlayImageRowProps {
  sectionId: number;
  row: ResolvedPlayImageRow;
  isLoading: boolean;
  priorityFirst: boolean;
  trailingGap: number;
  compactLegacyBannerOnDesktop: boolean;
  fallbackAlt: (itemIndex: number) => string;
}

/** Web `auto` keeps promotional artwork compact while mobile stays immersive. */
const AUTO_DESKTOP_SLIDES = 3;

const PlayImageRowSkeleton: FC = () => (
  <Skeleton className="block w-full aspect-409/240 rounded-lg" />
);

const PlayImageGrid: FC<
  Pick<PlayImageRowProps, "row" | "isLoading" | "priorityFirst" | "fallbackAlt">
> = ({ row, isLoading, priorityFirst, fallbackAlt }) => {
  const gap = normalizePlayImageGap(row.config.grid_gap);
  const borderRadiusStyle = getPlayImageBorderRadiusStyle(
    row.config.border_radius,
  );
  const itemCount = row.items.length || 1;
  const hoverEffect = String(row.config.hover_effect || "none").toLowerCase();

  return (
    <div className="home-play-image-grid grid" style={{ gap: `${gap}px` }}>
      {isLoading && row.items.length === 0 ? (
        <div
          className="home-play-image-grid-item"
          style={
            {
              "--play-image-span-desktop": 12,
              "--play-image-span-mobile": 12,
            } as CSSProperties
          }
        >
          <PlayImageRowSkeleton />
        </div>
      ) : (
        row.items.map((item, itemIndex) => {
          const itemConfig = getPlayImageItemConfig(item.config);
          const desktopSpan = getPlayImageGridSpan(itemConfig.cols, itemCount);
          const mobileSpan = getPlayImageGridSpan(
            itemConfig.cols_mobile ?? itemConfig.cols,
            itemCount,
          );

          return (
            <div
              key={item.id}
              className="home-play-image-grid-item min-w-0"
              style={
                {
                  "--play-image-span-desktop": desktopSpan,
                  "--play-image-span-mobile": mobileSpan,
                } as CSSProperties
              }
            >
              <PlayImageItemCard
                item={item}
                alt={
                  typeof itemConfig.alt === "string" && itemConfig.alt.trim()
                    ? itemConfig.alt.trim()
                    : fallbackAlt(itemIndex)
                }
                borderRadiusStyle={borderRadiusStyle}
                hoverEffect={hoverEffect}
                priority={priorityFirst && itemIndex === 0}
              />
            </div>
          );
        })
      )}
    </div>
  );
};

const PlayImageScroll: FC<
  Pick<
    PlayImageRowProps,
    | "row"
    | "isLoading"
    | "priorityFirst"
    | "compactLegacyBannerOnDesktop"
    | "fallbackAlt"
  >
> = ({
  row,
  isLoading,
  priorityFirst,
  compactLegacyBannerOnDesktop,
  fallbackAlt,
}) => {
  const { i18n } = useTranslation();
  const rtl = isRTL(i18n.resolvedLanguage || i18n.language);
  const prevRef = useRef<HTMLButtonElement>(null);
  const nextRef = useRef<HTMLButtonElement>(null);
  const gap = normalizePlayImageGap(row.config.grid_gap);
  const configuredDesktopCount = row.config.items_to_show;
  const configuredDesktopCountText =
    typeof configuredDesktopCount === "string"
      ? configuredDesktopCount.trim().toLowerCase()
      : null;
  const useAutoDesktopSlides =
    configuredDesktopCount == null ||
    configuredDesktopCountText === "" ||
    configuredDesktopCountText === "auto";
  const autoDesktopPerView =
    row.items.length > 0
      ? Math.min(AUTO_DESKTOP_SLIDES, row.items.length)
      : AUTO_DESKTOP_SLIDES;
  const desktopPerView = getPlayImageSlidesPerView(
    row.config.items_to_show,
    autoDesktopPerView,
  );
  const useLegacyBannerDesktopFrame =
    compactLegacyBannerOnDesktop &&
    !useAutoDesktopSlides &&
    desktopPerView <= 1;
  const mobilePerView = getPlayImageSlidesPerView(
    row.config.items_to_show_mobile,
    1,
  );
  const showBullets = normalizeBuilderBoolean(row.config.show_bullets);
  const showScrollButtons = normalizeBuilderBoolean(
    row.config.show_scroll_buttons,
  );
  const autoScroll = normalizeBuilderBoolean(row.config.auto_scroll);
  const configuredSeconds = Number(row.config.auto_scroll_time);
  const autoScrollDelay =
    Number.isFinite(configuredSeconds) && configuredSeconds > 0
      ? Math.max(1000, configuredSeconds * 1000)
      : 3000;
  const borderRadiusStyle = getPlayImageBorderRadiusStyle(
    row.config.border_radius,
  );
  const hoverEffect = String(row.config.hover_effect || "none").toLowerCase();

  return (
    <div className="group relative">
      <Swiper
        key={`${rtl ? "rtl" : "ltr"}-${row.row_index}`}
        dir={rtl ? "rtl" : "ltr"}
        className={`home-play-image-scroll ${showBullets ? "pb-7!" : ""}`}
        slidesPerView={mobilePerView}
        spaceBetween={gap}
        breakpoints={{
          768: {
            slidesPerView: useAutoDesktopSlides ? "auto" : desktopPerView,
          },
        }}
        modules={[Autoplay, Navigation, Pagination]}
        onBeforeInit={(swiper) => {
          const navigation = swiper.params.navigation;
          if (
            showScrollButtons &&
            navigation &&
            typeof navigation !== "boolean"
          ) {
            navigation.prevEl = prevRef.current;
            navigation.nextEl = nextRef.current;
          }
        }}
        navigation={showScrollButtons}
        pagination={showBullets ? { clickable: true } : false}
        autoplay={
          autoScroll
            ? {
                delay: autoScrollDelay,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
              }
            : false
        }
        loop={
          autoScroll &&
          row.items.length > Math.ceil(Math.max(desktopPerView, mobilePerView))
        }
      >
        {isLoading && row.items.length === 0 ? (
          <SwiperSlide>
            <PlayImageRowSkeleton />
          </SwiperSlide>
        ) : (
          row.items.map((item, itemIndex) => {
            const itemConfig = getPlayImageItemConfig(item.config);
            return (
              <SwiperSlide
                key={item.id}
                className={
                  useAutoDesktopSlides
                    ? "home-play-image-scroll-slide--auto"
                    : undefined
                }
              >
                <PlayImageItemCard
                  item={item}
                  alt={
                    typeof itemConfig.alt === "string" && itemConfig.alt.trim()
                      ? itemConfig.alt.trim()
                      : fallbackAlt(itemIndex)
                  }
                  borderRadiusStyle={borderRadiusStyle}
                  hoverEffect={hoverEffect}
                  priority={priorityFirst && itemIndex === 0}
                  compactOnDesktop={useAutoDesktopSlides || desktopPerView > 1}
                  legacyBannerOnDesktop={useLegacyBannerDesktopFrame}
                />
              </SwiperSlide>
            );
          })
        )}
      </Swiper>
      {showScrollButtons && (
        <SwiperNavigation prevRef={prevRef} nextRef={nextRef} rtl={rtl} />
      )}
    </div>
  );
};

/** Selects and renders one configured Play Image row. */
const PlayImageRow: FC<PlayImageRowProps> = ({
  sectionId,
  row,
  isLoading,
  priorityFirst,
  trailingGap,
  compactLegacyBannerOnDesktop,
  fallbackAlt,
}) => {
  const isFullWidth = row.config.container_width === "full";

  return (
    <div
      className={`${isFullWidth ? "w-page" : "w-full"} relative`}
      style={{
        ...getPlayImageRowSurfaceStyle(row),
        ...(isFullWidth
          ? {
              marginLeft: "calc(50% - 50cqw)",
              marginRight: "calc(50% - 50cqw)",
            }
          : {}),
        ...(trailingGap > 0 ? { marginBottom: `${trailingGap}px` } : {}),
      }}
      data-testid={`home-play-image-row-${sectionId}-${row.row_index}`}
      data-row-style={row.style}
    >
      {row.title && (
        <h3 className="mb-3 text-base font-semibold">{row.title}</h3>
      )}
      {row.style === "scroll" ? (
        <PlayImageScroll
          row={row}
          isLoading={isLoading}
          priorityFirst={priorityFirst}
          compactLegacyBannerOnDesktop={compactLegacyBannerOnDesktop}
          fallbackAlt={fallbackAlt}
        />
      ) : (
        <PlayImageGrid
          row={row}
          isLoading={isLoading}
          priorityFirst={priorityFirst}
          fallbackAlt={fallbackAlt}
        />
      )}
    </div>
  );
};

export default PlayImageRow;
