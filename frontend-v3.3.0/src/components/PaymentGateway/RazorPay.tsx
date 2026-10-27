import { isCheckoutPaymentMethodAvailable, gatewayCurrency, isPaymentMethodAvailable, paymentUnavailableMessage } from "@/helpers/currency";
import { useSettings } from "@/contexts/SettingsContext";
import { handleCheckout } from "@/helpers/functionalHelpers";
import { getCartDataFromRedux, getCheckoutCartFromRedux, getUserDataFromRedux } from "@/helpers/getters";
import { setPromoCode } from "@/lib/redux/slices/checkoutSlice";
import { confirmWalletRecharge, createRazorPayOrder } from "@/routes/api";
import { RazorpayOrderData } from "@/types/ApiResponse";
import { addToast, Button } from "@heroui/react";
import React, { FC, useCallback, useEffect, useRef, useState } from "react";
import { useDispatch } from "react-redux";
import {
  acquirePaymentAttempt,
  completePaymentAttempt,
  markPaymentAttemptPaidPending,
  paymentAttemptScope,
  releasePaymentAttempt,
  type PaymentAttemptLease,
} from "@/helpers/paymentReturn";
import { showPaymentInProgressToast } from "@/components/PaymentGateway/PaymentInProgressToast";
import { useTranslation } from "react-i18next";

// ✅ Razorpay Types
interface RazorpayPaymentResponse {
  razorpay_payment_id: string;
  razorpay_order_id: string;
  razorpay_signature: string;
}

interface RazorpayPaymentFailure {
  error: {
    code: string;
    description: string;
    source: string;
    step: string;
    reason: string;
    metadata: {
      order_id: string;
      payment_id: string;
    };
  };
}

interface RazorpayOptions {
  key: string;
  amount: number;
  currency: string;
  name?: string;
  image?: string | undefined;
  description: string;
  order_id: string;
  handler: (response: RazorpayPaymentResponse) => void;
  prefill?: {
    name: string;
    email: string;
    contact: string;
  };
  notes?: Record<string, string>;
  theme?: {
    color: string;
  };
  modal?: {
    ondismiss?: () => void;
    escape?: boolean;
    confirm_close?: boolean;
  };
}

interface RazorpayInstance {
  open: () => void;
  on: (
    event: "payment.failed",
    handler: (response: RazorpayPaymentFailure) => void
  ) => void;
}

declare global {
  interface Window {
    Razorpay: new (options: RazorpayOptions) => RazorpayInstance;
  }
}

const RazorPay: FC<{
  onSuccess: () => void;
  onError: () => void;
  setIsLoading: (value: boolean) => void;
  isLoading: boolean;
  usageType?: "order" | "wallet";
  walletOrderData?: any;
  triggerRef?: React.MutableRefObject<(() => void) | null>;
}> = ({
  onSuccess,
  onError,
  isLoading,
  setIsLoading,
  usageType = "order",
  walletOrderData,
  triggerRef,
}) => {
  const [sdkReady, setSdkReady] = useState(false);
  const [isConfirming, setIsConfirming] = useState(false);
  const [isAttemptActive, setIsAttemptActive] = useState(false);
  const attemptInFlightRef = useRef(false);
  const { paymentSettings, currency: systemCurrency } = useSettings();
  const userData = getUserDataFromRedux();
  const dispatch = useDispatch();
  const { t } = useTranslation();

  // ✅ Load Razorpay script dynamically
  useEffect(() => {
    if (document.getElementById("razorpay-sdk")) {
      setSdkReady(true);
      return;
    }

    const script = document.createElement("script");
    script.id = "razorpay-sdk";
    script.src = "https://checkout.razorpay.com/v1/checkout.js";
    script.async = true;
    script.onload = () => setSdkReady(true);
    script.onerror = () => console.error("Razorpay SDK failed to load.");
    document.body.appendChild(script);

    return () => {
      const existing = document.getElementById("razorpay-sdk");
      if (existing) existing.remove();
    };
  }, []);

  const handlePayment = useCallback(async () => {
    if (attemptInFlightRef.current) return;
    if (!sdkReady) {
      console.error("Razorpay SDK not ready yet.");
      return;
    }

    attemptInFlightRef.current = true;
    setIsAttemptActive(true);
    setIsLoading(true);
    let providerOpened = false;
    let sharedLease: PaymentAttemptLease | null = null;

    const releaseAttempt = () => {
      if (sharedLease) releasePaymentAttempt(sharedLease);
      attemptInFlightRef.current = false;
      setIsAttemptActive(false);
    };

    try {
      let order: RazorpayOrderData;
      let transactionId: string | undefined;
      let cartData = usageType === "order" ? getCheckoutCartFromRedux() : getCartDataFromRedux();
      const context = usageType === "wallet" ? { ...walletOrderData, ...walletOrderData?.wallet } : cartData;
      if (usageType === "order" ? !isCheckoutPaymentMethodAvailable(cartData, "razorpay", paymentSettings) : !isPaymentMethodAvailable(context, "razorpay")) {
        addToast({ title: paymentUnavailableMessage(context, "razorpay") || t("multiCurrency.paymentUnavailable"), color: "warning" });
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
      const activeLease = sharedLease;
      if (usageType === "order") {
        cartData = getCheckoutCartFromRedux();
        if (!isCheckoutPaymentMethodAvailable(cartData, "razorpay", paymentSettings) ||
          (cartData?.uuid || cartData?.id) !== (context?.uuid || context?.id) ||
          cartData?.payment_summary.payable_amount !== context?.payment_summary?.payable_amount ||
          gatewayCurrency(cartData) !== gatewayCurrency(context)) {
          addToast({ title: t("payments.cartRefreshRequired"), color: "warning" });
          return;
        }
      }

      // ✅ Wallet Flow: Use pre-prepared order data
      if (usageType === "wallet") {
        if (!walletOrderData?.payment_response) {
          addToast({
            title: "Invalid wallet order data",
            color: "danger",
          });
          return console.error("Wallet order data is missing");
        }

        order = {
          id: walletOrderData.payment_response.id,
          amount: walletOrderData.payment_response.amount / 100,
          currency: walletOrderData.payment_response.currency,
          receipt: walletOrderData.payment_response.receipt,
        } as RazorpayOrderData;

        transactionId = walletOrderData.transaction?.id?.toString();
      } else {
        const res = await createRazorPayOrder({
          amount: cartData?.payment_summary.payable_amount || 1,
          currency: gatewayCurrency(cartData) || systemCurrency,
          receipt: new Date().toISOString(),
        });

        if (!res.success || !res.data) {
          addToast({
            title: res.message || "Failed to Create Order!",
            color: "danger",
          });
          return console.error("Failed to create Razorpay order");
        }

        order = res.data;
      }

      const options: RazorpayOptions = {
        key: paymentSettings?.razorpayKeyId || "",
        amount: order.amount * 100,
        currency: order.currency,
        description: usageType === "wallet" ? "Wallet Recharge" : "Pay Safe",
        order_id: order.id,
        handler: async (response: RazorpayPaymentResponse) => {
          setIsConfirming(true);
          markPaymentAttemptPaidPending(activeLease);

          try {
            const checkoutData: any = {
              razorpay_order_id: response.razorpay_order_id,
              transaction_id: response.razorpay_payment_id,
              razorpay_signature: response.razorpay_signature,
            };

            if (usageType === "wallet" && transactionId) {
              checkoutData.wallet_transaction_id = transactionId;
            }

            const res = usageType === "wallet" && transactionId
              ? await confirmWalletRecharge({
                  razorpay_order_id: response.razorpay_order_id,
                  razorpay_payment_id: response.razorpay_payment_id,
                  razorpay_signature: response.razorpay_signature,
                })
              : await handleCheckout("razorpayPayment", checkoutData, {
                  paymentAttemptLease: activeLease,
                  paymentSettings,
                });

            if (res?.success) {
              completePaymentAttempt(activeLease);
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
              // The provider accepted a payment. Keep this attempt locked when
              // application confirmation fails so the customer cannot pay a
              // second time from the same screen.
              addToast({
                title:
                  usageType === "wallet"
                    ? "Wallet recharge is pending confirmation"
                    : "Payment received; order confirmation is pending",
                color: "warning",
                description:
                  res?.message ||
                  "Do not pay again. Check your account shortly or contact support.",
              });
            }
          } catch (error) {
            console.error("Error while processing Razorpay payment:", error);
            addToast({
              title: "Payment confirmation is pending",
              color: "warning",
              description:
                "Do not pay again. Check your account shortly or contact support.",
            });
          } finally {
            setIsLoading(false);
          }
        },
        prefill: {
          name: userData?.name || "",
          email: userData?.email || "",
          contact: userData?.mobile || "",
        },
        notes:
          usageType === "wallet" && walletOrderData?.payment_response?.notes
            ? walletOrderData.payment_response.notes
            : { timeOfPayment: order.receipt },
        modal: {
          ondismiss: () => {
            releaseAttempt();
            addToast({
              title: "Payment Cancelled",
              description: "You have closed the Razorpay checkout.",
              color: "warning",
            });
            setIsConfirming(false);
            setIsLoading(false);
            onError();
          },
          confirm_close: true,
        },
      };

      if (usageType === "order") {
        const latest = getCheckoutCartFromRedux();
        if (!isCheckoutPaymentMethodAvailable(latest, "razorpay", paymentSettings) ||
          (latest?.uuid || latest?.id) !== (cartData?.uuid || cartData?.id) ||
          latest?.payment_summary.payable_amount !== cartData?.payment_summary.payable_amount ||
          gatewayCurrency(latest) !== gatewayCurrency(cartData)) {
          addToast({ title: t("payments.cartRefreshRequired"), color: "warning" });
          return;
        }
      }
      const rzp = new window.Razorpay(options);

      rzp.on("payment.failed", (response: RazorpayPaymentFailure) => {
        releaseAttempt();
        console.error("Payment failed:", response.error);
        addToast({
          title: "Payment Failed",
          description: response.error.description,
          color: "danger",
        });
        onError();
        setIsLoading(false);
      });

      rzp.open();
      providerOpened = true;
    } catch (err) {
      console.error(err);
      addToast({
        title: "Error",
        description: "Failed to initialize payment. Please try again.",
        color: "danger",
      });
      onError();
    } finally {
      if (!providerOpened) releaseAttempt();
      setIsLoading(false);
    }
  }, [
    sdkReady,
    setIsLoading,
    usageType,
    walletOrderData,
    systemCurrency,
    paymentSettings,
    onSuccess,
    onError,
    userData,
    setIsConfirming,
    dispatch,
    t,
  ]);

  // ✅ Expose handlePayment via triggerRef for auto-triggering
  useEffect(() => {
    if (triggerRef) {
      triggerRef.current = handlePayment;
    }
  }, [sdkReady, walletOrderData, triggerRef, handlePayment]);

  return (
    <Button
      onPress={handlePayment}
      isDisabled={!sdkReady || isAttemptActive}
      color="primary"
      isLoading={isLoading || isConfirming}
    >
      {sdkReady
        ? isConfirming
          ? "Confirming"
          : usageType === "wallet"
            ? "Pay with Razorpay"
            : "Pay with Razorpay"
        : "Loading..."}
    </Button>
  );
};

export default RazorPay;
