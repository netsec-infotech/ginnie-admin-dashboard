import { describe, expect, it } from "vitest";
import checkoutReducer, {
  ScheduledDelivery,
  clearScheduledDelivery,
  setRusDelivery,
  setScheduledDelivery,
} from "@/lib/redux/slices/checkoutSlice";

const slot: ScheduledDelivery = {
  date: "2026-07-23",
  slotId: "12",
  slotDbId: 12,
  startHour: 13,
  endHour: 15,
  label: "01:00 PM - 03:00 PM",
  storeId: 11,
};

const initial = () => checkoutReducer(undefined, { type: "@@INIT" });

/**
 * Rush ("as soon as possible") and a booked window contradict each other, and
 * the backend takes `rush_delivery` and `delivery_time_slot_id` as independent
 * fields — nothing server-side rejects the combination. The reducers are the
 * one place the invariant can't be bypassed.
 */
describe("checkoutSlice — rush / scheduled exclusion", () => {
  it("clears a booked slot when rush is switched on", () => {
    const withSlot = checkoutReducer(initial(), setScheduledDelivery(slot));
    expect(withSlot.scheduledDelivery).not.toBeNull();

    const withRush = checkoutReducer(withSlot, setRusDelivery(true));
    expect(withRush.rushDelivery).toBe(true);
    expect(withRush.scheduledDelivery).toBeNull();
  });

  it("switches rush off when a slot is booked", () => {
    const withRush = checkoutReducer(initial(), setRusDelivery(true));
    expect(withRush.rushDelivery).toBe(true);

    const withSlot = checkoutReducer(withRush, setScheduledDelivery(slot));
    expect(withSlot.scheduledDelivery?.slotDbId).toBe(12);
    expect(withSlot.rushDelivery).toBe(false);
  });

  it("never holds both at once, whichever order they arrive in", () => {
    let state = initial();
    for (const action of [
      setRusDelivery(true),
      setScheduledDelivery(slot),
      setRusDelivery(true),
      setScheduledDelivery(slot),
    ]) {
      state = checkoutReducer(state, action);
      expect(state.rushDelivery && !!state.scheduledDelivery).toBe(false);
    }
  });

  it("does not clear a slot when rush is switched OFF", () => {
    // Only enabling rush conflicts — turning it off must not discard a choice
    // the customer just made.
    const withSlot = checkoutReducer(initial(), setScheduledDelivery(slot));
    const rushOff = checkoutReducer(withSlot, setRusDelivery(false));

    expect(rushOff.rushDelivery).toBe(false);
    expect(rushOff.scheduledDelivery?.slotDbId).toBe(12);
  });

  it("clearing the schedule leaves rush alone", () => {
    const withSlot = checkoutReducer(initial(), setScheduledDelivery(slot));
    const cleared = checkoutReducer(withSlot, clearScheduledDelivery());

    expect(cleared.scheduledDelivery).toBeNull();
    expect(cleared.rushDelivery).toBe(false);
  });

  it("setting the schedule to null does not re-enable rush", () => {
    const withRush = checkoutReducer(initial(), setRusDelivery(true));
    const nulled = checkoutReducer(withRush, setScheduledDelivery(null));

    // A null payload is a clear, not a booking — it must not touch rush.
    expect(nulled.scheduledDelivery).toBeNull();
    expect(nulled.rushDelivery).toBe(true);
  });
});

describe("checkoutSlice — scheduled delivery payload", () => {
  it("keeps the real delivery_time_slot_id", () => {
    const state = checkoutReducer(initial(), setScheduledDelivery(slot));
    expect(state.scheduledDelivery?.slotDbId).toBe(12);
    expect(state.scheduledDelivery?.storeId).toBe(11);
  });

  /**
   * Checkout state is persisted to localStorage. A session saved before the
   * real slots endpoint existed has a generated slotId and NO slotDbId —
   * consumers must treat that as "re-pick required", never coerce it.
   */
  it("tolerates a legacy slot with no numeric id", () => {
    const legacy = {
      date: "2026-07-19",
      slotId: "2026-07-19-08",
      startHour: 8,
      endHour: 9,
      label: "8 – 9 AM",
    } as ScheduledDelivery;

    const state = checkoutReducer(initial(), setScheduledDelivery(legacy));
    expect(state.scheduledDelivery?.slotDbId).toBeUndefined();
    expect(Number.isFinite(Number(legacy.slotId))).toBe(false);
  });
});
