/**
 * Order-recovery rules: when a flagged order can still be rescheduled or
 * cancelled by the customer.
 *
 * Pure and `now`-injected throughout, like helpers/deliverySlots.ts — deadline
 * logic is exactly the kind that silently rots when it reads the clock itself,
 * and "expired" is not a state you want to get wrong in either direction.
 */
import { CartDeliverySlot, Order } from "@/types/ApiResponse";

/** Order shape this module needs. Keeps callers free of the full Order type. */
export type RecoverableOrder = Pick<
  Order,
  "reschedule_status" | "reschedule_reason" | "reschedule_deadline"
>;

/**
 * Whether the backend has flagged this order for customer action.
 *
 * Only `needs_reschedule` counts. `rescheduled` means it's already been dealt
 * with, and the field being absent entirely is the norm for orders from a
 * backend that doesn't serve the feature.
 */
export const isFlaggedForReschedule = (
  order: RecoverableOrder | null | undefined,
): boolean => order?.reschedule_status === "needs_reschedule";

/**
 * Parse the deadline to epoch ms, or null if unusable.
 *
 * The backend sends ISO-8601 UTC ("2026-07-31T14:30:00.000000Z") with 6-digit
 * microseconds. `Date.parse` handles that, but a malformed or missing value must
 * come back null rather than NaN so callers can't accidentally compare against
 * it — every NaN comparison is false, which would read as "not expired" and
 * leave a dead button on screen.
 */
export const parseDeadline = (
  deadline: string | null | undefined,
): number | null => {
  if (typeof deadline !== "string" || deadline.trim() === "") return null;
  const ms = Date.parse(deadline);
  return Number.isFinite(ms) ? ms : null;
};

/**
 * Whether the window to act has closed.
 *
 * A flagged order with NO deadline is treated as still actionable: the backend
 * is the authority on refusal, and locking the customer out on a missing field
 * would strand the order with no path forward. An unparseable deadline is the
 * same case.
 */
export const isRecoveryExpired = (
  order: RecoverableOrder | null | undefined,
  now: Date,
): boolean => {
  const deadline = parseDeadline(order?.reschedule_deadline);
  if (deadline === null) return false;
  return now.getTime() >= deadline;
};

/**
 * The customer can act: flagged, and the deadline hasn't passed.
 * This is the single gate the UI should use.
 */
export const canRecoverOrder = (
  order: RecoverableOrder | null | undefined,
  now: Date,
): boolean => isFlaggedForReschedule(order) && !isRecoveryExpired(order, now);

/**
 * Whole minutes left to act, floored, never negative. Null when there is no
 * usable deadline — the caller shows the prompt without a countdown rather than
 * inventing one.
 */
export const minutesUntilDeadline = (
  order: RecoverableOrder | null | undefined,
  now: Date,
): number | null => {
  const deadline = parseDeadline(order?.reschedule_deadline);
  if (deadline === null) return null;
  return Math.max(0, Math.floor((deadline - now.getTime()) / 60_000));
};

/**
 * Coarse countdown for the banner: "2 days", "3 hours", "45 minutes".
 *
 * Returns null when there's no deadline. Deliberately one unit — a precise
 * "1 day, 4 hours, 12 minutes" reads as a stopwatch and needs re-rendering every
 * minute to stay honest.
 */
export const formatTimeRemaining = (
  order: RecoverableOrder | null | undefined,
  now: Date,
): string | null => {
  const minutes = minutesUntilDeadline(order, now);
  if (minutes === null) return null;

  if (minutes < 1) return "less than a minute";
  if (minutes < 60) return `${minutes} minute${minutes === 1 ? "" : "s"}`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"}`;

  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? "" : "s"}`;
};

/**
 * The store whose slots the recovery picker should show.
 *
 * `Order` has no `store_id` of its own — it lives on each item. Recovery needs
 * one store, so the first item's wins. That is exact for a single-store order
 * and the best available guess for a multi-store one, where the backend would
 * have to split the reschedule anyway; returning null instead would block the
 * customer entirely.
 */
export const getRecoveryStoreId = (
  order: { items?: { store_id?: number }[] } | null | undefined,
): number | null => {
  const id = order?.items?.find(
    (item) => typeof item?.store_id === "number" && item.store_id > 0,
  )?.store_id;

  return typeof id === "number" ? id : null;
};

/**
 * Is this slot bookable? Cart slots carry no `is_available` flag — capacity is
 * the only signal, so anything at or below zero is full.
 */
export const isCartSlotBookable = (slot: CartDeliverySlot): boolean =>
  Number(slot?.remaining_capacity ?? 0) > 0;

/**
 * The reschedule request body. With no slot at all, a date-only reschedule is
 * built — the backend serves dates without slots when slot scheduling is
 * disabled. But a slot that IS given yet missing either time returns null: a
 * half-identified window would be rejected by the backend, or worse book
 * something the customer didn't choose, and failing here keeps that out of
 * the request.
 */
export const toRescheduleParams = (
  date: string,
  slot?: Pick<CartDeliverySlot, "start_time" | "end_time"> | null,
): {
  scheduled_date: string;
  scheduled_slot_start_time?: string;
  scheduled_slot_end_time?: string;
} | null => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return null;

  if (!slot) return { scheduled_date: date };

  if (!slot.start_time || !slot.end_time) return null;

  return {
    scheduled_date: date,
    scheduled_slot_start_time: slot.start_time,
    scheduled_slot_end_time: slot.end_time,
  };
};
