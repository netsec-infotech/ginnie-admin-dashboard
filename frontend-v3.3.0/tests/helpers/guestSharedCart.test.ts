import { expect, it } from "vitest";
import { sharedItemToGuestItem } from "@/helpers/guestSharedCart";
import offlineCartReducer, { addOfflineCartItem } from "@/lib/redux/slices/offlineCartSlice";
import type { Product } from "@/types/ApiResponse";
import type { SharedCartPreviewItem } from "@/types/sharedCart";

const item: SharedCartPreviewItem = { product_variant_id: 10, store_id: 1, shared_quantity: 8, available_stock: 20, addons: [],
  is_available: true, is_deliverable: true, is_store_online: true, store_is_accepting_orders: true,
  store_name: "Shop", product_id: 1, product_name: "Rice", product_slug: "rice", product_image: "", variant_title: "1kg", effective_unit_price: 40, unavailable_reason: null };
const product = { title: "Rice", slug: "rice", minimum_order_quantity: 2, quantity_step_size: 2, total_allowed_quantity: 6,
  variants: [{ id: 10, store_id: 1, price: 50, special_price: 40, stock: 20, availability: true, addon_groups: [] }] } as unknown as Product;

it("uses current catalog prices and quantity rules, merging within the existing guest-cart limits", () => {
  const guest = sharedItemToGuestItem(item, product)!;
  expect(guest).toMatchObject({ id: "10", price: 40, minQuantity: 2, stepSize: 2, maxQuantity: 6 });
  const first = offlineCartReducer(undefined, addOfflineCartItem({ ...guest, quantity: 2 }));
  const merged = offlineCartReducer(first, addOfflineCartItem(guest));
  expect(merged.items).toHaveLength(1);
  expect(merged.totalQuantity).toBe(6);
  expect(merged.subtotal).toBe(240);
});

it.each([
  { is_available: false }, { is_deliverable: false }, { is_store_online: false }, { store_is_accepting_orders: false },
  { available_stock: 0 }, { product_variant_id: 999 }, { store_id: 999 }, { shared_quantity: -1 }, { shared_quantity: NaN },
])("skips unavailable or invalid shared items: %j", (change) => {
  expect(sharedItemToGuestItem({ ...item, ...change }, product)).toBeNull();
});

it("preserves selected addons and their guest-cart identity using live addon prices", () => {
  const catalog = { ...product, variants: [{ ...product.variants[0], addon_groups: [{ id: 3, is_required: true, selection_type: "multiple", items: [
    { id: 12, title: "Extra", price: 4, is_available: true }, { id: 2, title: "Bag", price: 1, is_available: true },
  ] }] }] } as Product;
  const selections = [{ addon_group_id: 3, addon_item_id: 12 }, { addon_group_id: 3, addon_item_id: 2 }];
  const guest = sharedItemToGuestItem({ ...item, addons: selections }, catalog)!;
  expect(guest.id).toBe("10-12-2");
  expect(guest.addons).toEqual([{ ...selections[0], title: "Extra", price: 4 }, { ...selections[1], title: "Bag", price: 1 }]);
  expect(sharedItemToGuestItem(item, catalog)).toBeNull();
  expect(sharedItemToGuestItem({ ...item, addons: [{ addon_group_id: 3, addon_item_id: 999 }] }, catalog)).toBeNull();
});
