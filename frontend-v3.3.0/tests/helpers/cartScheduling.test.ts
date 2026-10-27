import { describe, expect, it } from "vitest";
import {
  adaptCartSlot,
  adaptCartSlots,
  getScheduleDatesFromList,
} from "@/helpers/deliverySlots";
import { resolveDeliveryParams } from "@/helpers/functionalHelpers";
import { CartDeliverySlot } from "@/types/ApiResponse";

/**
 * The cart contract (`/cart/dates`, `/cart/slots`) identifies a delivery window
 * by date + start/end time — its slots carry no `id` at all. That makes it
 * incompatible with the store contract's `delivery_time_slot_id`, and these
 * tests pin the boundary so the two can't leak into each other.
 */

const slot = (over: Partial<CartDeliverySlot> = {}): CartDeliverySlot => ({
  start_time: "14:00:00",
  end_time: "15:00:00",
  formatted_time: "02:00 PM - 03:00 PM",
  remaining_capacity: 8,
  ...over,
});

describe("adaptCartSlot", () => {
  it("maps a slot from the spec's payload", () => {
    expect(adaptCartSlot(slot(), "2026-08-01", 0)).toEqual({
      id: "2026-08-01-14:00:00",
      dbId: undefined,
      date: "2026-08-01",
      startHour: 14,
      endHour: 15,
      startTime: "14:00:00",
      endTime: "15:00:00",
      label: "02:00 PM - 03:00 PM",
      period: "afternoon",
      isAvailable: true,
      remainingCapacity: 8,
    });
  });

  it("passes the backend's formatted_time through untouched", () => {
    // Re-deriving the label risks disagreeing with what the store configured.
    expect(
      adaptCartSlot(slot({ formatted_time: "2 til 3 in the afternoon" }), "2026-08-01", 0)
        .label,
    ).toBe("2 til 3 in the afternoon");
  });

  it("falls back to a derived label only when formatted_time is missing", () => {
    expect(
      adaptCartSlot(slot({ formatted_time: "" }), "2026-08-01", 0).label,
    ).toBe("2 – 3 PM");
  });

  it("marks a slot with no remaining capacity unavailable", () => {
    // Capacity is the only availability signal this contract offers.
    expect(
      adaptCartSlot(slot({ remaining_capacity: 0 }), "2026-08-01", 0).isAvailable,
    ).toBe(false);
  });

  it("gives slots on the same date distinct ids", () => {
    // The id is a React key. Two slots collapsing to one key would drop a row.
    const a = adaptCartSlot(slot({ start_time: "14:00:00" }), "2026-08-01", 0);
    const b = adaptCartSlot(slot({ start_time: "15:00:00" }), "2026-08-01", 0);
    expect(a.id).not.toBe(b.id);
  });

  it("keeps the half-hour in the id", () => {
    // startHour alone would collapse 14:00 and 14:30 into the same key.
    const onTheHour = adaptCartSlot(slot({ start_time: "14:00:00" }), "2026-08-01", 0);
    const halfPast = adaptCartSlot(slot({ start_time: "14:30:00" }), "2026-08-01", 0);
    expect(onTheHour.id).not.toBe(halfPast.id);
  });

  it("buckets by period from the start hour", () => {
    expect(adaptCartSlot(slot({ start_time: "09:00:00" }), "2026-08-01", 0).period)
      .toBe("morning");
    expect(adaptCartSlot(slot({ start_time: "18:00:00" }), "2026-08-01", 0).period)
      .toBe("evening");
  });
});

describe("adaptCartSlots", () => {
  it("maps the spec's two-slot response", () => {
    const out = adaptCartSlots(
      [
        slot(),
        slot({
          start_time: "15:00:00",
          end_time: "16:00:00",
          formatted_time: "03:00 PM - 04:00 PM",
          remaining_capacity: 5,
        }),
      ],
      "2026-08-01",
    );
    expect(out.map((s) => s.label)).toEqual([
      "02:00 PM - 03:00 PM",
      "03:00 PM - 04:00 PM",
    ]);
    expect(out.every((s) => s.isAvailable)).toBe(true);
  });

  it.each([
    ["null", null],
    ["undefined", undefined],
    ["a non-array", {} as unknown as CartDeliverySlot[]],
  ])("returns [] for %s rather than throwing", (_label, input) => {
    expect(adaptCartSlots(input as CartDeliverySlot[], "2026-08-01")).toEqual([]);
  });
});

describe("getScheduleDatesFromList", () => {
  // A Thursday.
  const now = new Date(2026, 7, 1, 10, 0, 0);

  it("expands the spec's dates array", () => {
    const out = getScheduleDatesFromList(
      ["2026-08-01", "2026-08-02", "2026-08-03"],
      now,
    );
    expect(out.map((d) => d.date)).toEqual([
      "2026-08-01",
      "2026-08-02",
      "2026-08-03",
    ]);
  });

  it("marks today and tomorrow", () => {
    const out = getScheduleDatesFromList(["2026-08-01", "2026-08-02"], now);
    expect(out[0].isToday).toBe(true);
    expect(out[0].isTomorrow).toBe(false);
    expect(out[1].isTomorrow).toBe(true);
  });

  it("treats every listed date as open", () => {
    // The backend already excluded closed days by omitting them, so nothing in
    // the list should render struck out.
    const out = getScheduleDatesFromList(["2026-08-01", "2026-08-09"], now);
    expect(out.every((d) => !d.isClosed)).toBe(true);
    expect(out.every((d) => d.availableCount > 0)).toBe(true);
  });

  it("sorts out-of-order dates", () => {
    const out = getScheduleDatesFromList(
      ["2026-08-03", "2026-08-01", "2026-08-02"],
      now,
    );
    expect(out.map((d) => d.date)).toEqual([
      "2026-08-01",
      "2026-08-02",
      "2026-08-03",
    ]);
  });

  it("drops malformed entries instead of rendering broken cells", () => {
    const out = getScheduleDatesFromList(
      ["2026-08-01", "not-a-date", "", "2026-8-2"],
      now,
    );
    expect(out.map((d) => d.date)).toEqual(["2026-08-01"]);
  });

  it("handles a non-contiguous set", () => {
    // Closed Sundays and holidays arrive as gaps; the calendar renders those as
    // disabled cells, so nothing special is needed here.
    const out = getScheduleDatesFromList(["2026-08-01", "2026-08-15"], now);
    expect(out).toHaveLength(2);
  });

  it.each([
    ["null", null],
    ["undefined", undefined],
  ])("returns [] for %s", (_label, input) => {
    expect(
      getScheduleDatesFromList(input as unknown as string[], now),
    ).toEqual([]);
  });
});

/**
 * The checkout seam. A cart-contract booking has NO slotDbId, so the old
 * `delivery_time_slot_id`-only gate would have silently dropped it.
 */
describe("resolveDeliveryParams — cart contract", () => {
  it("books by time when the slot has no numeric id", () => {
    expect(
      resolveDeliveryParams({
        scheduledDelivery: {
          date: "2026-08-01",
          slotStartTime: "14:00:00",
          slotEndTime: "15:00:00",
        },
      }),
    ).toEqual({
      delivery_time_slot_id: null,
      scheduled_date: "2026-08-01",
      scheduled_slot_start_time: "14:00:00",
      scheduled_slot_end_time: "15:00:00",
      delivery_type: "delivery",
      rush_delivery: false,
    });
  });

  it("accepts valid times without seconds (HH:MM format)", () => {
    expect(
      resolveDeliveryParams({
        scheduledDelivery: {
          date: "2026-08-01",
          slotStartTime: "14:00",
          slotEndTime: "15:00",
        },
      }),
    ).toEqual({
      delivery_time_slot_id: null,
      scheduled_date: "2026-08-01",
      scheduled_slot_start_time: "14:00",
      scheduled_slot_end_time: "15:00",
      delivery_type: "delivery",
      rush_delivery: false,
    });
  });

  it("suppresses rush for a time-booked window too", () => {
    // Same contradiction as the id-based path: a booked window beats rush.
    expect(
      resolveDeliveryParams({
        rushDelivery: true,
        scheduledDelivery: {
          date: "2026-08-01",
          slotStartTime: "14:00:00",
          slotEndTime: "15:00:00",
        },
      }).rush_delivery,
    ).toBe(false);
  });

  it("never sends slot times without a date", () => {
    const out = resolveDeliveryParams({
      scheduledDelivery: {
        slotStartTime: "14:00:00",
        slotEndTime: "15:00:00",
      },
    });
    expect(out.scheduled_slot_start_time).toBeNull();
    expect(out.scheduled_slot_end_time).toBeNull();
    expect(out.scheduled_date).toBeNull();
  });

  it.each([
    ["midnight times", "00:00:00", "01:00:00"],
    ["end of day times", "23:00", "23:59:59"],
    ["early morning without seconds", "05:30", "06:30"],
  ])("accepts %s (%s to %s)", (_label, start, end) => {
    const out = resolveDeliveryParams({
      scheduledDelivery: {
        date: "2026-08-01",
        slotStartTime: start,
        slotEndTime: end,
      },
    });
    expect(out.scheduled_slot_start_time).toBe(start);
    expect(out.scheduled_slot_end_time).toBe(end);
  });

  it.each([
    ["only a start", { slotStartTime: "14:00:00" }],
    ["only an end", { slotEndTime: "15:00:00" }],
    ["a malformed start", { slotStartTime: "14:xx", slotEndTime: "15:00:00" }],
    ["a display label", { slotStartTime: "2 PM", slotEndTime: "3 PM" }],
  ])("drops a booking with %s", (_label, partial) => {
    // Half an identity would be rejected by the backend, or book the wrong
    // window. Neither field is sent unless both are valid.
    const out = resolveDeliveryParams({
      scheduledDelivery: { date: "2026-08-01", ...partial },
    });
    expect(out.scheduled_slot_start_time).toBeNull();
    expect(out.scheduled_slot_end_time).toBeNull();
  });

  it("lets rush still apply when the saved times are unusable", () => {
    expect(
      resolveDeliveryParams({
        rushDelivery: true,
        scheduledDelivery: { date: "2026-08-01", slotStartTime: "14:00:00" },
      }).rush_delivery,
    ).toBe(true);
  });

  it("keeps the two contracts mutually exclusive", () => {
    // Should state ever hold both, each field still reports its own contract's
    // value — but no case may produce a slot id AND times, since the backend
    // would then have two conflicting notions of the booked window.
    const cases = [
      { date: "2026-08-01", slotDbId: 12 },
      { date: "2026-08-01", slotStartTime: "14:00:00", slotEndTime: "15:00:00" },
      { date: "2026-08-01" },
    ];
    for (const scheduledDelivery of cases) {
      const out = resolveDeliveryParams({ scheduledDelivery });
      const byId = out.delivery_time_slot_id !== null;
      const byTime = out.scheduled_slot_start_time !== null;
      expect(byId && byTime).toBe(false);
    }
  });

  it("preserves the minutes of a half-hour slot", () => {
    // startHour/endHour would round 14:30 to "14:00:00" and book the wrong
    // window — the raw times exist precisely to avoid that.
    expect(
      resolveDeliveryParams({
        scheduledDelivery: {
          date: "2026-08-01",
          slotStartTime: "14:30:00",
          slotEndTime: "15:30:00",
        },
      }).scheduled_slot_start_time,
    ).toBe("14:30:00");
  });
});
