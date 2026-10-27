import { formatMoney, gatewayCurrency } from "@/helpers/currency";
import type { CurrencyContext } from "@/types/currency";
import { FC, useEffect, useMemo, useRef, useState } from "react";
import {
  addToast,
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalHeader,
  Spinner,
} from "@heroui/react";
import { getStripeClient } from "@/lib/stripe";
import {
  Elements,
  PaymentElement,
  useElements,
  useStripe,
} from "@stripe/react-stripe-js";
import { useTranslation } from "react-i18next";
import { useSettings } from "@/contexts/SettingsContext";
import { getUserDataFromRedux } from "@/helpers/getters";
import { createStripeIntent } from "@/routes/api";

interface TipStripePaymentModalProps {
  isOpen: boolean;
  currencyContext?: CurrencyContext;
  /** Tip amount in currency units. */
  amount: number;
  /** Called with the succeeded PaymentIntent id — the caller records the tip. */
  onPaid: (paymentIntentId: string) => Promise<void>;
  onCancel: () => void;
}

const TipCheckoutForm: FC<{
  onPaid: (paymentIntentId: string) => Promise<void>;
}> = ({ onPaid }) => {
  const stripe = useStripe();
  const elements = useElements();
  const { t } = useTranslation();
  const [isPaying, setIsPaying] = useState(false);
  const submitting = useRef(false);
  const paymentSucceeded = useRef(false);
  const userData = getUserDataFromRedux();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!stripe || !elements || submitting.current || paymentSucceeded.current)
      return;

    submitting.current = true;
    setIsPaying(true);
    try {
      const { error, paymentIntent } = await stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: window.location.href,
          receipt_email: userData?.email || "",
        },
        redirect: "if_required",
      });

      if (error || !paymentIntent) {
        addToast({
          title: t("tip.paymentFailed"),
          description: error?.message,
          color: "danger",
        });
        return;
      }

      if (paymentIntent.status !== "succeeded") {
        addToast({
          title: t(
            ["processing", "requires_capture"].includes(paymentIntent.status)
              ? "tip.paymentPending"
              : "tip.paymentFailed",
          ),
          color: "warning",
        });
        return;
      }

      paymentSucceeded.current = true;
      try {
        await onPaid(paymentIntent.id);
      } catch (error) {
        console.error("Tip confirmation error:", error);
        addToast({
          title: t("tip.paidConfirmationFailed"),
          description: t("tip.paidConfirmationFailedDescription"),
          color: "danger",
        });
      }
    } finally {
      submitting.current = false;
      setIsPaying(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 pb-4">
      <div className="max-h-[50vh] overflow-y-auto px-1">
        <PaymentElement />
      </div>
      <Button
        type="submit"
        color="primary"
        className="w-full"
        isDisabled={!stripe || !elements || paymentSucceeded.current}
        isLoading={isPaying}
      >
        {t("tip.payNow")}
      </Button>
    </form>
  );
};

/**
 * Stripe can't collect through a headless popup the way Razorpay/Paystack
 * can — it needs a mounted PaymentElement — so stripe-paid tips get this
 * small modal. The tip is recorded by the caller only after the
 * PaymentIntent succeeds.
 */
const TipStripePaymentModal: FC<TipStripePaymentModalProps> = ({
  isOpen,
  currencyContext,
  amount,
  onPaid,
  onCancel,
}) => {
  const { t } = useTranslation();
  const { currencySymbol, paymentSettings } = useSettings();
  const [clientSecret, setClientSecret] = useState("");

  const stripePromise = useMemo(() => {
    const key = paymentSettings?.stripePublishableKey || "";
    return key ? getStripeClient(key) : null;
  }, [paymentSettings?.stripePublishableKey]);

  useEffect(() => {
    if (!isOpen || !amount) return;
    setClientSecret("");

    createStripeIntent({
      amount,
      currency: gatewayCurrency(currencyContext) || paymentSettings?.stripeCurrencyCode,
    }).then((res) => {
      if (res.success && res.data?.clientSecret) {
        setClientSecret(res.data.clientSecret);
      } else {
        addToast({
          title: res.message || t("tip.gatewayInitFailed"),
          color: "danger",
        });
        onCancel();
      }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, amount, currencyContext]);

  return (
    <Modal
      isOpen={isOpen}
      onOpenChange={(open) => {
        if (!open) onCancel();
      }}
      backdrop="blur"
      size="lg"
      scrollBehavior="inside"
    >
      <ModalContent data-stripe-checkout-active={isOpen ? "" : undefined}>
        <ModalHeader>
          <h2 className="font-semibold">
            {t("tip.addTipAmount", {
              amount: formatMoney(amount, currencyContext, currencySymbol),
            })}
          </h2>
        </ModalHeader>
        <ModalBody>
          {clientSecret && stripePromise ? (
            <Elements
              stripe={stripePromise}
              options={{
                clientSecret,
                appearance: {
                  theme: "stripe",
                  variables: { colorPrimary: "#2563eb" },
                },
              }}
            >
              <TipCheckoutForm onPaid={onPaid} />
            </Elements>
          ) : (
            <div className="flex justify-center py-8">
              <Spinner />
            </div>
          )}
        </ModalBody>
      </ModalContent>
    </Modal>
  );
};

export default TipStripePaymentModal;
