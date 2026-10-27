import React, { useCallback, useState } from "react";
import { getCategories } from "@/routes/api";
import { useInfiniteData } from "@/hooks/useInfiniteData";
import TabButton from "@/components/custom/TabButton";
import SkeletonTabButton from "@/components/custom/SkeletonTabButton";
import { Category } from "@/types/ApiResponse";
import { useTranslation } from "react-i18next";
import { Swiper, SwiperSlide } from "swiper/react";
import type { Swiper as SwiperType } from "swiper";
import { Mousewheel } from "swiper/modules";
import { isRTL } from "@/helpers/functionalHelpers";
import useScrollImageCollapse from "@/hooks/useScrollImageCollapse";
import { useHeaderScroll } from "@/contexts/HeaderScrollContext";
import InlineDataRetry from "./InlineDataRetry";

interface Props {
  parentSlug: string;
  selectedSubcategory?: string;
  onSelect?: (slug: string) => void;
  onClear?: () => void;
  className?: string;
}

const PER_PAGE = 12;

const SubcategoryTabs: React.FC<Props> = ({
  parentSlug,
  selectedSubcategory = "",
  onSelect,
  onClear,
  className = "",
}) => {
  const { t, i18n } = useTranslation();
  const [showLeftShadow, setShowLeftShadow] = useState(false);
  const [showRightShadow, setShowRightShadow] = useState(false);
  const { isMobileHeaderExpanded, isMobileViewport } = useHeaderScroll();
  const autoHideSubcategoryImages = useScrollImageCollapse({
    disabled: isMobileViewport,
  });
  const hideSubcategoryImages = isMobileViewport
    ? !isMobileHeaderExpanded
    : autoHideSubcategoryImages;
  const currentLang = i18n.resolvedLanguage || i18n.language;
  const rtl = isRTL(currentLang);

  const {
    data: subcategories,
    isLoading,
    isLoadingMore,
    hasMore,
    loadMore,
    error,
    loadMoreError,
    refetch,
    retryLoadMore,
    isValidating,
  } = useInfiniteData<Category>({
    fetcher: getCategories,
    perPage: PER_PAGE,
    extraParams: { slug: parentSlug, scope_category_slug: parentSlug },
    passLocation: true,
    dataKey: `subcategories-tabs-${parentSlug}`,
    forceFetchOnMount: true,
  });

  const handleClick = (slug: string) => {
    if (slug === "all") {
      onClear?.();
    } else {
      onSelect?.(slug);
    }
  };

  const handleSeeMore = () => {
    if (hasMore && !isLoadingMore) loadMore();
  };

  const updateShadows = useCallback((swiper: SwiperType) => {
    const hasOverflow =
      swiper.width <
      swiper.slides.reduce((sum, slide) => sum + slide.offsetWidth, 0);

    if (!hasOverflow) {
      setShowLeftShadow(false);
      setShowRightShadow(false);
      return;
    }

    setShowLeftShadow(!swiper.isBeginning);
    setShowRightShadow(!swiper.isEnd);
  }, []);

  return (
    <div
      className={`relative w-full bg-white/70 dark:bg-black/70 border-b border-white/50 dark:border-white/10 backdrop-blur-xl backdrop-saturate-150 ring-1 ring-inset ring-white/25 dark:ring-white/5 sticky z-40 isolate transition-[padding] duration-200 ease-in-out motion-reduce:transition-none ${hideSubcategoryImages ? "py-1" : "py-2"} ${className}`}
      style={{ top: "var(--site-navbar-height, 3.5rem)" }}
      aria-label={t("subcategories")}
    >
      <div
        className={`flex items-end gap-0.5 transition-[padding] duration-200 ease-in-out motion-reduce:transition-none ${hideSubcategoryImages ? "py-0.5" : "py-1"}`}
      >
        <TabButton
          slug="all"
          title={t("all_products")}
          isSelected={!selectedSubcategory}
          onClick={() => handleClick("all")}
          size="lg"
          hideIcon={hideSubcategoryImages}
        />

        {/* Swiper wrapper — shadows scoped here so they don't cover "All Products" */}
        <div className="relative flex-1 overflow-x-hidden">
          {/* Left shadow — only inside swiper area */}
          {showLeftShadow && (
            <div
              className="absolute left-0 top-0 bottom-0 w-8 
              bg-linear-to-r from-white via-white/80 to-transparent 
              dark:from-gray-900 dark:via-gray-900/80 pointer-events-none z-10"
            />
          )}

          {/* Right shadow */}
          {showRightShadow && (
            <div
              className="absolute right-0 top-0 bottom-0 w-8 
              bg-linear-to-l from-white via-white/80 to-transparent 
              dark:from-gray-900 dark:via-gray-900/80 pointer-events-none z-10"
            />
          )}

          <Swiper
            key={rtl ? "rtl-sct" : "ltr-sct"}
            dir={rtl ? "rtl" : "ltr"}
            grabCursor
            resizeObserver={false}
            slidesPerView="auto"
            slidesOffsetAfter={8}
            modules={[Mousewheel]}
            mousewheel
            spaceBetween={8}
            breakpoints={{
              0: {
                spaceBetween: 2,
              },
              640: {
                spaceBetween: 8,
              },
            }}
            onSwiper={(swiper) => {
              updateShadows(swiper);
            }}
            onSlideChange={updateShadows}
            onResize={updateShadows}
            onProgress={updateShadows}
          >
            {isLoading
              ? Array.from({ length: 6 }).map((_, i) => (
                  <SwiperSlide key={i} style={{ width: "auto" }}>
                    <SkeletonTabButton
                      size="lg"
                      hideIcon={hideSubcategoryImages}
                    />
                  </SwiperSlide>
                ))
              : subcategories.map((cat) => (
                  <SwiperSlide key={cat.id} style={{ width: "auto" }}>
                    <TabButton
                      slug={cat.slug}
                      title={cat.title}
                      isSelected={selectedSubcategory === cat.slug}
                      category={cat}
                      onClick={() => handleClick(cat.slug)}
                      size="lg"
                      hideIcon={hideSubcategoryImages}
                    />
                  </SwiperSlide>
                ))}

            {hasMore && (
              <SwiperSlide style={{ width: "auto" }}>
                <TabButton
                  slug="see-more"
                  title={
                    loadMoreError
                      ? t("retry", { defaultValue: "Try again" })
                      : isLoadingMore
                        ? t("loading")
                        : t("see_more")
                  }
                  isSelected={false}
                  isLoading={isLoadingMore}
                  onClick={loadMoreError ? retryLoadMore : handleSeeMore}
                  size="lg"
                  hideIcon={hideSubcategoryImages}
                />
              </SwiperSlide>
            )}

            {error && !isLoading && (
              <SwiperSlide style={{ width: "auto" }}>
                <InlineDataRetry
                  message={t("errors.subcategories_unavailable", {
                    defaultValue:
                      "More categories are taking a moment to load.",
                  })}
                  retryLabel={t("retry", { defaultValue: "Try again" })}
                  isRetrying={isValidating}
                  onRetry={() => void refetch()}
                />
              </SwiperSlide>
            )}
          </Swiper>
        </div>
      </div>
    </div>
  );
};

export default SubcategoryTabs;
