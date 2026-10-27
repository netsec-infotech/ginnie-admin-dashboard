import { FC, useEffect, useMemo, useState } from "react";
import {
  Button,
  Card,
  CardBody,
  Chip,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  useDisclosure,
} from "@heroui/react";
import { AnimatePresence, Variants, motion } from "framer-motion";
import { CalendarDays, Check, Clock, Sunrise, Sun, Moon, X } from "lucide-react";
import { useDispatch, useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import useSWR from "swr";

import { RootState } from "@/lib/redux/store";
import {
  clearScheduledDelivery,
  setScheduledDelivery,
} from "@/lib/redux/slices/checkoutSlice";
import {
  DeliverySlot,
  SlotPeriod,
  adaptCartSlots,
  adaptScheduledSlots,
  formatFullDate,
  formatScheduleSummary,
  getFirstSelectableDate,
  getScheduleDatesFromConfig,
  getScheduleDatesFromList,
  groupSlotsByPeriod,
} from "@/helpers/deliverySlots";
import {
  getCartDeliveryDates,
  getCartDeliverySlots,
  getScheduledDeliveryDates,
  getScheduledDeliverySlots,
} from "@/routes/api";
import { isCartScheduling } from "@/helpers/scheduling";
import {
  CartDeliveryDatesData,
  CartDeliverySlotsData,
  ScheduledDeliveryDatesData,
  ScheduledDeliverySlotsData,
} from "@/types/ApiResponse";
import ScheduleCalendar, { EASE } from "./ScheduleCalendar";

/**
 * Slot groups cascade in rather than appearing at once — enough to read as a
 * response to the date change, not a delay in front of it. All share the same
 * deceleration curve as the calendar so the two panes feel like one surface.
 * Framer skips these automatically under prefers-reduced-motion.
 */
export const slotGroupContainer: Variants = {
  // Exit is short on purpose: AnimatePresence `mode="wait"` plays it BEFORE the
  // incoming group, so anything slower here is just latency after a tap.
  hidden: { opacity: 0, transition: { duration: 0.1, ease: EASE } },
  show: { opacity: 1, transition: { staggerChildren: 0.035, delayChildren: 0.02 } },
};

export const slotGroupItem: Variants = {
  hidden: { opacity: 0, y: 6 },
  show: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.24, ease: EASE, staggerChildren: 0.022 },
  },
};

/** Individual chips ripple within their group. */
const slotChip: Variants = {
  hidden: { opacity: 0, scale: 0.96 },
  show: { opacity: 1, scale: 1, transition: { duration: 0.2, ease: EASE } },
};

const PERIOD_META: Record<
  SlotPeriod,
  { icon: typeof Sun; labelKey: string; fallback: string }
> = {
  morning: { icon: Sunrise, labelKey: "schedule.morning", fallback: "Morning" },
  afternoon: { icon: Sun, labelKey: "schedule.afternoon", fallback: "Afternoon" },
  evening: { icon: Moon, labelKey: "schedule.evening", fallback: "Evening" },
};

/**
 * One Morning/Afternoon/Evening block of slot chips. Exported for
 * OrderRecoveryModal, which renders the same picker for rescheduling.
 */
export const PeriodGroup: FC<{
  period: SlotPeriod;
  slots: DeliverySlot[];
  selectedSlotId: string | null;
  earliestId: string | null;
  onSelect: (slot: DeliverySlot) => void;
  earliestLabel: string;
}> = ({ period, slots, selectedSlotId, earliestId, onSelect, earliestLabel }) => {
  const { t } = useTranslation();
  const meta = PERIOD_META[period];
  const Icon = meta.icon;

  if (slots.length === 0) return null;

  return (
    <div className="space-y-2.5">
      <div className="flex items-center gap-1.5 text-default-500">
        <Icon className="h-3.5 w-3.5" />
        <span className="text-xxs font-medium uppercase tracking-wider">
          {t(meta.labelKey, meta.fallback)}
        </span>
      </div>

      <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
        {slots.map((slot) => {
          const isSelected = slot.id === selectedSlotId;
          return (
            <motion.button
              key={slot.id}
              variants={slotChip}
              whileTap={slot.isAvailable ? { scale: 0.96 } : undefined}
              type="button"
              role="radio"
              aria-checked={isSelected}
              disabled={!slot.isAvailable}
              onClick={() => onSelect(slot)}
              data-testid={`schedule-slot-${slot.id}`}
              // One exclusive branch: mixing border/bg utilities across two
              // template slots lets CSS source order decide the winner.
              className={`relative rounded-lg border px-3 py-2.5 text-xs font-medium transition-colors ${
                !slot.isAvailable
                  ? // Unavailable stays READABLE — the strike-through carries the
                    // meaning. Dimming text *and* opacity compounds to invisible
                    // on a dark surface.
                    "cursor-not-allowed border-default-100 bg-default-100/40 text-default-500 line-through"
                  : isSelected
                    ? "cursor-pointer border-primary bg-primary/10 text-primary"
                    : "cursor-pointer border-default-200 hover:border-default-400"
              }`}
            >
              <span className="flex items-center justify-center gap-1">
                <span className="flex flex-col items-center justify-center">
                  <span>{slot.label}</span>
                  {slot.isAvailable && slot.remainingCapacity !== undefined && (
                    <span className="text-[10px] font-normal tracking-wide opacity-75">
                      {t("schedule.capacityLeft", {
                        count: slot.remainingCapacity,
                        defaultValue: `${slot.remainingCapacity} left`,
                      })}
                    </span>
                  )}
                </span>
                {isSelected && <Check className="h-3 w-3 shrink-0" />}
              </span>

              {/* `text-success-foreground` rather than hardcoded white: white on
                  the bright success green is ~2:1. `success` isn't overridden in
                  the theme, so HeroUI guarantees a readable foreground for it.
                  Font stays on the scale (xxs = 10px), never an arbitrary px. */}
              {slot.id === earliestId && !isSelected && (
                <span className="absolute -top-2 end-1 rounded-full bg-success px-1.5 py-0.5 text-xxs font-semibold leading-none text-success-foreground shadow-sm">
                  {earliestLabel}
                </span>
              )}
            </motion.button>
          );
        })}
      </div>
    </div>
  );
};

/**
 * Scheduled delivery: pick a day, then one of the store's delivery windows.
 *
 * Backed by `/scheduled-delivery/stores/{id}/dates` (which weekdays, how far
 * ahead) and `/scheduled-delivery/stores/{id}/slots?date=` (the windows and
 * their live availability). Windows are whatever the store configured — they
 * are NOT a uniform hourly grid; the live data is 2h/2h/1h.
 *
 * A store with scheduling switched off shows nothing bookable rather than a
 * locally-invented fallback, so a customer can never pick a window the store
 * doesn't honour. The chosen slot's real id is kept on checkoutSlice as
 * `slotDbId`, ready for createOrder's `delivery_time_slot_id`.
 */
const ScheduledDelivery: FC = () => {
  const { t } = useTranslation();
  const dispatch = useDispatch();
  const scheduled = useSelector(
    (state: RootState) => state.checkout.scheduledDelivery,
  );
  const cartData = useSelector((state: RootState) => state.cart.cartData);
  const { isOpen, onOpen, onClose, onOpenChange } = useDisclosure();

  /**
   * A slot is one store's promise. Items from two stores can't share it —
   * they're picked and dispatched independently — so scheduling is blocked
   * until the cart is down to a single store.
   */
  const storeCount = useMemo(
    () => new Set((cartData?.items ?? []).map((item) => item.store_id)).size,
    [cartData?.items],
  );
  const isMultiStore = storeCount > 1;

  /**
   * The one store being ordered from, or null while the cart is empty/mixed.
   * The scheduled-delivery endpoints key off the numeric id — the slug 404s.
   */
  const storeId = isMultiStore
    ? null
    : (cartData?.items?.[0]?.store?.id ?? cartData?.items?.[0]?.store_id ?? null);

  /**
   * The store's scheduling config: which weekdays it delivers and how far ahead
   * it takes bookings. Says nothing about a specific day's availability — that
   * needs the slots endpoint below. A null key skips the request entirely, so a
   * mixed or empty cart never fetches.
   */
  /**
   * Returns `null` — not `undefined` — when the store has no scheduling config.
   *
   * The endpoint 422s for a store that doesn't offer scheduled delivery, and
   * `getScheduledDeliveryDates` turns that into `fallbackApiRes`, whose `data`
   * is undefined. Undefined is also what SWR reports while the request is in
   * flight, so the two were indistinguishable and a failed lookup fell through
   * to "enabled" — offering a Schedule button the store can't honour. The null
   * sentinel separates "asked and got nothing" from "still asking".
   */
  /**
   * Which contract the deployed backend speaks. The cart one returns explicit
   * dates and id-less slots; the store one returns rules and numbered slots.
   * See helpers/scheduling.ts — they are not interchangeable.
   */
  const useCartContract = isCartScheduling();

  const store = cartData?.items?.[0]?.store;
  const isScheduledDeliverySupported = store
    ? store.is_scheduled_delivery_enabled !== false &&
      store.is_scheduled_delivery !== false
    : true;

  const { data: config, isLoading: isConfigLoading } = useSWR(
    storeId && isScheduledDeliverySupported
      ? useCartContract
        ? "/cart/dates"
        : `/scheduled-delivery/stores/${storeId}/dates`
      : null,
    async () => {
      const res = useCartContract
        ? await getCartDeliveryDates()
        : await getScheduledDeliveryDates(storeId as number);
      return res.success && res.data ? res.data : null;
    },
    { revalidateOnFocus: false },
  );

  // `undefined` still means "not loaded yet" and must NOT read as disabled —
  // that would flash an error on the card before the request lands. `null` is a
  // settled answer: the store has no usable scheduling config.
  const isStoreDisabled = !isScheduledDeliverySupported
    ? true
    : config === undefined
    ? false
    : !config || !config.is_enabled;

  /**
   * The other half of the rush/scheduled exclusion (ExpressDelivery disables
   * its switch while a slot is booked). Both are blocked here rather than
   * silently overriding, so the customer sees the trade-off instead of having
   * their rush choice quietly dropped.
   */
  const isRushDelivery = useSelector(
    (state: RootState) => state.checkout.rushDelivery,
  );

  const canSchedule = !isMultiStore && !isStoreDisabled && !isRushDelivery;

  /**
   * A saved slot belongs to ONE store. It stops being valid when a second store
   * joins the cart, when the store turns scheduling off, or when the cart is
   * emptied and refilled from a different store — that last case matters now
   * that `slotId` is a real `delivery_time_slot_id`, since store B's id space
   * is unrelated to store A's and would otherwise be sent to checkout verbatim.
   */
  const isForAnotherStore =
    !!scheduled?.storeId && !!storeId && scheduled.storeId !== storeId;

  // Deliberately NOT keyed on `canSchedule`: that now includes the rush flag,
  // and `setRusDelivery` already clears the slot in the reducer. Reacting to it
  // here too would be a second, redundant path to the same state.
  const isScheduleInvalid = isMultiStore || isStoreDisabled || isForAnotherStore;

  useEffect(() => {
    if (scheduled && isScheduleInvalid) dispatch(clearScheduledDelivery());
  }, [isScheduleInvalid, scheduled, dispatch]);

  // Pinned when the picker opens: keeps slot availability stable while the
  // modal is open (a ticking clock must not re-grey a slot mid-selection).
  const [now, setNow] = useState<Date | null>(null);
  const [activeDate, setActiveDate] = useState<string | null>(null);
  const [draftSlot, setDraftSlot] = useState<DeliverySlot | null>(null);

  // Calendar days come from the store's rules; only /slots knows real
  // availability, so the grid marks closed days and leaves the rest open.
  const dates = useMemo(() => {
    if (!now || !config) return [];
    // The cart contract hands over the days themselves; the store contract
    // hands over rules that have to be expanded into days.
    return useCartContract
      ? getScheduleDatesFromList((config as CartDeliveryDatesData).dates, now)
      : getScheduleDatesFromConfig(
          now,
          config as ScheduledDeliveryDatesData,
        );
  }, [now, config, useCartContract]);

  /**
   * Slots for the selected day, fetched per date.
   *
   * These are the store's ACTUAL windows (2h/1h, irregular) with live
   * availability — the picker no longer invents a uniform 8am–9pm grid, so a
   * customer can't book a window the store never offered.
   */
  const { data: slotsData, isLoading: isSlotsLoading } = useSWR(
    storeId && activeDate
      ? useCartContract
        ? `/cart/slots?date=${activeDate}`
        : `/scheduled-delivery/stores/${storeId}/slots?date=${activeDate}`
      : null,
    async () =>
      (
        await (useCartContract
          ? getCartDeliverySlots(activeDate as string)
          : getScheduledDeliverySlots(storeId as number, activeDate as string))
      ).data,
    { revalidateOnFocus: false },
  );

  const slots = useMemo(() => {
    const date = activeDate ?? "";
    // Cart slots have no id and only `remaining_capacity` to go on; store slots
    // carry an id plus explicit availability flags.
    return useCartContract
      ? adaptCartSlots(
          (slotsData as CartDeliverySlotsData | undefined)?.slots,
          date,
        )
      : adaptScheduledSlots(
          (slotsData as ScheduledDeliverySlotsData | undefined)?.slots,
          date,
        );
  }, [slotsData, activeDate, useCartContract]);
  const grouped = useMemo(() => groupSlotsByPeriod(slots), [slots]);
  const availableCount = slots.filter((s) => s.isAvailable).length;
  const earliestId = slots.find((s) => s.isAvailable)?.id ?? null;
  // The backend explains a wholly-unavailable date ("Cannot schedule delivery
  // more than the allowed days in advance") — surface it rather than an
  // unexplained empty panel.
  const slotsMessage = slotsData?.message ?? null;

  const handleDateSelect = (date: string) => {
    setActiveDate(date);
    setDraftSlot(null);
  };

  /**
   * Resolve "now" when the picker opens — never during render or in an effect.
   * Reading the clock on open keeps SSR/client markup identical (no hydration
   * skew) and pins availability for the lifetime of the modal.
   */
  const handleOpen = () => {
    const current = new Date();
    // Same derivation as the render path — a mismatch here would preselect a
    // day the calendar then shows as closed, so both contracts branch alike.
    const upcoming = !config
      ? []
      : useCartContract
        ? getScheduleDatesFromList(
            (config as CartDeliveryDatesData).dates,
            current,
          )
        : getScheduleDatesFromConfig(
            current,
            config as ScheduledDeliveryDatesData,
          );

    // Land on the user's existing choice, else the first open day.
    const initial =
      upcoming.find((d) => d.date === scheduled?.date && !d.isClosed) ??
      getFirstSelectableDate(upcoming);

    setNow(current);
    setActiveDate(initial?.date ?? null);
    // Can't preselect here any more — slots are fetched per date, so the
    // existing choice is re-matched in the effect below once they arrive.
    setDraftSlot(null);
    onOpen();
  };

  /**
   * The slot the Confirm button acts on.
   *
   * Derived rather than an effect: slots arrive asynchronously, so reopening
   * via "Change" has nothing to preselect at open time. Falling back to the
   * saved choice — once its slots load and only while it's still available —
   * lands the user on their existing pick instead of a disabled Confirm, with
   * no setState-in-effect cascade. An explicit tap always wins.
   */
  const activeSlot =
    draftSlot ??
    (scheduled && activeDate === scheduled.date
      ? (slots.find((s) => s.id === scheduled.slotId && s.isAvailable) ?? null)
      : null);

  const handleConfirm = () => {
    if (!activeSlot) return;

    /**
     * Exactly one identity is stored, per contract.
     *
     * Cart slots have no id — theirs is synthesised as "<date>-<start>", which
     * Number() turns into NaN and the guard below drops. That's correct but
     * incidental, so the contract is checked explicitly rather than relying on
     * the id happening not to parse.
     */
    const raw = useCartContract
      ? undefined
      : activeSlot.dbId;

    dispatch(
      setScheduledDelivery({
        date: activeSlot.date,
        slotId: activeSlot.id,
        slotDbId: raw,
        slotStartTime: activeSlot.startTime,
        slotEndTime: activeSlot.endTime,
        startHour: activeSlot.startHour,
        endHour: activeSlot.endHour,
        label: activeSlot.label,
        storeId: storeId ?? undefined,
      }),
    );
    onClose();
  };

  const summary = scheduled
    ? formatScheduleSummary(scheduled.date, scheduled.label, new Date())
    : null;

  return (
    <>
      <Card className="w-full" radius="md" shadow="sm">
        <CardBody>
          <div className="flex items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-3">
              {/* Matches ExpressDelivery's icon treatment. The dark: override is
                  required because tailwind.config.ts pins `primary`'s dark scale
                  to the same un-inverted values as light — so bg-primary-100 is
                  a near-white #dbeafe on a dark card, unlike success/warning
                  which HeroUI inverts. Same workaround as ConfirmationModal. */}
              <div className="p-2 bg-primary-100 dark:bg-primary-100/20 rounded-lg">
                <CalendarDays className="w-5 h-5 text-primary" />
              </div>

              <div className="min-w-0">
                <p className="text-xs font-semibold md:text-small">
                  {t("schedule.title", "Scheduled delivery")}
                </p>

                {/* Swap the hint for the confirmed slot with a short rise —
                    the card is the only feedback that the choice took. */}
                <AnimatePresence mode="wait" initial={false}>
                  {isMultiStore ? (
                    // Say WHY it's unavailable — a dead button with no reason
                    // reads as a bug.
                    <motion.p
                      key="multi-store"
                      initial={{ opacity: 0, y: 4 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -4 }}
                      transition={{ duration: 0.2, ease: EASE }}
                      className="text-xxs text-warning md:text-xs"
                      data-testid="schedule-multistore"
                    >
                      {t(
                        "schedule.multiStore",
                        "Not available for orders from multiple stores",
                      )}
                    </motion.p>
                  ) : isRushDelivery ? (
                    <motion.p
                      key="rush-active"
                      initial={{ opacity: 0, y: 4 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -4 }}
                      transition={{ duration: 0.2, ease: EASE }}
                      className="text-xxs text-warning md:text-xs"
                      data-testid="schedule-rush-active"
                    >
                      {t(
                        "schedule.rushActive",
                        "Turn off rush delivery to schedule a time",
                      )}
                    </motion.p>
                  ) : isStoreDisabled ? (
                    // The backend ships a `message` here, but it's English-only
                    // — this app runs in en/hi/ar, so translate our own.
                    <motion.p
                      key="store-disabled"
                      initial={{ opacity: 0, y: 4 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -4 }}
                      transition={{ duration: 0.2, ease: EASE }}
                      className="text-xxs text-warning md:text-xs"
                      data-testid="schedule-store-disabled"
                    >
                      {t(
                        "schedule.storeDisabled",
                        "This store doesn't offer scheduled delivery",
                      )}
                    </motion.p>
                  ) : summary ? (
                    <motion.p
                      key="summary"
                      initial={{ opacity: 0, y: 4 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -4 }}
                      transition={{ duration: 0.2, ease: EASE }}
                      className="truncate text-xxs font-medium text-primary md:text-xs"
                      data-testid="schedule-summary"
                    >
                      {summary}
                    </motion.p>
                  ) : (
                    <motion.p
                      key="hint"
                      initial={{ opacity: 0, y: 4 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -4 }}
                      transition={{ duration: 0.2, ease: EASE }}
                      className="text-xxs text-default-500 md:text-xs"
                    >
                      {t("schedule.description", "Pick a delivery window that suits you")}
                    </motion.p>
                  )}
                </AnimatePresence>
              </div>
            </div>

            <div className="flex shrink-0 items-center gap-1">
              {scheduled && canSchedule && (
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  radius="full"
                  aria-label={t("schedule.clear", "Clear schedule")}
                  onPress={() => dispatch(clearScheduledDelivery())}
                  data-testid="schedule-clear"
                >
                  <X className="h-4 w-4 text-default-500" />
                </Button>
              )}

              <Button
                size="sm"
                color="primary"
                variant={scheduled ? "bordered" : "solid"}
                className="h-7 text-xs"
                // Opening before the config lands would show an empty calendar
                // — the dates depend on it.
                isDisabled={!canSchedule || isConfigLoading || !config}
                isLoading={isConfigLoading}
                onPress={handleOpen}
                data-testid="schedule-open"
              >
                {scheduled
                  ? t("schedule.change", "Change")
                  : t("schedule.cta", "Schedule")}
              </Button>
            </div>
          </div>
        </CardBody>
      </Card>

      <Modal
        isOpen={isOpen}
        onOpenChange={onOpenChange}
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
        <ModalContent>
          <ModalHeader className="flex flex-col gap-1 px-6 pb-4 pt-5">
            <span className="text-base leading-tight">
              {t("schedule.modalTitle", "Schedule your delivery")}
            </span>
            <span className="text-xs font-normal text-default-500">
              {t(
                "schedule.modalSubtitle",
                "Choose a day and a delivery window that works for you",
              )}
            </span>
          </ModalHeader>

          <ModalBody className="px-6 pb-2 pt-0">
            {/* Spacing around the rule is symmetric BY CONSTRUCTION: each pane
                pads itself (pe-8 / ps-8) around a logical border, rather than
                `gap` + `divide-x` — which stacks all the space on one side of
                the line and leaves the other flush. `border-s` + ps/pe are
                logical, so the rule stays on the correct side under RTL. */}
            <div className="grid gap-6 sm:grid-cols-[minmax(0,304px)_1fr] sm:gap-0">
              {/* Self-centred: a 6-row month is shorter than the slot column, so
                  any slack splits above/below rather than pooling under the grid
                  and reading as a broken layout. */}
              <div
                role="radiogroup"
                aria-label={t("schedule.pickDate", "Pick a date")}
                className="sm:self-center sm:pe-8"
              >
                <ScheduleCalendar
                  dates={dates}
                  activeDate={activeDate}
                  onSelect={handleDateSelect}
                />
              </div>

              {/* Slots */}
              <div className="min-h-[260px] space-y-5 sm:border-s sm:border-default-100 sm:ps-8">
                <div className="flex items-start justify-between gap-3 pb-1">
                  <div className="min-w-0">
                    <span className="block text-small font-semibold">
                      {t("schedule.selectSlot", "Select a time")}
                    </span>
                    {/* States which day these slots belong to — the calendar's
                        highlighted circle is easy to lose track of. */}
                    {activeDate && (
                      <span
                        className="block truncate text-xs text-default-500"
                        data-testid="schedule-active-date"
                      >
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

                {isSlotsLoading ? (
                  // Slots are fetched per date now, so switching days has real
                  // latency. Skeletons in the grid's own shape keep the panel
                  // from collapsing and rebounding.
                  <div className="space-y-4" data-testid="schedule-slots-loading">
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
                ) : availableCount === 0 ? (
                  <div
                    className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-default-200 py-10 text-center"
                    data-testid="schedule-empty"
                  >
                    <Clock className="h-6 w-6 text-default-300" />
                    <p className="text-xs text-default-500">
                      {/* The backend explains WHY when a whole date is refused
                          (e.g. beyond the booking window) — prefer its reason
                          over a generic "no slots". */}
                      {slotsMessage ||
                        t("schedule.noSlots", "No slots left for this day")}
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
                              selectedSlotId={activeSlot?.id ?? null}
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
          </ModalBody>

          <ModalFooter className="gap-2 px-6 pb-5 pt-4">
            {/* Deliberately NOT size="sm": HeroUI's sm button applies `text-tiny`,
                and tailwind.config.ts pins tiny to 0.4375rem (7px), which renders
                the label unreadable. Elsewhere the codebase pairs size="sm" with
                an explicit `text-xs`; here the default (md → text-small) is right. */}
            <Button
              variant="flat"
              className="text-foreground"
              onPress={onClose}
            >
              {t("cancel", "Cancel")}
            </Button>
            <Button
              color="primary"
              className="text-white"
              isDisabled={!activeSlot}
              onPress={handleConfirm}
              data-testid="schedule-confirm"
            >
              {activeSlot
                ? `${t("schedule.confirm", "Confirm")} · ${activeSlot.label}`
                : t("schedule.confirm", "Confirm")}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
};

export default ScheduledDelivery;
