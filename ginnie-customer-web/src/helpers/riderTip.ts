import {
  Order,
  OrderStatus,
  PaymentSettings,
  TippingConfig,
} from "@/types/ApiResponse";

/**
 * Statuses during which the customer may still add a rider tip from the
 * order detail page. Kept as an allowlist: the backend accepts tips even on
 * delivered orders and emits statuses this client doesn't model (e.g.
 * `rejected_by_seller`), so anything unknown must fall on the "hidden" side.
 */
const TIP_ELIGIBLE_STATUSES: OrderStatus[] = [
  "pending",
  "awaiting_store_response",
  "partially_accepted",
  "preparing",
  "ready_for_pickup",
  "assigned",
  "out_for_delivery",
];

export const canTipOrder = (status: OrderStatus | string): boolean =>
  TIP_ELIGIBLE_STATUSES.includes(status as OrderStatus);

/** Whether the order detail page should render the tip box at all. */
export const shouldShowOrderTipBox = (order: Order): boolean =>
  !!order.tipping_config?.enable_rider_tips && canTipOrder(order.status);

export const getTipPresets = (config: TippingConfig): number[] =>
  [config.preset_1, config.preset_2, config.preset_3].filter(
    (amount) => Number(amount) > 0,
  );

export type TipAmountVerdict = "ok" | "invalid" | "below_min" | "above_max";

export const validateTipAmount = (
  amount: number,
  config: TippingConfig,
): TipAmountVerdict => {
  if (!Number.isFinite(amount) || amount <= 0) return "invalid";
  if (amount < config.min_tip_amount) return "below_min";
  if (amount > config.max_tip_amount) return "above_max";
  return "ok";
};

/**
 * Payment methods offered for post-order tips, as the identifiers the
 * `/user/orders/{slug}/tip` endpoint expects (short gateway names, unlike
 * checkout's `razorpayPayment`-style keys). COD is deliberately never
 * offered — tips are not collected in cash.
 *
 * The tip endpoint only RECORDS a tip; the money must be collected
 * client-side beforehand. So a gateway is only listed once a tip collection
 * flow exists for it: wallet (backend-deducted), Razorpay + Paystack
 * (headless popups), Stripe (PaymentElement modal). Flutterwave is
 * excluded — its checkout is a full-page redirect driven by a payment link
 * the backend only issues during order creation, so a standalone tip can't
 * be collected without backend support. Listing a gateway without its flow
 * would record tips nobody paid for.
 */
export const getTipPaymentMethods = (
  settings: PaymentSettings | null,
): string[] => {
  if (!settings) return [];
  const methods: string[] = [];
  if (settings.wallet) methods.push("wallet");
  if (settings.razorpayPayment) methods.push("razorpay");
  if (settings.stripePayment) methods.push("stripe");
  if (settings.paystackPayment) methods.push("paystack");
  if (settings.xenditPayment) methods.push("xenditPayment");
  return methods;
};
