import { handleAppleLogin } from "@/helpers/auth";
import { Button } from "@heroui/react";
import { Apple } from "lucide-react";
import { FC, useEffect, useState } from "react";
import { useTranslation } from "react-i18next";

interface AppleLoginBtnProps {
  isLoading: boolean;
  setIsLoading: (loading: boolean) => void;
  onOpenChange: () => void;
  context?: "login" | "register";
}

const AppleLoginBtn: FC<AppleLoginBtnProps> = ({
  isLoading,
  setIsLoading,
  onOpenChange,
  context = "login",
}) => {
  const { t } = useTranslation();
  const [isWindows, setIsWindows] = useState(false);

  useEffect(() => {
    if (typeof window !== "undefined") {
      const userAgent = window.navigator.userAgent.toLowerCase();
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setIsWindows(userAgent.includes("windows"));
    }
  }, []);

  if (isWindows) return null;

  return (
    <Button
      isDisabled={isLoading}
      variant="bordered"
      className="w-full font-medium"
      onPress={() => handleAppleLogin({ setIsLoading, onOpenChange, context })}
      startContent={<Apple size={20} className="text-foreground" />}
    >
      {t("continue_with_apple")}
    </Button>
  );
};

export default AppleLoginBtn;
