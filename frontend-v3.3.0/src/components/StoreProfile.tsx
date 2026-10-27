import React, { useMemo, useState } from "react";
import { Avatar, Chip, Image } from "@heroui/react";
import { MapPin, Phone, Mail, Star, Package } from "lucide-react";
import { Store } from "@/types/ApiResponse";
import { useTranslation } from "react-i18next";
import Lightbox from "yet-another-react-lightbox";
import StoreTiming from "./StoreTiming";
import { WatchAndBuyStoriesSection } from "@/components/WatchAndBuy";
import { toStoreStoryGroup } from "@/components/WatchAndBuy/adapters";
import type { StoryGroup } from "@/components/Stories/types";
import { sanitizeRichText } from "@/helpers/sanitizeRichText";
import { parseStoreOperatingHours } from "@/helpers/storeTiming";
import StoreNotifyButton from "@/components/StoreNotifyButton";

interface StoreProfileProps {
  store: Store;
}

/* --------------------------------------------
   Reusable Store Content
--------------------------------------------- */
const StoreContent = ({
  store,
  rating,
  t,
  textColor = "text-foreground",
  openLightbox,
  storyGroup,
  storySectionId,
}: any) => {
  const description = useMemo(
    () => sanitizeRichText(store.description ?? ""),
    [store.description],
  );
  const getStatusColor = () => {
    if (!store.status.is_open) return "danger";
    return store.status.status === "online" ? "success" : "default";
  };

  const getStatusText = () => {
    if (!store.status.is_open) return "Closed";
    return store.status.status === "online" ? "Open Now" : "Offline";
  };

  return (
    <div className="relative flex w-full flex-col items-center gap-3 px-0 lg:flex-row lg:gap-4 lg:px-6 lg:[text-shadow:0_1px_3px_rgba(0,0,0,0.95),0_0_8px_rgba(0,0,0,0.7)] lg:[&_svg]:drop-shadow-[0_1px_2px_rgba(0,0,0,0.95)]">
      <div className="relative z-10 shrink-0">
        <WatchAndBuyStoriesSection
          emptyState={
            <button
              type="button"
              aria-label={t("pages.storeProductsPage.stories.openLogo", {
                defaultValue: "View {{store}} logo",
                store: store.name,
              })}
              className="relative rounded-full bg-white/80 p-1 outline-none transition-transform hover:scale-[1.025] focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none"
              onClick={() => openLightbox("avatar")}
            >
              <span className="block rounded-full bg-content1 p-[3px]">
                <Avatar
                  src={store.logo}
                  alt={store.name}
                  className="h-24 w-24 shadow-2xl sm:h-28 sm:w-28 lg:h-32 lg:w-32"
                  radius="full"
                />
              </span>
            </button>
          }
          fallbackGroups={storyGroup ? [storyGroup] : []}
          hideWhenEmpty={false}
          sectionId={storySectionId}
          showEmptyStateAsFallback
          storeId={store.id}
          storeSlug={store.slug}
          renderTrigger={({ progress, onOpen }) => {
            const isSeen = progress.state === "seen";
            return (
              <button
                type="button"
                aria-label={t("pages.storeProductsPage.stories.openState", {
                  defaultValue: "View {{store}} Stories, {{state}}",
                  store: store.name,
                  state: isSeen
                    ? t("stories.viewed", { defaultValue: "Viewed" })
                    : t("stories.newCount", {
                        count: progress.unseen,
                        defaultValue: "{{count}} new",
                      }),
                })}
                className={`relative rounded-full p-1 outline-none transition-transform hover:scale-[1.025] focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none ${
                  isSeen
                    ? "bg-default-300 shadow-lg"
                    : "bg-linear-to-tr from-primary via-fuchsia-500 to-orange-400 shadow-[0_10px_35px_rgba(168,85,247,0.28)]"
                }`}
                data-state={progress.state}
                onClick={onOpen}
              >
                <span className="block rounded-full bg-content1 p-[3px]">
                  <Avatar
                    src={store.logo}
                    alt={store.name}
                    className="h-24 w-24 shadow-2xl sm:h-28 sm:w-28 lg:h-32 lg:w-32"
                    radius="full"
                  />
                </span>
              </button>
            );
          }}
        />
      </div>
      {/* Content */}
      <div className="relative z-10 w-full min-w-0 px-1 pb-1 pt-3 text-center lg:flex-1 lg:p-0 lg:text-left">
        <h2
          className={`text-xl font-bold sm:text-2xl lg:text-3xl ${textColor}`}
        >
          {store.name}
        </h2>

        {/* Status + Products + Rating + Opening notification */}
        <div className="mt-2 flex flex-wrap items-center justify-center gap-1.5 lg:justify-start lg:gap-2.5">
          <Chip
            size="sm"
            color={getStatusColor()}
            variant="dot"
            className="text-xs lg:border lg:border-white/20 lg:bg-black/25 lg:text-white lg:backdrop-blur-sm lg:shadow-[0_2px_8px_rgba(0,0,0,0.18)]"
          >
            {getStatusText()}
          </Chip>

          <Chip
            size="sm"
            variant="bordered"
            startContent={<Package className="w-4 h-4" />}
            className="text-xs font-semibold lg:border-white/20 lg:bg-black/25 lg:text-white lg:backdrop-blur-sm lg:shadow-[0_2px_8px_rgba(0,0,0,0.18)]"
          >
            {t("products_count", { count: store.product_count })}
          </Chip>

          {rating > 0 && (
            <Chip
              size="sm"
              variant="bordered"
              startContent={
                <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
              }
              className="text-xs font-bold lg:border-white/20 lg:bg-black/25 lg:text-white lg:backdrop-blur-sm lg:shadow-[0_2px_8px_rgba(0,0,0,0.18)]"
            >
              {rating}/5 {`(${store.total_store_feedback})`}
            </Chip>
          )}
          {!store.status.is_open && (
            <StoreNotifyButton
              key={store.id}
              store={store}
              className="lg:border-white/30 lg:bg-black/30 lg:text-white dark:lg:text-white lg:backdrop-blur-sm lg:[text-shadow:none] lg:[&_svg]:drop-shadow-none"
            />
          )}
        </div>

        {/* Description */}
        {store.description && (
          <div className="mx-auto mt-3 max-w-3xl lg:mx-0">
            <div
              className="text-sm leading-5 text-foreground/60 lg:text-white/90"
              dangerouslySetInnerHTML={{ __html: description }}
            />
          </div>
        )}
      </div>
    </div>
  );
};

/* --------------------------------------------
   Main Component
--------------------------------------------- */
const StoreProfile: React.FC<StoreProfileProps> = ({ store }) => {
  const { t } = useTranslation();

  const [open, setOpen] = useState(false);
  const [slides, setSlides] = useState<any[]>([]);

  const rating = parseFloat(store.avg_store_rating).toFixed(1) || 0;
  const lat = store.latitude;
  const lng = store.longitude;
  const storyGroup = useMemo<StoryGroup | null>(
    () => toStoreStoryGroup(store),
    [store],
  );
  const hasStructuredHours = useMemo(
    () => parseStoreOperatingHours(store.metadata) !== null,
    [store.metadata],
  );

  /* Lightbox handler */
  const openLightbox = (clicked: "banner" | "avatar") => {
    const banner = store.banner || "/images/roof.png";
    const avatar = store.logo || "/images/roof.png";

    setSlides(
      clicked === "avatar"
        ? [{ src: avatar }, { src: banner }]
        : [{ src: banner }, { src: avatar }],
    );
    setOpen(true);
  };

  return (
    <div className="w-full mx-auto overflow-hidden rounded-lg bg-content1 shadow-none">
      {/* Desktop and mobile layouts duplicate the visible profile heading. Keep
          one canonical page heading for crawlers and assistive technology. */}
      <h1 className="sr-only">{store.name}</h1>
      <Lightbox open={open} close={() => setOpen(false)} slides={slides} />

      <div className="relative overflow-hidden lg:grid lg:grid-cols-1">
        {/* ================= Banner ================= */}
        <div className="relative h-40 w-full overflow-hidden sm:h-56 md:h-72 lg:col-start-1 lg:row-start-1 lg:h-96">
          <button
            type="button"
            aria-label={t("pages.storeProductsPage.stories.openBanner", {
              defaultValue: "View {{store}} banner",
              store: store.name,
            })}
            className="absolute inset-0 h-full w-full cursor-pointer"
            onClick={() => openLightbox("banner")}
          >
            <Image
              src={store.banner || "/images/roof.png"}
              alt={`${store.name} banner`}
              className="h-full w-full object-cover"
              removeWrapper
            />
          </button>
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-x-0 bottom-0 hidden h-2/3 bg-linear-to-t from-black/80 via-black/35 to-transparent lg:block"
          />
        </div>

        {/* The desktop banner grows with its content; the contact strip has its own row. */}
        <div className="relative z-10 -mt-12 px-4 pb-4 lg:col-start-1 lg:row-start-1 lg:mt-0 lg:self-center lg:px-0 lg:py-6">
          <StoreContent
            store={store}
            rating={rating}
            t={t}
            textColor="text-foreground lg:text-white"
            openLightbox={openLightbox}
            storyGroup={storyGroup}
            storySectionId={`store-${store.id}-stories`}
          />
        </div>

        {/* ================= Contact Section ================= */}
        <div className="border-t border-black/15 bg-white/95 p-2.5 shadow-[0_-8px_24px_rgba(0,0,0,0.14)] backdrop-blur-md dark:border-white/15 dark:bg-black/90 sm:px-6 sm:py-3 lg:col-start-1 lg:row-start-2 lg:z-20">
          <div className="relative z-10 grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-4 xl:gap-0">
            {(hasStructuredHours || store.timing) && (
              <div className="flex min-w-0 items-center rounded-lg bg-default-100/70 px-2.5 py-2 sm:bg-transparent sm:px-0 sm:py-1 xl:justify-center xl:rounded-none xl:border-e xl:border-divider xl:px-4">
                <StoreTiming metadata={store.metadata} timing={store.timing} />
              </div>
            )}

            {store.address && (
              <div className="flex min-w-0 items-center gap-2 rounded-lg bg-default-100/70 px-2.5 py-2 text-foreground/70 sm:gap-3 sm:bg-transparent sm:px-0 sm:py-1 xl:justify-center xl:rounded-none xl:border-e xl:border-divider xl:px-4">
                <MapPin className="h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                <a
                  href={`https://www.google.com/maps?q=${lat},${lng}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  title={store.address}
                  className="min-w-0 truncate text-xs font-medium hover:underline sm:text-sm"
                >
                  {store.address}
                </a>
              </div>
            )}

            {store.contact_number && (
              <div className="flex min-w-0 items-center gap-2 rounded-lg bg-default-100/70 px-2.5 py-2 text-foreground/70 sm:gap-3 sm:bg-transparent sm:px-0 sm:py-1 xl:justify-center xl:rounded-none xl:border-e xl:border-divider xl:px-4">
                <Phone className="h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                <a
                  href={`tel:${store.contact_number}`}
                  title={store.contact_number}
                  className="min-w-0 truncate text-xs font-medium hover:underline sm:text-sm"
                >
                  {store.contact_number}
                </a>
              </div>
            )}

            {store.contact_email && (
              <div className="flex min-w-0 items-center gap-2 rounded-lg bg-default-100/70 px-2.5 py-2 text-foreground/70 sm:gap-3 sm:bg-transparent sm:px-0 sm:py-1 xl:justify-center xl:rounded-none xl:px-4">
                <Mail className="h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                <a
                  href={`mailto:${store.contact_email}`}
                  title={store.contact_email}
                  className="min-w-0 truncate text-xs font-medium hover:underline sm:text-sm"
                >
                  {store.contact_email}
                </a>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default StoreProfile;
