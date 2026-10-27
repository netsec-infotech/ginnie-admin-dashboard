import { Card, CardBody, CardFooter } from "@heroui/react";
import { ArrowRight, Package } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { productCardStyles } from "@/components/Cards/productCardStyles";
import { useSettings } from "@/contexts/SettingsContext";

export interface ProductCardPreviewData {
  title: string;
  unit: string;
  image: string;
  href: string;
  price?: number | null;
  specialPrice?: number | null;
  isProduct?: boolean;
}

// UI previews share catalog geometry without inventing purchasable product data.
export default function ProductCardPreview({
  preview,
}: {
  preview: ProductCardPreviewData;
}) {
  const { t } = useTranslation();
  const { currencySymbol } = useSettings();
  const [failedImage, setFailedImage] = useState<string | null>(null);
  const price =
    typeof preview.price === "number" && Number.isFinite(preview.price)
      ? preview.price
      : null;
  const specialPrice =
    typeof preview.specialPrice === "number" &&
    Number.isFinite(preview.specialPrice) &&
    price !== null &&
    preview.specialPrice < price
      ? preview.specialPrice
      : null;

  return (
    <Card
      as={Link}
      href={preview.href}
      aria-label={preview.title}
      className={`${productCardStyles.container} group min-w-0 outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2`}
      disableRipple
      shadow="none"
    >
      <CardBody className="p-0 px-0">
        <div
          className={`${productCardStyles.image} relative mb-2 overflow-hidden bg-default-50`}
        >
          {preview.image && failedImage !== preview.image ? (
            <Image
              src={preview.image}
              alt=""
              fill
              sizes="(max-width: 639px) 50vw, (max-width: 1023px) 33vw, (max-width: 1279px) 25vw, 240px"
              className="object-contain transition-transform duration-200 group-hover:scale-105 motion-reduce:transition-none"
              onError={() => setFailedImage(preview.image)}
            />
          ) : (
            <div className="absolute inset-0 flex items-center justify-center">
              <Package
                className="size-9 text-default-400"
                strokeWidth={1}
                aria-hidden="true"
              />
            </div>
          )}
        </div>
        <div className="w-full space-y-1 px-2">
          <h3 className="min-h-8 line-clamp-2 text-xs font-semibold">
            {preview.title}
          </h3>
          {preview.unit && (
            <p className="line-clamp-2 text-xxs text-foreground/50">
              {preview.unit}
            </p>
          )}
        </div>
      </CardBody>
      <CardFooter className={`${productCardStyles.footer} flex-wrap gap-2`}>
        {price !== null && (
          <div className="flex shrink-0 flex-col">
            <span className="text-xs font-semibold md:text-sm">
              {currencySymbol}
              {(specialPrice ?? price).toFixed(2)}
            </span>
            {specialPrice !== null && (
              <span className="text-xxs text-foreground/50 line-through">
                {currencySymbol}
                {price.toFixed(2)}
              </span>
            )}
          </div>
        )}
        <span
          className={`inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-primary/10 px-2 text-xs font-medium text-primary transition-colors group-hover:bg-primary/20 ${price === null ? "w-full" : ""}`}
        >
          {t(preview.isProduct ? "blog.viewProduct" : "blog.exploreProducts")}
          <ArrowRight size={14} className="rtl:rotate-180" aria-hidden="true" />
        </span>
      </CardFooter>
    </Card>
  );
}
