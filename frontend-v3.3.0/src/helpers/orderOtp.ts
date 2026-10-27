import type { Order, OrderItem } from "@/types/ApiResponse";

type DeliveryOtpContext = Pick<
  Order,
  | "delivery_otp_mode"
  | "delivery_otp_required"
  | "delivery_otp"
  | "delivery_otp_verified"
>;

/** An order-wise code is displayed once, never copied to individual items. */
export const getOrderDeliveryOtp = (
  order?: DeliveryOtpContext | null,
): string | null =>
  order?.delivery_otp_mode === "order_wise" &&
  order.delivery_otp_required !== false &&
  !order.delivery_otp_verified
    ? order.delivery_otp || null
    : null;

export type ItemOtps = {
  /** Handed to the courier when this item is delivered. */
  deliveryOtp: string | null;
  /** Handed to the agent when this returned item is collected. */
  returnPickupOtp: string | null;
};

/**
 * Resolve the OTPs that belong to a single ORDER ITEM.
 *
 * In item-wise mode (or older responses without a mode), delivery OTPs belong
 * to individual items. In order-wise mode, delivery is shown once at order
 * level. Return pickup OTPs always stay keyed by order_item_id.
 *
 * A verified OTP is spent, so it is not returned — showing it would only
 * invite the customer to read out a code the agent can no longer use.
 */
export const getItemOtps = (
  item?: OrderItem | null,
  order?: DeliveryOtpContext | null,
): ItemOtps => {
  const deliveryOtp =
    order?.delivery_otp_mode !== "order_wise" &&
    order?.delivery_otp_required !== false &&
    item?.otp &&
    !item.otp_verified
      ? item.otp
      : null;

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
