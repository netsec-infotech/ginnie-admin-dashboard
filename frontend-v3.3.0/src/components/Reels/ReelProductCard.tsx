import { MapPin, Star } from "lucide-react";
import Image from "next/image";
import type { FC } from "react";
import { useTranslation } from "react-i18next";

import type { ReelProduct } from "./types";

interface ReelProductCardProps {
  product: ReelProduct;
  onPress: (product: ReelProduct) => void;
}

const ReelProductCard: FC<ReelProductCardProps> = ({ product, onPress }) => {
  const { t } = useTranslation();
  const isAvailable = Boolean(product.href);
  const actionLabel = isAvailable
    ? (product.actionLabel ??
      t("reels.viewer.viewProduct", { defaultValue: "View product" }))
    : t("reels.viewer.productUnavailable", { defaultValue: "Unavailable" });
  const storeLabel = [product.store?.name, product.store?.distanceLabel]
    .filter(Boolean)
    .join(" · ");
  const unavailableLabel = t("reels.viewer.productUnavailable", {
    defaultValue: "Unavailable",
  });

  return (
    <button
      data-reel-control
      type="button"
      aria-label={`${product.name}${product.price ? `, ${product.price}` : ""}${
        storeLabel ? `, ${storeLabel}` : ""
      }${isAvailable ? "" : `, ${unavailableLabel}`}`}
      className="group/product relative flex h-[132px] w-[270px] shrink-0 gap-2.5 overflow-hidden rounded-2xl bg-content1 p-2 text-left text-foreground shadow-none outline-none transition-transform [&_*]:pointer-events-none enabled:hover:-translate-y-0.5 enabled:focus-visible:ring-2 enabled:focus-visible:ring-primary-500 disabled:pointer-events-none disabled:cursor-default disabled:opacity-80 motion-reduce:transition-none"
      disabled={!isAvailable}
      onClick={() => onPress(product)}
      onDragStart={(event) => event.preventDefault()}
    >
      <span className="relative h-full w-[102px] shrink-0 overflow-hidden rounded-xl bg-content2">
        <Image
          fill
          alt=""
          aria-hidden="true"
          draggable={false}
          className="object-cover transition-transform duration-300 [-webkit-user-drag:none] group-enabled/product:group-hover/product:scale-105 motion-reduce:transition-none"
          sizes="102px"
          src={product.imageSrc}
          style={{ objectPosition: product.imagePosition ?? "center" }}
        />
        {product.rating !== undefined && (
          <span className="absolute bottom-1 left-1 inline-flex h-6 items-center gap-1 rounded-md bg-content1 px-1.5 text-[11px] font-extrabold text-foreground shadow-sm">
            {product.rating.toFixed(1)}
            <Star
              aria-hidden="true"
              className="fill-success text-success"
              size={12}
            />
          </span>
        )}
      </span>

      <span className="flex min-w-0 flex-1 flex-col py-0.5">
        {product.badge && (
          <span className="mb-1 w-fit rounded-full bg-primary-50 dark:bg-primary-500/20 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wide text-primary-700 dark:text-primary-300">
            {product.badge}
          </span>
        )}
        <span className="line-clamp-2 text-[12px] font-bold leading-[1.25] text-foreground">
          {product.name}
        </span>
        {(product.price || product.compareAtPrice) && (
          <span className="mt-1 flex items-baseline gap-1.5">
            {product.price && (
              <span className="text-[17px] font-black leading-none text-foreground">
                {product.price}
              </span>
            )}
            {product.compareAtPrice && (
              <span className="text-[10px] font-semibold text-default-500 line-through">
                {product.compareAtPrice}
              </span>
            )}
          </span>
        )}
        {product.ratingCount !== undefined && (
          <span className="mt-0.5 text-[9px] font-medium text-default-500">
            {t("reels.viewer.ratingCount", {
              count: product.ratingCount,
              defaultValue: "{{count}} ratings",
            })}
          </span>
        )}
        {storeLabel && (
          <span className="mt-0.5 flex min-w-0 items-center gap-1 text-[9px] font-semibold text-default-500">
            <MapPin aria-hidden="true" className="shrink-0" size={10} />
            <span className="truncate">{storeLabel}</span>
          </span>
        )}
        <span
          className={`mt-auto flex h-7 items-center justify-center rounded-lg px-3 text-[11px] font-extrabold ${
            isAvailable
              ? "bg-primary-500 text-white group-enabled/product:group-hover/product:bg-primary-600"
              : "bg-default-200 text-default-600"
          }`}
        >
          {actionLabel}
        </span>
      </span>
    </button>
  );
};

export default ReelProductCard;
