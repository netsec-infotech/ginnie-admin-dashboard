import { OrderItem } from "@/types/ApiResponse";

export type ItemOtps = {
  /** Handed to the courier when this item is delivered. */
  deliveryOtp: string | null;
  /** Handed to the agent when this returned item is collected. */
  returnPickupOtp: string | null;
};

/**
 * Resolve the OTPs that belong to a single ORDER ITEM.
 *
 * Both codes are per-item, not per-product: an order with three items yields
 * three independent delivery OTPs, and each return request carries its own
 * pickup OTP keyed by order_item_id.
 *
 * A verified OTP is spent, so it is not returned — showing it would only
 * invite the customer to read out a code the agent can no longer use.
 */
export const getItemOtps = (item?: OrderItem | null): ItemOtps => {
  const deliveryOtp = item?.otp && !item.otp_verified ? item.otp : null;

  // Newest unverified return wins: an item can be returned more than once
  // (e.g. a rejected request followed by a fresh one).
  const activeReturn = (item?.returns ?? [])
    .filter((request) => request?.pickup_otp && !request.pickup_otp_verified)
    .sort((a, b) => (b?.id ?? 0) - (a?.id ?? 0))[0];

  return {
    deliveryOtp,
    returnPickupOtp: activeReturn?.pickup_otp ?? null,
  };
};
