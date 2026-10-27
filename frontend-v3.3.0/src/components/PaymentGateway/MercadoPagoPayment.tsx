import { useRef } from "react";
import { Button } from "@heroui/react";
import { useTranslation } from "react-i18next";
import { getCheckoutCartFromRedux } from "@/helpers/getters";
import { isCheckoutPaymentMethodAvailable } from "@/helpers/currency";
import { useSettings } from "@/contexts/SettingsContext";
import { handleCheckout } from "@/helpers/functionalHelpers";

export default function MercadoPagoPayment({
  isLoading,
  setIsLoading,
  onError,
}: {
  isLoading: boolean;
  setIsLoading: (value: boolean) => void;
  onError: () => void;
}) {
  const { t } = useTranslation();
  const { paymentSettings } = useSettings();
  const submitting = useRef(false);
  const enabled = isCheckoutPaymentMethodAvailable(getCheckoutCartFromRedux(), "mercadopago", paymentSettings);

  const pay = async () => {
    if (submitting.current || isLoading || !isCheckoutPaymentMethodAvailable(getCheckoutCartFromRedux(), "mercadopago", paymentSettings)) return;
    submitting.current = true;
    setIsLoading(true);
    let redirected = false;
    try {
      const result = await handleCheckout("mercadoPagoPayment", {}, { paymentSettings });
      redirected = result?.success === true && result.message === "Redirected";
      if (!redirected) onError();
      // handleCheckout navigates to Mercado Pago. Do not navigate to Orders
      // via the modal's success handler and overwrite that redirect.
    } catch {
      onError();
    } finally {
      if (!redirected) {
        submitting.current = false;
        setIsLoading(false);
      }
    }
  };

  return (
    <Button
      color="primary"
      className="w-full"
      isLoading={isLoading}
      isDisabled={!enabled || isLoading}
      onPress={() => void pay()}
    >
      {t("payments.mercadoPago.pay")}
    </Button>
  );
}
