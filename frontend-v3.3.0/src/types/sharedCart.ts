import type { CartResponse } from "@/types/ApiResponse";

export interface SharedCartData {
  share_code: string;
  share_url: string;
  title: string;
  items_count: number;
  expires_at: string;
}

export interface SharedCartPreviewItem {
  store_id: number;
  store_name: string;
  is_store_online: boolean;
  store_is_accepting_orders: boolean;
  is_deliverable: boolean | null;
  product_id: number;
  product_name: string;
  product_slug: string;
  product_image: string;
  product_variant_id: number;
  variant_title: string;
  shared_quantity: number;
  effective_unit_price: number;
  available_stock: number;
  addons: { addon_group_id: number; addon_item_id: number }[];
  is_available: boolean;
  unavailable_reason: string | null;
}

export interface SharedCartPreview extends Omit<SharedCartData, "items_count"> {
  is_expired: boolean;
  items: SharedCartPreviewItem[];
  summary: { total_items: number; available_items: number; unavailable_items: number; estimated_items_total: number };
}

// Import returns contents, without the location-dependent checkout totals.
export type ImportedCart = Omit<CartResponse, "payment_summary" | "delivery_zone" | "removed_count"> & {
  delivery_zone: CartResponse["delivery_zone"] | null;
  removed_count: number | null;
};

export interface ImportedCartItem {
  store_id: number;
  product_id?: number;
  product_name: string;
  product_variant_id: number;
  quantity: number;
  addons?: { addon_group_id: number; addon_item_id: number }[];
  reason?: string;
}

export interface CartImportSummary {
  imported_count: number;
  updated_count: number;
  unchanged_count: number;
  skipped_count: number;
  imported_items: ImportedCartItem[];
  updated_items: ImportedCartItem[];
  unchanged_items: ImportedCartItem[];
  skipped_items: ImportedCartItem[];
}

export type CartImportAction = "merge" | "replace";

export interface ImportSharedCartData {
  action: CartImportAction;
  cart: ImportedCart;
  import_summary: CartImportSummary;
}
