import { describe, expect, it } from "vitest";
import {
  canRecoverOrder,
  formatTimeRemaining,
  isCartSlotBookable,
  isFlaggedForReschedule,
  isRecoveryExpired,
  minutesUntilDeadline,
  parseDeadline,
  toRescheduleParams,
} from "@/helpers/orderRecovery";
import { CartDeliverySlot } from "@/types/ApiResponse";

/**
 * Recovery gates the only path a customer has to save a flagged order, so both
 * failure directions are real: locking them out early strands the order, and
 * leaving the UI live past the deadline sends a request the backend rejects.
 *
 * `now` is injected everywhere — these assertions must not drift with the clock.
 */

/** Backend format: ISO-8601 UTC with 6-digit microseconds. */
const DEADLINE = "2026-07-31T14:30:00.000000Z";
const at = (iso: string) => new Date(iso);

describe("isFlaggedForReschedule", () => {
  it("flags only needs_reschedule", () => {
    expect(
      isFlaggedForReschedule({ reschedule_status: "needs_reschedule" }),
    ).toBe(true);
  });

  it.each([
    ["already rescheduled", "rescheduled"],
    ["cancelled", "cancelled"],
    ["explicitly none", "none"],
  ])("does not flag an order that is %s", (_label, status) => {
    expect(
      isFlaggedForReschedule({
        reschedule_status: status as "rescheduled",
      }),
    ).toBe(false);
  });

  it("does not flag orders from a backend without the feature", () => {
    // The field is absent on every order predating the feature — that must read
    // as "nothing to do", never as a prompt.
    expect(isFlaggedForReschedule({})).toBe(false);
    expect(isFlaggedForReschedule(null)).toBe(false);
    expect(isFlaggedForReschedule(undefined)).toBe(false);
  });
});

describe("parseDeadline", () => {
  it("parses the backend's microsecond ISO format", () => {
    expect(parseDeadline(DEADLINE)).toBe(Date.parse(DEADLINE));
  });

  it.each([
    ["null", null],
    ["undefined", undefined],
    ["empty", ""],
    ["whitespace", "   "],
    ["unparseable", "not-a-date"],
  ])("returns null for %s rather than NaN", (_label, value) => {
    // NaN would be poison: every comparison against it is false, which reads as
    // "not expired" and leaves a dead button on screen.
    expect(parseDeadline(value as string)).toBeNull();
  });
});

describe("isRecoveryExpired", () => {
  it("is not expired before the deadline", () => {
    expect(
      isRecoveryExpired(
        { reschedule_deadline: DEADLINE },
        at("2026-07-31T14:29:59.000Z"),
      ),
    ).toBe(false);
  });

  it("is expired exactly at the deadline", () => {
    // The boundary is inclusive: at the deadline the window has closed.
    expect(
      isRecoveryExpired(
        { reschedule_deadline: DEADLINE },
        at("2026-07-31T14:30:00.000Z"),
      ),
    ).toBe(true);
  });

  it("is expired after the deadline", () => {
    expect(
      isRecoveryExpired(
        { reschedule_deadline: DEADLINE },
        at("2026-07-31T15:00:00.000Z"),
      ),
    ).toBe(true);
  });

  it("treats a missing deadline as still actionable", () => {
    // The backend is the authority on refusal. Locking the customer out on a
    // field the backend simply didn't send would strand the order.
    expect(isRecoveryExpired({}, at("2026-07-31T14:30:00.000Z"))).toBe(false);
    expect(
      isRecoveryExpired(
        { reschedule_deadline: "garbage" },
        at("2026-07-31T14:30:00.000Z"),
      ),
    ).toBe(false);
  });
});

describe("canRecoverOrder", () => {
  const flagged = {
    reschedule_status: "needs_reschedule" as const,
    reschedule_deadline: DEADLINE,
  };

  it("allows a flagged order before its deadline", () => {
    expect(canRecoverOrder(flagged, at("2026-07-30T10:00:00.000Z"))).toBe(true);
  });

  it("blocks a flagged order past its deadline", () => {
    expect(canRecoverOrder(flagged, at("2026-08-01T10:00:00.000Z"))).toBe(false);
  });

  it("blocks an unflagged order even well before the deadline", () => {
    // Both conditions are required — a deadline alone is not an invitation.
    expect(
      canRecoverOrder(
        { reschedule_status: "none", reschedule_deadline: DEADLINE },
        at("2026-07-30T10:00:00.000Z"),
      ),
    ).toBe(false);
  });

  it("allows a flagged order with no deadline at all", () => {
    expect(
      canRecoverOrder(
        { reschedule_status: "needs_reschedule" },
        at("2026-07-30T10:00:00.000Z"),
      ),
    ).toBe(true);
  });
});

describe("minutesUntilDeadline", () => {
  it("floors to whole minutes", () => {
    expect(
      minutesUntilDeadline(
        { reschedule_deadline: DEADLINE },
        at("2026-07-31T14:00:30.000Z"),
      ),
    ).toBe(29);
  });

  it("never goes negative once past", () => {
    expect(
      minutesUntilDeadline(
        { reschedule_deadline: DEADLINE },
        at("2026-08-05T00:00:00.000Z"),
      ),
    ).toBe(0);
  });

  it("returns null with no usable deadline", () => {
    expect(minutesUntilDeadline({}, at("2026-07-31T00:00:00.000Z"))).toBeNull();
  });
});

describe("formatTimeRemaining", () => {
  it.each([
    ["2026-07-31T14:29:30.000Z", "less than a minute"],
    ["2026-07-31T14:29:00.000Z", "1 minute"],
    ["2026-07-31T13:45:00.000Z", "45 minutes"],
    ["2026-07-31T13:30:00.000Z", "1 hour"],
    ["2026-07-31T09:30:00.000Z", "5 hours"],
    ["2026-07-30T14:30:00.000Z", "1 day"],
    ["2026-07-29T14:30:00.000Z", "2 days"],
  ])("at %s reads %s", (now, expected) => {
    expect(formatTimeRemaining({ reschedule_deadline: DEADLINE }, at(now))).toBe(
      expected,
    );
  });

  it("returns null with no deadline, so the banner omits the countdown", () => {
    expect(formatTimeRemaining({}, at("2026-07-31T00:00:00.000Z"))).toBeNull();
  });
});

describe("isCartSlotBookable", () => {
  const slot = (remaining_capacity: number): CartDeliverySlot => ({
    start_time: "14:00:00",
    end_time: "15:00:00",
    formatted_time: "02:00 PM - 03:00 PM",
    remaining_capacity,
  });

  it("is bookable with capacity left", () => {
    expect(isCartSlotBookable(slot(8))).toBe(true);
    expect(isCartSlotBookable(slot(1))).toBe(true);
  });

  it("is not bookable when full", () => {
    // Capacity is the ONLY availability signal on a cart slot — there is no
    // is_available flag to fall back on.
    expect(isCartSlotBookable(slot(0))).toBe(false);
    expect(isCartSlotBookable(slot(-1))).toBe(false);
  });

  it("treats a missing capacity as full", () => {
    expect(isCartSlotBookable({} as CartDeliverySlot)).toBe(false);
  });
});

describe("toRescheduleParams", () => {
  const slot = { start_time: "15:00:00", end_time: "16:00:00" };

  it("builds the identity triple the endpoint expects", () => {
    expect(toRescheduleParams("2026-08-01", slot)).toEqual({
      scheduled_date: "2026-08-01",
      scheduled_slot_start_time: "15:00:00",
      scheduled_slot_end_time: "16:00:00",
    });
  });

  it("builds a date-only reschedule when no slot is given", () => {
    // Slot scheduling can be disabled server-side; the backend then serves
    // dates with no slots and a date-only reschedule is the whole request.
    expect(toRescheduleParams("2026-08-01", null)).toEqual({
      scheduled_date: "2026-08-01",
    });
    expect(toRescheduleParams("2026-08-01")).toEqual({
      scheduled_date: "2026-08-01",
    });
  });

  it.each([
    ["a slot missing its end", "2026-08-01", { start_time: "15:00:00" }],
    ["a slot missing its start", "2026-08-01", { end_time: "16:00:00" }],
  ])("refuses %s", (_label, date, partial) => {
    // A half-identified window would be rejected by the backend, or worse book
    // something the customer didn't choose. Fail before the request.
    expect(
      toRescheduleParams(date, partial as { start_time: string; end_time: string }),
    ).toBeNull();
  });

  it.each([["30-08-2026"], ["2026-8-1"], [""], ["not-a-date"]])(
    "refuses the malformed date %s",
    (date) => {
      expect(toRescheduleParams(date, slot)).toBeNull();
    },
  );
});
