import React from "react";
import { useTranslation } from "react-i18next";
import { Card, CardBody, Button, Image } from "@heroui/react";
import { ShoppingBag, Truck } from "lucide-react";
import { useRouter } from "next/router";
import { useSettings } from "@/contexts/SettingsContext";

/** Dot colours for the feature list, cycled so any number of items works. */
const FEATURE_DOTS = ["bg-green-400", "bg-blue-400", "bg-purple-400"];

/**
 * "Order Now" promo banner, driven by the backend's `orderNow*` web settings.
 *
 * Every string, the image and the CTA target come from admin; the i18n strings
 * remain as fallbacks so the section still reads correctly before settings load
 * or if a field is left blank. Hidden entirely when `orderNowSection` is false.
 */
const DeliveryBanner: React.FC = () => {
  const { t } = useTranslation();
  const router = useRouter();
  const { webSettings } = useSettings();

  // Explicit `=== false` rather than a falsy check: `undefined` means settings
  // haven't loaded, and hiding then would make the banner flicker in.
  if (webSettings?.orderNowSection === false) return null;

  const tagline = webSettings?.orderNowSectionTagline || t("home.delivery.header");
  const title = webSettings?.orderNowSectionTitle || t("home.delivery.title");
  const highlight =
    webSettings?.orderNowSectionTitleHighlight || t("home.delivery.highlight");
  const description =
    webSettings?.orderNowSectionDescription || t("home.delivery.subtitle");
  const buttonText = webSettings?.orderNowButtonText || t("home.delivery.button");
  const buttonUrl = webSettings?.orderNowButtonUrl || "/brands";
  const image =
    webSettings?.orderNowSectionImage || "/images/delivery-boy-blue.png";

  const features =
    webSettings?.orderNowFeatures?.filter(Boolean)?.length
      ? webSettings.orderNowFeatures.filter(Boolean)
      : [
          t("home.delivery.features.tracking"),
          t("home.delivery.features.packaging"),
          t("home.delivery.features.contact_free"),
        ];

  /**
   * The configured URL may point at another domain (it currently does), which
   * next/router can't handle — it would push a path that 404s. Route internally
   * only for same-origin links.
   */
  const handleCta = () => {
    const isExternal = /^https?:\/\//i.test(buttonUrl);

    if (!isExternal) {
      router.push(buttonUrl);
      return;
    }

    try {
      const target = new URL(buttonUrl);
      if (typeof window !== "undefined" && target.origin === window.location.origin) {
        router.push(`${target.pathname}${target.search}${target.hash}`);
      } else {
        window.open(buttonUrl, "_blank", "noopener,noreferrer");
      }
    } catch {
      // Malformed URL from admin — fail closed rather than navigating nowhere.
      router.push("/brands");
    }
  };

  return (
    <section id="delivery-banner" className="my-7">
      <div className="w-full relative overflow-visible">
        <Card className="relative overflow-visible bg-linear-to-br from-primary to-primary-600 rtl:bg-linear-to-bl shadow-2xl border-none">
          <CardBody className="relative z-10 p-0 overflow-visible">
            {/* GRID */}
            <div className="grid grid-cols-12 min-h-[220px] relative lg:rtl:grid-flow-col-dense">
              {/* ================= CONTENT ================= */}
              <div
                className="
                  col-span-12 lg:col-span-7
                  p-4 lg:p-10
                  flex flex-col justify-center
                  space-y-5
                  text-left rtl:text-right
                  max-w-full
                  rtl:lg:max-w-[85%]
                "
              >
                {/* Header */}
                <div className="flex items-center gap-3 w-full">
                  <div className="p-2 bg-white/20 rounded-full backdrop-blur-sm rtl:scale-90">
                    {/* Admin can supply its own badge icon; the Truck is the
                        fallback so the row never renders empty. */}
                    {webSettings?.orderNowSectionBadgeIcon ? (
                      <Image
                        src={webSettings.orderNowSectionBadgeIcon}
                        alt=""
                        removeWrapper
                        className="w-4 h-4 sm:w-6 sm:h-6 object-contain"
                      />
                    ) : (
                      <Truck className="w-4 h-4 sm:w-6 sm:h-6 text-white" />
                    )}
                  </div>
                  <span className="text-xs sm:text-sm font-medium text-white/90">
                    {tagline}
                  </span>
                </div>

                {/* Title */}
                <h1 className="text-xl sm:text-4xl lg:text-5xl font-bold text-white leading-tight">
                  {title}
                  {highlight && (
                    <span className="mx-2 bg-linear-to-r from-yellow-200 to-orange-200 bg-clip-text text-transparent inline-block">
                      {highlight}
                    </span>
                  )}
                </h1>

                {/* Subtitle */}
                <p className="text-xs sm:text-lg text-white/90 font-medium">
                  {description}
                </p>

                {/* CTA */}
                <Button
                  radius="full"
                  onPress={handleCta}
                  startContent={
                    <ShoppingBag className="w-4 h-4 sm:w-5 sm:h-5" />
                  }
                  className="
                    font-semibold bg-white text-black/80
                    px-6 py-3 sm:px-8 sm:py-6
                    sm:text-lg
                    shadow-lg hover:shadow-xl
                    hover:scale-105 transition-all
                    self-start
                  "
                >
                  {buttonText}
                </Button>

                {/* FEATURES — admin-defined, so the count isn't fixed at 3. */}
                <div className="flex flex-wrap gap-6 pt-3">
                  {features.map((feature, i) => (
                    <div
                      key={feature}
                      className="flex items-center gap-2 text-white/80"
                    >
                      <span
                        className={`w-2 h-2 rounded-full ${
                          FEATURE_DOTS[i % FEATURE_DOTS.length]
                        }`}
                      />
                      <span className="text-xxs sm:text-sm">{feature}</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* ================= IMAGE ================= */}
              <div
                className="
                  col-span-12 lg:col-span-5
                  relative flex items-center
                  justify-center
                  lg:justify-end
                 
                "
              >
                <div
                  className="
                    relative z-20
                    lg:absolute
                     lg:-top-6
                    lg:rtl:-left-4
                  "
                >
                  {/* Glow */}
                  <div className="absolute inset-0 bg-linear-to-br from-yellow-400/30 to-orange-400/30 rounded-full blur-2xl scale-110" />

                  {/* Image */}
                  <Image
                    src={image}
                    alt={title || "Delivery person"}
                    radius="lg"
                    className="relative z-10 w-56 h-60 sm:w-72 sm:h-80 lg:w-80 lg:h-96 object-cover drop-shadow-2xl"
                    classNames={{
                      img: "hover:scale-105 transition-transform duration-500 rtl:-scale-x-100 rtl:hover:-scale-x-105",
                    }}
                  />
                </div>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>
    </section>
  );
};

export default DeliveryBanner;
