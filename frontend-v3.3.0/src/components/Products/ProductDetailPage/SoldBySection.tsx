import { Product } from "@/types/ApiResponse";
import { Card, CardBody, CardHeader, Avatar, Chip } from "@heroui/react";
import Link from "next/link";
import { FC } from "react";
import { useTranslation } from "react-i18next";
import { Star } from "lucide-react"; // ⭐ Lucide icon
import useSWR from "swr";
import { getSpecificStore } from "@/routes/api";

const SoldBySection: FC<{ product: Product }> = ({ product }) => {
  const { t } = useTranslation();
  const {
    seller,
    variants,
    store_status = {
      is_open: false,
      current_slot: null,
      next_opening_time: null,
    },
    seller_ratings,
  } = product || {};
  const defaultVariant = variants?.[0];
  const seller_rating = seller_ratings?.average_rating || 0;
  const storeSlug = defaultVariant?.store_slug;
  const storeHref = storeSlug ? `/stores/${storeSlug}` : undefined;
  const { data: storeLogo } = useSWR(
    storeSlug ? ["sold-by-store-logo", storeSlug] : null,
    async ([, slug]: [string, string]) => {
      const response = await getSpecificStore(slug);
      return response.success ? response.data?.logo || null : null;
    },
    { revalidateOnFocus: false },
  );

  const avatar = (
    <Avatar
      size="lg"
      src={storeLogo || undefined}
      name={defaultVariant?.store_name || seller || t("soldBySection.noLogo")}
      alt={defaultVariant?.store_name || seller || t("soldBySection.noLogo")}
      radius="lg"
    />
  );

  return (
    <Card className="w-full">
      <CardHeader className="flex items-center gap-3">
        {storeHref ? (
          <Link href={storeHref} className="rounded-lg focus-visible:outline-2 focus-visible:outline-primary">
            {avatar}
          </Link>
        ) : avatar}
        <div className="flex flex-col">
          <div className="flex gap-2 justify-start items-center">
            <h3 className="text-medium font-semibold">
              {storeHref ? (
                <Link href={storeHref} className="hover:underline focus-visible:outline-2 focus-visible:outline-primary">
                  {seller || t("soldBySection.noSellerInfo")}
                </Link>
              ) : seller || t("soldBySection.noSellerInfo")}
            </h3>

            {/* Seller rating */}
            <div className="flex items-center gap-1 mt-0.5">
              <Star size={16} className="text-yellow-500 fill-yellow-500" />
              <span className="text-sm font-medium">
                {!isNaN(Number(seller_rating))
                  ? Number(seller_rating).toFixed(1)
                  : ""}
              </span>
            </div>
          </div>

          {/* Store link */}
          {defaultVariant?.store_slug && (
            <div className="flex items-center gap-2 mt-1">
              <span className="text-xs text-foreground/50">
                {t("soldBySection.storeLabel")}
              </span>
              <Link
                href={`/stores/${defaultVariant.store_slug}`}
                className="text-xs text-foreground/50"
                title={defaultVariant.store_name}
              >
                {defaultVariant.store_name}
              </Link>
            </div>
          )}
        </div>
      </CardHeader>

      <CardBody className="pt-1">
        {store_status?.is_open !== undefined && (
          <Chip
            color={store_status.is_open ? "success" : "danger"}
            variant="flat"
            className="mb-2"
          >
            {store_status.is_open
              ? t("soldBySection.open")
              : t("soldBySection.closed")}
          </Chip>
        )}

        <p className="text-sm text-foreground/50 mb-1">
          {store_status?.is_open
            ? t("soldBySection.acceptingOrders")
            : t("soldBySection.storeClosed")}
        </p>

        {/* Show next opening time only if closed */}
        {!store_status?.is_open && store_status?.next_opening_time && (
          <p className="text-sm text-primary font-medium">
            {t("soldBySection.opensAt")} {store_status.next_opening_time}
          </p>
        )}
      </CardBody>
    </Card>
  );
};

export default SoldBySection;
