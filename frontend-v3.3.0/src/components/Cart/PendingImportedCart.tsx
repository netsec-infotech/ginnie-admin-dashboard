import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Button, Card, CardBody, CardHeader } from "@heroui/react";
import { useSettings } from "@/contexts/SettingsContext";
import { updateCartData } from "@/helpers/updators";
import CartAdditionalInfo from "@/views/CartPageView/CartAdditionalInfo";
import CartItems from "@/views/CartPageView/CartItems";
import type { ImportedCart } from "@/types/sharedCart";
import ShareCartButton from "@/components/Cart/ShareCartButton";

export default function PendingImportedCart({ cart }: { cart: ImportedCart }) {
  const { t } = useTranslation();
  const { currencySymbol } = useSettings();
  const [loading, setLoading] = useState(false);
  const refresh = async () => {
    if (loading) return;
    setLoading(true);
    try {
      await updateCartData(true, false, 0, false, false);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col gap-4 md:flex-row">
      <div className="w-full md:w-[60%] lg:w-[65%]"><CartAdditionalInfo cart={cart} /></div>
      <Card className="w-full md:w-[40%] lg:w-[35%] h-fit" shadow="sm">
        <CardHeader className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="shrink-0 font-semibold">{t("checkout.yourOrder")}</h2>
          <ShareCartButton compact />
        </CardHeader>
        <CardBody className="gap-4">
          <p role="status" className="text-sm">{t("cartSharing.refreshNeeded")}</p>
          <Button color="primary" onPress={refresh} isLoading={loading}>{t("cartSharing.refreshCart")}</Button>
          <CartItems items={cart.items} currencySymbol={currencySymbol} currencyContext={cart} />
        </CardBody>
      </Card>
    </div>
  );
}
