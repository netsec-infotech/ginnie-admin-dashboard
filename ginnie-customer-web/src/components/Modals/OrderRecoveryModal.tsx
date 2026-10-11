import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Chip,
  Spinner,
  addToast,
} from "@heroui/react";
import { AnimatePresence, motion } from "framer-motion";
import { Clock } from "lucide-react";
import { useTranslation } from "react-i18next";
import {
  cancelFlaggedOrder,
  getOrderRecoverySlots,
  getScheduledDeliveryDates,
  rescheduleOrder,
} from "@/routes/api";
import { toRescheduleParams } from "@/helpers/orderRecovery";
import {
  DeliverySlot,
  ScheduleDate,
  SlotPeriod,
  adaptCartSlots,
  formatFullDate,
  getFirstSelectableDate,
  getScheduleDatesFromConfig,
  groupSlotsByPeriod,
} from "@/helpers/deliverySlots";
import ScheduleCalendar from "@/components/Cart/ScheduleCalendar";
import {
  PeriodGroup,
  slotGroupContainer,
  slotGroupItem,
} from "@/components/Cart/ScheduledDelivery";

/** The store's scheduling rules, as inlined on the order detail response. */
export interface RecoveryStoreConfig {
  is_scheduled_delivery_enabled?: boolean;
  max_advance_booking_days?: number;
  /** Full day names, e.g. ["Monday", "Friday"]; unlisted days are closed. */
  available_days?: string[];
}

interface OrderRecoveryModalProps {
  isOpen: boolean;
  onClose: () => void;
  orderId: number | string;
  /**
   * The store's scheduling rules, when the order response already carries
   * them (`order.store`). Shapes the calendar with zero extra requests:
   * closed weekdays struck out, window capped — the same rules checkout
   * applies.
   */
  storeConfig?: RecoveryStoreConfig | null;
  /**
   * Fallback when `storeConfig` is absent: the store whose rules to FETCH
   * (`/scheduled-delivery/stores/{id}/dates`). Null when the order can't
   * resolve one; the calendar then falls back to a neutral open window and
   * lets the slots endpoint refuse invalid days.
   */
  storeId?: number | null;
  /**
   * Offer "Cancel Order" alongside rescheduling. On for the flagged-order
   * recovery flow (the customer may prefer a refund over a new window); off
   * for a voluntary reschedule, where cancellation has its own flow.
   */
  showCancel?: boolean;
  /** Refresh the order after a successful reschedule or cancellation. */
  onRecovered?: () => void;
}

/**
 * The modal's stateful body. Split out so it can be remounted per open via
 * conditional rendering — that resets the whole selection for free, instead of
 * clearing six pieces of state in an effect (which cascades renders and trips
 * react-hooks/set-state-in-effect). It also means dates/slots are only ever
 * fetched while the modal is actually open, and `now` is pinned per open so
 * availability stays stable for the lifetime of the modal.
 */
const RecoveryBody: React.FC<Omit<OrderRecoveryModalProps, "isOpen">> = ({
  onClose,
  orderId,
  storeConfig,
  storeId,
  showCancel = true,
  onRecovered,
}) => {
  const { t } = useTranslation();

  /**
   * Computed once per open (the body remounts each open). Rules already on
   * the order (`storeConfig`) build the calendar synchronously; with neither
   * config nor store id, the neutral open window stands in. Only the
   * config-less-but-id case defers to the fetch effect below, and those
   * placeholders are replaced before anything renders past the spinner.
   */
  const [initial] = useState(() => {
    const now = new Date();
    const dates = storeConfig
      ? getScheduleDatesFromConfig(now, storeConfig)
      : storeId
        ? null
        : getScheduleDatesFromConfig(now, {});
    return {
      now,
      dates: dates ?? [],
      firstDate: dates ? (getFirstSelectableDate(dates)?.date ?? null) : null,
    };
  });

  const [dates, setDates] = useState<ScheduleDate[]>(initial.dates);
  const [activeDate, setActiveDate] = useState<string | null>(
    initial.firstDate,
  );
  const [slots, setSlots] = useState<DeliverySlot[]>([]);
  const [slotsMessage, setSlotsMessage] = useState<string | null>(null);
  const [draftSlot, setDraftSlot] = useState<DeliverySlot | null>(null);

  const [loadingDates, setLoadingDates] = useState(!storeConfig && !!storeId);
  // Starts true when a date was preselected: the slots fetch begins on mount,
  // so the first paint is the skeleton rather than a flash of "no times".
  const [loadingSlots, setLoadingSlots] = useState(!!initial.firstDate);
  const [submitting, setSubmitting] = useState(false);
  const [confirmingCancel, setConfirmingCancel] = useState(false);

  /**
   * The store's scheduling rules — the SAME config checkout's picker uses, so
   * a weekday the store is closed on is struck out here too, and the window
   * is capped at the store's max advance-booking days. Skipped entirely when
   * the order already carried the rules (`storeConfig`). A failed lookup (or
   * a store with no config) falls back to the neutral open window rather than
   * dead-ending the customer: the recovery slots endpoint still refuses any
   * day the store can't serve.
   */
  useEffect(() => {
    if (storeConfig || !storeId) return;
    let cancelled = false;

    getScheduledDeliveryDates(storeId).then((res) => {
      if (cancelled) return;

      const list = getScheduleDatesFromConfig(
        initial.now,
        res.success && res.data ? res.data : {},
      );
      setDates(list);

      const first = getFirstSelectableDate(list);
      if (first) {
        setActiveDate(first.date);
        setLoadingSlots(true);
      }
      setLoadingDates(false);
    });

    // The modal can be closed mid-flight; without this the response would land
    // after unmount and warn about setting state on a dead component.
    return () => {
      cancelled = true;
    };
  }, [storeConfig, storeId, initial.now]);

  // Fetch the windows for the picked date from the order-scoped recovery
  // endpoint — it resolves the store itself, unlike the /slots used at
  // checkout. Effect (not a click handler) so an in-flight response for a
  // previous date can be discarded when the customer clicks faster than the
  // network answers.
  useEffect(() => {
    if (!activeDate) return;
    let cancelled = false;

    getOrderRecoverySlots(orderId, activeDate)
      .then((res) => {
        if (cancelled) return;
        setSlots(
          res.success ? adaptCartSlots(res.data?.slots, activeDate) : [],
        );
        setSlotsMessage(res.data?.message ?? null);
      })
      .finally(() => {
        if (!cancelled) setLoadingSlots(false);
      });

    return () => {
      cancelled = true;
    };
  }, [orderId, activeDate]);

  const pickDate = useCallback(
    (date: string) => {
      // Re-clicking the current date must be a no-op: it wouldn't re-run the
      // fetch effect, so flipping loading on here would strand the spinner.
      if (date === activeDate) return;
      setActiveDate(date);
      // A slot belongs to its date — carrying one across dates could book a
      // window the customer never saw.
      setDraftSlot(null);
      // Loading flips on here, not in the fetch effect: activeDate only
      // changes through this handler (or the async dates callback above), and
      // the lint rule (set-state-in-effect) forbids the synchronous set inside
      // the effect body.
      setLoadingSlots(true);
    },
    [activeDate],
  );

  const grouped = useMemo(() => groupSlotsByPeriod(slots), [slots]);
  const availableCount = slots.filter((s) => s.isAvailable).length;
  const earliestId = slots.find((s) => s.isAvailable)?.id ?? null;

  const submitReschedule = async () => {
    // Empty-string fallbacks are deliberate: a slot missing either time makes
    // toRescheduleParams return null, so a half-identified window can never
    // reach the API.
    const params = toRescheduleParams(
      activeDate ?? "",
      draftSlot
        ? {
            start_time: draftSlot.startTime ?? "",
            end_time: draftSlot.endTime ?? "",
          }
        : null,
    );
    // Guards the button's own disabled state.
    if (!params) return;

    setSubmitting(true);
    const res = await rescheduleOrder(orderId, params);
    setSubmitting(false);

    if (res.success) {
      addToast({
        color: "success",
        title: res.message || t("order_recovery.reschedule_success"),
      });
      onRecovered?.();
      onClose();
      return;
    }

    // Surface the backend's reason — it knows why (slot just filled, deadline
    // passed) and the customer is owed that rather than a generic failure.
    addToast({
      color: "danger",
      title: res.message || t("order_recovery.reschedule_error"),
    });
  };

  const submitCancel = async () => {
    setSubmitting(true);
    const res = await cancelFlaggedOrder(orderId);
    setSubmitting(false);

    if (res.success) {
      addToast({
        color: "success",
        title: res.message || t("order_recovery.cancel_success"),
      });
      onRecovered?.();
      onClose();
      return;
    }

    addToast({
      color: "danger",
      title: res.message || t("order_recovery.cancel_error"),
    });
  };

  return (
    <ModalContent>
      <ModalHeader className="flex flex-col gap-1 px-6 pb-4 pt-5">
        <span className="text-base leading-tight">
          {t("order_recovery.modal_title")}
        </span>
        <span className="text-xs font-normal text-default-500">
          {t(
            "schedule.modalSubtitle",
            "Choose a day and a delivery window that works for you",
          )}
        </span>
      </ModalHeader>

      <ModalBody className="px-6 pb-2 pt-0">
        {confirmingCancel ? (
          <div className="flex flex-col gap-2 py-2">
            <p className="font-medium">{t("order_recovery.cancel_confirm")}</p>
            <p className="text-sm text-foreground/60">
              {t("order_recovery.cancel_confirm_body")}
            </p>
          </div>
        ) : loadingDates ? (
          <div className="flex justify-center py-16">
            <Spinner />
          </div>
        ) : dates.length === 0 ? (
          <p className="py-8 text-sm text-foreground/60">
            {t("order_recovery.no_dates")}
          </p>
        ) : (
          /* Same two-pane layout as the checkout picker: calendar left, slots
             right, split by a logical border that holds under RTL. */
          <div className="grid gap-6 sm:grid-cols-[minmax(0,304px)_1fr] sm:gap-0">
            <div
              role="radiogroup"
              aria-label={t("schedule.pickDate", "Pick a date")}
              className="sm:self-center sm:pe-8"
            >
              <ScheduleCalendar
                dates={dates}
                activeDate={activeDate}
                onSelect={pickDate}
              />
            </div>

            {/* Slots */}
            <div className="min-h-[260px] space-y-5 sm:border-s sm:border-default-100 sm:ps-8">
              <div className="flex items-start justify-between gap-3 pb-1">
                <div className="min-w-0">
                  <span className="block text-small font-semibold">
                    {t("schedule.selectSlot", "Select a time")}
                  </span>
                  {activeDate && (
                    <span className="block truncate text-xs text-default-500">
                      {formatFullDate(activeDate)}
                    </span>
                  )}
                </div>
                <Chip
                  size="sm"
                  variant="flat"
                  color={availableCount > 0 ? "success" : "default"}
                  classNames={{ content: "text-xxs font-medium" }}
                >
                  {t("schedule.available", {
                    count: availableCount,
                    defaultValue: `${availableCount} available`,
                  })}
                </Chip>
              </div>

              {loadingSlots ? (
                <div className="space-y-4">
                  {[0, 1].map((group) => (
                    <div key={group} className="space-y-2.5">
                      <div className="h-3 w-20 rounded bg-default-100" />
                      <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        {[0, 1, 2].map((i) => (
                          <div
                            key={i}
                            className="h-[42px] animate-pulse rounded-lg bg-default-100"
                          />
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              ) : slots.length === 0 ? (
                /* No windows served at all — slot scheduling is off for this
                   store, so the date alone is the whole choice and Confirm
                   stays enabled. Distinct from "all sold out" below. */
                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-default-200 py-10 text-center">
                  <Clock className="h-6 w-6 text-default-300" />
                  <p className="text-xs text-default-500">
                    {slotsMessage ||
                      (activeDate
                        ? t("order_recovery.date_only", {
                            date: formatFullDate(activeDate),
                            defaultValue: `Your order will be delivered on ${formatFullDate(activeDate)}.`,
                          })
                        : t("order_recovery.no_slots"))}
                  </p>
                </div>
              ) : availableCount === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-default-200 py-10 text-center">
                  <Clock className="h-6 w-6 text-default-300" />
                  <p className="text-xs text-default-500">
                    {slotsMessage || t("order_recovery.no_slots")}
                  </p>
                  <p className="text-xxs text-default-400">
                    {t("schedule.tryAnother", "Try another date")}
                  </p>
                </div>
              ) : (
                // Re-keyed on the date so switching days replays the stagger —
                // the panel visibly responds instead of silently swapping.
                <AnimatePresence mode="wait" initial={false}>
                  <motion.div
                    key={activeDate}
                    className="space-y-4"
                    role="radiogroup"
                    aria-label={t("schedule.selectSlot", "Select a time")}
                    variants={slotGroupContainer}
                    initial="hidden"
                    animate="show"
                    exit="hidden"
                  >
                    {(["morning", "afternoon", "evening"] as SlotPeriod[]).map(
                      (period) => (
                        <motion.div key={period} variants={slotGroupItem}>
                          <PeriodGroup
                            period={period}
                            slots={grouped[period]}
                            selectedSlotId={draftSlot?.id ?? null}
                            earliestId={earliestId}
                            onSelect={setDraftSlot}
                            earliestLabel={t("schedule.earliest", "Earliest")}
                          />
                        </motion.div>
                      ),
                    )}
                  </motion.div>
                </AnimatePresence>
              )}
            </div>
          </div>
        )}
      </ModalBody>

      <ModalFooter
        className={`flex ${showCancel ? "justify-between" : "justify-end"} gap-2 px-6 pb-5 pt-4`}
      >
        {confirmingCancel ? (
          <>
            <Button
              variant="bordered"
              onPress={() => setConfirmingCancel(false)}
              isDisabled={submitting}
            >
              {t("order_recovery.cancel_keep")}
            </Button>
            <Button
              color="danger"
              onPress={submitCancel}
              isLoading={submitting}
            >
              {t("order_recovery.cancel_cta")}
            </Button>
          </>
        ) : (
          <>
            {showCancel && (
              <Button
                color="danger"
                variant="light"
                onPress={() => setConfirmingCancel(true)}
                isDisabled={submitting}
              >
                {t("order_recovery.cancel_cta")}
              </Button>
            )}
            <Button
              color="primary"
              className="text-white"
              onPress={submitReschedule}
              isLoading={submitting}
              // A date with visible windows needs one picked; a date the
              // backend serves no slots for may still be rescheduled
              // date-only (slot scheduling disabled server-side).
              isDisabled={
                !activeDate ||
                loadingDates ||
                loadingSlots ||
                (slots.length > 0 && !draftSlot)
              }
            >
              {draftSlot
                ? `${t("order_recovery.reschedule_submit")} · ${draftSlot.label}`
                : t("order_recovery.reschedule_submit")}
            </Button>
          </>
        )}
      </ModalFooter>
    </ModalContent>
  );
};

/**
 * Pick a new delivery window for an order — either because the backend flagged
 * it `needs_reschedule` (recovery, with "Cancel Order" offered alongside) or
 * because the customer chose to reschedule voluntarily (`showCancel={false}`).
 *
 * Renders the SAME picker as the checkout ScheduledDelivery modal — the month
 * calendar plus morning/afternoon/evening slot chips — so rescheduling looks
 * exactly like scheduling did at order time, including the store's own rules:
 * closed weekdays are struck out and the window is capped at the store's max
 * advance-booking days (via `/scheduled-delivery/stores/{id}/dates`). The
 * order-scoped recovery slots endpoint remains the authority on each day.
 */
const OrderRecoveryModal: React.FC<OrderRecoveryModalProps> = ({
  isOpen,
  onClose,
  orderId,
  storeConfig,
  storeId,
  showCancel = true,
  onRecovered,
}) => (
  <Modal
    isOpen={isOpen}
    onClose={onClose}
    size="3xl"
    scrollBehavior="inside"
    placement="center"
    // Anchor the ✕ to `foreground` (the one colour the theme pins per mode)
    // so it stays visible on both the light and dark modal surface.
    classNames={{
      closeButton:
        "text-foreground/70 hover:text-foreground hover:bg-default-100 z-10",
    }}
  >
    {/* Mounted only while open, so each open starts from a clean selection and
        no dates/slots are fetched for a modal the customer never opened. */}
    {isOpen && (
      <RecoveryBody
        onClose={onClose}
        orderId={orderId}
        storeConfig={storeConfig}
        storeId={storeId}
        showCancel={showCancel}
        onRecovered={onRecovered}
      />
    )}
  </Modal>
);

export default OrderRecoveryModal;
