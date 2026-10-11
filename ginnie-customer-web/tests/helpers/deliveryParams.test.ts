import { describe, expect, it } from "vitest";
import { resolveDeliveryParams } from "@/helpers/functionalHelpers";

/**
 * The last gate before an order is created. The backend accepts
 * `rush_delivery` and `delivery_time_slot_id` as independent fields, so nothing
 * server-side catches a contradictory pair — it has to be right here.
 */
describe("resolveDeliveryParams", () => {
  it("sends the real delivery_time_slot_id when a slot is booked", () => {
    expect(
      resolveDeliveryParams({
        scheduledDelivery: { slotDbId: 12, date: "2026-07-30" },
      }),
    ).toEqual({
      delivery_time_slot_id: 12,
      rush_delivery: false,
      delivery_type: "delivery",
      scheduled_date: "2026-07-30",
      scheduled_slot_start_time: null,
      scheduled_slot_end_time: null,
    });
  });

  it("sends rush with no slot when nothing is scheduled", () => {
    expect(
      resolveDeliveryParams({ rushDelivery: true, scheduledDelivery: null }),
    ).toEqual({
      delivery_time_slot_id: null,
      rush_delivery: true,
      delivery_type: "delivery",
      scheduled_date: null,
      scheduled_slot_start_time: null,
      scheduled_slot_end_time: null,
    });
  });

  it("suppresses a stale rush flag when a slot is booked", () => {
    // Belt-and-braces over the reducers: if both somehow survive in state,
    // the slot wins and rush is dropped rather than both being submitted.
    expect(
      resolveDeliveryParams({
        rushDelivery: true,
        scheduledDelivery: { slotDbId: 12, date: "2026-07-30" },
      }),
    ).toEqual({
      delivery_time_slot_id: 12,
      rush_delivery: false,
      delivery_type: "delivery",
      scheduled_date: "2026-07-30",
      scheduled_slot_start_time: null,
      scheduled_slot_end_time: null,
    });
  });

  it("never returns both at once", () => {
    const cases = [
      {
        rushDelivery: true,
        scheduledDelivery: { slotDbId: 11, date: "2026-07-30" },
      },
      {
        rushDelivery: false,
        scheduledDelivery: { slotDbId: 13, date: "2026-07-30" },
      },
      { rushDelivery: true, scheduledDelivery: null },
      {},
    ];
    for (const input of cases) {
      const out = resolveDeliveryParams(input);
      expect(out.rush_delivery && out.delivery_time_slot_id !== null).toBe(
        false,
      );
    }
  });

  /**
   * Legacy persisted state: sessions saved before the real slots endpoint have
   * a generated slotId and no slotDbId. Coercing that would send a string the
   * backend rejects, so it must be treated as "no slot".
   */
  it.each([
    ["a legacy slot with no numeric id", { slotId: "2026-07-19-08" }],
    ["an undefined slotDbId", { slotDbId: undefined }],
    ["a NaN slotDbId", { slotDbId: Number.NaN }],
  ])("ignores %s", (_label, scheduledDelivery) => {
    expect(
      resolveDeliveryParams({
        scheduledDelivery: scheduledDelivery as { slotDbId?: number },
      }).delivery_time_slot_id,
    ).toBeNull();
  });

  it("lets rush still apply when the saved slot is unusable", () => {
    // A legacy slot isn't a real booking, so it must not suppress rush.
    expect(
      resolveDeliveryParams({
        rushDelivery: true,
        scheduledDelivery: { slotId: "2026-07-19-08" } as { slotDbId?: number },
      }),
    ).toEqual({
      delivery_time_slot_id: null,
      rush_delivery: true,
      delivery_type: "delivery",
      scheduled_date: null,
      scheduled_slot_start_time: null,
      scheduled_slot_end_time: null,
    });
  });

  it("defaults to neither for empty state", () => {
    expect(resolveDeliveryParams({})).toEqual({
      delivery_time_slot_id: null,
      rush_delivery: false,
      delivery_type: "delivery",
      scheduled_date: null,
      scheduled_slot_start_time: null,
      scheduled_slot_end_time: null,
    });
  });

  it("treats slot id 0 as no slot", () => {
    // Backend ids are 1-based, so 0 is a bug rather than a booking — and a
    // falsy-but-present id must not read as "scheduled" in one branch and
    // "not scheduled" in the next.
    expect(
      resolveDeliveryParams({
        rushDelivery: true,
        scheduledDelivery: { slotDbId: 0, date: "2026-07-30" },
      }),
    ).toEqual({
      delivery_time_slot_id: null,
      rush_delivery: true,
      delivery_type: "delivery",
      scheduled_date: null,
      scheduled_slot_start_time: null,
      scheduled_slot_end_time: null,
    });
  });

  it("rejects a negative slot id", () => {
    expect(
      resolveDeliveryParams({ scheduledDelivery: { slotDbId: -1 } })
        .delivery_time_slot_id,
    ).toBeNull();
  });

  /**
   * The backend requires `delivery_type` on every order, and rejects a slot
   * that arrives without `scheduled_date` ("Scheduled date is required when
   * selecting a delivery time slot"). Both rules are enforced here.
   */
  describe("delivery_type and scheduled_date", () => {
    it("always sends a delivery_type", () => {
      // The field is required on every order, and this storefront has no
      // self-collection flow — so it is "delivery" in every case, including
      // a booked window (the backend flags that via `is_scheduled_delivery`).
      const cases = [
        {},
        { rushDelivery: true },
        { scheduledDelivery: { slotDbId: 5, date: "2026-07-30" } },
        { scheduledDelivery: { slotId: "legacy" } as { slotDbId?: number } },
      ];
      for (const input of cases) {
        expect(resolveDeliveryParams(input).delivery_type).toBe("delivery");
      }
    });

    it("always reports delivery as the type", () => {
      // Confirmed against a live order: a booked window comes back as
      // `is_scheduled_delivery: true` with `delivery_type: "delivery"`.
      // Rush is likewise carried by the separate `rush_delivery` flag.
      const out = resolveDeliveryParams({ rushDelivery: true });
      expect(out.delivery_type).toBe("delivery");
      expect(out.scheduled_date).toBeNull();
    });

    it("never sends a slot id without a date", () => {
      const cases = [
        { scheduledDelivery: { slotDbId: 9 } },
        { scheduledDelivery: { slotDbId: 9, date: "" } },
        { scheduledDelivery: { slotDbId: 9, date: "30-07-2026" } },
        { scheduledDelivery: { slotDbId: 9, date: "2026-7-3" } },
      ];
      for (const input of cases) {
        const out = resolveDeliveryParams(input);
        expect(out.delivery_time_slot_id === null).toBe(
          out.scheduled_date === null,
        );
      }
    });

    it("drops a booking whose persisted date is unusable", () => {
      // Same rule as a missing slotDbId: an incomplete booking is not sent,
      // the customer re-picks, rather than the order failing validation.
      expect(
        resolveDeliveryParams({
          scheduledDelivery: { slotDbId: 9, date: "not-a-date" },
        }),
      ).toEqual({
        delivery_time_slot_id: null,
        rush_delivery: false,
        delivery_type: "delivery",
        scheduled_date: null,
        scheduled_slot_start_time: null,
        scheduled_slot_end_time: null,
      });
    });

    it("lets rush still apply when the saved date is unusable", () => {
      expect(
        resolveDeliveryParams({
          rushDelivery: true,
          scheduledDelivery: { slotDbId: 9, date: "not-a-date" },
        }).rush_delivery,
      ).toBe(true);
    });
  });
});
