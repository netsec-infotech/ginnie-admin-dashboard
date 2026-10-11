import { getOrderStatusBtnConfig } from "@/helpers/getters";
import { Order } from "@/types/ApiResponse";
import { Avatar, Button, Card, CardBody, CardHeader, Chip } from "@heroui/react";
import { CalendarClock, Edit, Phone, Star, Truck } from "lucide-react";
import React, { FC } from "react";
import { useTranslation } from "react-i18next";

/**
 * Format the backend's plain `YYYY-MM-DD` for display.
 *
 * Parsed field-by-field rather than via `new Date("2026-07-28")`, which JS
 * reads as UTC midnight — in any negative-offset timezone that renders as the
 * previous day, showing the customer the wrong delivery date.
 */
const formatScheduledDate = (value: string): string => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return value;

  const [, year, month, day] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day));
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

interface DeliveryInfoProps {
  order: Order;
  onDeliveryRatingOpen: () => void;
}
// DeliveryInfo component
const DeliveryInfo: FC<DeliveryInfoProps> = ({
  order,
  onDeliveryRatingOpen,
}) => {
  const buttonConfig = getOrderStatusBtnConfig(order.status);
  const { t } = useTranslation();

  const isScheduled = !!order.is_scheduled_delivery;
  // 0/1 from the backend rather than a real boolean.
  const isRush = !!Number(order.is_rush_order);
  const isPickup = order.delivery_type === "pickup";

  return (
    <>
      <Card shadow="sm" radius="sm">
        <CardHeader className="pb-2">
          <div className="flex items-center gap-2">
            <Truck className="w-4 h-4 text-gray-600 dark:text-gray-300" />
            <h3 className="text-sm font-medium text-gray-900 dark:text-gray-100">
              {t("delivery_info")}
            </h3>
          </div>
        </CardHeader>
        <CardBody className="pt-0">
          <div className="space-y-2 text-xs">
            {/*
              Rendered as plain label/value rows like every other card in this
              view. The status accent goes on a Chip, matching PaymentInfo,
              rather than a tinted sub-panel — nothing else here nests a box
              inside the card body.
            */}
            <div className="flex justify-between items-center gap-2">
              <span className="text-gray-600 dark:text-gray-300">
                {t("deliveryType")}
              </span>
              <div className="flex gap-1 flex-wrap justify-end">
                {isRush && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color="warning"
                    radius="sm"
                    classNames={{ content: "text-xs" }}
                    title={t("rushDelivery")}
                  >
                    {t("rushDelivery")}
                  </Chip>
                )}
                <Chip
                  size="sm"
                  variant="flat"
                  color={isScheduled ? "primary" : "default"}
                  radius="sm"
                  classNames={{ content: "text-xs" }}
                  startContent={
                    isScheduled ? (
                      <CalendarClock className="w-3 h-3" />
                    ) : undefined
                  }
                  title={
                    isScheduled ? t("scheduledDelivery") : order.delivery_type
                  }
                >
                  {isScheduled ? t("scheduledDelivery") : order.delivery_type}
                </Chip>
              </div>
            </div>

            {isScheduled && order.scheduled_date && (
              <div className="flex justify-between items-center gap-2">
                <span className="text-gray-600 dark:text-gray-300">
                  {t("scheduledDate")}
                </span>
                <span className="text-gray-900 dark:text-gray-100 text-right">
                  {formatScheduledDate(order.scheduled_date)}
                </span>
              </div>
            )}

            {isScheduled && order.scheduled_time_slot_label && (
              <div className="flex justify-between items-center gap-2">
                <span className="text-gray-600 dark:text-gray-300">
                  {t("scheduledTimeSlot")}
                </span>
                <span className="text-gray-900 dark:text-gray-100 text-right">
                  {order.scheduled_time_slot_label}
                </span>
              </div>
            )}

            <div className="flex justify-between items-center gap-2">
              <span className="text-gray-600 dark:text-gray-300">
                {t("fulfillmentType")}
              </span>
              <span className="text-gray-900 dark:text-gray-100 capitalize">
                {order.fulfillment_type}
              </span>
            </div>

            {/*
              A scheduled window supersedes the "X mins" estimate — showing
              both would give the customer two different answers.
            */}
            {buttonConfig.deliveryTime && !isScheduled ? (
              <div className="flex justify-between">
                <span className="text-gray-600 dark:text-gray-300">
                  {t("estimatedDeliveryTime")}
                </span>
                <span className="text-gray-900 dark:text-gray-100">
                  {order.estimated_delivery_time
                    ? `${order.estimated_delivery_time} ${t("mins")}`
                    : "TBD"}
                </span>
              </div>
            ) : null}

            {isPickup && order.pickup_instructions && (
              <div className="pt-1">
                <span className="text-gray-600 dark:text-gray-300 block mb-0.5">
                  {t("pickupInstructions")}
                </span>
                <span className="text-gray-900 dark:text-gray-100">
                  {order.pickup_instructions}
                </span>
              </div>
            )}
          </div>
        </CardBody>
      </Card>
      {order.delivery_boy_id && (
        <Card shadow="sm" radius="sm">
          <CardHeader className="pb-2 flex justify-between w-full items-start">
            <div className="flex items-center gap-2">
              <Truck className="w-4 h-4 text-gray-600 dark:text-gray-300" />
              <h3 className="text-sm font-medium text-gray-900 dark:text-gray-100">
                {t("deliveryPartner")}
              </h3>
            </div>
            {buttonConfig.review &&
              order.delivery_boy_id &&
              !order.is_delivery_feedback_given && (
                <Button
                  size="sm"
                  color="warning"
                  variant="flat"
                  className="text-xs"
                  onPress={onDeliveryRatingOpen}
                  startContent={<Truck className="w-4 h-4" />}
                  title={t("deliveryReview")}
                >
                  {t("deliveryReview")}
                </Button>
              )}
          </CardHeader>
          <CardBody className="pt-0">
            <div className="flex items-start gap-3">
              <Avatar
                showFallback
                src={order.delivery_boy_profile}
                name={order.delivery_boy_name}
                size="sm"
                className="shrink-0 w-10 h-10 text-xs cursor-pointer"
                title={order.delivery_boy_name}
                onClick={() => {
                  if (order.delivery_boy_profile) {
                    window.open(order.delivery_boy_profile, "_blank");
                  }
                }}
              />
              <div className="flex-1 space-y-2">
                <div className="text-sm font-medium">
                  {order.delivery_boy_name}
                </div>
                {order.delivery_boy_phone && (
                  <div className="flex items-center gap-1 text-xs">
                    <Phone className="w-3.5 h-3.5 text-gray-400" />
                    <span>{order.delivery_boy_phone}</span>
                  </div>
                )}
                {order.delivery_feedback && (
                  <div className="flex items-start w-full justify-between gap-2 p-2 bg-green-50 dark:bg-green-900/20 rounded-md">
                    <div className="flex items-start gap-2">
                      <div className="flex items-center gap-1">
                        <Star className="w-3 h-3 fill-yellow-400 text-yellow-400" />
                        <span className="text-xs font-medium">
                          {order.delivery_feedback.rating}/5
                        </span>
                      </div>
                      <div className="flex flex-col gap-0.5">
                        <div className="text-xs">
                          {order.delivery_feedback.title}
                        </div>
                        <div className="text-xxs text-foreground/50 block ml-1">
                          {order.delivery_feedback.description}
                        </div>
                      </div>
                    </div>
                    <Button
                      isIconOnly
                      variant="light"
                      color="primary"
                      size="sm"
                      className="p-0"
                      onPress={onDeliveryRatingOpen}
                      startContent={<Edit className="w-4 h-4" />}
                    />
                  </div>
                )}
              </div>
            </div>
          </CardBody>
        </Card>
      )}
    </>
  );
};

export default DeliveryInfo;
