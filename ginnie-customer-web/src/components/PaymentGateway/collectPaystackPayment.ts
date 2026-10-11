import { addToast } from "@heroui/react";
import i18n from "../../../i18n";
import { paystackCreateOrder } from "@/routes/api";

export interface PaystackPaymentResult {
  reference: string;
}

/** Resolves once inline.js is on the page (shared with <PayStack/> via the same script id). */
const loadPaystackSdk = (): Promise<boolean> =>
  new Promise((resolve) => {
    if (typeof window === "undefined") return resolve(false);
    if (window.PaystackPop) return resolve(true);

    const existing = document.getElementById(
      "paystack-sdk",
    ) as HTMLScriptElement | null;
    const script =
      existing ??
      Object.assign(document.createElement("script"), {
        id: "paystack-sdk",
        src: "https://js.paystack.co/v2/inline.js",
        async: true,
      });

    script.addEventListener("load", () => resolve(true));
    script.addEventListener("error", () => resolve(false));
    if (!existing) document.body.appendChild(script);
  });

/**
 * Standalone Paystack collection for amounts that aren't the cart checkout
 * (rider tips today). Mirrors `collectRazorpayPayment`: server-side order
 * first, popup second, and resolves `null` on dismissal/failure so the
 * caller records nothing unpaid.
 */
export const collectPaystackPayment = async (opts: {
  amount: number;
}): Promise<PaystackPaymentResult | null> => {
  const sdkReady = await loadPaystackSdk();
  if (!sdkReady || !window.PaystackPop) {
    addToast({ title: i18n.t("tip.gatewayInitFailed"), color: "danger" });
    return null;
  }

  const orderRes = await paystackCreateOrder({ amount: opts.amount });
  const accessCode = orderRes.data?.payment_response?.access_code;

  if (!orderRes.success || !accessCode) {
    addToast({
      title: orderRes.message || i18n.t("tip.gatewayInitFailed"),
      color: "danger",
    });
    return null;
  }

  const PaystackPop = window.PaystackPop;

  return new Promise((resolve) => {
    new PaystackPop().resumeTransaction(accessCode, {
      onSuccess: (response) => resolve({ reference: response.reference }),
      onCancel: () => {
        addToast({ title: i18n.t("tip.paymentCancelled"), color: "warning" });
        resolve(null);
      },
    });
  });
};
