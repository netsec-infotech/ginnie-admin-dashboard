import React, { useState } from "react";
import { Avatar, Chip, Image } from "@heroui/react";
import { MapPin, Phone, Mail, Star, Package } from "lucide-react";
import { Store } from "@/types/ApiResponse";
import { useTranslation } from "react-i18next";
import Lightbox from "yet-another-react-lightbox";
import StoreTiming from "./StoreTiming";

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
  textColor = "text-gray-900",
  openLightbox,
}: any) => {
  const getStatusColor = () => {
    if (!store.status.is_open) return "danger";
    return store.status.status === "online" ? "success" : "default";
  };

  const getStatusText = () => {
    if (!store.status.is_open) return "Closed";
    return store.status.status === "online" ? "Open Now" : "Offline";
  };

  return (
    <div className="relative flex md:items-end items-start gap-4 px-4 md:px-6 w-full">
      <div
        className="absolute 
             left-0 right-0 
             -top-10 h-[calc(100%+5rem)]
             z-0
             bg-linear-to-t
             to-transparent 
             md:from-black/70 
             md:via-black/60 
             md:to-transparent"
      />

      <div className="md:-translate-y-1/3">
        <Avatar
          isBordered
          src={store.logo}
          alt={store.name}
          className="w-32 h-32 shadow-2xl cursor-pointer border border-white"
          radius="full"
          onClick={() => openLightbox("avatar")}
        />
      </div>
      {/* Content */}
      <div className="relative z-10 p-4 sm:p-6">
        <h1 className={`text-2xl sm:text-3xl font-bold ${textColor}`}>
          {store.name}
        </h1>

        {/* Status + Products + Rating */}
        <div className="flex flex-wrap items-center gap-2.5 mt-2">
          <Chip
            size="sm"
            color={getStatusColor()}
            variant="dot"
            className="text-xs md:text-white"
          >
            {getStatusText()}
          </Chip>

          <Chip
            size="sm"
            variant="bordered"
            startContent={<Package className="w-4 h-4" />}
            className="font-semibold text-xs md:text-white"
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
              className="font-bold text-xs md:text-white"
            >
              {rating}/5 {`(${store.total_store_feedback})`}
            </Chip>
          )}
        </div>

        {/* Description */}
        {store.description && (
          <div className="mt-3 max-w-3xl">
            <div
              className={`text-sm text-foreground/50 md:text-gray-300 `}
              dangerouslySetInnerHTML={{ __html: store.description }}
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

  /* Lightbox handler */
  const openLightbox = (clicked: "banner" | "avatar") => {
    const banner = store.banner || "/images/roof.png";
    const avatar = store.logo || "/images/roof.png";

    setSlides(
      clicked === "avatar"
        ? [{ src: avatar }, { src: banner }]
        : [{ src: banner }, { src: avatar }]
    );
    setOpen(true);
  };

  return (
    <div className="w-full mx-auto rounded-lg overflow-hidden bg-white dark:bg-content1 shadow-lg">
      <Lightbox open={open} close={() => setOpen(false)} slides={slides} />

      {/* ================= Banner ================= */}
      <div className="relative h-64 sm:h-72 md:h-80 w-full overflow-hidden">
        <div
          className="absolute inset-0 cursor-pointer"
          onClick={() => openLightbox("banner")}
        >
          <Image
            src={store.banner || "/images/roof.png"}
            alt={`${store.name} banner`}
            className="w-full h-full object-cover"
            removeWrapper
          />
        </div>

        {/* -------- Desktop Overlay -------- */}
        <div className="absolute inset-0 hidden lg:flex flex-col justify-end">
          <div className="relative z-10 text-white">
            <div className="flex gap-4 items-end">
              <div className="flex-1">
                <StoreContent
                  store={store}
                  rating={rating}
                  t={t}
                  textColor="text-white"
                  openLightbox={openLightbox}
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ================= Mobile Content ================= */}
      <div className="block lg:hidden px-4 py-5">
        <div className="flex gap-4 items-start">
          <div className="flex-1">
            <StoreContent
              store={store}
              rating={rating}
              t={t}
              openLightbox={openLightbox}
            />
          </div>
        </div>
      </div>

      {/* ================= Contact Section ================= */}
      <div className="px-4 sm:px-6 py-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
          <StoreTiming timing={store.timing} />

          {store.address && (
            <div className="flex items-center gap-3 text-foreground/70">
              <MapPin className="w-5 h-5" />
              <a
                href={`https://www.google.com/maps?q=${lat},${lng}`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-sm  hover:underline line-clamp-1 max-w-[90%]"
              >
                {store.address}
              </a>
            </div>
          )}

          {store.contact_number && (
            <div className="flex items-center gap-3 text-foreground/70">
              <Phone className="w-5 h-5 " />
              <a
                href={`tel:${store.contact_number}`}
                className="text-sm  hover:underline"
              >
                {store.contact_number}
              </a>
            </div>
          )}

          {store.contact_email && (
            <div className="flex items-center gap-3 text-foreground/70">
              <Mail className="w-5 h-5 " />
              <a
                href={`mailto:${store.contact_email}`}
                className="text-sm  hover:underline truncate"
              >
                {store.contact_email}
              </a>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default StoreProfile;
