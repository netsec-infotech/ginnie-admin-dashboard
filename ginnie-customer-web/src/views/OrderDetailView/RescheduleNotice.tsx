import React, { useEffect, useMemo, useState } from "react";
import { Button } from "@heroui/react";
import { AlertTriangle } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Order } from "@/types/ApiResponse";
import {
  canRecoverOrder,
  formatTimeRemaining,
  getRecoveryStoreId,
  isFlaggedForReschedule,
  isRecoveryExpired,
} from "@/helpers/orderRecovery";
import OrderRecoveryModal from "@/components/Modals/OrderRecoveryModal";

interface RescheduleNoticeProps {
  order: Order;
  /** Refresh the order once it's been rescheduled or cancelled. */
  onRecovered?: () => void;
}

/**
 * Banner for an order the backend flagged `needs_reschedule` — stock changed,
 * the store closed, or its hours moved.
 *
 * Renders nothing at all for an unflagged order, which is every order on a
 * backend that doesn't serve the recovery feature.
 */
const RescheduleNotice: React.FC<RescheduleNoticeProps> = ({
  order,
  onRecovered,
}) => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);

  /**
   * The deadline is compared against a clock that has to advance, otherwise a
   * page left open would keep offering a reschedule the backend now refuses.
   * A minute's granularity matches the coarse countdown copy.
   */
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 60_000);
    return () => clearInterval(id);
  }, []);

  // Shapes the modal's calendar with the store's own scheduling rules; null
  // just means the modal falls back to a neutral window. The id is only a
  // fetch fallback for order payloads without the inlined `store` rules.
  const storeId = useMemo(
    () => order.store?.id ?? getRecoveryStoreId(order),
    [order],
  );

  // Not flagged: nothing to say. Checked before the deadline so an ordinary
  // order never renders this.
  if (!isFlaggedForReschedule(order)) return null;

  const expired = isRecoveryExpired(order, now);
  const canAct = canRecoverOrder(order, now);
  const remaining = formatTimeRemaining(order, now);

  return (
    <>
      <div className="mb-4 rounded-lg border border-warning-300 bg-warning-50 dark:bg-warning-100/10 p-3">
        <div className="flex items-start gap-3">
          <AlertTriangle className="w-4 h-4 mt-0.5 text-warning-600 shrink-0" />

          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium">
              {t("order_recovery.banner_title")}
            </p>

            {/* The backend's reason is English-only, but it's specific to this
                order ("Store is temporarily closed") and more useful than a
                generic line, so it's shown when present. */}
            {order.reschedule_reason && (
              <p className="text-xs text-foreground/70 mt-0.5">
                {order.reschedule_reason}
              </p>
            )}

            <p className="text-xs text-foreground/70 mt-1">
              {expired
                ? t("order_recovery.deadline_passed")
                : remaining
                  ? t("order_recovery.deadline", { time: remaining })
                  : null}
            </p>
          </div>

          {canAct && (
            <Button
              size="sm"
              color="warning"
              variant="solid"
              className="shrink-0"
              onPress={() => setIsOpen(true)}
            >
              {t("order_recovery.reschedule_cta")}
            </Button>
          )}
        </div>
      </div>

      {/* Mounted only once actionable, so the modal never fetches slots for an
          order that can no longer be changed. */}
      {canAct && (
        <OrderRecoveryModal
          isOpen={isOpen}
          onClose={() => setIsOpen(false)}
          orderId={order.id}
          storeConfig={order.store}
          storeId={storeId}
          onRecovered={onRecovered}
        />
      )}
    </>
  );
};

export default RescheduleNotice;
