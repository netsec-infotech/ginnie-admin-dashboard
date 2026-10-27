import { Chip } from "@heroui/react";
import { Truck } from "lucide-react";
import { useTranslation } from "react-i18next";
import { getItemOtps, getOrderDeliveryOtp } from "@/helpers/orderOtp";
import type { Order, OrderItem } from "@/types/ApiResponse";

export default function OrderDeliveryOtp({
  order,
  item,
  className,
}: {
  order: Order;
  item?: OrderItem;
  className?: string;
}) {
  const { t } = useTranslation();
  const otp = item
    ? getItemOtps(item, order).deliveryOtp
    : getOrderDeliveryOtp(order);
  if (!otp) return null;

  return (
    <Chip
      className={className}
      size="sm"
      color="primary"
      variant="bordered"
      radius="sm"
      startContent={<Truck className="h-3 w-3" />}
      title={`${t("deliveryOtp")}: ${otp}`}
      classNames={{ content: "text-xxs sm:text-xs font-medium" }}
    >
      {t("deliveryOtp")}: {otp}
    </Chip>
  );
}
