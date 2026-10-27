import { useSettings } from "@/contexts/SettingsContext";
import { handleCheckout } from "@/helpers/functionalHelpers";
import { Button } from "@heroui/react";
import { FC, useRef } from "react";

const XenditPayment: FC<{
  onSuccess: () => void;
  onError: () => void;
  setIsLoading: (value: boolean) => void;
  isLoading: boolean;
}> = ({ onError, isLoading, setIsLoading }) => {
  const { paymentSettings } = useSettings();
  const submitting = useRef(false);
  const handlePayment = async () => {
    if (submitting.current || isLoading) return;
    submitting.current = true;
    setIsLoading(true);
    let redirected = false;

    try {
      const result = await handleCheckout("xenditPayment", {}, { paymentSettings });

      redirected = result?.success === true && result.message === "Redirected";
      // The gateway redirect owns navigation; opening Orders here cancels it.
      if (!redirected) onError();
    } catch (err) {
      console.error(err);
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
      isLoading={isLoading}
      isDisabled={isLoading}
      onPress={handlePayment}
      className="w-full"
    >
      Pay With Xendit
    </Button>
  );
};

export default XenditPayment;
