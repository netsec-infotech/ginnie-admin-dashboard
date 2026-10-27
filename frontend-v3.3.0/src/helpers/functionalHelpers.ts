import { productPriceFields, canCheckoutCurrency, canUseCheckoutWallet, getWalletContext, isCheckoutPaymentMethodAvailable, paymentUnavailableMessage } from "@/helpers/currency";
import { setUserDataRedux } from "@/lib/redux/slices/authSlice";
import {
  acquirePaymentAttempt,
  completePaymentAttempt,
  ownsPaymentAttempt,
  paymentAttemptScope,
  preservePaymentAttemptForRedirect,
  releasePaymentAttempt,
  rememberPaymentReturn,
  type PaymentAttemptLease,
} from "@/helpers/paymentReturn";
import { showPaymentInProgressToast } from "@/components/PaymentGateway/PaymentInProgressToast";
import { notifyCheckoutCompletion } from "@/helpers/checkoutCompletion";
import { store } from "@/lib/redux/store";
import {
  addToCart,
  checkDeliveryZone,
  createOrder,
  getUserData,
  updateCartItem,
  xenditCreateOrder,
  getProductBySlug,
} from "@/routes/api";
import {
  ApiResponse,
  CartResponse,
  PaymentSettings,
  Product,
  ProductVariant,
  Settings,
  userData,
} from "@/types/ApiResponse";
import { getWebSettings } from "@/helpers/getters";
import { validateAddressDelivery } from "@/services/addressDeliveryService";
import { staticLat, staticLng } from "@/config/constants";
import { addToast } from "@heroui/react";
import axios, { AxiosError } from "axios";
import { GetServerSidePropsContext } from "next";
// Import the new actions
import {
  clearCart,
  setCartData,
  setCartLoading,
  setError,
} from "@/lib/redux/slices/cartSlice";
import { fallbackApiRes } from "@/config/constants";
import i18n from "../../i18n";
import { isValidUrl } from "./validator";
import {
  clearScheduledDelivery,
  setOrderNote,
  setPromoCode,
  setRusDelivery,
  setSelectedAddress,
  setUseWallet,
  setUseLoyaltyPoints,
} from "@/lib/redux/slices/checkoutSlice";
import { updateCartData } from "./updators";
import {
  addOfflineCartItem,
  updateOfflineCartItem,
} from "@/lib/redux/slices/offlineCartSlice";
import { trackPurchase } from "@/lib/analytics";

export const makeTabClick = (dataKey: string): void => {
  // Select the button using the data-key attribute
  const button = document.querySelector<HTMLButtonElement>(
    `button[data-key="${dataKey}"]`,
  );

  // Check if the button exists
  if (!button) {
    console.error(`Button with data-key="${dataKey}" not found.`);
    return;
  }

  // Trigger a click event on the button
  button.click();

  // Scroll the button into view
  button.scrollIntoView({
    behavior: "smooth",
    block: "start",
    inline: "start",
  });
};

export const updateUserDataInRedux = async () => {
  try {
    const res: ApiResponse<userData> = await getUserData();

    if (res.success) {
      store.dispatch(setUserDataRedux(res.data || {}));
    } else {
      console.error(
        "Failed to update user data in Redux:",
        res.message || "Unknown error",
      );
    }
  } catch (error) {
    // Handle network or unexpected errors
    console.error("An error occurred while updating user data:", error);
  }
};

export const handleCheckZone = async (
  latitude: string | number,
  longitude: string | number,
): Promise<boolean> => {
  try {
    const res = await checkDeliveryZone({ latitude, longitude });

    if (res.success && res.data?.is_deliverable) {
      return true;
    }

    return false;
  } catch (error) {
    console.error("Error checking delivery zone:", error);
    return false;
  }
};

export const isAxiosError = (
  error: unknown,
): error is AxiosError<{ message?: string }> => {
  return axios.isAxiosError(error);
};

export const getUserLocationFromContext = async (
  context: GetServerSidePropsContext,
): Promise<{ lat: string; lng: string } | null> => {
  try {
    const raw = context.req.cookies.userLocation;
    if (raw) {
      let parsedLocation: { lat: string; lng: string } | null = null;
      try {
        parsedLocation = JSON.parse(decodeURIComponent(raw));
      } catch {
        parsedLocation = JSON.parse(raw);
      }
      return parsedLocation;
    }
    return null;
  } catch (error) {
    console.error("Error getting user Location from context:", error);
    return null;
  }
};

/**
 * Location to use for SSR when the request carries no `userLocation` cookie.
 *
 * **Crawlers don't send cookies.** The catalog API is delivery-zone scoped and
 * rejects a request with blank coordinates (`"Error fetching product"`), so
 * without a fallback every bot-rendered product page came back empty — no title,
 * no price, and `/products/undefined` as its canonical.
 *
 * Prefers the backend's own `web.defaultLatitude/defaultLongitude`, which is
 * deliberate rather than arbitrary: `scripts/generate-sitemap.mjs` resolves the
 * product URLs it publishes at exactly those coordinates, so anything IN the
 * sitemap is guaranteed resolvable here. A hardcoded guess would silently miss
 * products in other zones — verified: `staticLat` fails to find products that
 * the backend default finds.
 *
 * Returns null when neither source is usable, so callers can tell "no location"
 * apart from a real one and skip the fetch rather than send garbage.
 */
export const getFallbackLocation = (
  settings?: Settings | null,
): { lat: string; lng: string } | null => {
  const webSettings = getWebSettings(settings);
  const lat = webSettings?.defaultLatitude;
  const lng = webSettings?.defaultLongitude;

  // Guard against ""/"0"/"abc" — a non-finite coord is worse than none, since
  // the API treats it as a validation error rather than a miss.
  if (lat && lng && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))) {
    return { lat: String(lat), lng: String(lng) };
  }

  return staticLat && staticLng
    ? { lat: String(staticLat), lng: String(staticLng) }
    : null;
};

export const handleUpdateCartItem = async (params: {
  cartItemId: string | number;
  quantity: string | number;
  addons: { addon_group_id: number; addon_item_id: number }[];
  onClose: () => void;
  renderToast: boolean;
}) => {
  const { onClose = () => {}, renderToast = true } = params;

  try {
    store.dispatch(setCartLoading(true));

    const updateRes = await updateCartItem({
      cartItemId: params.cartItemId,
      quantity: params.quantity,
      addons: params.addons,
    });

    if (updateRes.success) {
      onClose();
      if (renderToast) {
        addToast({
          title: i18n.t("cart_updated_title"),
          color: "success",
        });
      }

      let passAddress = false;
      if (
        window.location.pathname === "/cart" ||
        window.location.pathname === "/cart/"
      ) {
        passAddress = true;
      }

      const cartRes = await updateCartData(passAddress, true, 0, false, true, true);
      if (cartRes?.success && cartRes.data) {
        store.dispatch(setCartData(cartRes.data));
      }
    } else {
      if (renderToast) {
        addToast({
          title: i18n.t("update_failed_title"),
          description: updateRes.message || i18n.t("update_failed_description"),
          color: "danger",
        });
      }
    }
    return updateRes;
  } catch (error) {
    console.error("handleUpdateCartItem error", error);
    if (renderToast) {
      addToast({
        title: i18n.t("cart.error_title"),
        description: i18n.t("cart.error_description"),
        color: "danger",
      });
    }
    return { success: false, message: "Error updating cart item", data: null };
  } finally {
    store.dispatch(setCartLoading(false));
  }
};

export const handleAddToCart = async (params: {
  product_variant_id: string | number;
  store_id: string | number;
  quantity: string | number;
  onClose: () => void;
  renderToast: boolean;
  replace_quantity?: boolean;
  addons?: { addon_group_id: number; addon_item_id: number }[];
  is_notify?: number | boolean;
}) => {
  const { onClose = () => {}, renderToast = true, is_notify } = params;

  try {
    const isLoggedIn = store.getState().auth.isLoggedIn;

    if (!isLoggedIn) {
      onClose();
      const btn = document.getElementById("login-btn");
      btn?.click();
      addToast({
        title: i18n.t("cart.login_required"),
        color: "warning",
      });

      return {
        success: false,
        message: `${i18n.t("cart.login_required")}`,
        data: null,
      };
    }

    store.dispatch(setCartLoading(true));

    const addRes: ApiResponse<CartResponse> = await addToCart(params);

    if (addRes.success) {
      onClose();
      if (renderToast) {
        if (is_notify === 1 || is_notify === true) {
          addToast({
            title: i18n.t("notification_set_success", {
              defaultValue: "Notification set! We'll notify you when this item is back in stock.",
            }),
            color: "success",
          });
        } else if (is_notify === 0 || is_notify === false) {
          addToast({
            title: i18n.t("notification_cancelled", {
              defaultValue: "Notification reminder updated.",
            }),
            color: "default",
          });
        } else {
          addToast({
            title: i18n.t("cart.add_success"),
            color: "success",
          });
        }
      }
      let passAddress = false;

      if (
        window.location.pathname === "/cart" ||
        window.location.pathname === "/cart/"
      ) {
        passAddress = true;
      }

      const cartRes = await updateCartData(passAddress, true, 0, false, true, true);

      if (cartRes?.success && cartRes.data) {
        store.dispatch(setCartData(cartRes.data));
        if (window.location.pathname.startsWith("/cart")) {
          document.getElementById("refetch-similar-products")?.click();
        }
      } else if (cartRes) {
        store.dispatch(setError(i18n.t("cart.fetch_failed")));

        if (renderToast) {
          addToast({
            title: i18n.t("cart.fetch_failed_title"),
            description: i18n.t("cart.fetch_failed_description"),
            color: "warning",
          });
        }
      }
    } else {
      store.dispatch(setError(addRes.message || i18n.t("cart.add_failed")));
      if (renderToast) {
        addToast({
          title: i18n.t("cart.add_failed_title"),
          description: addRes.message || i18n.t("cart.add_failed_description"),
          color: "danger",
        });
      }
    }

    return addRes;
  } catch (error) {
    console.error("handleAddToCart error", error);
    store.dispatch(setError(i18n.t("cart.error_message")));
    if (renderToast) {
      addToast({
        title: i18n.t("cart.error_title"),
        description: i18n.t("cart.error_description"),
        color: "danger",
      });
    }

    return {
      success: false,
      message: i18n.t("cart.error_description"),
      data: {
        id: null,
        uuid: null,
        user_id: null,
        items_count: 0,
        total_quantity: 0,
        items: [],
        payment_summary: {
          items_total: null,
          per_store_drop_off_fee: null,
          is_rush_delivery: false,
          is_rush_delivery_available: false,
          delivery_charges: 0,
          handling_charges: 0,
          delivery_distance_charges: 0,
          delivery_distance_km: 0,
          total_stores: 0,
          total_delivery_charges: 0,
          estimated_delivery_time: 0,
          use_wallet: false,
          wallet_balance: 0,
          wallet_amount_used: 0,
          payable_amount: 0,

          promo_code: "",
          promo_discount: 0,
          promo_applied: [],
          promo_error: null,
        },
        created_at: "",
        updated_at: "",
      },
    };
  } finally {
    store.dispatch(setCartLoading(false));
  }
};

export const handleOfflineAddToCart = (params: {
  product: Product;
  variant: ProductVariant;
  quantity: number;
  onClose?: () => void;
  renderToast?: boolean;
  addons?: {
    addon_group_id: number;
    addon_item_id: number;
    title: string;
    price: number;
  }[];

}) => {
  const {
    product,
    variant,
    quantity,
    onClose,
    renderToast = true,
    addons = [],
  } = params;
  const minQuantity = product.minimum_order_quantity || 1;
  const stepSize = product.quantity_step_size || 1;
  const maxAllowed = product.total_allowed_quantity || 1;
  const stock = variant.stock || 0;
  const maxQuantity = Math.min(maxAllowed, stock);
  const addonSuffix =
    addons.length > 0
      ? `-${addons
          .map((a) => a.addon_item_id)
          .sort()
          .join("-")}`
      : "";
  const itemId = `${variant.id}${addonSuffix}`;

  store.dispatch(
    addOfflineCartItem({
      id: itemId,
      name: product.title || variant.title,
      slug: product.slug,
      image: variant.image || product.main_image,
      ...productPriceFields(variant, product),
      quantity: quantity || minQuantity,
      storeName: variant.store_name,
      storeSlug: variant.store_slug,
      minQuantity: minQuantity,
      maxQuantity: maxQuantity,
      stepSize: stepSize || 1,
      stock: stock,
      product_variant_id: variant.id,
      store_id: variant.store_id,
      addons: addons,
    }),
  );

  if (renderToast) {
    addToast({
      title: i18n.t("cart.add_success"),
      description: i18n.t("cart.login_required"),
      color: "primary",
    });
  }

  onClose?.();

  return {
    success: true,
    data: null,
    message: "offline_cart_updated",
  };
};

export const handleUpdateOfflineCartItem = (params: {
  product: Product;
  variant: ProductVariant;
  quantity: number;
  oldId: string;
  onClose?: () => void;
  renderToast?: boolean;
  addons?: {
    addon_group_id: number;
    addon_item_id: number;
    title: string;
    price: number;
  }[];
}) => {
  const {
    product,
    variant,
    quantity,
    oldId,
    onClose,
    renderToast = true,
    addons = [],
  } = params;
  const minQuantity = product.minimum_order_quantity || 1;
  const stepSize = product.quantity_step_size || 1;
  const maxAllowed = product.total_allowed_quantity || 1;
  const stock = variant.stock || 0;
  const maxQuantity = Math.min(maxAllowed, stock);

  const addonSuffix =
    addons.length > 0
      ? `-${addons
          .map((a) => a.addon_item_id)
          .sort()
          .join("-")}`
      : "";
  const nextId = `${variant.id}${addonSuffix}`;

  store.dispatch(
    updateOfflineCartItem({
      id: oldId,
      newItem: {
        id: nextId,
        name: product.title || variant.title,
        slug: product.slug,
        image: variant.image || product.main_image,
        ...productPriceFields(variant, product),
        quantity: quantity || minQuantity,
        storeName: variant.store_name,
        storeSlug: variant.store_slug,
        minQuantity: minQuantity,
        maxQuantity: maxQuantity,
        stepSize: stepSize || 1,
        stock: stock,
        product_variant_id: variant.id,
        store_id: variant.store_id,
        addons: addons,
      },
    }),
  );

  if (renderToast) {
    addToast({
      title: i18n.t("cart_updated_title"),
      color: "primary",
    });
  }

  onClose?.();

  return {
    success: true,
    data: null,
    message: "offline_cart_updated",
  };
};

export const resetCheckOutState = () => {
  store.dispatch(setPromoCode(""));
  store.dispatch(setRusDelivery(false));
  store.dispatch(setUseWallet(false));
  store.dispatch(setUseLoyaltyPoints(false));
  store.dispatch(setOrderNote(""));
  store.dispatch(setSelectedAddress(null));
  // Checkout state is persisted to localStorage, so without this the next
  // order silently inherits the slot booked for the last one.
  store.dispatch(clearScheduledDelivery());
  // Clear attachments from window
  if (typeof window !== "undefined") {
    (window as any).__cartAttachments = {};
  }
};

/**
 * Resolve the delivery fields for an order from checkout state.
 *
 * Extracted and pure so the rules are testable without a redux store or a
 * network call — both of them are easy to get wrong and expensive to get wrong.
 */
export const resolveDeliveryParams = (checkout: {
  rushDelivery?: boolean;
  scheduledDelivery?: {
    slotDbId?: number;
    date?: string;
    slotStartTime?: string;
    slotEndTime?: string;
  } | null;
}): {
  rush_delivery: boolean;
  delivery_time_slot_id: number | null;
  /**
   * The cart contract's slot identity. Both null on the store contract, where
   * `delivery_time_slot_id` identifies the window instead — the two are
   * mutually exclusive, never both populated.
   */
  scheduled_slot_start_time: string | null;
  scheduled_slot_end_time: string | null;
  /**
   * Required by the backend on every order. Accepted values are
   * "delivery" | "pickup" | "scheduled".
   *
   * Always "delivery" from this storefront. A booked window does NOT make it
   * "scheduled" — confirmed against a live order, which came back with
   * `is_scheduled_delivery: true` and `delivery_type: "delivery"`; the two are
   * independent. Rush is likewise carried by `rush_delivery`, not the type.
   * "pickup" is never produced here: there is no self-collection flow and
   * checkout always requires a delivery address. Both stay in the union so
   * adding either is a change to this function rather than to every caller.
   */
  delivery_type: "delivery" | "pickup" | "scheduled";
  /** Local YYYY-MM-DD. Required by the backend alongside a booked slot. */
  scheduled_date: string | null;
} => {
  /**
   * `slotDbId` is optional because checkout state is persisted to
   * localStorage: a session saved before the real slots endpoint existed holds
   * a generated `slotId` like "2026-07-19-08" and no numeric id. Sending that
   * would fail the backend's integer validation, so an old schedule is simply
   * not submitted — the customer re-picks.
   */
  const slotDbId = checkout?.scheduledDelivery?.slotDbId;
  // `> 0`, not just finite: backend ids are 1-based, so 0 is a bug rather than
  // a booking. Guarding here also keeps the rush suppression below consistent —
  // a falsy-but-present id must not count as "scheduled" in one line and "not
  // scheduled" in the next.
  const delivery_time_slot_id =
    typeof slotDbId === "number" && Number.isFinite(slotDbId) && slotDbId > 0
      ? slotDbId
      : null;

  /**
   * The backend rejects a slot that arrives without a date
   * ("Scheduled date is required when selecting a delivery time slot"), so the
   * two travel together or not at all: a slot whose persisted state has no
   * usable date is dropped and the customer re-picks, exactly as for a missing
   * `slotDbId` above. Format is validated rather than trusted because this
   * value also comes out of localStorage.
   */
  const rawDate = checkout?.scheduledDelivery?.date;
  const hasValidDate =
    typeof rawDate === "string" && /^\d{4}-\d{2}-\d{2}$/.test(rawDate);

  /**
   * The cart contract identifies a window by its start/end times instead of an
   * id, so a booking made that way has no `slotDbId` at all. Both are validated
   * as "HH:MM:SS" rather than trusted — like everything else here, they come
   * back out of localStorage and a malformed pair would fail backend validation
   * after the customer has already pressed pay.
   */
  const isTime = (v: unknown): v is string =>
    typeof v === "string" && /^\d{2}:\d{2}(:\d{2})?$/.test(v);

  const startTime = checkout?.scheduledDelivery?.slotStartTime;
  const endTime = checkout?.scheduledDelivery?.slotEndTime;
  // Both or neither: a half-identified window is worse than none, since the
  // backend would either reject it or book something the customer didn't pick.
  const hasSlotTimes = isTime(startTime) && isTime(endTime);

  // Scheduled under EITHER contract — an id, or a time pair. The date is
  // required by both.
  const isScheduled =
    (delivery_time_slot_id !== null || hasSlotTimes) && hasValidDate;

  // Rush ("as soon as possible") and a booked window contradict each other.
  // The reducers keep them exclusive, but this is the last gate before the
  // order exists — a stale rush flag must never ride along with a slot.
  return {
    delivery_time_slot_id: isScheduled ? delivery_time_slot_id : null,
    scheduled_slot_start_time:
      isScheduled && hasSlotTimes ? (startTime as string) : null,
    scheduled_slot_end_time:
      isScheduled && hasSlotTimes ? (endTime as string) : null,
    scheduled_date: isScheduled ? (rawDate as string) : null,
    // Always "delivery": the backend tracks a booked window separately via
    // `is_scheduled_delivery`, and returns `delivery_type: "delivery"` even on
    // a scheduled order. Only a self-collection flow would send "pickup".
    delivery_type: "delivery",
    rush_delivery: isScheduled ? false : (checkout?.rushDelivery ?? false),
  };
};

/** Recheck persisted addresses against the cart's zone before starting payment. */
export const validateCheckoutAddress = async (): Promise<boolean> => {
  const state = store.getState();
  if (state.cart.deliveryLocationNeedsSelection) {
    addToast({ title: i18n.t("address.deliveryNeedsSelection"), color: "warning" });
    return false;
  }
  const address = state.checkout.selectedAddress;
  if (!address) {
    addToast({ title: i18n.t("checkout.no_address"), color: "warning" });
    return false;
  }
  const zoneId = state.cart.cartData?.delivery_zone?.zone_id;
  const result = await validateAddressDelivery(address, zoneId);
  const latest = store.getState();
  if (latest.checkout.selectedAddress !== address || latest.cart.cartData?.delivery_zone?.zone_id !== zoneId) {
    addToast({ title: i18n.t("address.zoneCheckFailed"), color: "warning" });
    return false;
  }
  if (!result.success) {
    addToast({ title: i18n.t(result.messageKey), color: "warning" });
    return false;
  }
  return true;
};

export const handleCheckout = async (
  payment_type: string = "cod",
  extra_params: object,
  options: { paymentAttemptLease?: PaymentAttemptLease; paymentSettings?: Partial<PaymentSettings> | null } = {},
) => {
  let managedLease: PaymentAttemptLease | null = null;
  let preserveManagedLease = false;
  try {
    if (!(await validateCheckoutAddress())) return fallbackApiRes;
    const state = store.getState();
    const cart = state.cart.cartData;
    const currencyError = state.cart.isLoading || state.cart.error || state.cart.deliveryLocationNeedsSelection
      ? i18n.t("payments.cartRefreshRequired")
      : !canCheckoutCurrency(cart)
      ? i18n.t("multiCurrency.mixedCart")
      : !isCheckoutPaymentMethodAvailable(cart, payment_type, options.paymentSettings)
        ? paymentUnavailableMessage(cart, payment_type) || i18n.t("multiCurrency.paymentUnavailable")
        : (state.checkout.useWallet || payment_type === "wallet") && !canUseCheckoutWallet(cart, getWalletContext(state.auth.user), options.paymentSettings)
          ? i18n.t("multiCurrency.walletMismatch")
          : undefined;
    if (currencyError) {
      addToast({ title: currencyError, color: "warning" });
      return { ...fallbackApiRes, message: currencyError };
    }
    const address_id = state?.checkout?.selectedAddress?.id || "";
    const order_note = state?.checkout?.orderNote || "";
    const use_wallet = state?.checkout?.useWallet || false;
    const use_loyalty_points = state?.checkout?.useLoyaltyPoints || false;
    const promo_code = state?.checkout?.promoCode || "";
    const payable_amount = state?.cart?.cartData?.payment_summary?.payable_amount;
    const siteBaseUrl = (
      process.env.NEXT_PUBLIC_SITE_URL || window.location.origin
    )
      .trim()
      .replace(/\/+$/, "");
    const ordersRedirectUrl = `${siteBaseUrl}/my-account/orders`;

    const {
      rush_delivery,
      delivery_time_slot_id,
      delivery_type,
      scheduled_date,
      scheduled_slot_start_time,
      scheduled_slot_end_time,
    } = resolveDeliveryParams(state?.checkout ?? {});

    if (!address_id) {
      console.error(i18n.t("checkout.no_address"));
      return fallbackApiRes;
    }

    // Get attachments from window
    const attachments = (window as any).__cartAttachments || {};

    // Always use FormData
    const formData = new FormData();
    formData.append("payment_type", payment_type);
    // Transaction-first Xendit creates its payment intent inside this request,
    // before an order exists. Send the server-calculated cart amount in the
    // same major currency unit used by the legacy Xendit create-order call.
    if (payment_type === "xenditPayment") {
      const amount = Number(payable_amount);
      if (!Number.isFinite(amount) || amount <= 0) {
        addToast({
          title: i18n.t("checkout.error_title"),
          color: "danger",
        });
        return {
          ...fallbackApiRes,
          message: "Invalid Xendit payment amount",
        };
      }
      formData.append("amount", String(payable_amount));
    }
    formData.append("promo_code", promo_code);
    formData.append("gift_card", "");
    formData.append("gift_card_discount", "");
    formData.append("rush_delivery", rush_delivery ? "1" : "0");
    formData.append("use_wallet", use_wallet ? "1" : "0");
    formData.append("use_loyalty_points", use_loyalty_points ? "1" : "0");
    formData.append("address_id", address_id.toString());
    formData.append("order_note", order_note);
    // Required on every order, standard or scheduled.
    formData.append("delivery_type", delivery_type);
    // Omitted entirely when absent — an empty string would reach the backend as
    // "" rather than null and fail its integer validation. `scheduled_date` is
    // resolved alongside the slot id, so the pair is always sent together.
    if (delivery_time_slot_id) {
      formData.append(
        "delivery_time_slot_id",
        delivery_time_slot_id.toString(),
      );
    }
    if (scheduled_date) {
      formData.append("scheduled_date", scheduled_date);
    }
    // The cart contract's alternative to `delivery_time_slot_id`. Resolved as a
    // pair, so testing one is enough — but both are appended together.
    if (scheduled_slot_start_time && scheduled_slot_end_time) {
      formData.append("scheduled_slot_start_time", scheduled_slot_start_time);
      formData.append("scheduled_slot_end_time", scheduled_slot_end_time);
    }
    formData.append("redirect_url", ordersRedirectUrl);

    // Add extra params
    Object.entries(extra_params).forEach(([key, value]) => {
      formData.append(key, value as string);
    });

    // Add attachments if any
    Object.entries(attachments).forEach(
      ([productId, attachment]: [string, any]) => {
        if (Array.isArray(attachment)) {
          attachment.forEach((fileItem) => {
            if (fileItem && fileItem.file) {
              formData.append(`attachments[${productId}][]`, fileItem.file);
            }
          });
        } else if (attachment && attachment.file) {
          formData.append(`attachments[${productId}][]`, attachment.file);
        }
      },
    );

    const attemptScope = paymentAttemptScope({
      usageType: "order",
      userId: state.auth.user?.id,
      cartId: state.cart.cartData?.uuid || state.cart.cartData?.id,
    });
    const suppliedLease = options.paymentAttemptLease;
    const activeLease = suppliedLease
      ? ownsPaymentAttempt(suppliedLease)
        ? suppliedLease
        : null
      : await acquirePaymentAttempt(attemptScope);

    if (!activeLease) {
      showPaymentInProgressToast(attemptScope, i18n.t);
      return fallbackApiRes;
    }
    if (!suppliedLease) managedLease = activeLease;

    const latest = store.getState();
    if (latest.cart.cartData !== cart || latest.cart.isLoading || latest.cart.error || latest.cart.deliveryLocationNeedsSelection) {
      const message = i18n.t("payments.cartRefreshRequired");
      addToast({ title: message, color: "warning" });
      return { ...fallbackApiRes, message };
    }

    const response = await createOrder(formData);

    if (response.success) {
      const { data } = response;
      if (!data) {
        return { ...response, success: false };
      }

      const isPaymentIntent = "transaction_uuid" in data;
      const transactionUuid = isPaymentIntent ? data.transaction_uuid : "";
      const order = isPaymentIntent ? data.order : data;
      const isHostedPayment =
        payment_type === "mercadoPagoPayment" ||
        payment_type === "flutterwavePayment" ||
        payment_type === "xenditPayment";

      if (order && !isHostedPayment) {
        trackPurchase(
          order.id?.toString?.() || "",
          order.final_total || 0,
          order.currency_code || "",
          order.promo_code || "",
          order.delivery_charge,
          order.items || [],
        );
      }

      let paymentLink =
        data.payment_response?.link ||
        data.payment_response?.payment_url ||
        data.payment_response?.invoice_url ||
        data.payment_response?.checkout_url ||
        data.payment_response?.url ||
        (isPaymentIntent
          ? data.link ||
            data.payment_url ||
            data.invoice_url ||
            data.checkout_url ||
            data.url
          : "") ||
        "";

      if (isHostedPayment) {
        // Older backends create the Xendit invoice after creating the order.
        // The transaction-first contract has no order id and supplies the
        // hosted link directly, so the legacy request must not run for it.
        if (
          payment_type === "xenditPayment" &&
          !transactionUuid &&
          order &&
          !isValidUrl(paymentLink)
        ) {
          // Xendit's hosted checkout only prefills name/email/mobile when they
          // are baked into the invoice at creation, so send the logged-in
          // user's details for the backend to forward as the `customer`.
          const user = store.getState().auth.user;
          const [givenNames, ...surnameParts] = (user?.name || "")
            .trim()
            .split(/\s+/);
          const xenditRes = await xenditCreateOrder({
            amount: order.final_total || 0,
            order_id: order.id || "",
            redirect_url: ordersRedirectUrl,
            customer: {
              given_names: givenNames || undefined,
              surname: surnameParts.join(" ") || undefined,
              email: user?.email || undefined,
              mobile_number: user?.mobile || undefined,
            },
          });
          const xenditData = xenditRes.data;
          paymentLink = xenditRes.success
            ? xenditData?.payment_response?.link ||
              xenditData?.payment_response?.invoice_url ||
              xenditData?.payment_response?.checkout_url ||
              xenditData?.payment_response?.url ||
              xenditData?.invoice_url ||
              xenditData?.checkout_url ||
              xenditData?.link ||
              xenditData?.url ||
              ""
            : "";
        }

        const paymentUrl = isValidUrl(paymentLink)
          ? new URL(paymentLink)
          : null;
        if (
          paymentUrl?.protocol === "https:" &&
          !paymentUrl.username &&
          !paymentUrl.password
        ) {
          if (transactionUuid) {
            rememberPaymentReturn(transactionUuid, state.auth.user?.id);
          } else if (order) {
            rememberPaymentReturn(order.id, order.slug, state.auth.user?.id);
          }
          resetCheckOutState();
          // The provider now owns navigation. Keep this cart locked while the
          // customer completes the hosted checkout; a refresh in this tab can
          // reclaim the same collecting lease.
          preserveManagedLease = true;
          if (managedLease) preservePaymentAttemptForRedirect(managedLease);
          window.location.href = paymentLink;

          return { success: true, data: null, message: "Redirected" };
        } else {
          const gateway =
            payment_type === "mercadoPagoPayment"
              ? "mercadoPago"
              : payment_type === "flutterwavePayment"
                ? "flutterwave"
                : "xendit";
          addToast({
            title: i18n.t(`checkout.${gateway}_link_invalid`),
            description:
              i18n.t(`checkout.${gateway}_link_error_message`) ||
              "Payment link is invalid or missing. Please try again.",
            color: "danger",
          });
          console.error(`Invalid ${payment_type} payment link`);
          return {
            ...response,
            success: false,
            message: i18n.t(`checkout.${gateway}_link_error_message`),
          };
        }
      }

      // Preserve the page's completion snapshot before clearing the active cart
      // and header badge. Hosted payments returned above have not completed yet.
      notifyCheckoutCompletion(state.cart.cartData);
      store.dispatch(clearCart());
      addToast({
        title: i18n.t("checkout.success"),
        size: "lg",
      });
      resetCheckOutState();
      if (managedLease) {
        if (payment_type === "cod") {
          // COD has finished creating the order; there is no online payment
          // to protect when this reusable cart is filled again.
          releasePaymentAttempt(managedLease);
        } else {
          completePaymentAttempt(managedLease);
        }
        managedLease = null;
      }
    } else {
      const errorMsg = response.message || i18n.t("checkout.error_title");
      addToast({
        title: errorMsg,
        color: "danger",
      });
      console.error(errorMsg);

      // If the backend returns validation errors for the scheduled slot (e.g. cutoff time passed),
      // clear the slot from Redux so the user is forced to pick a valid one.
      if (
        (response as any).errors?.scheduled_slot_start_time ||
        (response as any).errors?.scheduled_slot_end_time ||
        (response as any).errors?.delivery_time_slot_id
      ) {
        store.dispatch(clearScheduledDelivery());
      }
    }
    return response;
  } catch (error) {
    console.error("Checkout error:", error);
    return fallbackApiRes;
  } finally {
    if (managedLease && !preserveManagedLease) {
      releasePaymentAttempt(managedLease);
    }
  }
};

export const formatAmount = (value: number | string) => {
  if (!value) return "0.00";
  return Number(value).toFixed(2);
};

export const urlToFile = async (
  url: string,
  filename: string,
): Promise<File> => {
  try {
    const response = await fetch(url);
    const blob = await response.blob();
    const file = new File([blob], filename, { type: blob.type });
    return file;
  } catch (error) {
    console.error(`Failed to fetch image from ${url}:`, error);
    throw error;
  }
};

export const isRTL = (currentLang: string = "") => {
  return ["ar", "fa", "ur"].includes(currentLang);
};

export const handleNotifyProduct = async (params: {
  slug: string;
  variant_id: string | number;
  is_notify: number;
  onClose?: () => void;
}) => {
  const { onClose = () => {}, is_notify, slug, variant_id } = params;
  try {
    const isLoggedIn = store.getState().auth.isLoggedIn;

    if (!isLoggedIn) {
      onClose();
      const btn = document.getElementById("login-btn");
      btn?.click();
      addToast({
        title: i18n.t("cart.login_required"),
        color: "warning",
      });

      return {
        success: false,
        message: i18n.t("cart.login_required"),
        data: null,
      };
    }

    const cookieLoc = document.cookie
      .split("; ")
      .find((row) => row.startsWith("userLocation="));
    const userLocStr = cookieLoc
      ? decodeURIComponent(cookieLoc.split("=")[1])
      : null;
    let lat: string | number = "";
    let lng: string | number = "";
    if (userLocStr) {
      try {
        const userLoc = JSON.parse(userLocStr);
        lat = userLoc.lat;
        lng = userLoc.lng;
      } catch {
        // ignore
      }
    }

    const res = await getProductBySlug({
      slug,
      latitude: lat,
      longitude: lng,
      is_notify,
      variant_id,
    });

    if (res.success) {
      if (is_notify === 1) {
        addToast({
          title: i18n.t("notification_set_success", {
            defaultValue: "Notification set! We'll notify you when this item is back in stock.",
          }),
          color: "success",
        });
      } else {
        addToast({
          title: i18n.t("notification_cancelled", {
            defaultValue: "Notification reminder updated.",
          }),
          color: "default",
        });
      }
      return { success: true };
    } else {
      addToast({
        title: res.message || "Failed to update notification.",
        color: "danger",
      });
      return { success: false, message: res.message };
    }
  } catch {
    addToast({
      title: i18n.t("cart.error_title"),
      description: "Something went wrong.",
      color: "danger",
    });
    return { success: false };
  }
};
