import {
  clearCart,
  setCartData,
  setCartLoading,
  setError,
} from "@/lib/redux/slices/cartSlice";
import { store } from "@/lib/redux/store";
import { getCart, syncOfflineCart } from "@/routes/api";
import { ApiResponse, CartResponse, CartSyncData } from "@/types/ApiResponse";
import { addToast } from "@heroui/react";
import i18n from "../../i18n";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { resetCheckOutState } from "./functionalHelpers";
import { clearOfflineCart } from "@/lib/redux/slices/offlineCartSlice";
import { removedItemsStore } from "@/stores/removedItemsStore";

/**
 * Whether a failed cart response means "the cart is empty" rather than a real
 * error.
 *
 * Matched loosely rather than against the exact string `"Your cart is empty"`:
 * that equality check silently stopped working whenever the backend reworded
 * the message, sending an empty cart down the generic-error path where the
 * stale cart was left in redux. Wording drifts; the two keywords don't.
 */
export const isEmptyCartMessage = (message?: string | null): boolean => {
  if (!message) return false;
  const normalized = message.toLowerCase();
  return normalized.includes("cart") && normalized.includes("empty");
};

export const updateCartData = async (
  passAddress: boolean = true,
  renderToast: boolean = true,
  customAddress: string | number = 0,
  customRushMode: boolean = true,
  emtyCartToast: boolean = true
): Promise<ApiResponse<CartResponse> | undefined> => {
  try {
    const isLoggedIn = store.getState().auth.isLoggedIn;
    const address_id = store
      .getState()
      .checkout.selectedAddress?.id?.toString();

    if (!isLoggedIn) {
      return;
    }

    const { lat = "", lng = "" } =
      (getCookie("userLocation") as UserLocation) || {};

    store.dispatch(setCartLoading(true));
    const state = store.getState();
    const use_wallet = state?.checkout?.useWallet || false;
    const use_loyalty_points = state?.checkout?.useLoyaltyPoints || false;
    const rush_delivery = customRushMode
      ? state?.checkout?.rushDelivery || false
      : false;
    const promo_code = state?.checkout?.promoCode || "";
    const cartRes: ApiResponse<CartResponse> = await getCart({
      address_id: customAddress ? customAddress : passAddress ? address_id : "",
      use_wallet,
      use_loyalty_points,
      rush_delivery,
      promo_code,
      latitude: lat,
      longitude: lng,
    });

    const rushCheck = customAddress || address_id;

    if (cartRes.success && cartRes.data) {
      const removed = cartRes.data.removed_items;
      const hasRemovals = (cartRes.data.removed_count ?? 0) > 0 && !!removed?.length;

      store.dispatch(setCartData(cartRes.data));

      if (hasRemovals) {
        /**
         * The removal response describes the cart as it was BEFORE the removal:
         * `removed_items` lists what went, but `items`, `items_count` and
         * `payment_summary` still include it. Rendering that verbatim left the
         * dropped product visible in the cart and the navbar badge, alongside
         * charges computed for a store that can't deliver here (the wild
         * "₹582,368 delivery / 52,639 mins" totals on an out-of-zone address).
         *
         * So: settle the cart FIRST, then announce. The notice used to be
         * raised before this re-read, which showed the customer a "removed"
         * dialog on top of a cart that still listed the item at nonsense
         * totals, both of which then changed underneath them.
         *
         * Calls `getCart` directly rather than recursing through
         * `updateCartData`, so it can't loop.
         */
        const freshRes = await getCart({
          address_id: customAddress
            ? customAddress
            : passAddress
              ? address_id
              : "",
          use_wallet,
          use_loyalty_points,
          rush_delivery,
          promo_code,
          latitude: lat,
          longitude: lng,
        });

        if (freshRes.success && freshRes.data) {
          store.dispatch(setCartData(freshRes.data));
        } else if (!freshRes.success && isEmptyCartMessage(freshRes.message)) {
          // Everything in the cart was undeliverable to this address.
          store.dispatch(clearCart());
          resetCheckOutState();
        }

        // Announced last, and held in its own store so it survives the
        // `clearCart()` above.
        removedItemsStore.show(removed);
      }
      if (cartRes?.data?.payment_summary?.is_rush_delivery_available) {
        document.getElementById("rush-delivery-available")?.click();
      }
      if (
        !cartRes?.data?.payment_summary?.is_rush_delivery_available &&
        rush_delivery &&
        rushCheck
      ) {
        if (customAddress) {
          document.getElementById("rush-delivery-off")?.click();
        }

        if (renderToast) {
          addToast({
            title: i18n.t("rushDeliveryUnavailable"),
            color: "danger",
          });
        }
      }
    } else if (!cartRes.success && isEmptyCartMessage(cartRes.message)) {
      store.dispatch(clearCart());
      resetCheckOutState();
      if (renderToast && emtyCartToast) {
        addToast({
          title: "Cart is Empty",
          description: "Add Product to processed !",
          color: "warning",
        });
      }
    } else {
      /**
       * The fetch failed for some other reason — most often a zone change that
       * emptied the cart server-side and came back with a message this client
       * doesn't recognise.
       *
       * `setError` alone used to be the whole branch, and it deliberately
       * leaves `cartData` in place. That stranded the last-known cart in redux,
       * so the navbar badge and the cart page kept showing items the server had
       * already dropped. Clearing here keeps the UI honest: the one thing we
       * know is that the current cart state is no longer trustworthy.
       */
      store.dispatch(clearCart());
      store.dispatch(setError("Failed to fetch updated cart"));
      if (renderToast) {
        addToast({
          title: "Cart Fetch Failed",
          description: "Could not update cart after adding item",
          color: "warning",
        });
      }
    }
    return cartRes;
  } catch (error) {
    console.error(error);
  } finally {
    store.dispatch(setCartLoading(false));
  }
};

export const syncOfflineCartToServer = async (): Promise<boolean> => {
  try {
    const offlineCartItems = store.getState().offlineCart.items;

    // If no offline cart items, skip sync
    if (!offlineCartItems || offlineCartItems.length === 0) {
      return true;
    }

    // Transform offline cart items to API format
    const items = offlineCartItems.map((item) => ({
      store_id: item.store_id,
      product_variant_id: item.product_variant_id,
      quantity: item.quantity,
      addons:
        item.addons?.map((addon) => ({
          addon_group_id: addon.addon_group_id,
          addon_item_id: addon.addon_item_id,
        })) || [],
    }));

    // Call sync API
    const response: ApiResponse<CartSyncData> = await syncOfflineCart({
      items,
    });

    if (response.success && response.data) {
      // Update cart with the synced cart data
      if (response.data.cart) {
        store.dispatch(setCartData(response.data.cart));
      }

      // Clear offline cart after successful sync
      store.dispatch(clearOfflineCart());

      // Show failed items modal if there are any failed items
      if (response.data.failed_items && response.data.failed_items.length > 0) {
        // Trigger the global modal function
        if (
          typeof window !== "undefined" &&
          (window as any).showFailedItemsModal
        ) {
          (window as any).showFailedItemsModal(response.data.failed_items);
        }
      }

      // Show success message after 3 seconds
      setTimeout(() => {
        addToast({
          title: i18n.t("cart.sync_success_title") || "Cart Synced",
          description:
            i18n.t("cart.sync_success_desc") ||
            "Your cart items have been synced successfully",
          color: "success",
        });
      }, 3000);

      return true;
    } else {
      console.error("Failed to sync offline cart:", response.message);

      // Show error message after 3 seconds
      setTimeout(() => {
        addToast({
          title: i18n.t("cart.sync_error_title") || "Sync Failed",
          description:
            response.message || "Failed to sync your cart. Please try again.",
          color: "warning",
        });
      }, 2000);

      return false;
    }
  } catch (error) {
    console.error("Error syncing offline cart:", error);
    addToast({
      title: i18n.t("cart.sync_error_title") || "Sync Error",
      description:
        i18n.t("cart.sync_error_desc") ||
        "An error occurred while syncing your cart",
      color: "danger",
    });
    return false;
  }
};

export const updateDataOnAuth = async () => {
  if (typeof window === "undefined") return;

  const pathButtonMap: Record<string, string[]> = {
    "/": ["home-products-refetch", "home-sections-refetch"],
    "/shopping-list": ["shopping-list-refetch"],
  };

  const normalizePath = (path: string) =>
    path !== "/" ? path.replace(/\/+$/, "") : "/";

  const currentPath = normalizePath(window.location.pathname);

  let buttonIds: string[] = [];

  if (currentPath.startsWith("/feature-sections/")) {
    // Handle dynamic slug
    buttonIds = ["refetch-section-products"];
  } else if (currentPath.startsWith("/products/")) {
    buttonIds = ["similar-products-refetch", "specific-product-refetch"];
  } else if (currentPath.startsWith("/brands/")) {
    buttonIds = ["refetch-brand-products"];
  } else if (currentPath.startsWith("/categories/")) {
    buttonIds = ["category-products-refetch"];
  } else if (currentPath.startsWith("/stores/")) {
    buttonIds = ["refetch-store-products"];
  } else {
    // Handle exact matches
    buttonIds = pathButtonMap[currentPath] || [];
  }

  buttonIds.forEach((id) => document.getElementById(id)?.click?.());
};
