import { isCheckoutPaymentMethodAvailable, gatewayCurrency, isPaymentMethodAvailable, paymentUnavailableMessage } from "@/helpers/currency";
import React, {
  useState,
  useEffect,
  useRef,
  useMemo,
  useCallback,
} from "react";
import type { PaymentIntent, StripeElementsOptions } from "@stripe/stripe-js";
import type { CartResponse } from "@/types/ApiResponse";
import { getStripeClient } from "@/lib/stripe";
import { useTranslation } from "react-i18next";
import {
  Elements,
  PaymentElement,
  useStripe,
  useElements,
} from "@stripe/react-stripe-js";
import { addToast, Button } from "@heroui/react";
import { useSettings } from "@/contexts/SettingsContext";
import { getCartDataFromRedux, getCheckoutCartFromRedux, getUserDataFromRedux } from "@/helpers/getters";
import { handleCheckout } from "@/helpers/functionalHelpers";
import { createStripeIntent } from "@/routes/api";
import { setPromoCode } from "@/lib/redux/slices/checkoutSlice";
import { useDispatch } from "react-redux";
import { LockKeyhole } from "lucide-react";
import { useTheme } from "next-themes";
import {
  acquirePaymentAttempt,
  completePaymentAttempt,
  hasCompetingPaymentAttempt,
  markPaymentAttemptPaidPending,
  paymentAttemptScope,
  releasePaymentAttempt,
  type PaymentAttemptLease,
} from "@/helpers/paymentReturn";
import { showPaymentInProgressToast } from "@/components/PaymentGateway/PaymentInProgressToast";
import { PaymentInProgressNotice } from "@/components/PaymentGateway/PaymentInProgressToast";

const showPaymentError = (message: string) => {
  addToast({ title: message, color: "danger" });
};

const PaymentFormSkeleton = () => {
  const { t } = useTranslation();
  return (
    <div role="status" className="min-h-80 w-full space-y-6 py-4">
      <div className="flex items-center gap-3">
        <span aria-hidden="true" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"><LockKeyhole size={18} /></span>
        <div>
          <p className="text-sm font-medium">{t("stripe_form_loading")}</p>
          <p className="mt-1 text-xs text-foreground/60">{t("stripe_loading_help")}</p>
        </div>
      </div>
      <div aria-hidden="true" className="space-y-3 motion-safe:animate-pulse">
        {[0, 1, 2].map((row) => (
          <div key={row} className="flex h-14 items-center gap-3 rounded-xl border border-default-200 px-4">
            <div className="h-5 w-5 rounded-full bg-default-100" />
            <div className="h-3 w-28 rounded bg-default-100" />
          </div>
        ))}
      </div>
    </div>
  );
};

const CheckoutForm: React.FC<{
  onSuccess: () => void;
  onError: () => void;
  setIsLoading: (value: boolean) => void;
  usageType?: "order" | "wallet";
  walletOrderData?: any;
  triggerRef?: React.MutableRefObject<(() => void) | null>;
  onRetry: () => void;
  clientSecret: string;
  cartQuote?: CartResponse | null;
}> = ({
  onError,
  onSuccess,
  setIsLoading,
  usageType = "order",
  walletOrderData,
  triggerRef,
  onRetry,
  clientSecret,
  cartQuote,
}) => {
  const { t } = useTranslation();
  const { paymentSettings } = useSettings();
  const stripe = useStripe();
  const elements = useElements();
  const [isReady, setIsReady] = useState(false);
  const [isComplete, setIsComplete] = useState(false);
  const [loadFailed, setLoadFailed] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const submittingRef = useRef(false);
  const paymentLockedRef = useRef(false);
  const [paymentLocked, setPaymentLocked] = useState(false);
  const userData = getUserDataFromRedux();
  const dispatch = useDispatch();

  // A newly mounted form has not submitted a payment. The modal's shared
  // loading flag can belong to a previous gateway or a closed form.
  useEffect(() => {
    setIsLoading(false);
  }, [setIsLoading]);

  const handleSubmit = useCallback(
    async (e?: React.FormEvent) => {
      if (e) e.preventDefault();
      if (submittingRef.current || paymentLockedRef.current) return;

      if (!stripe || !elements) {
        console.error("❌ Stripe or Elements not loaded");
        showPaymentError("Stripe hasn't loaded yet. Please try again.");
        return;
      }

      if (!isReady) {
        console.error("❌ Payment form not ready");
        showPaymentError("Payment form is still loading. Please wait...");
        return;
      }

      if (!isComplete) return;

      submittingRef.current = true;
      setIsSubmitting(true);
      setIsLoading(true);
      let sharedLease: PaymentAttemptLease | null = null;
      let preserveSharedLease = false;

      const lockPayment = () => {
        paymentLockedRef.current = true;
        setPaymentLocked(true);
      };
      const stopForStatus = (status: PaymentIntent["status"]) => {
        const messages: Partial<Record<PaymentIntent["status"], string>> = {
          succeeded: "stripe_already_paid",
          processing: "stripe_payment_pending",
          requires_capture: "stripe_payment_pending",
          canceled: "stripe_payment_canceled",
        };
        const key = messages[status];
        if (!key) return false;
        if (
          sharedLease &&
          ["succeeded", "processing", "requires_capture"].includes(status)
        ) {
          markPaymentAttemptPaidPending(sharedLease);
          preserveSharedLease = true;
        }
        lockPayment();
        showPaymentError(t(key));
        return true;
      };

      try {
        const cartData = usageType === "order" ? cartQuote : getCartDataFromRedux();
        const cartStillPayable = () => {
          if (usageType === "wallet") return true;
          const current = getCheckoutCartFromRedux();
          return isCheckoutPaymentMethodAvailable(current, "stripe", paymentSettings) &&
            (current?.uuid || current?.id) === (cartData?.uuid || cartData?.id) &&
            current?.payment_summary.payable_amount === cartData?.payment_summary.payable_amount &&
            gatewayCurrency(current) === gatewayCurrency(cartData);
        };
        if (!cartStillPayable()) {
          showPaymentError(t("payments.cartRefreshRequired"));
          return;
        }
        const attemptScope = paymentAttemptScope({
          usageType,
          userId: userData?.id,
          cartId: cartData?.uuid || cartData?.id,
          walletTransactionId: walletOrderData?.transaction?.id,
        });
        sharedLease = await acquirePaymentAttempt(attemptScope);
        if (!sharedLease) {
          showPaymentInProgressToast(attemptScope, t);
          return;
        }

        // A previous confirmation may have succeeded even if its response or
        // the subsequent order request failed. Never charge again blindly.
        const current = await stripe.retrievePaymentIntent(clientSecret);
        if (current.error || !current.paymentIntent) {
          showPaymentError(t("stripe_status_unavailable"));
          onError();
          return;
        }
        if (stopForStatus(current.paymentIntent.status)) return;

        if (!cartStillPayable()) {
          showPaymentError(t("payments.cartRefreshRequired"));
          return;
        }

        const { error, paymentIntent } = await stripe.confirmPayment({
          elements,
          confirmParams: {
            return_url: `${window.location.origin}/my-account/${
              usageType === "wallet" ? "wallet" : "orders"
            }`,
            receipt_email: userData?.email || "",
          },
          redirect: "if_required",
        });

        if (error) {
          if (error.code === "payment_intent_unexpected_state") {
            // Re-read in case another confirmation completed after our check.
            const latest = await stripe.retrievePaymentIntent(clientSecret);
            if (latest.paymentIntent && stopForStatus(latest.paymentIntent.status)) return;
            lockPayment();
            showPaymentError(t("stripe_status_unavailable"));
            onError();
            return;
          }
          const errorMessage = error.decline_code === "fraudulent"
            ? t("stripe_card_declined")
            : error.message || t("stripe_payment_failed");
          onError();
          showPaymentError(errorMessage);
          return;
        }

        if (!paymentIntent) {
          console.error("❌ Payment Intent is undefined");
          showPaymentError("Payment failed. No payment intent returned.");
          onError();
          return;
        }

        if (paymentIntent.status !== "succeeded") {
          if (!stopForStatus(paymentIntent.status)) {
            showPaymentError(t("stripe_payment_failed"));
          }
          return;
        }
        // Keep locked even if order creation fails or the success callback throws.
        markPaymentAttemptPaidPending(sharedLease);
        preserveSharedLease = true;
        lockPayment();

        // Handle wallet vs order flow
        let res;
        if (usageType === "wallet" && walletOrderData?.transaction?.id) {
          res = {
            success: true,
            message: "Wallet Recharge Done!",
          };
        } else {
          res = await handleCheckout("stripePayment", {
            transaction_id: paymentIntent.id,
          }, { paymentAttemptLease: sharedLease, paymentSettings });
        }

        if (res.success) {
          completePaymentAttempt(sharedLease);
          onSuccess();
          addToast({
            title:
              usageType === "wallet"
                ? "Wallet Recharged Successfully!"
                : "Order Placed Successfully!",
            color: "success",
          });

          if (usageType !== "wallet") {
            dispatch(setPromoCode(""));
          }
        } else {
          showPaymentError(t("stripe_paid_order_failed"));
        }
      } catch (err) {
        const failureMessage = t(paymentLockedRef.current ? "stripe_paid_order_failed" : "stripe_status_unavailable");
        showPaymentError(failureMessage);
        console.error("Payment error:", err);
        onError();
      } finally {
        if (sharedLease && !preserveSharedLease) {
          releasePaymentAttempt(sharedLease);
        }
        submittingRef.current = false;
        setIsSubmitting(false);
        setIsLoading(false);
      }
    },
    [
      stripe,
      elements,
      isReady,
      isComplete,
      userData?.id,
      userData?.email,
      usageType,
      walletOrderData?.transaction?.id,
      onSuccess,
      onError,
      setIsLoading,
      dispatch,
      clientSecret,
      cartQuote,
      paymentSettings,
      t,
    ]
  );

  // ✅ Expose handleSubmit via triggerRef for auto-triggering
  useEffect(() => {
    if (triggerRef && stripe && elements && isReady && isComplete) {
      triggerRef.current = () => handleSubmit();
      console.log("✅ Payment trigger ready");
    }
    return () => {
      if (triggerRef) triggerRef.current = null;
    };
  }, [stripe, elements, isReady, isComplete, triggerRef, handleSubmit]);

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-4 w-full flex justify-center flex-col items-end"
    >
      <div className={`relative w-full min-w-0 ${!isReady && !loadFailed ? "h-80 overflow-hidden" : ""}`} aria-busy={!isReady && !loadFailed}>
        {/* Keep Stripe in normal flow and visible so its iframe can measure and
            paint. Cover initialization without hiding or repositioning it. */}
        {!isReady && !loadFailed && <div className="absolute inset-0 z-10 bg-content1"><PaymentFormSkeleton /></div>}
        <div aria-hidden={!isReady} inert={!isReady ? true : undefined}>
          <PaymentElement
            options={{
              paymentMethodOrder: ["apple_pay", "google_pay", "card"],
              defaultValues: {
                billingDetails: {
                  name: userData?.name || "",
                  email: userData?.email || "",
                },
              },
              layout: {
                type: "accordion",
                defaultCollapsed: false,
                radios: "always",
                spacedAccordionItems: true,
                visibleAccordionItemsCount: 3,
              },
            }}
            onReady={() => { setIsReady(true); setLoadFailed(false); }}
            onChange={(event) => setIsComplete(event.complete)}
            onLoadError={(event) => {
              setIsReady(false);
              setLoadFailed(true);
              showPaymentError(event.error.message || "Unable to load the payment form. Please try again.");
            }}
          />
        </div>
      </div>

      {loadFailed && <Button type="button" variant="flat" onPress={onRetry}>{t("stripe_form_retry")}</Button>}
      {isReady && <Button
        type="submit"
        isDisabled={!stripe || !elements || !isReady || !isComplete || isSubmitting || paymentLocked}
        color="primary"
        className="w-full min-h-12 rounded-xl font-semibold"
        isLoading={isSubmitting}
      >
        {isSubmitting ? "Processing..." : "Pay Now"}
      </Button>}
    </form>
  );
};

interface StripeProps {
  onSuccess: () => void;
  onError: () => void;
  setIsLoading: (value: boolean) => void;
  isLoading: boolean;
  isActive?: boolean;
  usageType?: "order" | "wallet";
  walletOrderData?: any;
  triggerRef?: React.MutableRefObject<(() => void) | null>;
}

const Stripe: React.FC<StripeProps> = ({
  onError,
  onSuccess,
  setIsLoading,
  isActive = true,
  usageType = "order",
  walletOrderData,
  triggerRef,
}) => {
  const { paymentSettings, webSettings, currency: systemCurrency } = useSettings();
  const { resolvedTheme } = useTheme();
  const isDark = resolvedTheme === "dark";
  const { t } = useTranslation();
  const [retry, setRetry] = useState(0);
  const [initialization, setInitialization] = useState<{
    key: string; clientSecret?: string; error?: string;
  } | null>(null);
  const pendingRef = useRef<{ key: string; promise: Promise<string> } | null>(null);
  const cartData = usageType === "order" ? getCheckoutCartFromRedux() : getCartDataFromRedux();
  const userData = getUserDataFromRedux();
  const amount = Number(usageType === "wallet"
    ? walletOrderData?.transaction?.gateway_amount ?? walletOrderData?.transaction?.amount
    : cartData?.payment_summary?.payable_amount);
  const walletSecret = walletOrderData?.payment_response?.clientSecret || "";
  const publishableKey = paymentSettings?.stripePublishableKey || "";
  const currencyContext = usageType === "wallet" ? walletOrderData?.transaction : cartData;
  const currency = gatewayCurrency(currencyContext) || paymentSettings?.stripeCurrencyCode || systemCurrency;
  const currencyError = paymentUnavailableMessage(currencyContext, "stripe");
  const currencyAllowed = usageType === "wallet"
    ? isPaymentMethodAvailable({ ...walletOrderData, ...walletOrderData?.wallet }, "stripe")
    : isCheckoutPaymentMethodAvailable(cartData, "stripe", paymentSettings);
  const attemptScope = paymentAttemptScope({
    usageType,
    userId: userData?.id,
    cartId: cartData?.uuid || cartData?.id,
    walletTransactionId: walletOrderData?.transaction?.id,
  });
  const initializationKey = JSON.stringify([
    attemptScope,
    amount,
    currency,
    currencyAllowed,
    publishableKey,
    walletSecret,
    retry,
  ]);
  const paymentBlocked = hasCompetingPaymentAttempt(attemptScope);
  const [stripePromise, setStripePromise] = useState<ReturnType<typeof getStripeClient> | null>(null);

  useEffect(() => {
    let cancelled = false;
    if (!isActive) return;
    if (paymentBlocked) {
      const timeout = window.setTimeout(() => setIsLoading(false), 0);
      return () => window.clearTimeout(timeout);
    }
    if (!pendingRef.current || pendingRef.current.key !== initializationKey) {
      const promise = (async () => {
        if (!currencyAllowed) throw new Error(currencyError || t("multiCurrency.paymentUnavailable"));
        if (!publishableKey || !Number.isFinite(amount) || amount <= 0) {
          throw new Error(t("stripe_form_unavailable"));
        }
        const sdk = getStripeClient(publishableKey);
        setStripePromise(sdk);
        const secret = usageType === "wallet"
          ? Promise.resolve(walletSecret)
          : createStripeIntent({ amount, currency }).then((response) => {
              if (!response.success || !response.data?.clientSecret) {
                throw new Error(response.message || t("stripe_form_unavailable"));
              }
              return response.data.clientSecret;
            });
        const [stripe, clientSecret] = await Promise.all([sdk, secret]);
        if (!stripe || !clientSecret) throw new Error(t("stripe_form_unavailable"));
        return clientSecret;
      })();
      pendingRef.current = { key: initializationKey, promise };
    }
    pendingRef.current.promise.then(
      (clientSecret) => {
        if (!cancelled) setInitialization({ key: initializationKey, clientSecret });
      },
      (error: unknown) => {
        if (!cancelled) setInitialization({
          key: initializationKey,
          error: error instanceof Error ? error.message : t("stripe_form_unavailable"),
        });
      },
    );
    return () => { cancelled = true; };
  }, [initializationKey, isActive, paymentBlocked, amount, currency, currencyAllowed, currencyError, publishableKey, usageType, walletSecret, setIsLoading, t]);

  const current = initialization?.key === initializationKey ? initialization : null;
  const clientSecret = current?.clientSecret;
  const options = useMemo<StripeElementsOptions>(() => ({
    clientSecret,
    loader: "always",
    appearance: {
      theme: isDark ? "night" : "stripe",
      variables: {
        colorPrimary: webSettings?.primaryColor || "#2563eb",
        colorBackground: isDark ? "#18181b" : "#ffffff",
        colorText: isDark ? "#f4f4f5" : "#27272a",
        colorTextSecondary: isDark ? "#a1a1aa" : "#52525b",
        colorTextPlaceholder: isDark ? "#a1a1aa" : "#71717a",
        colorDanger: isDark ? "#f871a0" : "#c20e4d",
        borderRadius: "10px",
        fontSizeBase: "16px",
        spacingUnit: "4px",
      },
      rules: {
        ".AccordionItem": { border: `1px solid ${isDark ? "#3f3f46" : "#e4e4e7"}`, boxShadow: "none" },
        ".Input": { border: `1px solid ${isDark ? "#3f3f46" : "#e4e4e7"}`, boxShadow: "none", padding: "12px" },
      },
    },
  }), [clientSecret, webSettings?.primaryColor, isDark]);

  if (paymentBlocked) {
    return (
      <div
        role="alert"
        className="w-full space-y-3 rounded-xl border border-warning-300 bg-warning-50 p-4 text-warning-800 dark:bg-warning-950/30 dark:text-warning-200"
      >
        <p className="font-semibold">{t("paymentAttempt.inProgressTitle")}</p>
        <PaymentInProgressNotice scope={attemptScope} t={t} />
      </div>
    );
  }

  if (current?.error) {
    return (
      <div className="min-h-80 w-full space-y-4 rounded-xl border border-danger-200 p-4">
        <p role="alert" className="text-sm text-danger">{current.error}</p>
        <Button variant="flat" onPress={() => setRetry((value) => value + 1)}>{t("stripe_form_retry")}</Button>
      </div>
    );
  }
  if (!clientSecret || !stripePromise) return <PaymentFormSkeleton />;

  return (
    <div className="w-full" data-stripe-checkout-active={isActive ? "" : undefined}>
      <Elements key={clientSecret} stripe={stripePromise} options={options}>
        <CheckoutForm
          clientSecret={clientSecret}
          cartQuote={cartData}
          onRetry={() => setRetry((value) => value + 1)}
          onSuccess={onSuccess}
          onError={onError}
          setIsLoading={setIsLoading}
          usageType={usageType}
          walletOrderData={walletOrderData}
          triggerRef={triggerRef}
        />
      </Elements>
    </div>
  );
};

export default Stripe;
