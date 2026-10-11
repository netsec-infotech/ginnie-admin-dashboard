import { addToast } from "@heroui/react";
import i18n from "../../../i18n";
import { createRazorPayOrder } from "@/routes/api";

export interface RazorpayPaymentResult {
  razorpay_order_id: string;
  razorpay_payment_id: string;
  razorpay_signature: string;
}

/** Resolves once checkout.js is on the page (shared with <RazorPay/> via the same script id). */
const loadRazorpaySdk = (): Promise<boolean> =>
  new Promise((resolve) => {
    if (typeof window === "undefined") return resolve(false);
    if (window.Razorpay) return resolve(true);

    const existing = document.getElementById(
      "razorpay-sdk",
    ) as HTMLScriptElement | null;
    const script =
      existing ??
      Object.assign(document.createElement("script"), {
        id: "razorpay-sdk",
        src: "https://checkout.razorpay.com/v1/checkout.js",
        async: true,
      });

    script.addEventListener("load", () => resolve(true));
    script.addEventListener("error", () => resolve(false));
    if (!existing) document.body.appendChild(script);
  });

/**
 * Standalone Razorpay collection for amounts that aren't the cart checkout
 * (rider tips today): creates a gateway order for `amount`, opens the popup,
 * and resolves with the payment ids on success or `null` if the customer
 * dismisses it / the payment fails. The caller records the paid thing only
 * on a non-null result — money first, record second.
 */
export const collectRazorpayPayment = async (opts: {
  keyId: string;
  /** In currency units (rupees), not paise. */
  amount: number;
  description: string;
  prefill?: { name: string; email: string; contact: string };
}): Promise<RazorpayPaymentResult | null> => {
  const sdkReady = await loadRazorpaySdk();
  if (!sdkReady || !opts.keyId) {
    addToast({ title: i18n.t("tip.gatewayInitFailed"), color: "danger" });
    return null;
  }

  const orderRes = await createRazorPayOrder({
    amount: opts.amount,
    currency: "INR",
    receipt: new Date().toISOString(),
  });

  if (!orderRes.success || !orderRes.data) {
    addToast({
      title: orderRes.message || i18n.t("tip.gatewayInitFailed"),
      color: "danger",
    });
    return null;
  }

  const order = orderRes.data;

  return new Promise((resolve) => {
    const rzp = new window.Razorpay({
      key: opts.keyId,
      amount: order.amount * 100,
      currency: order.currency,
      description: opts.description,
      order_id: order.id,
      handler: (response) =>
        resolve({
          razorpay_order_id: response.razorpay_order_id,
          razorpay_payment_id: response.razorpay_payment_id,
          razorpay_signature: response.razorpay_signature,
        }),
      prefill: opts.prefill,
      modal: {
        ondismiss: () => {
          addToast({ title: i18n.t("tip.paymentCancelled"), color: "warning" });
          resolve(null);
        },
        confirm_close: true,
      },
    });

    rzp.on("payment.failed", (response) => {
      addToast({
        title: i18n.t("tip.paymentFailed"),
        description: response.error.description,
        color: "danger",
      });
      resolve(null);
    });

    rzp.open();
  });
};
