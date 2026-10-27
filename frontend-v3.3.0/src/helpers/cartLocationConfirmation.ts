import { store } from "@/lib/redux/store";
import { cartLocationConfirmationStore } from "@/stores/cartLocationConfirmationStore";
import { validateAddressDelivery } from "@/services/addressDeliveryService";
import { addToast } from "@heroui/react";
import i18n from "../../i18n";

export const cartLocationKey = (latitude: string | number, longitude: string | number) =>
  `location:${Number(latitude)},${Number(longitude)}`;

// API refreshes replace object references even when the cart has not changed.
export const cartContentsKey = () => {
  const state = store.getState();
  const cart = state.cart.pendingImportedCart ?? state.cart.cartData;
  return JSON.stringify([cart?.id, cart?.items.map((item) =>
    [item.id, item.store_id, item.product_variant_id, item.quantity],
  )]);
};

let accepted: { contents: string; destination: string } | null = null;

export const invalidateCartLocationConsent = () => { accepted = null; };

/** Ask BEFORE fetching: the cart endpoint removes unavailable items as a side effect. */
export async function confirmCartLocation(
  key: string,
  coordinates?: { latitude: string | number; longitude: string | number },
  interactive = true,
): Promise<boolean> {
  const state = store.getState();
  const cart = state.cart.pendingImportedCart ?? state.cart.cartData;
  if (!cart?.items?.length) {
    accepted = null;
    // Explicitly choosing a location with an empty cart needs no confirmation.
    // A background fetch may discover a server cart, so still validate its target.
    if (interactive) return true;
  }
  const contents = cartContentsKey();

  const selectedAddress = store.getState().checkout.selectedAddress;
  const locationMatch = /^location:([^,]+),([^,]+)$/.exec(key);
  const destination = coordinates ?? (
    locationMatch
      ? { latitude: locationMatch[1], longitude: locationMatch[2] }
      : selectedAddress && key === `address:${selectedAddress.id}`
        ? { latitude: selectedAddress.latitude, longitude: selectedAddress.longitude }
        : undefined
  );
  if (!destination) {
    if (interactive) addToast({ title: i18n.t("address.zoneCheckFailed"), color: "warning" });
    return false;
  }
  const destinationKey = cartLocationKey(destination.latitude, destination.longitude);
  if (accepted?.contents === contents && accepted.destination === destinationKey) return true;

  // Delivery validation, not a new address ID, decides whether consent is needed.
  const delivery = await validateAddressDelivery(
    destination,
    cart?.delivery_zone?.zone_id,
  );
  if (cartContentsKey() !== contents) return false;
  if (delivery.success) {
    accepted = { contents, destination: destinationKey };
    return true;
  }
  if (delivery.messageKey !== "address.zoneMismatch") {
    if (interactive) addToast({ title: i18n.t(delivery.messageKey), color: "warning" });
    return false;
  }
  if (!interactive) return false;

  const confirmed = await cartLocationConfirmationStore.request(`${contents}:${destinationKey}`);
  // Never apply consent to a cart that changed while the dialog was open.
  if (!confirmed || cartContentsKey() !== contents) return false;
  accepted = { contents, destination: destinationKey };
  return true;
}
