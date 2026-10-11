import { FC, Fragment, useDeferredValue } from "react";
import { useRouter } from "next/router";

import { useInfiniteData } from "@/hooks/useInfiniteData";
import { HomeLayoutData, HomeLayoutSection } from "@/types/ApiResponse";
import {
  fetchHomeLayout,
  toSectionListPage,
} from "@/helpers/homeLayout";
import { getActiveCategory } from "@/helpers/getters";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { Skeleton } from "@heroui/react";
import InfiniteScroll from "@/components/Functional/InfiniteScroll";
import LoadMoreSentinel from "@/components/Functional/LoadMoreSentinel";
import ProductCardSkeleton from "@/components/Skeletons/ProductCardSkeleton";
import HomeSectionRenderer from "./HomeSectionRenderer";
import HomeCategoryEmpty from "@/components/Empty/HomeCategoryEmpty";
import HomeHeroSection, { HomeHeroSkeleton } from "./HomeHeroSection";

/** Sections (not products) per page — the layout list is itself paginated. */
export const SECTIONS_PER_PAGE = 5;

/**
 * Start the next page once this many sections from the end scroll into view.
 * Waiting for the bottom of the document meant the user reached the footer
 * before anything was requested; two sections of runway hides the fetch.
 */
export const PRELOAD_SECTIONS_FROM_END = 2;

interface HomeLayoutViewProps {
  initialLayout?: HomeLayoutData | null;
}

/**
 * Extra viewport, in pixels, allowed above the marker before the next page is
 * requested. Stacks with PRELOAD_SECTIONS_FROM_END: together they mean the page
 * is usually already in place by the time the last sections are reached.
 */
const PRELOAD_ROOT_MARGIN = "800px 0px";

/** Cards in a placeholder rail — the Swiper's widest breakpoint shows 7. */
const SKELETON_CARDS = 7;

/**
 * Placeholder shaped like a real section: a heading and ONE row of cards that
 * runs off the edge, exactly as the Swiper rails do.
 *
 * It used to be a 3-column x 3-row grid, which stood about three rails tall.
 * Swapping that for the real one-row section collapsed the page by hundreds of
 * pixels mid-scroll, which is what made every page-in feel like a jolt.
 */
const SectionSkeleton: FC = () => (
  <div className="mb-8" data-testid="home-section-skeleton">
    {/* section heading placeholder */}
    <div className="flex justify-between w-full items-center mb-6">
      <Skeleton className="w-40 h-6 rounded-md" />
      <Skeleton className="w-16 h-4 rounded-md" />
    </div>

    {/* grid-flow-col keeps it to a single row; the overflow is clipped so the
        cards trail off the edge the way an un-scrolled rail does. */}
    <div className="grid grid-flow-col gap-2 sm:gap-3 overflow-hidden auto-cols-[46%] sm:auto-cols-[31%] lg:auto-cols-[23%] xl:auto-cols-[19%] 2xl:auto-cols-[13.5%]">
      {Array.from({ length: SKELETON_CARDS }).map((_, i) => (
        <ProductCardSkeleton key={i} />
      ))}
    </div>
  </div>
);

/**
 * The /home-layout homepage: renders backend-driven sections and lazily loads
 * further pages of sections as the user scrolls.
 *
 * Category and location are part of the request/cache scope, so a category
 * switch can reuse prefetched content instead of replacing one shared layout
 * cache. Location is optional: the API falls back to a global-scope layout.
 */
const HomeLayoutView: FC<HomeLayoutViewProps> = ({ initialLayout }) => {
  const router = useRouter();
  const activeCategory = (router.query.category as string) || "all";
  const userLocation = getCookie<UserLocation>("userLocation");
  const locationKey = `${userLocation?.lat ?? ""}:${userLocation?.lng ?? ""}`;
  const categoryCacheKey = `${activeCategory}:${locationKey}`;
  const settledCategory = useDeferredValue(activeCategory);

  const {
    data: sections,
    isLoading,
    isLoadingMore,
    hasMore,
    loadMore,
    refetch,
    isValidating,
  } = useInfiniteData<HomeLayoutSection>({
    fetcher: async (params) => {
      const { category_slug, page, per_page } = params;
      const currentLocation = getCookie<UserLocation>("userLocation");
      const queryCategory =
        typeof window !== "undefined"
          ? new URLSearchParams(window.location.search).get("category")
          : null;
      const requestedCategory =
        category_slug !== undefined
          ? category_slug || queryCategory || "all"
          : getActiveCategory() || activeCategory;
      const res = await fetchHomeLayout({
        // An empty category_slug is an explicit request for All. Only fall
        // back to the URL/cookie when an older manual-mutate key has no scope.
        categorySlug: requestedCategory,
        page,
        perPage: per_page,
        latitude: currentLocation?.lat,
        longitude: currentLocation?.lng,
      });

      // /home-layout puts its array at data.sections, not data.data.
      return toSectionListPage(res);
    },
    perPage: SECTIONS_PER_PAGE,
    initialData: initialLayout?.sections ?? [],
    initialTotal: initialLayout?.total ?? 0,
    dataKey: "home-layout",
    cacheKey: categoryCacheKey,
    extraParams: {
      category_slug:
        activeCategory === "all" ? "" : activeCategory,
    },
  });

  const isCategoryTransitioning =
    isValidating || settledCategory !== activeCategory;

  // Preserve the prior layout during a category refresh. Replacing it with a
  // generic skeleton collapsed the first full banner and visibly pulled the
  // content upward; stale content is preferable for the brief request window.
  const showSkeleton = isLoading && sections.length === 0;

  // A category the backend has no sections for would otherwise render nothing
  // at all, leaving the user on a blank page with no way back to "All".
  const isEmpty = !showSkeleton && sections.length === 0;

  // `hero` is the current API type; older builder payloads call the same thing a
  // full-width `banners`/`sliders` section. Pull it ahead of the rails so the
  // category-coloured header always flows directly into the hero.
  const heroSection = sections.find(
    (section) =>
      section.type === "hero" ||
      ((section.type === "banners" || section.type === "sliders") &&
        section.style === "full"),
  );
  const contentSections = heroSection
    ? sections.filter((section) => section.id !== heroSection.id)
    : sections;

  // Keep the first content rail from touching the navbar when this category
  // has no full-width hero/banner. Do not add the gap during the initial
  // skeleton state, because the replacement layout may include a hero.
  const needsNavbarGap = !showSkeleton && !heroSection;

  // Where the "load the next page" marker goes. Clamped so a list shorter than
  // the runway still triggers — otherwise a 1-section page could never grow.
  const triggerIndex = Math.max(
    0,
    contentSections.length - PRELOAD_SECTIONS_FROM_END,
  );

  return (
    <section
      id="home-layout"
      aria-busy={isCategoryTransitioning}
      className={`relative w-2X ${needsNavbarGap ? "mt-6" : ""}`}
    >
      {/* Location changes still trigger a refresh through this button. Category
          changes now switch the cache key and do not need a second refetch. */}
      <button
        id="home-sections-refetch"
        className="hidden"
        onClick={() => refetch()}
      />

      <InfiniteScroll
        hasMore={hasMore && !isCategoryTransitioning}
        isLoading={isLoadingMore || isCategoryTransitioning}
        onLoadMore={loadMore}
      >
        <div
          className={`transition-opacity duration-300 motion-reduce:transition-none ${
            isCategoryTransitioning && !showSkeleton
              ? "opacity-65"
              : "opacity-100"
          }`}
        >
          {showSkeleton ? (
            <>
              <HomeHeroSkeleton />
              <SectionSkeleton />
            </>
          ) : isEmpty ? (
            <HomeCategoryEmpty />
          ) : (
            <>
              {heroSection &&
                (isCategoryTransitioning ? (
                  <HomeHeroSkeleton />
                ) : (
                  <HomeHeroSection section={heroSection} />
                ))}

              {contentSections.length === 0 && (
                <LoadMoreSentinel
                  onVisible={loadMore}
                  disabled={!hasMore || isCategoryTransitioning}
                  rootMargin={PRELOAD_ROOT_MARGIN}
                />
              )}

              {contentSections.map((section, index) => (
                <Fragment key={section.id}>
                  {/* Sits BEFORE the last couple of sections, so the next page is
                      requested while those are still being scrolled through. */}
                  {index === triggerIndex && (
                    <LoadMoreSentinel
                      onVisible={loadMore}
                      disabled={!hasMore || isCategoryTransitioning}
                      rootMargin={PRELOAD_ROOT_MARGIN}
                    />
                  )}

                  {/* Only sections beyond the first page fade in — animating the
                      ones already on screen would replay on every re-render. */}
                  <div
                    className={
                      index >= SECTIONS_PER_PAGE ? "home-section-in" : undefined
                    }
                  >
                    <HomeSectionRenderer
                      section={section}
                      isLoading={isCategoryTransitioning}
                    />
                  </div>
                </Fragment>
              ))}
            </>
          )}
        </div>

        {isCategoryTransitioning && !showSkeleton && (
          <div
            className="pointer-events-none sticky top-0 z-10 h-0"
            role="status"
            aria-label="Loading category content"
          >
            <div className="h-0.5 w-full overflow-hidden bg-primary/10">
              <div className="h-full w-1/3 animate-[home-category-progress_1.1s_ease-in-out_infinite] rounded-full bg-primary" />
            </div>
          </div>
        )}

        {isLoadingMore && <SectionSkeleton />}
      </InfiniteScroll>
    </section>
  );
};

export default HomeLayoutView;
