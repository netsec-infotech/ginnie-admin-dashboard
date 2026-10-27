import React, {
  useCallback,
  useEffect,
  useState,
  useRef,
  useSyncExternalStore,
} from "react";
import { useInfiniteData } from "@/hooks/useInfiniteData";
import { getCategories } from "@/routes/api";
import { Swiper, SwiperSlide } from "swiper/react";
import type { Swiper as SwiperType } from "swiper";
import TabButton from "../custom/TabButton";
import SkeletonTabButton from "../custom/SkeletonTabButton";
import { getCookie, setCookie } from "@/lib/cookies";
import { onHomeCategoryChange } from "@/helpers/events";
import { prefetchHomeLayout } from "@/helpers/homeLayout";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { useSettings } from "@/contexts/SettingsContext";
import { useRouter } from "next/router";
import { Mousewheel } from "swiper/modules";
import { useTranslation } from "react-i18next";
import { isRTL } from "@/helpers/functionalHelpers";
import {
  applyHomeCategoryTheme,
  resolveHomeCategoryTheme,
} from "@/helpers/homeCategoryTheme";
import useScrollImageCollapse from "@/hooks/useScrollImageCollapse";
import InlineDataRetry from "./InlineDataRetry";

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
  background_image?: string | null;
  background_image_web?: string | null;
  background_image_web_mobile?: string | null;
}

interface CategoryTabsProps {
  defaultCategory?: string;
  className?: string;
  sticky?: boolean;
  mergedSurface?: boolean;
  isCompact?: boolean;
  hideImages?: boolean;
  onBackgroundImageChange?: (
    backgroundImage: string | null,
    backgroundColor: string | null,
    mobileBackgroundImage: string | null,
  ) => void;
}

const PER_PAGE = 15;
const HOME_LAYOUT_PREFETCH_PER_PAGE = 5;
const subscribeToHydration = () => () => undefined;
const getHydratedSnapshot = () => true;
const getServerHydrationSnapshot = () => false;

const CategoryTabs: React.FC<CategoryTabsProps> = ({
  defaultCategory = "all",
  className = "",
  sticky = false,
  mergedSurface = false,
  isCompact = false,
  hideImages,
  onBackgroundImageChange,
}) => {
  const [showLeftShadow, setShowLeftShadow] = useState(false);
  const [showRightShadow, setShowRightShadow] = useState(false);
  const autoHideCategoryImages = useScrollImageCollapse({
    disabled: hideImages !== undefined,
  });
  const hideCategoryImages = hideImages ?? autoHideCategoryImages;
  const swiperRef = useRef<SwiperType | null>(null);

  const { homeGeneralSettings, webSettings } = useSettings();
  const router = useRouter();
  const isHydrated = useSyncExternalStore(
    subscribeToHydration,
    getHydratedSnapshot,
    getServerHydrationSnapshot,
  );
  const { t, i18n } = useTranslation();
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
    loadMoreError,
    error,
    hasLoadedSuccessfully,
    refetch,
    retryLoadMore,
    isValidating,
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

  useEffect(() => {
    if (!router.isReady) return;

    // Prefer each web-specific image. When the mobile field is empty, the
    // header component keeps the desktop category image visible at all widths.
    const categoryBackgroundImage =
      selectedCategory === "all"
        ? null
        : activeCategory?.background_image_web?.trim() ||
          activeCategory?.background_image?.trim() ||
          null;
    const categoryMobileBackgroundImage =
      selectedCategory === "all"
        ? null
        : activeCategory?.background_image_web_mobile?.trim() || null;
    const categoryBackgroundColor =
      selectedCategory !== "all" &&
      (categoryBackgroundImage || categoryMobileBackgroundImage)
        ? activeCategory?.background_color?.trim() || null
        : null;

    onBackgroundImageChange?.(
      categoryBackgroundImage,
      categoryBackgroundColor,
      categoryMobileBackgroundImage,
    );
  }, [
    activeCategory?.background_color,
    activeCategory?.background_image_web,
    activeCategory?.background_image_web_mobile,
    activeCategory?.background_image,
    onBackgroundImageChange,
    router.isReady,
    selectedCategory,
  ]);

  const activeCategoryTitle =
    selectedCategory === "all"
      ? homeGeneralSettings?.title || "All"
      : activeCategory?.title || selectedCategory;
  const allCategoryModel = {
    active_icon: homeGeneralSettings?.activeIcon || "",
    description: "",
    icon: homeGeneralSettings?.icon || "",
    id: 0,
    image: "",
    slug: "all",
    status: "",
    title: homeGeneralSettings?.title || "All",
  };
  const activeCategoryModel =
    selectedCategory === "all" ? allCategoryModel : activeCategory;

  const prefetchedCategories = useRef(new Set<string>());
  const prefetchCategory = useCallback((categorySlug: string) => {
    if (prefetchedCategories.current.has(categorySlug)) return;

    prefetchedCategories.current.add(categorySlug);
    const location = getCookie<UserLocation>("userLocation");

    prefetchHomeLayout({
      categorySlug,
      latitude: location?.lat,
      longitude: location?.lng,
      page: 1,
      perPage: HOME_LAYOUT_PREFETCH_PER_PAGE,
    });
  }, []);

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

      if (
        (isAll && selectedCategory === "all") ||
        (!isAll && categorySlug === selectedCategory)
      ) {
        return;
      }

      await router.replace(
        {
          pathname: router.pathname,
          query: isAll ? {} : { category: categorySlug },
        },
        undefined,
        { shallow: true },
      );

      window.scrollTo({ top: 0, left: 0, behavior: "auto" });
    },
    [router, selectedCategory],
  );

  // A category slug in the URL that this location doesn't serve (stale link,
  // changed catalog) falls back to "all". Clearing the query is enough — the
  // sync effect above reacts to it and handles the cookie and refetch.
  useEffect(() => {
    if (
      !router.isReady ||
      isLoading ||
      error ||
      !hasLoadedSuccessfully ||
      selectedCategory === "all"
    ) {
      return;
    }

    const exists = categories?.some((c) => c.slug === selectedCategory);
    if (!exists) {
      handleCategorySelect("all");
    }
  }, [
    categories,
    error,
    hasLoadedSuccessfully,
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

  const hasInitialCategoriesError = Boolean(error && !hasLoadedSuccessfully);

  const renderSurface = (children: React.ReactNode) => (
    <div
      className={`w-full ${
        mergedSurface
          ? "border-transparent bg-transparent shadow-none"
          : isCompact
            ? "bg-background/[0.55] shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_12px_32px_rgba(15,23,42,0.12)] backdrop-blur-2xl dark:bg-black/40"
            : "bg-background/[0.68] shadow-[inset_0_1px_0_rgba(255,255,255,0.06),0_5px_20px_rgba(15,23,42,0.08)] backdrop-blur-2xl dark:bg-black/[0.48]"
      } ${sticky ? "sticky top-0 z-[70]" : "relative"}`}
    >
      <div
        className={`w-full max-w-screen-2xl mx-auto px-2 md:px-6 overflow-hidden ${
          isCompact ? "py-1.5" : "py-2.5"
        } ${className}`}
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
  if (!isHydrated || !router.isReady) {
    return renderShell(
      <div className="w-full relative">
        <Swiper
          key={rtl ? "rtl-ct" : "ltr-ct"}
          dir={rtl ? "rtl" : "ltr"}
          resizeObserver={false}
          spaceBetween={8}
          grabCursor={true}
          slidesPerView="auto"
          slidesOffsetAfter={8}
          modules={[Mousewheel]}
          mousewheel
        >
          <SwiperSlide style={{ width: "auto" }}>
            <SkeletonTabButton size="home" hideIcon={hideCategoryImages} />
          </SwiperSlide>
          {Array.from({ length: 5 }).map((_, i) => (
            <SwiperSlide key={i} style={{ width: "auto" }}>
              <SkeletonTabButton size="home" hideIcon={hideCategoryImages} />
            </SwiperSlide>
          ))}
        </Swiper>
      </div>,
    );
  }

  return renderShell(
    <div className="relative w-full">
      {/* Full tabs */}
      <div
        aria-hidden={isCompact}
        inert={isCompact}
        className={`overflow-hidden transition-[max-height,opacity,transform] motion-reduce:transition-none ${
          isCompact
            ? "pointer-events-none max-h-0 -translate-y-1 opacity-0 duration-300 ease-out"
            : "max-h-32 translate-y-0 opacity-100 duration-300 ease-out"
        }`}
      >
        <div className="w-full relative">
          <button
            className="hidden"
            id="home-category-tabs"
            onClick={() => refetch()}
          />
          {hasInitialCategoriesError ? (
            <InlineDataRetry
              variant="panel"
              message={t("errors.categories_unavailable", {
                defaultValue: "Categories are taking a moment to load.",
              })}
              retryLabel={t("retry", { defaultValue: "Try again" })}
              isRetrying={isValidating}
              onRetry={() => void refetch()}
            />
          ) : (
            <div className="flex gap-2">
              <TabButton
                slug="all"
                title={homeGeneralSettings?.title || "All"}
                size="home"
                surface="home"
                isSelected={selectedCategory === "all"}
                category={allCategoryModel}
                hideIcon={hideCategoryImages}
                onPointerEnter={() => prefetchCategory("all")}
                onFocus={() => prefetchCategory("all")}
                onClick={() => {
                  handleCategorySelect("all");
                }}
              />

              <div
                className={`min-w-0 flex-1 relative overflow-x-hidden ${className}`}
              >
                {/* Desktop-only overflow fades. They read as dark blocks against
                    the edge-to-edge mobile header artwork, so mobile relies on
                    the naturally clipped carousel instead. */}
                <div
                  data-category-left-overflow-fade
                  className={`absolute left-0 top-0 bottom-0 hidden w-6 md:block
              bg-linear-to-r
              from-gray-400/20 via-gray-400/10 to-transparent
              dark:from-gray-500/30 dark:via-gray-400/20
              z-10 pointer-events-none
              transition-opacity duration-300 ease-in-out
              ${showLeftShadow ? "opacity-100" : "opacity-0"}`}
                />

                {/* Right Shadow */}
                <div
                  data-category-right-overflow-fade
                  className={`absolute right-0 top-0 bottom-0 hidden w-6 md:block
                bg-linear-to-l
              from-gray-400/20 via-gray-400/10 to-transparent
              dark:from-gray-500/30 dark:via-gray-400/20
                z-10 pointer-events-none
                transition-opacity duration-300 ease-in-out
                ${showRightShadow ? "opacity-100" : "opacity-0"}`}
                />

                <Swiper
                  grabCursor
                  resizeObserver={false}
                  slidesPerView="auto"
                  slidesOffsetAfter={8}
                  modules={[Mousewheel]}
                  mousewheel
                  spaceBetween={12}
                  breakpoints={{
                    0: { spaceBetween: 12 },
                    640: { spaceBetween: 14 },
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
                          <SkeletonTabButton
                            size="home"
                            hideIcon={hideCategoryImages}
                          />
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
                            hideIcon={hideCategoryImages}
                            isSelected={selectedCategory === category.slug}
                            onPointerEnter={() =>
                              prefetchCategory(category.slug)
                            }
                            onFocus={() => prefetchCategory(category.slug)}
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
                        title={
                          loadMoreError
                            ? t("retry", { defaultValue: "Try again" })
                            : isLoadingMore
                              ? "Loading..."
                              : "See More"
                        }
                        size="home"
                        surface="home"
                        hideIcon={hideCategoryImages}
                        isSelected={false}
                        isLoading={isLoadingMore}
                        onClick={loadMoreError ? retryLoadMore : handleSeeMore}
                      />
                    </SwiperSlide>
                  )}

                  {error && hasLoadedSuccessfully && !isLoading && (
                    <SwiperSlide style={{ width: "auto" }}>
                      <InlineDataRetry
                        message={t("errors.categories_unavailable", {
                          defaultValue:
                            "Categories are taking a moment to load.",
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
          )}
        </div>
      </div>

      {/* Pill — fixed height so max-h-12 is fine here */}
      <div
        aria-hidden={!isCompact}
        inert={!isCompact}
        className={`overflow-hidden ${
          isCompact
            ? "max-h-12 translate-y-0 opacity-100"
            : "pointer-events-none max-h-0 translate-y-1 opacity-0"
        }`}
      >
        {error && !hasLoadedSuccessfully ? (
          <InlineDataRetry
            message={t("errors.categories_unavailable_short", {
              defaultValue: "Categories unavailable.",
            })}
            retryLabel={t("retry", { defaultValue: "Try again" })}
            isRetrying={isValidating}
            onRetry={() => void refetch()}
          />
        ) : isLoading && !activeCategory ? (
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
