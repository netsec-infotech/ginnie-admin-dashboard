import { FC, useRef } from "react";
import { useTranslation } from "react-i18next";
import useSWR from "swr";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation } from "swiper/modules";

import ProductCard from "@/components/Cards/ProductCard";
import ProductCardSkeleton from "@/components/Skeletons/ProductCardSkeleton";
import { getActiveCategory, isSSR } from "@/helpers/getters";
import SectionHeading from "@/components/SectionHeading";
import { Boxes } from "lucide-react";
import { getSections } from "@/routes/api";
import { FeaturedSection } from "@/types/ApiResponse";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import Link from "next/link";
import { useScreenType } from "@/hooks/useScreenType";
import { isRTL } from "@/helpers/functionalHelpers";
import SwiperNavigation from "@/components/SwiperNavigation";
import { useRailReport } from "@/hooks/useRailReport";

interface HomeFeaturedSectionsProps {
  initialSections?: FeaturedSection[];
}

const fetcher = async () => {
  const location = getCookie("userLocation") as UserLocation | undefined;
  const { lat = "", lng = "" } = location || {};

  if (!lat || !lng) {
    return [];
  }

  const validSlug = getActiveCategory();

  const response = await getSections({
    latitude: lat,
    longitude: lng,
    scope_category_slug: validSlug,
  });

  if (!response.success || !response.data) {
    console.error(response.message || "Failed to fetch featured sections");
  }

  return response.data?.data ?? [];
};

const HomeFeaturedSections: FC<HomeFeaturedSectionsProps> = ({
  initialSections = [],
}) => {
  const { t, i18n } = useTranslation();
  const screen = useScreenType();

  const {
    data: sections = [],
    isLoading,
    mutate,
  } = useSWR("/featured-sections", fetcher, {
    fallbackData: isSSR() ? initialSections : undefined,
    revalidateOnFocus: false,
    revalidateOnMount: !isSSR(),
  });
  const shouldHide = sections?.length === 0;

  useRailReport("featured-sections", sections?.length === 0, isLoading);

  const encodeUrl = (url: string) =>
    url.replace(/\(/g, "%28").replace(/\)/g, "%29").replace(/\s/g, "%20");

  const getBackgroundStyle = (
    section: FeaturedSection,
    screen: "mobile" | "tablet" | "desktop" | "desktop-4k",
  ) => {
    if (section.background_type === "image") {
      let image = "";

      switch (screen) {
        case "desktop-4k":
          image =
            section.desktop_4k_background_image ||
            section.desktop_fdh_background_image;
          break;

        case "desktop":
          image = section.desktop_fdh_background_image;
          break;

        case "tablet":
          image =
            section.tablet_background_image ||
            section.desktop_fdh_background_image;
          break;

        case "mobile":
          image =
            section.mobile_background_image || section.tablet_background_image;
          break;
      }

      if (image) {
        return {
          backgroundImage: `url("${encodeUrl(image)}")`,
          backgroundSize: "cover",
          backgroundPosition: "top",
          backgroundRepeat: "no-repeat",
        };
      }
    }

    if (section.background_type === "color" && section.background_color) {
      return {
        backgroundColor: section.background_color,
      };
    }

    return {};
  };

  const prevRef = useRef(null);
  const nextRef = useRef(null);
  const currentLang = i18n.resolvedLanguage || i18n.language;
  const rtl = isRTL(currentLang);
  return (
    <section id="featured-sections">
      <button
        onClick={() => mutate()}
        className="hidden"
        id="home-sections-refetch"
      />
      {!shouldHide && (
        <div className="w-full mb-4">
          {sections
            .filter(
              (section) => section.products && section.products.length > 0,
            )
            .map((section) => {
              const isStyle2 = section.style === "with_background";
              const backgroundStyle = isStyle2
                ? getBackgroundStyle(section, screen)
                : {};

              return (
                <div key={section.id} className="mb-8">
                  {/* Style 1 - Default Layout */}
                  {!isStyle2 && (
                    <>
                      <div className="flex justify-between w-full items-center mb-6">
                        <SectionHeading
                          title={section.title}
                          description={section.short_description}
                          icon={<Boxes size={16} className="text-white" />}
                        />

                        <Link
                          href={`/feature-sections/${section.slug}`}
                          className="text-xs sm:text-sm"
                          title={t("see_all")}
                        >
                          {t("see_all")}
                        </Link>
                      </div>
                      <div className="relative group">
                        <SwiperNavigation
                          prevRef={prevRef}
                          nextRef={nextRef}
                          rtl={rtl}
                        />

                        <Swiper
                          key={
                            rtl
                              ? `rtl-hfs-${section.id}`
                              : `ltr-hfs-${section.id}`
                          }
                          dir={rtl ? "rtl" : "ltr"}
                          slidesPerView={2}
                          spaceBetween={4}
                          breakpoints={{
                            640: { slidesPerView: 3, spaceBetween: 12 },
                            1024: { slidesPerView: 4, spaceBetween: 12 },
                            1280: { slidesPerView: 5, spaceBetween: 12 },
                            1440: { slidesPerView: 7, spaceBetween: 12 },
                          }}
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
                        >
                          {isLoading
                            ? Array.from({ length: 7 }).map((_, index) => (
                                <SwiperSlide key={`skeleton-${index}`}>
                                  <div className="py-0.5">
                                    <ProductCardSkeleton />
                                  </div>
                                </SwiperSlide>
                              ))
                            : section?.products?.map((product) => (
                                <SwiperSlide key={product.id}>
                                  <div className="py-0.5">
                                    <ProductCard product={product} />
                                  </div>
                                </SwiperSlide>
                              ))}
                        </Swiper>
                      </div>
                    </>
                  )}

                  {/* Style 2 - Background Enhanced Layout with Half-Covered Product Cards */}
                  {isStyle2 && (
                    <div className="relative pt-5">
                      <div
                        className="rounded-xl overflow-hidden absolute  top-0 left-0 right-0 aspect-2/1 md:aspect-5/1"
                        style={{
                          ...backgroundStyle,
                          backgroundPosition: "center top",
                        }}
                      />

                      {/* Content Container */}
                      <div className="relative px-1 sm:px-6 pt-[2%] pb-2">
                        <div className="flex justify-between w-full items-center mb-6">
                          <div>
                            {section.background_type != "image" && (
                              <SectionHeading
                                title={section.title}
                                description={section.short_description}
                                icon={
                                  <Boxes size={16} className="text-white" />
                                }
                                color={section.text_color}
                              />
                            )}
                          </div>
                          <Link
                            href={`/feature-sections/${section.slug}`}
                            title={t("see_all")}
                            className="inline-flex items-center justify-center bg-gray-100 dark:bg-content1 px-2 py-1 text-xs  font-medium rounded-xl border-gray-100 dark:border-default-100 transition hover:opacity-80"
                          >
                            {t("see_all")}
                          </Link>
                        </div>
                        <div className="relative group">
                          <SwiperNavigation
                            prevRef={prevRef}
                            nextRef={nextRef}
                            rtl={rtl}
                          />

                          <Swiper
                            key={
                              rtl
                                ? `rtl-hfs-${section.id}`
                                : `ltr-hfs-${section.id}`
                            }
                            dir={rtl ? "rtl" : "ltr"}
                            slidesPerView={2}
                            spaceBetween={4}
                            breakpoints={{
                              640: { slidesPerView: 3, spaceBetween: 12 },
                              1024: { slidesPerView: 4, spaceBetween: 12 },
                              1280: { slidesPerView: 5, spaceBetween: 12 },
                              1440: { slidesPerView: 7, spaceBetween: 12 },
                            }}
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
                          >
                            {isLoading
                              ? Array.from({ length: 7 }).map((_, index) => (
                                  <SwiperSlide key={`skeleton-${index}`}>
                                    <div className="py-0.5">
                                      <ProductCardSkeleton />
                                    </div>
                                  </SwiperSlide>
                                ))
                              : section?.products?.map((product) => (
                                  <SwiperSlide key={product.id}>
                                    <div className="py-0.5">
                                      <ProductCard product={product} />
                                    </div>
                                  </SwiperSlide>
                                ))}
                          </Swiper>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
        </div>
      )}
    </section>
  );
};

export default HomeFeaturedSections;
