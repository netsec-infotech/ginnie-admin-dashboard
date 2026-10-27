/* eslint-disable @next/next/no-img-element */
// Native <img> elements are used here for Swiper carousel compatibility (preventing lazy-load failure on initial load) and the custom zoom lens.
import { FC, useState, useRef, memo, useEffect, type MouseEvent } from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import type { Swiper as SwiperType } from "swiper";

import { Navigation, Pagination, Mousewheel } from "swiper/modules";
import ProductImgSectionSkeleton from "@/components/Skeletons/ProductImgSectionSkeleton";
import Lightbox from "yet-another-react-lightbox";
import Thumbnails from "yet-another-react-lightbox/plugins/thumbnails";
import { ProductVariant } from "@/types/ApiResponse";
import { useTranslation } from "react-i18next";
import { isRTL } from "@/helpers/functionalHelpers";
import { getProductImageZoom } from "@/helpers/productImageZoom";
import { Maximize2 } from "lucide-react";

interface ProductImgSectionProps {
  allImages: string[];
  isVertical: boolean;
  isLoading: boolean;
  video?: {
    url?: string | null;
    type?: "self_hosted" | "youtube" | null;
  } | null;
  variants?: ProductVariant[];
  selectedVariant?: ProductVariant | null;
  variantImagesStartIndex?: number;
}

const ProductImgSection: FC<ProductImgSectionProps> = memo(
  ({
    allImages,
    isVertical,
    isLoading,
    video,
    variants = [],
    selectedVariant = null,
    variantImagesStartIndex = 0,
  }) => {
    const [activeIndex, setActiveIndex] = useState(0);
    const mainSwiperRef = useRef<SwiperType | null>(null);
    const thumbnailSwiperRef = useRef<SwiperType | null>(null);
    const [zoom, setZoom] = useState<
      (NonNullable<ReturnType<typeof getProductImageZoom>> & { index: number }) | null
    >(null);
    const zoomPreviewRef = useRef<HTMLDivElement>(null);
    const isHover = zoom !== null;
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);
    const [collapsedHintFor, setCollapsedHintFor] = useState<string | null>(null);
    const hintImage = allImages[0] || "";
    const showEnlargeLabel = collapsedHintFor !== hintImage;
    const { t, i18n } = useTranslation();
    const currentLang = i18n.resolvedLanguage || i18n.language;
    const rtl = isRTL(currentLang);

    useEffect(() => {
      if (isLoading || !hintImage) return;
      const timer = window.setTimeout(() => setCollapsedHintFor(hintImage), 2000);
      return () => window.clearTimeout(timer);
    }, [isLoading, hintImage]);

    // Switch to variant's image when variant changes
    useEffect(() => {
      if (selectedVariant && variants.length > 0) {
        const variantIndex = variants.findIndex(
          (v) => v.id === selectedVariant.id,
        );
        if (variantIndex !== -1) {
          const imageIndex = variantImagesStartIndex + variantIndex;
          if (imageIndex < allImages.length) {
            // Only update swiper slides, activeIndex will be updated by onSlideChange
            mainSwiperRef.current?.slideTo(imageIndex);
            thumbnailSwiperRef.current?.slideTo(imageIndex);
          }
        }
      }
    }, [selectedVariant, variants, variantImagesStartIndex, allImages.length]);

    // Scrolling can move the gallery away from a stationary pointer without
    // firing mouseleave. Dismiss the detached preview as soon as the viewport
    // moves, then allow normal mouse movement over the gallery to reopen it.
    useEffect(() => {
      if (!isHover) return;

      const dismissZoom = () => setZoom(null);

      window.addEventListener("scroll", dismissZoom, {
        capture: true,
        passive: true,
      });
      window.addEventListener("resize", dismissZoom);
      window.addEventListener("blur", dismissZoom);

      return () => {
        window.removeEventListener("scroll", dismissZoom, true);
        window.removeEventListener("resize", dismissZoom);
        window.removeEventListener("blur", dismissZoom);
      };
    }, [isHover]);

    // Helper to pull YouTube ID and thumbnail
    const getYouTubeId = (url?: string | null) => {
      if (!url) return null;
      try {
        const u = new URL(url);
        if (u.hostname.includes("youtu.be")) return u.pathname.slice(1);
        if (u.hostname.includes("youtube.com")) return u.searchParams.get("v");
      } catch {
        // fallback simple regex
        const m = url.match(/(?:v=|youtu\.be\/)([\w-]{11})/);
        return m ? m[1] : null;
      }
      return null;
    };

    const youTubeId = getYouTubeId(video?.url ?? null);
    const hasVideo = Boolean(video?.url);

    const showBottomSection = allImages.length > 1 || hasVideo;

    const updateZoom = (event: MouseEvent<HTMLButtonElement>, index: number) => {
      if (
        typeof window.matchMedia === "function" &&
        !window.matchMedia("(min-width: 768px) and (hover: hover) and (pointer: fine)").matches
      ) {
        setZoom(null);
        return;
      }
      const image = event.currentTarget.querySelector("img");
      if (!image) return;
      const rect = image.getBoundingClientRect();
      const previewSize = window.innerWidth * 0.2 - 2; // Exclude preview borders.
      const geometry = getProductImageZoom({
        surfaceWidth: rect.width,
        surfaceHeight: rect.height,
        naturalWidth: image.naturalWidth,
        naturalHeight: image.naturalHeight,
        pointerX: event.clientX - rect.left,
        pointerY: event.clientY - rect.top,
        previewWidth: zoomPreviewRef.current?.clientWidth || previewSize,
        previewHeight: zoomPreviewRef.current?.clientHeight || previewSize,
      });
      setZoom(geometry ? { ...geometry, index } : null);
    };

    if (isLoading) {
      return <ProductImgSectionSkeleton isVertical={isVertical} />;
    }

    return (
      <div
        className={`w-full h-full flex gap-4 ${isVertical ? "" : "flex-col"}`}
      >
        <div
          className={`w-full ${isVertical ? "order-2" : "order-1"} relative`}
          onMouseLeave={() => setZoom(null)}
          onWheel={() => setZoom(null)}
        >
          {/* Swiper for Main Image */}
          <Swiper
            spaceBetween={10}
            slidesPerView={1}
            onSlideChange={(swiper) => {
              setZoom(null);
              setActiveIndex(swiper.activeIndex);
              thumbnailSwiperRef.current?.slideTo(swiper.activeIndex);
            }}
            onSwiper={(swiper) => (mainSwiperRef.current = swiper)}
            modules={[Navigation, Pagination, Mousewheel]}
            className="mb-4"
            mousewheel
          >
            {allImages.map((image, index) => (
              <SwiperSlide key={index}>
                <button
                  type="button"
                  aria-label={t("productGallery.openImage", { index: index + 1 })}
                  className="relative h-[40vh] md:h-[60vh] md:min-h-[550px] w-full flex justify-center items-center bg-gray-100 dark:bg-gray-950 rounded-lg overflow-hidden cursor-zoom-in border-0 p-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                  onMouseEnter={(event) => updateZoom(event, index)}
                  onMouseMove={(event) => updateZoom(event, index)}
                  onMouseLeave={() => setZoom(null)}
                  onClick={() => {
                    setZoom(null);
                    setLightboxIndex(index);
                  }}
                >
                  <img
                    src={image}
                    alt={t("productGallery.mainImage", { index: index + 1 })}
                    loading={index === 0 ? "eager" : "lazy"}
                    decoding="async"
                    className="w-full h-[40vh] md:h-[60vh] md:min-h-[550px] object-contain"
                    draggable={false}
                  />
                  {zoom?.index === index && (
                    <span
                      aria-hidden="true"
                      data-product-zoom-lens
                      className="pointer-events-none absolute z-10 hidden border-2 border-primary bg-primary/15 shadow-sm md:block"
                      style={zoom.lens}
                    />
                  )}
                  <span
                    aria-hidden="true"
                    data-product-enlarge-hint
                    data-expanded={showEnlargeLabel}
                    onMouseEnter={() => setZoom(null)}
                    onMouseMove={(event) => event.stopPropagation()}
                    className="product-enlarge-hint absolute bottom-3 end-3 z-20 inline-flex items-center rounded-full border border-default-200 bg-content1/95 p-2 text-xs text-foreground shadow-sm"
                  >
                    <Maximize2 size={14} aria-hidden="true" className="shrink-0" />
                    <span className="product-enlarge-hint-label">
                      <span className="min-w-0 overflow-hidden whitespace-nowrap">
                        {t("productGallery.enlargeImage")}
                      </span>
                    </span>
                  </span>
                </button>
              </SwiperSlide>
            ))}

            {/* Video Slide (appended as last slide) */}
            {hasVideo && (
              <SwiperSlide key="video">
                <div className="h-[40vh] md:h-[60vh] md:min-h-[550px] w-full flex justify-center items-center bg-black rounded-lg overflow-hidden">
                  {video?.type === "youtube" && youTubeId ? (
                    <iframe
                      title={t("productGallery.videoTitle")}
                      src={`https://www.youtube.com/embed/${youTubeId}?rel=0&showinfo=0`}
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      allowFullScreen
                      className="w-full h-[40vh] md:h-[60vh] md:min-h-[550px]"
                    />
                  ) : (
                    <video
                      controls
                      src={video?.url ?? undefined}
                      className="w-full h-[40vh] md:h-[60vh] md:min-h-[550px] object-contain bg-black"
                    />
                  )}
                </div>
              </SwiperSlide>
            )}
          </Swiper>

          {/* Zoom Window Outside the Main Image */}
          {zoom && zoom.index === activeIndex && activeIndex < allImages.length && (
            <div
              ref={zoomPreviewRef}
              aria-hidden="true"
              data-product-zoom-preview
              className={`pointer-events-none absolute top-0 z-30 hidden h-[20vw] w-[20vw] select-none overflow-hidden rounded-lg border border-gray-300 bg-gray-100 shadow-lg md:block dark:border-default-200 dark:bg-gray-950 ${
                rtl
                  ? "-left-[10vw] md:-left-[20vw]"
                  : "-right-[10vw] md:-right-[20vw]"
              }`}
            >
              <img
                src={allImages[activeIndex]}
                alt=""
                className="absolute max-w-none"
                style={zoom.preview}
              />
            </div>
          )}
        </div>

        <div
          className={`w-full cursor-grab ${isVertical ? "order-1" : "order-2"}`}
        >
          {/* Swiper for Thumbnails */}
          {showBottomSection && (
            <Swiper
              direction={isVertical ? "vertical" : "horizontal"}
              spaceBetween={10}
              onSwiper={(swiper) => (thumbnailSwiperRef.current = swiper)}
              modules={[Mousewheel]}
              mousewheel
              className={`my-swiper ${
                isVertical ? "h-[40vh] md:h-[60vh] md:min-h-[550px] w-28" : ""
              }`}
              breakpoints={{
                320: { slidesPerView: 3.5 },
                768: {
                  slidesPerView: 7.5,
                },
                1024: {
                  slidesPerView: isVertical ? 4.5 : 7.5,
                },
              }}
            >
              {allImages.map((image, index) => (
                <SwiperSlide
                  key={index}
                  onClick={() => {
                    setZoom(null);
                    setActiveIndex(index);
                    mainSwiperRef.current?.slideTo(index); // Update the main Swiper
                  }}
                >
                  <div
                    className={`h-16 ${
                      isVertical ? "md:h-28" : "md:h-20"
                    } w-full object-contain rounded-lg flex justify-center items-center bg-gray-100 dark:bg-gray-950 active:scale-95 transition-transform duration-100 dark:border-default-100 ${
                      activeIndex === index
                        ? "border-primary dark:border-default-400"
                        : "border-gray-300"
                    } border`}
                  >
                    <img
                      src={image}
                      alt={t("productGallery.thumbnail", { index: index + 1 })}
                      loading="eager"
                      decoding="async"
                      className="w-full h-full max-h-14 md:max-w-24 rounded-lg object-contain"
                    />
                  </div>
                </SwiperSlide>
              ))}

              {/* Video thumbnail */}
              {hasVideo && (
                <SwiperSlide
                  key="video-thumb"
                  onClick={() => {
                    setZoom(null);
                    const targetIndex = allImages.length; // last slide index
                    setActiveIndex(targetIndex);
                    mainSwiperRef.current?.slideTo(targetIndex);
                  }}
                >
                  <div
                    className={`h-16 ${
                      isVertical ? "md:h-28" : "md:h-20"
                    } w-full rounded-lg flex justify-center items-center bg-gray-900 dark:bg-gray-950 relative active:scale-95 transition-transform duration-100 border ${
                      activeIndex === allImages.length
                        ? "border-primary dark:border-default-400"
                        : "border-gray-300 dark:border-default-100"
                    }`}
                  >
                    {/* Use YouTube thumbnail when possible */}
                    {video?.type === "youtube" && youTubeId ? (
                      <img
                        loading="eager"
                        decoding="async"
                        src={`https://img.youtube.com/vi/${youTubeId}/hqdefault.jpg`}
                        alt={t("productGallery.videoThumbnail")}
                        className="w-full h-full max-h-14 md:max-w-24 rounded-lg object-cover"
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center text-white">
                        {t("productGallery.video")}
                      </div>
                    )}

                    {/* Play overlay */}
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                      <svg
                        width="48"
                        height="48"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                      >
                        <circle cx="12" cy="12" r="12" fill="rgba(0,0,0,0.6)" />
                        <path d="M10 8L16 12L10 16V8Z" fill="#fff" />
                      </svg>
                    </div>
                  </div>
                </SwiperSlide>
              )}
            </Swiper>
          )}
        </div>
        {allImages.length > 0 && lightboxIndex !== null && (
          <Lightbox
            open={lightboxIndex !== null}
            index={lightboxIndex}
            plugins={[Thumbnails]}
            close={() => setLightboxIndex(null)}
            slides={allImages.map((src) => ({ src }))}
            thumbnails={{
              position: "bottom",
              width: 100,
              height: 70,
              borderRadius: 6,
              gap: 8,
            }}
          />
        )}
      </div>
    );
  },
);

ProductImgSection.displayName = "ProductImgSection";

export default ProductImgSection;
