import { FC } from "react";
import useSWR from "swr";
import { getBannerImages } from "@/routes/api";
import { getActiveCategory, isSSR } from "@/helpers/getters";
import { Banner, BannerData, HomeLayoutSection } from "@/types/ApiResponse";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { useRailReport } from "@/hooks/useRailReport";
import HomeHeroSection, { HomeHeroSkeleton } from "./HomeHeroSection";

type HomeTopSliderProps = {
  initialBanners?: BannerData;
};

// Fetcher function for SWR
const fetcher = async () => {
  const scopeCategorySlug = getActiveCategory();

  const location = getCookie("userLocation") as UserLocation | undefined;
  const { lat = "", lng = "" } = location || {};

  const response = await getBannerImages({
    scope_category_slug: scopeCategorySlug,
    per_page: 50,
    latitude: lat || undefined,
    longitude: lng || undefined,
  });

  if (!response.success || !response.data) {
    console.error(response.message || "Failed to fetch banner images");
  }

  return response.data?.data ?? { top: [], carousel: [] };
};

const legacyBannerHref = (banner: Banner) => {
  switch (banner.type) {
    case "product":
      return banner.product_slug;
    case "category":
      return banner.category_slug;
    case "brand":
      return banner.brand_slug;
    default:
      return banner.slug || null;
  }
};

/**
 * The legacy `/banners` endpoint stays supported while sharing the exact hero
 * presentation used by `/home-layout` sections. This avoids a visual fork when
 * the backend setting selects the classic homepage.
 */
const toHeroSection = (banners: Banner[]): HomeLayoutSection => ({
  id: 0,
  type: "banners",
  title: null,
  style: "full",
  config: {},
  background_image: null,
  hero_media: null,
  card_background_image: null,
  content: {
    items: banners.map((banner) => ({
      id: banner.id,
      image: banner.banner_image,
      link_type: banner.type,
      link_url: banner.custom_url,
      target_id:
        banner.type === "product"
          ? banner.product_id
          : banner.type === "category"
            ? banner.category_id
            : banner.type === "brand"
              ? banner.brand_id
              : null,
      slug: legacyBannerHref(banner),
    })),
  },
});

const HomeTopSlider: FC<HomeTopSliderProps> = ({
  initialBanners = { top: [], carousel: [] },
}) => {
  const {
    data: bannerImages,
    isLoading,
    isValidating,
    mutate,
  } = useSWR("/banners", fetcher, {
    fallbackData: isSSR() ? initialBanners : undefined,
    revalidateOnFocus: false,
    revalidateOnMount: !isSSR(),
  });

  // Must run before the early return below — hooks can't be called
  // conditionally.
  useRailReport(
    "top-banners",
    bannerImages?.top?.length === 0,
    isLoading || isValidating || !bannerImages
  );

  return (
    <section id="home-slider">
      <button
        onClick={() => mutate()}
        className="hidden"
        id="home-banners-refetch"
      />
      {isLoading || !bannerImages ? (
        <HomeHeroSkeleton />
      ) : bannerImages.top.length > 0 ? (
        <HomeHeroSection section={toHeroSection(bannerImages.top)} />
      ) : null}
    </section>
  );
};

export default HomeTopSlider;
