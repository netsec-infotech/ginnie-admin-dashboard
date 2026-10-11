import React, { useCallback, useEffect, useState, useRef } from "react";
import { useInfiniteData } from "@/hooks/useInfiniteData";
import { getCategories } from "@/routes/api";
import { Swiper, SwiperSlide } from "swiper/react";
import type { Swiper as SwiperType } from "swiper";
import TabButton from "../custom/TabButton";
import SkeletonTabButton from "../custom/SkeletonTabButton";
import { setCookie } from "@/lib/cookies";
import { onHomeCategoryChange } from "@/helpers/events";
import { useSettings } from "@/contexts/SettingsContext";
import { useRouter } from "next/router";
import { Mousewheel } from "swiper/modules";
import { useTranslation } from "react-i18next";
import { isRTL } from "@/helpers/functionalHelpers";
import {
  applyHomeCategoryTheme,
  resolveHomeCategoryTheme,
} from "@/helpers/homeCategoryTheme";

interface Category {
  id: number;
  title: string;
  slug: string;
  image: string;
  icon: string;
  active_icon: string;
  description: string | null;
  status: string;
  background_color?: string | null;
}

interface CategoryTabsProps {
  defaultCategory?: string;
  className?: string;
  sticky?: boolean;
  mergedSurface?: boolean;
  isCompact?: boolean;
}

const PER_PAGE = 15;

const CategoryTabs: React.FC<CategoryTabsProps> = ({
  defaultCategory = "all",
  className = "",
  sticky = false,
  mergedSurface = false,
  isCompact = false,
}) => {
  const fullTabsRef = useRef<HTMLDivElement>(null);
  const [fullTabsHeight, setFullTabsHeight] = useState<number>(0);

  // Measure actual height on mount and resize
  useEffect(() => {
    if (!fullTabsRef.current) return;
    const ro = new ResizeObserver(([entry]) => {
      setFullTabsHeight(entry.contentRect.height);
    });
    ro.observe(fullTabsRef.current);
    return () => ro.disconnect();
  }, []);

  const [showLeftShadow, setShowLeftShadow] = useState(false);
  const [showRightShadow, setShowRightShadow] = useState(false);
  const swiperRef = useRef<SwiperType | null>(null);

  const { homeGeneralSettings, webSettings } = useSettings();
  const router = useRouter();
  const { i18n } = useTranslation();
  const currentLang = i18n.resolvedLanguage || i18n.language;
  const rtl = isRTL(currentLang);

  // The URL is authoritative, so the selection is derived rather than stored:
  // navigating to a bare "/" (e.g. clicking the header logo from
  // "/?category=beauty") must fall back to `defaultCategory`, not to whatever
  // the cookie still remembers. The cookie is only a hint for the first SSR
  // hit, when there is no query yet.
  const queryCategory = (router.query.category as string) || "";
  const selectedCategory = queryCategory || defaultCategory;

  // Mirror the derived selection out to the external systems that can't read
  // it: the cookie SSR/scoped fetches use, and the refetch event.
  const syncedCategory = useRef<string | null>(null);

  useEffect(() => {
    if (!router.isReady) return;
    if (syncedCategory.current === selectedCategory) return;

    const isFirstSync = syncedCategory.current === null;
    syncedCategory.current = selectedCategory;

    setCookie("homeCategory", selectedCategory);

    // On the first pass the server already rendered this category, so firing
    // the event would trigger a redundant refetch of data we just received.
    if (!isFirstSync) {
      onHomeCategoryChange();
    }
  }, [router.isReady, selectedCategory]);

  const {
    data: categories,
    isLoading,
    isLoadingMore,
    hasMore,
    loadMore,
    error,
    refetch,
  } = useInfiniteData<Category>({
    fetcher: getCategories,
    perPage: PER_PAGE,
    dataKey: "categories-tab",
    forceFetchOnMount: true,
    passLocation: true,
    extraParams: { home: true },
  });

  // The hero reacts to the selected category colour. The navigation shell stays
  // neutral so the controls remain calm and legible against every hero palette.
  useEffect(() => {
    if (!router.isReady) return;

    const categoryColor =
      selectedCategory === "all"
        ? null
        : categories.find((category) => category.slug === selectedCategory)
            ?.background_color;

    applyHomeCategoryTheme(
      resolveHomeCategoryTheme(categoryColor, webSettings?.primaryColor),
    );
  }, [categories, router.isReady, selectedCategory, webSettings?.primaryColor]);

  const activeCategory = categories.find(
    (category) => category.slug === selectedCategory,
  );
  const activeCategoryTitle =
    selectedCategory === "all"
      ? homeGeneralSettings?.title || "All"
      : activeCategory?.title || selectedCategory;
  const activeCategoryModel =
    selectedCategory === "all"
      ? {
          active_icon: homeGeneralSettings?.activeIcon || "",
          description: "",
          icon: homeGeneralSettings?.icon || "",
          id: 0,
          image: "",
          slug: "",
          status: "",
          title: activeCategoryTitle,
        }
      : activeCategory;

  const updateShadows = (swiper: SwiperType) => {
    // Check if slides actually overflow container
    const hasOverflow =
      swiper.width <
      swiper.slides.reduce((sum, slide) => sum + slide.offsetWidth, 0);

    if (!hasOverflow) {
      // No overflow → no shadows at all
      setShowLeftShadow(false);
      setShowRightShadow(false);
      return;
    }

    setShowLeftShadow(!swiper.isBeginning);
    setShowRightShadow(!swiper.isEnd);
  };

  // Only drives the URL — the sync effect above picks the change up and owns
  // the cookie and the refetch event, so tab clicks and logo navigations take
  // the exact same path.
  const handleCategorySelect = useCallback(
    async (categorySlug: string) => {
      const isAll = !categorySlug || categorySlug === "all";

      await router.replace(
        {
          pathname: router.pathname,
          query: isAll ? {} : { category: categorySlug },
        },
        undefined,
        { shallow: true },
      );
    },
    [router],
  );

  // A category slug in the URL that this location doesn't serve (stale link,
  // changed catalog) falls back to "all". Clearing the query is enough — the
  // sync effect above reacts to it and handles the cookie and refetch.
  useEffect(() => {
    if (!router.isReady || isLoading || selectedCategory === "all") return;

    const exists = categories?.some((c) => c.slug === selectedCategory);
    if (!exists) {
      handleCategorySelect("all");
    }
  }, [
    categories,
    isLoading,
    router.isReady,
    selectedCategory,
    handleCategorySelect,
  ]);

  const handleSeeMore = () => {
    if (hasMore && !isLoadingMore) {
      loadMore();
    }
  };

  if (error) {
    console.error(error);
  }

  const renderSurface = (children: React.ReactNode) => (
    <div
      className={`w-full transition-[background-color,border-color,box-shadow] motion-reduce:transition-none ${
        isCompact
          ? "duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)]"
          : "duration-[380ms] ease-[cubic-bezier(0.0,0,0.2,1)]"
      } ${
        mergedSurface
          ? "border-transparent bg-transparent shadow-none"
          : isCompact
            ? "bg-background/[0.55] shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_12px_32px_rgba(15,23,42,0.12)] backdrop-blur-2xl dark:bg-black/40"
            : "bg-background/[0.68] shadow-[inset_0_1px_0_rgba(255,255,255,0.06),0_5px_20px_rgba(15,23,42,0.08)] backdrop-blur-2xl dark:bg-black/[0.48]"
      } ${sticky ? "sticky top-0 z-[70]" : "relative"}`}
    >
      <div
        className={`w-full max-w-full mx-auto px-4 md:px-8 will-change-[padding] transition-[padding] motion-reduce:transition-none ${
          isCompact
            ? "duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)] py-1.5"
            : "duration-[380ms] ease-[cubic-bezier(0.0,0,0.2,1)] py-2.5"
        } overflow-hidden ${className}`}
        onTransitionEnd={(event) => {
          if (event.target !== event.currentTarget) return;
          const swiper = swiperRef.current;
          if (!swiper) return;
          swiper.update();
          updateShadows(swiper);
        }}
      >
        {children}
      </div>
    </div>
  );

  const renderShell = (children: React.ReactNode) => {
    return renderSurface(children);
  };

  // router.query is empty until the router is ready, so wait before rendering
  // tabs — otherwise the correct tab can't be highlighted yet.
  if (!router.isReady) {
    return renderShell(
      <div className="w-full relative">
        <Swiper
          key={rtl ? "rtl-ct" : "ltr-ct"}
          dir={rtl ? "rtl" : "ltr"}
          spaceBetween={8}
          grabCursor={true}
          slidesPerView="auto"
          slidesOffsetAfter={8}
          modules={[Mousewheel]}
          mousewheel
        >
          <SwiperSlide style={{ width: "auto" }}>
            <SkeletonTabButton size="home" />
          </SwiperSlide>
          {Array.from({ length: 5 }).map((_, i) => (
            <SwiperSlide key={i} style={{ width: "auto" }}>
              <SkeletonTabButton size="home" />
            </SwiperSlide>
          ))}
        </Swiper>
      </div>,
    );
  }

  return renderShell(
    <div className="relative w-full">
      <div
        aria-hidden={isCompact}
        inert={isCompact}
        style={{ maxHeight: isCompact ? 0 : fullTabsHeight || undefined }}
        className={`overflow-hidden transition-[max-height,opacity,transform] motion-reduce:transition-none ${
          isCompact
            ? "pointer-events-none max-h-0 -translate-y-1 opacity-0 duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)]"
            : "max-h-32 translate-y-0 opacity-100 duration-[380ms] ease-[cubic-bezier(0.0,0,0.2,1)]"
        }`}
      >
        <div ref={fullTabsRef} className="w-full relative">
          <button
            className="hidden"
            id="home-category-tabs"
            onClick={() => refetch()}
          />
          <div className="flex gap-2">
            <TabButton
              slug="all"
              title={homeGeneralSettings?.title || "All"}
              size="home"
              surface="home"
              isSelected={selectedCategory === "all"}
              category={activeCategoryModel}
              onClick={() => {
                handleCategorySelect("all");
              }}
            />

            <div
              className={`min-w-0 flex-1 relative overflow-x-hidden ${className}`}
            >
              {/* Left Shadow */}
              <div
                className={`absolute left-0 top-0 bottom-0 w-6
                bg-linear-to-r
                from-gray-400/20 via-gray-400/10 to-transparent
                dark:from-gray-500/30 dark:via-gray-400/20
                z-10 pointer-events-none
                transition-opacity duration-300 ease-in-out
                ${showLeftShadow ? "opacity-100" : "opacity-0"}`}
              />

              {/* Right Shadow */}
              <div
                className={`absolute right-0 top-0 bottom-0 w-6
                  bg-linear-to-l
                from-gray-400/20 via-gray-400/10 to-transparent
                dark:from-gray-500/30 dark:via-gray-400/20
                  z-10 pointer-events-none
                  transition-opacity duration-300 ease-in-out
                  ${showRightShadow ? "opacity-100" : "opacity-0"}`}
              />

              <Swiper
                grabCursor
                slidesPerView="auto"
                slidesOffsetAfter={8}
                modules={[Mousewheel]}
                mousewheel
                spaceBetween={12}
                breakpoints={{
                  0: {
                    spaceBetween: 12,
                  },
                  640: {
                    spaceBetween: 14,
                  },
                }}
                onSwiper={(swiper) => {
                  swiperRef.current = swiper;
                  updateShadows(swiper);
                }}
                onSlideChange={updateShadows}
                onResize={updateShadows}
                onProgress={updateShadows}
              >
                {/* Category tabs */}
                {isLoading
                  ? Array.from({ length: PER_PAGE }).map((_, i) => (
                      <SwiperSlide key={i} style={{ width: "auto" }}>
                        <SkeletonTabButton size="home" />
                      </SwiperSlide>
                    ))
                  : categories.map((category) => (
                      <SwiperSlide
                        key={category.slug}
                        style={{ width: "auto" }}
                      >
                        <TabButton
                          slug={category.slug}
                          title={category.title}
                          size="home"
                          surface="home"
                          category={category}
                          isSelected={selectedCategory === category.slug}
                          onClick={() => {
                            handleCategorySelect(category.slug);
                          }}
                        />
                      </SwiperSlide>
                    ))}

                {/* See More */}
                {hasMore && (
                  <SwiperSlide style={{ width: "auto" }}>
                    <TabButton
                      slug="see-more"
                      title={isLoadingMore ? "Loading..." : "See More"}
                      size="home"
                      surface="home"
                      isSelected={false}
                      isLoading={isLoadingMore}
                      onClick={handleSeeMore}
                    />
                  </SwiperSlide>
                )}
              </Swiper>
            </div>
          </div>
        </div>
      </div>

      <div
        aria-hidden={!isCompact}
        inert={!isCompact}
        className={`overflow-hidden transition-[max-height,opacity,transform] motion-reduce:transition-none ${
          isCompact
            ? "max-h-12 translate-y-0 opacity-100 duration-[250ms] ease-[cubic-bezier(0.4,0,1,1)]"
            : "pointer-events-none max-h-0 translate-y-1 opacity-0 duration-[380ms] ease-[cubic-bezier(0.0,0,0.2,1)]"
        }`}
      >
        {isLoading && !activeCategory ? (
          <SkeletonTabButton size="pill" />
        ) : (
          <TabButton
            slug={selectedCategory}
            title={activeCategoryTitle}
            size="pill"
            titleOnly
            surface="home"
            category={activeCategoryModel}
            isSelected
          />
        )}
      </div>
    </div>,
  );
};

export default CategoryTabs;
