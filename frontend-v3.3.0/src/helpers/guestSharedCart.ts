import { productPriceFields, productAmount } from "@/helpers/currency";
import type { Product } from "@/types/ApiResponse";
import type { SharedCartPreviewItem } from "@/types/sharedCart";
import type { OfflineCartItem } from "@/lib/redux/slices/offlineCartSlice";

// Resolve live catalog constraints instead of inventing limits from the shared snapshot.
export function sharedItemToGuestItem(item: SharedCartPreviewItem, product: Product): OfflineCartItem | null {
  const variant = product.variants.find((entry) => entry.id === item.product_variant_id && entry.store_id === item.store_id);
  if (!item.is_available || item.is_deliverable === false || !item.is_store_online || !item.store_is_accepting_orders ||
      !variant || variant.availability === false || Number(variant.stock) <= 0 ||
      !Number.isFinite(Number(item.shared_quantity)) || Number(item.shared_quantity) <= 0) return null;
  const minQuantity = Number(product.minimum_order_quantity) || 1;
  const stepSize = Number(product.quantity_step_size) || 1;
  const stock = Math.min(Number(variant.stock), Number(item.available_stock));
  const maxQuantity = Math.min(Number(product.total_allowed_quantity) || stock, stock);
  if (!Number.isFinite(maxQuantity) || maxQuantity < minQuantity) return null;
  const addons: NonNullable<OfflineCartItem["addons"]> = [];
  for (const selected of item.addons || []) {
    const group = variant.addon_groups?.find((entry) => entry.id === selected.addon_group_id);
    const addon = group?.items.find((entry) => entry.id === selected.addon_item_id);
    if (!addon || !addon.is_available || !Number.isFinite(Number(addon.price)) || Number(addon.price) < 0) return null;
    addons.push({ ...selected, title: addon.title, price: Number(addon.price) });
  }
  // Missing required groups or invalid single selections cannot be imported faithfully.
  if (variant.addon_groups?.some((group) => {
    const count = addons.filter((addon) => addon.addon_group_id === group.id).length;
    return (group.is_required && count === 0) || (group.selection_type === "single" && count > 1);
  })) return null;
  const price = productAmount(variant);
  if (!Number.isFinite(price) || price < 0) return null;
  const addonSuffix = addons.length ? `-${addons.map((addon) => addon.addon_item_id).sort().join("-")}` : "";
  return {
    id: `${variant.id}${addonSuffix}`, name: product.title, slug: product.slug,
    image: variant.image || product.main_image, ...productPriceFields(variant, product), quantity: Number(item.shared_quantity),
    storeName: variant.store_name || item.store_name, storeSlug: variant.store_slug,
    minQuantity, maxQuantity, stepSize, stock, product_variant_id: variant.id, store_id: variant.store_id, addons,
  };
}
