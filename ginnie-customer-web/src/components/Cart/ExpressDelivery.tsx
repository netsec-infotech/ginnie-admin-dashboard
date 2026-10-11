import { updateCartData } from "@/helpers/updators";
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
    <Card className="w-full" radius="md" shadow="sm">
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
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-success-100 rounded-lg">
              <Truck className="w-5 h-5 text-success" />
            </div>
            <div>
              <div className="flex gap-2">
                <p className="font-semibold text-xs md:text-small">
                  {t("express_rushDelivery")}
                </p>
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
            </div>
          </div>
          <Switch
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
