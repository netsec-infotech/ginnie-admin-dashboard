import { orderStatusPriority } from "@/config/constants";

type CancellationItem = {
  status?: string;
  product?: {
    is_cancelable?: boolean | number | string;
    cancelable_till?: string | null;
  } | null;
};

/** Item status owns cancellation in orders containing stores at different stages. */
export const canCancelOrderItem = (
  item: CancellationItem,
  orderStatus: string,
): boolean => {
  const enabled = item.product?.is_cancelable;
  if (enabled !== true && enabled !== 1 && enabled !== "1") return false;
  const normalize = (status: string) =>
    status === "approved" ? "accepted" : status;
  const status = normalize(item.status || orderStatus);
  const terminal = [
    "cancelled",
    "delivered",
    "returned",
    "rejected_by_seller",
    "rejected",
  ];
  if (terminal.includes(status) || terminal.includes(orderStatus)) return false;
  const current = orderStatusPriority[status];
  const limit =
    orderStatusPriority[
      normalize(item.product?.cancelable_till || "preparing")
    ];
  return current !== undefined && limit !== undefined && current <= limit;
};
