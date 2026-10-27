import { money } from "@/helpers/currency";
import { updateCartData } from "@/helpers/updators";
import { useSettings } from "@/contexts/SettingsContext";
import { setRusDelivery } from "@/lib/redux/slices/checkoutSlice";
import { RootState } from "@/lib/redux/store";
import { Card, CardBody, Switch } from "@heroui/react";
import { Truck } from "lucide-react";
import React, { useState } from "react";
import { useTranslation } from "react-i18next";
import { useDispatch, useSelector } from "react-redux";

const ExpressDelivery: React.FC = () => {
  const { selectedAddress, scheduledDelivery } = useSelector(
    (state: RootState) => state.checkout,
  );
  const isLoading = useSelector((state: RootState) => state.cart.isLoading);
  const { cartData, deliveryLocationNeedsSelection } = useSelector(
    (state: RootState) => state.cart,
  );
  const { currencySymbol } = useSettings();
  const quotedCharge = cartData?.payment_summary?.rush_delivery_charge;
  const rushCharge = Number(quotedCharge);
  const hasRushQuote = !!selectedAddress?.id && !deliveryLocationNeedsSelection &&
    cartData?.payment_summary?.is_rush_delivery_available &&
    quotedCharge != null && Number.isFinite(rushCharge) && rushCharge >= 0;

  const [isRushEnabled, setIsRushEnabled] = useState(false);
  const [isRushAvailable, setIsRushAvailable] = useState(false);

  const { t } = useTranslation();

  const dispatch = useDispatch();

  /**
   * Rush means "as soon as possible"; a booked window means "at this time".
   * They contradict, and the backend takes both fields independently — so the
   * exclusion has to be enforced here.
   */
  const isScheduled = !!scheduledDelivery;

  const handleRushSwitchToggle = async (checked: boolean) => {
    setIsRushEnabled(checked);

    dispatch(setRusDelivery(checked));

    setTimeout(async () => {
      const res = await updateCartData(true, false);

      if (
        !res?.data?.payment_summary?.is_rush_delivery_available &&
        checked &&
        selectedAddress?.id
      ) {
        await handleRushSwitchToggle(false);
        setIsRushAvailable(false);
      }
    }, 500);
  };

  const makeRushDeliveryOff = async () => {
    handleRushSwitchToggle(false);
  };

  const makeRushDeliveryAvailable = async () => {
    setIsRushAvailable(true);
  };

  return (
    <Card
      className="w-full border border-black/10 bg-white/95 text-black shadow-sm backdrop-blur-md dark:border-white/15 dark:bg-black/90 dark:text-white"
      radius="md"
      shadow="none"
    >
      <CardBody className="space-y-4">
        <button
          onClick={makeRushDeliveryOff}
          className="hidden"
          id="rush-delivery-off"
        />
        <button
          onClick={makeRushDeliveryAvailable}
          className="hidden"
          id="rush-delivery-available"
        />
        <div className="flex items-center justify-between gap-3">
          <div className="flex min-w-0 items-center gap-3">
            <div className="shrink-0 p-2 bg-success-100 rounded-lg">
              <Truck className="w-5 h-5 text-success" />
            </div>
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-semibold text-xs md:text-small">
                  {t("express_rushDelivery")}
                </p>
                {hasRushQuote && (
                  <span
                    className="rounded-full bg-black px-2 py-1 text-xs font-semibold text-white dark:bg-white dark:text-black"
                    aria-live="polite"
                  >
                    {t("express_rushDeliveryCharge", {
                      amount: money({ ...cartData, ...cartData?.payment_summary }, "rush_delivery_charge", currencySymbol),
                    })}
                  </span>
                )}
              </div>
              {/* Say WHY it's unavailable — a dead switch with no reason
                  reads as a bug. */}
              <p className="text-xxs md:text-xs text-default-500">
                {isScheduled
                  ? t(
                      "express_unavailableScheduled",
                      "Not available with a scheduled delivery",
                    )
                  : t("express_rushDeliveryDescription")}
              </p>
              {!hasRushQuote && !isScheduled && (
                <p className="mt-1 text-xxs font-medium text-foreground/70 md:text-xs">
                  {!selectedAddress?.id || deliveryLocationNeedsSelection
                    ? t("express_rushDeliveryChargePending")
                    : t("express_rushDeliveryChargeUnavailable")}
                </p>
              )}
            </div>
          </div>
          <Switch
            className="shrink-0"
            isDisabled={isLoading || !isRushAvailable || isScheduled}
            // Force off while scheduled: `isRushEnabled` is local state, so it
            // could otherwise still read true from before the slot was picked.
            isSelected={isRushEnabled && !isScheduled}
            onValueChange={handleRushSwitchToggle}
            color="success"
            data-testid="rush-delivery-switch"
          />
        </div>
      </CardBody>
    </Card>
  );
};

export default ExpressDelivery;
