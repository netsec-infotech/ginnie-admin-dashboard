import { FC, useEffect, useMemo, useState } from "react";
import {
  addToast,
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalHeader,
  Spinner,
} from "@heroui/react";
import { loadStripe } from "@stripe/stripe-js";
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
import { formatAmount } from "@/helpers/functionalHelpers";

interface TipStripePaymentModalProps {
  isOpen: boolean;
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
  const userData = getUserDataFromRedux();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!stripe || !elements) return;

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

      await onPaid(paymentIntent.id);
    } finally {
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
        isDisabled={!stripe || !elements}
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
  amount,
  onPaid,
  onCancel,
}) => {
  const { t } = useTranslation();
  const { currencySymbol, paymentSettings } = useSettings();
  const [clientSecret, setClientSecret] = useState("");

  const stripePromise = useMemo(() => {
    const key = paymentSettings?.stripePublishableKey || "";
    return key ? loadStripe(key) : null;
  }, [paymentSettings?.stripePublishableKey]);

  useEffect(() => {
    if (!isOpen || !amount) return;
    setClientSecret("");

    createStripeIntent({
      amount,
      currency: paymentSettings?.stripeCurrencyCode || "usd",
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
  }, [isOpen, amount]);

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
      <ModalContent>
        <ModalHeader>
          <h2 className="font-semibold">
            {t("tip.addTipAmount", {
              amount: `${currencySymbol}${formatAmount(amount)}`,
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
