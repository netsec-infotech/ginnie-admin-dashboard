import { gatewayCurrency, isCheckoutPaymentMethodAvailable, isPaymentMethodAvailable, paymentUnavailableMessage } from "@/helpers/currency";
import { useSettings } from "@/contexts/SettingsContext";
import { handleCheckout } from "@/helpers/functionalHelpers";
import { getCartDataFromRedux, getCheckoutCartFromRedux, getUserDataFromRedux } from "@/helpers/getters";
import { setPromoCode } from "@/lib/redux/slices/checkoutSlice";
import { paystackCreateOrder } from "@/routes/api";
import { ApiResponse, PaystackCreateOrderResponse } from "@/types/ApiResponse";
import { addToast, Button } from "@heroui/react";
import React, { FC, useEffect, useRef, useState } from "react";
import { useDispatch } from "react-redux";
import Script from "next/script";
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
// ✅ PayStack Types
interface PayStackResponse {
  reference: string;
  status: string;
  message: string;
  trans: string;
  transaction: string;
  trxref: string;
}

interface PayStackOrderData {
  transaction: {
    id: number;
    transaction_id: string;
    uuid: string;
    amount: string;
    currency?: string;
    currency_code?: string;
    payment_method: string;
    payment_status: string;
  };
  payment_response: {
    authorization_url: string;
    access_code: string;
    reference: string;
  };
}

declare global {
  interface Window {
    PaystackPop?: new () => {
      resumeTransaction(
        accessCode: string,
        options: {
          onSuccess: (response: PayStackResponse) => void;
          onCancel: () => void;
        }
      ): void;
    };
  }
}

const PayStack: FC<{
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
  const dispatch = useDispatch();
  const { t } = useTranslation();
  const { paymentSettings } = useSettings();

  // ✅ Load PayStack script dynamically using next/script
  // This avoids issues with document.currentScript in async scripts
  // We handle it in the render function.

  const handlePayment = React.useCallback(async () => {
    if (attemptInFlightRef.current) return;
    if (!sdkReady || !window.PaystackPop) {
      console.error("PayStack SDK not ready yet.");
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
      let orderData: PayStackOrderData;
      let transactionId: string | undefined = undefined;
      let cartData = usageType === "order" ? getCheckoutCartFromRedux() : getCartDataFromRedux();
      const context = usageType === "wallet" ? { ...walletOrderData, ...walletOrderData?.wallet } : cartData;
      if (usageType === "order" ? !isCheckoutPaymentMethodAvailable(cartData, "paystack", paymentSettings) : !isPaymentMethodAvailable(context, "paystack")) {
        addToast({ title: paymentUnavailableMessage(context, "paystack") || t("multiCurrency.paymentUnavailable"), color: "warning" });
        return;
      }
      const userData = getUserDataFromRedux();
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
        if (!isCheckoutPaymentMethodAvailable(cartData, "paystack", paymentSettings) ||
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
          setIsLoading(false);
          return console.error("Wallet order data is missing");
        }

        orderData = walletOrderData;
        transactionId = walletOrderData.transaction?.id?.toString();
      } else {
        // ✅ Order Flow: Create new PayStack order
        // ✅ Always create a fresh order to avoid duplicate reference errors
        const res: ApiResponse<PaystackCreateOrderResponse> =
          await paystackCreateOrder({
            amount: cartData?.payment_summary.payable_amount || 1 * 100,
            currency: gatewayCurrency(cartData),
          });

        if (!res.success || !res.data) {
          addToast({
            title: res.message || "Failed to Create Order!",
            color: "danger",
          });
          setIsLoading(false);
          return console.error("Failed to create PayStack order");
        }

        orderData = res.data;

        // ✅ Ensure we have a valid, unique reference
        if (!orderData.payment_response?.reference) {
          addToast({
            title: "Invalid payment reference",
            color: "danger",
          });
          setIsLoading(false);
          return console.error("Payment reference is missing");
        }
      }

      const processPayment = async (response: PayStackResponse) => {
        setIsConfirming(true);
        markPaymentAttemptPaidPending(activeLease);

        try {
          const checkoutData: any = {
            reference: response.reference,
            transaction_id: response.reference,
          };

          if (usageType === "wallet" && transactionId) {
            checkoutData.wallet_transaction_id = transactionId;
          }

          const res =
            usageType === "wallet" && transactionId
              ? { success: true, message: "Wallet Recharge Done!" }
              : await handleCheckout("paystackPayment", checkoutData, {
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
            // application confirmation fails so a retry cannot charge again.
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
          console.error("Error while processing PayStack payment:", error);
          addToast({
            title: "Payment confirmation is pending",
            color: "warning",
            description:
              "Do not pay again. Check your account shortly or contact support.",
          });
        } finally {
          setIsLoading(false);
        }
      };

      if (usageType === "order") {
        const latest = getCheckoutCartFromRedux();
        if (!isCheckoutPaymentMethodAvailable(latest, "paystack", paymentSettings) ||
          (latest?.uuid || latest?.id) !== (cartData?.uuid || cartData?.id) ||
          latest?.payment_summary.payable_amount !== cartData?.payment_summary.payable_amount ||
          gatewayCurrency(latest) !== gatewayCurrency(cartData)) {
          addToast({ title: t("payments.cartRefreshRequired"), color: "warning" });
          return;
        }
      }
      const popup = new window.PaystackPop();

      popup.resumeTransaction(orderData.payment_response.access_code, {
        onSuccess: (response: PayStackResponse) => {
          // ✅ Payment successful, process it
          processPayment(response);
        },
        onCancel: () => {
          releaseAttempt();
          // ✅ User closed the popup
          addToast({
            title: "Payment Cancelled",
            description: "You have closed the PayStack checkout.",
            color: "warning",
          });
          setIsConfirming(false);
          setIsLoading(false);
          onError();
        },
      });

      providerOpened = true;
      setIsLoading(false);
    } catch (err) {
      console.error(err);
      addToast({
        title: "Error",
        description: "Failed to initialize payment. Please try again.",
        color: "danger",
      });
      onError();
      setIsLoading(false);
    } finally {
      if (!providerOpened) {
        releaseAttempt();
        setIsLoading(false);
      }
    }
  }, [
    sdkReady,
    setIsLoading,
    usageType,
    walletOrderData,
    paymentSettings,
    onSuccess,
    onError,
    setIsConfirming,
    dispatch,
    t,
  ]);

  // ✅ Expose handlePayment via triggerRef for auto-triggering
  useEffect(() => {
    if (triggerRef && sdkReady && !isLoading && !isConfirming) {
      triggerRef.current = () => {
        if (!isLoading && !isConfirming) {
          handlePayment();
        }
      };
    }
  }, [
    sdkReady,
    walletOrderData,
    paymentSettings,
    triggerRef,
    isLoading,
    isConfirming,
    handlePayment,
  ]);

  return (
    <>
      <Script
        id="paystack-sdk"
        src="https://js.paystack.co/v2/inline.js"
        strategy="lazyOnload"
        onLoad={() => setSdkReady(true)}
        onError={() => console.error("PayStack SDK failed to load.")}
      />
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
            ? "Pay with PayStack"
            : "Pay with PayStack"
        : "Loading..."}
      </Button>
    </>
  );
};

export default PayStack;
