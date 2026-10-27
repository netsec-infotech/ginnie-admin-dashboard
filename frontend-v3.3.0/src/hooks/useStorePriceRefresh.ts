import { useEffect, useRef } from "react";
import { useSWRConfig } from "swr";
import { useSettings } from "@/contexts/SettingsContext";
import {
  getConfiguredDefaultLocation,
  getWebSettings,
} from "@/helpers/getters";
import { getCookie } from "@/lib/cookies";
import { getProductBySlug } from "@/routes/api";
import type { Product, ProductVariant } from "@/types/ApiResponse";

/** Refresh a different store's quote without reusing another store's cache. */
export const useStorePriceRefresh = (
  product: Product,
  variant: ProductVariant | null,
  onRefresh: (variant: ProductVariant) => void,
): void => {
  const previous = useRef<{ slug: string; storeId: number } | null>(null);
  const { mutate } = useSWRConfig();
  const { settings } = useSettings();

  useEffect(() => {
    if (!variant) return;
    const last = previous.current;
    previous.current = { slug: product.slug, storeId: variant.store_id };
    if (
      !last ||
      last.slug !== product.slug ||
      last.storeId === variant.store_id
    )
      return;

    let active = true;
    const location =
      getCookie<{ lat: number; lng: number }>("userLocation") ||
      getConfiguredDefaultLocation(getWebSettings(settings));
    void mutate(
      [`/products/${product.slug}`, variant.store_id],
      async () => {
        const response = await getProductBySlug({
          slug: product.slug,
          store_id: variant.store_id,
          latitude: location?.lat ?? "",
          longitude: location?.lng ?? "",
        });
        if (!response.success || !response.data || Array.isArray(response.data))
          throw new Error("Store quote unavailable");
        return response.data;
      },
      { revalidate: false },
    )
      .then((fresh: Product | undefined) => {
        const quote = fresh?.variants.find(
          (entry) =>
            entry.id === variant.id && entry.store_id === variant.store_id,
        );
        if (active && quote) onRefresh(quote);
      })
      .catch(() => undefined);
    return () => {
      active = false;
    };
  }, [product, variant, onRefresh, settings, mutate]);
};
