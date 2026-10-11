import { describe, expect, it } from "vitest";
import {
  adaptScheduledSlot,
  adaptScheduledSlots,
  getScheduleDatesFromConfig,
  groupSlotsByPeriod,
} from "@/helpers/deliverySlots";
import { ScheduledDeliverySlot } from "@/types/ApiResponse";

/**
 * Verbatim from
 * /api/scheduled-delivery/stores/11/slots?date=2026-07-23 — note the windows
 * are 2h, 2h then 1h. Nothing may assume a uniform hourly grid.
 */
const LIVE_SLOTS: ScheduledDeliverySlot[] = [
  {
    id: 11,
    label: "10:00 AM - 12:00 PM",
    start_time: "10:00:00",
    end_time: "12:00:00",
    remaining_capacity: 10,
    max_orders_reached: false,
    is_available: true,
  },
  {
    id: 12,
    label: "01:00 PM - 03:00 PM",
    start_time: "13:00:00",
    end_time: "15:00:00",
    remaining_capacity: 10,
    max_orders_reached: false,
    is_available: true,
  },
  {
    id: 13,
    label: "04:00 PM - 05:00 PM",
    start_time: "16:00:00",
    end_time: "17:00:00",
    remaining_capacity: 10,
    max_orders_reached: false,
    is_available: true,
  },
];

const DATE = "2026-07-23";

describe("adaptScheduledSlot", () => {
  it("keeps the backend id — it's the delivery_time_slot_id", () => {
    // Losing this is the whole failure mode: without the real id, createOrder
    // has nothing to send.
    expect(adaptScheduledSlot(LIVE_SLOTS[0], DATE, 0).dbId).toBe(11);
    expect(adaptScheduledSlot(LIVE_SLOTS[2], DATE, 0).dbId).toBe(13);
  });

  it("passes the backend label through untouched", () => {
    // Re-deriving from start/end could disagree with what the store configured.
    expect(adaptScheduledSlot(LIVE_SLOTS[0], DATE, 0).label).toBe(
      "10:00 AM - 12:00 PM",
    );
  });

  it("parses 24h times into hours", () => {
    const slot = adaptScheduledSlot(LIVE_SLOTS[1], DATE, 0);
    expect(slot.startHour).toBe(13);
    expect(slot.endHour).toBe(15);
  });

  it("preserves an irregular window rather than forcing 1 hour", () => {
    const twoHour = adaptScheduledSlot(LIVE_SLOTS[0], DATE, 0);
    const oneHour = adaptScheduledSlot(LIVE_SLOTS[2], DATE, 0);
    expect(twoHour.endHour - twoHour.startHour).toBe(2);
    expect(oneHour.endHour - oneHour.startHour).toBe(1);
  });

  it("assigns the period from the start hour", () => {
    expect(adaptScheduledSlot(LIVE_SLOTS[0], DATE, 0).period).toBe("morning");
    expect(adaptScheduledSlot(LIVE_SLOTS[1], DATE, 0).period).toBe("afternoon");
    // 16:00 is still afternoon under the existing <17 rule.
    expect(adaptScheduledSlot(LIVE_SLOTS[2], DATE, 0).period).toBe("afternoon");
  });

  it("marks a cutoff-passed slot unavailable", () => {
    // Real shape returned for today's slots.
    const past: ScheduledDeliverySlot = {
      ...LIVE_SLOTS[0],
      is_available: false,
      reason: "Cutoff time passed for this slot.",
    };
    expect(adaptScheduledSlot(past, DATE, 0).isAvailable).toBe(false);
  });

  it("marks a slot unavailable when capacity is gone", () => {
    // max_orders_reached must veto even if is_available says otherwise.
    const full: ScheduledDeliverySlot = {
      ...LIVE_SLOTS[0],
      remaining_capacity: 0,
      max_orders_reached: true,
    };
    expect(adaptScheduledSlot(full, DATE, 0).isAvailable).toBe(false);
  });

  it("stamps the requested date onto the slot", () => {
    expect(adaptScheduledSlot(LIVE_SLOTS[0], DATE, 0).date).toBe(DATE);
  });
});

describe("adaptScheduledSlots", () => {
  it("adapts the live payload in order", () => {
    const slots = adaptScheduledSlots(LIVE_SLOTS, DATE);
    expect(slots.map((s) => s.dbId)).toEqual([11, 12, 13]);
  });

  it.each([
    ["null", null],
    ["undefined", undefined],
    ["a non-array", {} as unknown as ScheduledDeliverySlot[]],
  ])("returns an empty list for %s", (_label, input) => {
    expect(
      adaptScheduledSlots(input as ScheduledDeliverySlot[] | null, DATE),
    ).toEqual([]);
  });

  it("groups into the picker's period columns", () => {
    const grouped = groupSlotsByPeriod(adaptScheduledSlots(LIVE_SLOTS, DATE));
    expect(grouped.morning.map((s) => s.dbId)).toEqual([11]);
    expect(grouped.afternoon.map((s) => s.dbId)).toEqual([12, 13]);
    expect(grouped.evening).toEqual([]);
  });
});

/** Fixed clock: Tue 21 Jul 2026, 10:00 local. */
const at = (h: number) => new Date(2026, 6, 21, h);

describe("getScheduleDatesFromConfig", () => {
  it("builds a window of max_advance_booking_days", () => {
    const dates = getScheduleDatesFromConfig(at(10), {
      available_days: [
        "Monday", "Tuesday", "Wednesday", "Thursday",
        "Friday", "Saturday", "Sunday",
      ],
      max_advance_booking_days: 7,
      max_days: 7,
    });

    expect(dates).toHaveLength(7);
    expect(dates[0].date).toBe("2026-07-21");
    expect(dates[0].isToday).toBe(true);
    expect(dates[1].isTomorrow).toBe(true);
    expect(dates[6].date).toBe("2026-07-27");
  });

  it("marks the store's closed weekdays", () => {
    // Mon/Tue/Thu/Fri/Sat open → Wed + Sun shut.
    const dates = getScheduleDatesFromConfig(at(10), {
      available_days: ["Monday", "Tuesday", "Thursday", "Friday", "Saturday"],
      max_days: 7,
    });

    const byDate = (key: string) => dates.find((d) => d.date === key)!;
    expect(byDate("2026-07-22").isClosed).toBe(true); // Wednesday
    expect(byDate("2026-07-26").isClosed).toBe(true); // Sunday
    expect(byDate("2026-07-23").isClosed).toBe(false); // Thursday
  });

  it("gives closed days a cell so the month grid doesn't shift", () => {
    const dates = getScheduleDatesFromConfig(at(10), {
      available_days: ["Monday"],
      max_days: 7,
    });
    // Every day in the window is present, open or not.
    expect(dates).toHaveLength(7);
    expect(dates.filter((d) => d.isClosed)).toHaveLength(6);
  });

  it("prefers max_days over the configured window when both are set", () => {
    expect(
      getScheduleDatesFromConfig(at(10), {
        available_days: ["Monday"],
        max_advance_booking_days: 7,
        max_days: 3,
      }),
    ).toHaveLength(3);
  });

  it("falls back to max_advance_booking_days when max_days is 0", () => {
    // max_days reads 0 while scheduling is off — that must not empty the grid.
    expect(
      getScheduleDatesFromConfig(at(10), {
        available_days: ["Monday"],
        max_advance_booking_days: 5,
        max_days: 0,
      }),
    ).toHaveLength(5);
  });

  it("treats an absent available_days as open every day", () => {
    // null from toClosedWeekdays means "unknown" — closing everything would
    // leave a dead calendar with nothing clickable.
    const dates = getScheduleDatesFromConfig(at(10), { max_days: 7 });
    expect(dates.every((d) => !d.isClosed)).toBe(true);
  });

  it("rolls into the next month correctly", () => {
    const dates = getScheduleDatesFromConfig(new Date(2026, 6, 29, 10), {
      max_days: 7,
    });
    expect(dates.map((d) => d.date)).toContain("2026-08-01");
  });
});
