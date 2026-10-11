import { handleCheckout } from "@/helpers/functionalHelpers";
import { Button } from "@heroui/react";
import { FC } from "react";

const XenditPayment: FC<{
  onSuccess: () => void;
  onError: () => void;
  setIsLoading: (value: boolean) => void;
  isLoading: boolean;
}> = ({ onSuccess, onError, isLoading, setIsLoading }) => {
  const handlePayment = async () => {
    setIsLoading(true);

    try {
      await handleCheckout("xenditPayment", {});

      onSuccess();
    } catch (err) {
      console.error(err);
      onError();
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Button color="primary" isLoading={isLoading} onPress={handlePayment} className="w-full">
      Pay With Xendit
    </Button>
  );
};

export default XenditPayment;
