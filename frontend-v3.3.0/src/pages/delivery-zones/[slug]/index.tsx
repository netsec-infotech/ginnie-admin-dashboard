import { GetServerSideProps, GetStaticPaths, GetStaticProps } from "next";
import { getDeliveryZoneBySlug, getSettings } from "@/routes/api";
import React from "react";
import { isSSR } from "@/helpers/getters";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import PageHeader from "@/components/custom/PageHeader";
import { DeliveryZone } from "@/types/ApiResponse";
import { NextPageWithLayout } from "@/types";
import GoogleMapForZone from "@/components/Location/GoogleMapForZone";
import { Card, CardBody, CardHeader, Divider, Skeleton } from "@heroui/react";
import { Clock, MapPin, Truck } from "lucide-react";
import { useSettings } from "@/contexts/SettingsContext";
import useSWR from "swr";
import { useRouter } from "next/router";
import { loadTranslations } from "../../../../i18n";
import { useTranslation } from "react-i18next";
import DynamicSEO from "@/SEO/DynamicSEO";
import { generateBreadcrumbSchema } from "@/helpers/seo";
import {
  getStaticApiResponse,
  getStaticDeliveryZoneIdentifiers,
  getStaticGenerationContext,
} from "@/services/staticGenerationService";

interface DeliveryZoneDetailPageProps {
  zone: DeliveryZone | null;
  slug: string;
  error?: string;
}

// SWR fetcher function
const fetchZoneBySlug = async (zoneSlug: string): Promise<DeliveryZone> => {
  const response = await getDeliveryZoneBySlug({ slug: zoneSlug });
  if (!response.data) {
    throw new Error("Zone not found");
  }
  return response.data;
};

const DeliveryZoneDetailPage: NextPageWithLayout<
  DeliveryZoneDetailPageProps
> = ({ zone: initialZone, slug, error: initialError }) => {
  const { t } = useTranslation();
  const { currencySymbol } = useSettings();
  const router = useRouter();
  const zoneSlug = slug || (router.query.slug as string);

  const {
    data: zone,
    error,
    isLoading,
  } = useSWR(
    zoneSlug ? `delivery-zone-${zoneSlug}` : null,
    () => fetchZoneBySlug(zoneSlug),
    {
      fallbackData: initialZone || undefined,
      revalidateOnMount: true,
    },
  );

  // Loading state
  if (isLoading && !zone && !error) {
    return (
      <>
        <DynamicSEO
          title={t("loading")}
          description={t("pages.deliveryZoneDetail.subtitle")}
          robots="noindex, follow"
        />
        <div className="min-h-screen">
        <MyBreadcrumbs
          breadcrumbs={[
            { href: "/delivery-zones", label: t("pageTitle.delivery-zones") },
            { href: `/delivery-zones/${zoneSlug}`, label: t("loading") },
          ]}
        />

        {/* Page Header Skeleton */}
        <div className="flex items-center justify-between mb-6">
          <div className="space-y-2">
            <Skeleton className="h-8 w-48 rounded-lg" />
            <Skeleton className="h-4 w-64 rounded-lg" />
          </div>
        </div>

        {/* Content Skeleton */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
          {/* Map Section Skeleton */}
          <div className="lg:col-span-2">
            <Card className="w-full h-full min-h-[150px] sm:min-h-[300px] md:min-h-[500px] lg:min-h-[500px]">
              <CardHeader className="pb-0">
                <Skeleton className="h-6 w-40 rounded-lg" />
              </CardHeader>
              <CardBody>
                <Skeleton className="h-[150px] sm:h-[300px] md:h-[500px] lg:h-full w-full rounded-lg" />
              </CardBody>
            </Card>
          </div>

          {/* Details Section Skeleton */}
          <div className="lg:col-span-1">
            <Card className="w-full h-full">
              <CardHeader className="pb-0">
                <Skeleton className="h-6 w-32 rounded-lg" />
              </CardHeader>
              <CardBody>
                <div className="space-y-4">
                  {/* Delivery Fees Skeleton */}
                  <div>
                    <Skeleton className="h-5 w-36 rounded-lg mb-2" />
                    <div className="space-y-2 pl-6">
                      {[1, 2, 3].map((i) => (
                        <div key={i} className="flex justify-between">
                          <Skeleton className="h-4 w-28 rounded-lg" />
                          <Skeleton className="h-4 w-16 rounded-lg" />
                        </div>
                      ))}
                    </div>
                  </div>

                  <Divider />

                  {/* Delivery Times Skeleton */}
                  <div>
                    <Skeleton className="h-5 w-32 rounded-lg mb-2" />
                    <div className="space-y-2 pl-6">
                      {[1, 2].map((i) => (
                        <div key={i} className="flex justify-between">
                          <Skeleton className="h-4 w-32 rounded-lg" />
                          <Skeleton className="h-4 w-12 rounded-lg" />
                        </div>
                      ))}
                    </div>
                  </div>

                  <Divider />

                  {/* Coverage Details Skeleton */}
                  <div>
                    <Skeleton className="h-5 w-36 rounded-lg mb-2" />
                    <div className="space-y-2 pl-6">
                      {[1, 2, 3].map((i) => (
                        <div key={i} className="flex justify-between">
                          <Skeleton className="h-4 w-24 rounded-lg" />
                          <Skeleton className="h-4 w-20 rounded-lg" />
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </CardBody>
            </Card>
          </div>
        </div>
        </div>
      </>
    );
  }

  // Error state
  if (error || (!zone && !isLoading)) {
    const isNotFound =
      error?.message === "Zone not found" || initialError === "Zone not found";

    return (
      <>
        <DynamicSEO
          title={
            isNotFound
              ? t("pages.deliveryZoneDetail.zoneNotFound")
              : t("pages.deliveryZoneDetail.errorLoading")
          }
          description={
            isNotFound
              ? t("pages.deliveryZoneDetail.zoneNotFoundDesc")
              : t("pages.deliveryZoneDetail.errorLoadingDesc")
          }
          robots="noindex, follow"
        />
        <div className="min-h-screen">
        <MyBreadcrumbs
          breadcrumbs={[
            { href: "/delivery-zones", label: t("pageTitle.delivery-zones") },
            { href: `/delivery-zones/${zoneSlug}`, label: "N/A" },
          ]}
        />

        <div className="text-center min-h-[70vh] flex justify-center items-center flex-col">
          <MapPin className="w-16 h-16 mx-auto mb-4" />
          <h1 className="text-2xl font-semibold mb-2">
            {isNotFound
              ? t("pages.deliveryZoneDetail.zoneNotFound")
              : t("pages.deliveryZoneDetail.errorLoading")}
          </h1>
          <p className="text-foreground/50 mb-4">
            {isNotFound
              ? t("pages.deliveryZoneDetail.zoneNotFoundDesc")
              : t("pages.deliveryZoneDetail.errorLoadingDesc")}
          </p>
        </div>
        </div>
      </>
    );
  }

  if (!zone) return null;

  // The backend detail endpoint is ID-keyed despite the historical `slug`
  // parameter name used by this page and API wrapper.
  const canonicalIdentifier = zoneSlug || String(zone.id);
  const metaDescription = t("pages.deliveryZoneDetail.metaDescription", {
    zoneName: zone.name,
  });
  const serviceSchema = {
    "@context": "https://schema.org",
    "@type": "Service",
    name: `${zone.name} local delivery`,
    description: metaDescription,
    serviceType: "Local delivery",
    areaServed: {
      "@type": "Place",
      name: zone.name,
      geo: {
        "@type": "GeoCoordinates",
        latitude: zone.center_latitude,
        longitude: zone.center_longitude,
      },
    },
  };

  return (
    <>
      <DynamicSEO
        title={`${zone.name} - ${t("pages.deliveryZoneDetail.titleSuffix")}`}
        description={metaDescription}
        keywords={`${zone.name}, local delivery, delivery area, service zone`}
        canonical={`/delivery-zones/${canonicalIdentifier}`}
        ogType="website"
        ogTitle={`${zone.name} - ${t("pages.deliveryZoneDetail.titleSuffix")}`}
        ogDescription={metaDescription}
        jsonLd={[
          serviceSchema,
          generateBreadcrumbSchema([
            { name: "Home", url: "/" },
            { name: t("pageTitle.delivery-zones"), url: "/delivery-zones" },
            {
              name: zone.name,
              url: `/delivery-zones/${canonicalIdentifier}`,
            },
          ]),
        ]}
      />

      <div className="min-h-screen">
        <MyBreadcrumbs
          breadcrumbs={[
            { href: "/delivery-zones", label: t("pageTitle.delivery-zones") },
            { href: `/delivery-zones/${zoneSlug}`, label: zone.name },
          ]}
        />

        <div className="flex items-center justify-between">
          <PageHeader
            title={zone.name}
            subtitle={t("pages.deliveryZoneDetail.subtitle")}
          />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
          {/* Map Section */}
          <div className="lg:col-span-2">
            <Card className="w-full h-full min-h-[150px] sm:min-h-[300px] md:min-h-[500px] lg:min-h-[500px]">
              <CardHeader className="pb-0">
                <h3 className="text-lg font-semibold">
                  {t("pages.deliveryZoneDetail.coverageArea")}
                </h3>
              </CardHeader>
              <CardBody>
                <GoogleMapForZone
                  zone={zone}
                  className="h-[150px] sm:h-[300px] md:h-[500px] lg:h-full"
                />
              </CardBody>
            </Card>
          </div>

          {/* Details Section */}
          <div className="lg:col-span-1">
            <Card className="w-full h-full">
              <CardHeader className="pb-0">
                <h3 className="text-lg font-semibold">
                  {t("pages.deliveryZoneDetail.zoneInfo")}
                </h3>
              </CardHeader>
              <CardBody>
                <div className="space-y-4">
                  {/* Delivery Fees */}
                  <div>
                    <h4 className="font-medium mb-2 flex items-center gap-2">
                      <Truck className="w-4 h-4" />{" "}
                      {t("pages.deliveryZoneDetail.deliveryFees")}
                    </h4>
                    <ul className="space-y-2 pl-6">
                      <li className="flex justify-between">
                        <span className="text-sm text-foreground/70">
                          {t("pages.deliveryZoneDetail.regularDelivery")}
                        </span>
                        <span className="font-medium">
                          {currencySymbol}
                          {zone.regular_delivery_charges}
                        </span>
                      </li>
                      {zone.rush_delivery_enabled && (
                        <li className="flex justify-between">
                          <span className="text-sm text-foreground/70">
                            {t("pages.deliveryZoneDetail.rushDelivery")}
                          </span>
                          <span className="font-medium">
                            {currencySymbol}
                            {zone.rush_delivery_charges}
                          </span>
                        </li>
                      )}
                      {zone.free_delivery_amount && (
                        <li className="flex justify-between">
                          <span className="text-sm text-foreground/70">
                            {t("pages.deliveryZoneDetail.freeDeliveryAbove")}
                          </span>
                          <span className="font-medium text-success">
                            {currencySymbol}
                            {zone.free_delivery_amount}
                          </span>
                        </li>
                      )}
                      {zone.distance_based_delivery_charges > 0 && (
                        <li className="flex justify-between">
                          <span className="text-sm text-foreground/70">
                            {t("pages.deliveryZoneDetail.perKmCharge")}
                          </span>
                          <span className="font-medium">
                            {currencySymbol}
                            {zone.distance_based_delivery_charges}/km
                          </span>
                        </li>
                      )}
                      {zone.per_store_drop_off_fee > 0 && (
                        <li className="flex justify-between">
                          <span className="text-sm text-foreground/70">
                            {t("pages.deliveryZoneDetail.perStoreFee")}
                          </span>
                          <span className="font-medium">
                            {currencySymbol}
                            {zone.per_store_drop_off_fee}
                          </span>
                        </li>
                      )}
                      {zone.handling_charges > 0 && (
                        <li className="flex justify-between">
                          <span className="text-sm text-foreground/70">
                            {t("pages.deliveryZoneDetail.handlingFee")}
                          </span>
                          <span className="font-medium">
                            {currencySymbol}
                            {zone.handling_charges}
                          </span>
                        </li>
                      )}
                    </ul>
                  </div>

                  <Divider />

                  {/* Delivery Times */}
                  <div>
                    <h4 className="font-medium mb-2 flex items-center gap-2">
                      <Clock className="w-4 h-4" />{" "}
                      {t("pages.deliveryZoneDetail.deliveryTimes")}
                    </h4>
                    <ul className="space-y-2 pl-6">
                      <li className="flex justify-between">
                        <span className="text-sm text-foreground/70">
                          {t("pages.deliveryZoneDetail.regularTimePerKm")}
                        </span>
                        <span className="font-medium">
                          {zone.delivery_time_per_km} min
                        </span>
                      </li>
                      {zone.rush_delivery_enabled && (
                        <li className="flex justify-between">
                          <span className="text-sm text-foreground/70">
                            {t("pages.deliveryZoneDetail.rushTimePerKm")}
                          </span>
                          <span className="font-medium">
                            {zone.rush_delivery_time_per_km} min
                          </span>
                        </li>
                      )}
                      {zone.buffer_time > 0 && (
                        <li className="flex justify-between">
                          <span className="text-sm text-foreground/70">
                            {t("pages.deliveryZoneDetail.bufferTime")}
                          </span>
                          <span className="font-medium">
                            {zone.buffer_time} min
                          </span>
                        </li>
                      )}
                    </ul>
                  </div>

                  <Divider />

                  {/* Coverage Details */}
                  <div>
                    <h4 className="font-medium mb-2 flex items-center gap-2">
                      <MapPin className="w-4 h-4" />{" "}
                      {t("pages.deliveryZoneDetail.coverageDetails")}
                    </h4>
                    <ul className="space-y-2 pl-6">
                      <li className="flex justify-between">
                        <span className="text-sm text-foreground/70">
                          {t("pages.deliveryZoneDetail.radius")}
                        </span>
                        <span className="font-medium">{zone.radius_km} km</span>
                      </li>
                      <li className="flex justify-between">
                        <span className="text-sm text-foreground/70">
                          {t("pages.deliveryZoneDetail.centerCoords")}
                        </span>
                        <span className="font-medium text-xs">
                          {zone.center_latitude}, {zone.center_longitude}
                        </span>
                      </li>
                      <li className="flex justify-between">
                        <span className="text-sm text-foreground/70">
                          {t("pages.deliveryZoneDetail.boundaryPoints")}
                        </span>
                        <span className="font-medium">
                          {zone.boundary_json?.length || 0} points
                        </span>
                      </li>
                    </ul>
                  </div>
                </div>
              </CardBody>
            </Card>
          </div>
        </div>
      </div>
    </>
  );
};

export const getRequestTimeDeliveryZoneProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      try {
        const slug = context.params?.slug as string;

        const [zoneResult, settingsResult] = await Promise.allSettled([
          getDeliveryZoneBySlug({ slug }),
          getSettings(),
        ]);

        await loadTranslations(context);

        return {
          props: {
            zone:
              zoneResult.status === "fulfilled"
                ? (zoneResult.value.data ?? null)
                : null,
            slug,
            initialSettings:
              settingsResult.status === "fulfilled"
                ? (settingsResult.value.data ?? null)
                : null,
            error:
              zoneResult.status === "rejected" ||
              settingsResult.status === "rejected"
                ? "Some data failed to load"
                : null,
          },
        };
      } catch (error) {
        console.error("Unexpected error:", error);
        return {
          props: {
            zone: null,
            slug: context.params?.slug as string,
            initialSettings: null,
            error: "Unexpected failure",
          },
        };
      }
    }
  : undefined;

export const getStaticPaths: GetStaticPaths = async () => ({
      paths: (await getStaticDeliveryZoneIdentifiers()).map((slug) => ({
        params: { slug },
      })),
      fallback: isSSR() ? "blocking" : false,
    });

export const getStaticProps: GetStaticProps = async (context) => {
      const slug = context.params?.slug;
      if (!slug || Array.isArray(slug)) return { notFound: true };

      const [{ settings }, zone] = await Promise.all([
        getStaticGenerationContext(),
        getStaticApiResponse(`delivery-zone details for ${slug}`, () =>
          getDeliveryZoneBySlug({ slug }),
        ),
      ]);
      if (!zone.success || !zone.data) return { notFound: true };

      return {
        props: {
          zone: zone.data,
          slug,
          initialSettings: settings,
        },
        ...(isSSR() ? { revalidate: 300 } : {}),
      };
    };

export default DeliveryZoneDetailPage;
