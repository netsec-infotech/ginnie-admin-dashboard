import { describe, expect, it } from "vitest";

import {
  formatDayHours,
  formatMinutes,
  getTodayHours,
  hasUniformHours,
  parseStoreTiming,
} from "@/helpers/storeTiming";

/**
 * The strings below are REAL values pulled from /api/stores/{slug} — including
 * the junk. `timing` is free text, so the parser's most important job is
 * knowing when to give up.
 */
const CANONICAL =
  "Mon: 09:00-21:00, Tue: 09:00-21:00, Thu: 09:00-21:00, Fri: 09:00-21:00, Sat: 09:00-21:00";

describe("parseStoreTiming", () => {
  it("parses the canonical admin-generated string", () => {
    const week = parseStoreTiming(CANONICAL);
    expect(week).not.toBeNull();

    const mon = week!.find((d) => d.weekday === 1);
    expect(mon).toMatchObject({ short: "Mon", opensAt: 540, closesAt: 1260 });
  });

  it("returns all seven days, Monday first", () => {
    const week = parseStoreTiming(CANONICAL)!;
    expect(week).toHaveLength(7);
    expect(week.map((d) => d.short)).toEqual([
      "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun",
    ]);
  });

  it("treats days absent from the string as closed", () => {
    // Cross-checked against this store's scheduled_delivery_info.available_days,
    // which lists exactly Mon/Tue/Thu/Fri/Sat — so Wed and Sun really are shut.
    const week = parseStoreTiming(CANONICAL)!;
    const closed = week.filter((d) => d.opensAt === null).map((d) => d.short);
    expect(closed).toEqual(["Wed", "Sun"]);
  });

  it("accepts a Sunday-inclusive string", () => {
    const week = parseStoreTiming(
      "Tue: 09:00-21:00, Wed: 09:00-21:00, Thu: 09:00-21:00, Fri: 09:00-21:00, Sat: 09:00-21:00, Sun: 09:00-21:00",
    )!;
    expect(week.find((d) => d.weekday === 0)?.opensAt).toBe(540);
    expect(week.find((d) => d.weekday === 1)?.opensAt).toBeNull();
  });

  it("is case-insensitive on day names", () => {
    expect(parseStoreTiming("MON: 09:00-21:00")).not.toBeNull();
  });

  it("keeps the last entry when a day is duplicated", () => {
    const week = parseStoreTiming("Mon: 09:00-21:00, Mon: 10:00-18:00")!;
    expect(week.find((d) => d.weekday === 1)?.opensAt).toBe(600);
  });

  // The whole point: bail out rather than invent a schedule.
  it.each([
    ["null", null],
    ["undefined", undefined],
    ["empty", ""],
    ["whitespace", "   "],
    ["seller junk", "aaaaaaaaaaaaaa"],
    ["legacy day range", "Mon-Fri"],
    ["legacy prose", "MON-SUN 9 to 5 pm"],
    ["legacy bracketed", "11 AM – 8 PM (Mon–Sat)"],
    ["unknown day name", "Xyz: 09:00-21:00"],
    ["missing time", "Mon: 09:00-"],
    ["impossible hour", "Mon: 33:00-21:00"],
    ["impossible minute", "Mon: 09:99-21:00"],
  ])("returns null for %s", (_label, input) => {
    expect(parseStoreTiming(input as string | null)).toBeNull();
  });

  it("rejects the whole string if a single segment is malformed", () => {
    // Partial success is the dangerous case — one bad segment must not yield a
    // table that silently marks the rest of the week closed.
    expect(parseStoreTiming("Mon: 09:00-21:00, Tue: whenever")).toBeNull();
  });

  it("keeps an overnight range as written rather than rejecting it", () => {
    const week = parseStoreTiming("Fri: 22:00-02:00")!;
    expect(week.find((d) => d.weekday === 5)).toMatchObject({
      opensAt: 1320,
      closesAt: 120,
    });
  });
});

describe("formatMinutes", () => {
  it.each([
    [0, "12:00 AM"],
    [540, "9:00 AM"],
    [690, "11:30 AM"],
    [720, "12:00 PM"],
    [1260, "9:00 PM"],
    [1439, "11:59 PM"],
  ])("formats %i as %s", (minutes, expected) => {
    expect(formatMinutes(minutes)).toBe(expected);
  });
});

describe("formatDayHours", () => {
  it("renders a range for an open day", () => {
    const week = parseStoreTiming(CANONICAL)!;
    expect(formatDayHours(week[0])).toBe("9:00 AM – 9:00 PM");
  });

  it("returns null for a closed day so the caller can label it", () => {
    const week = parseStoreTiming(CANONICAL)!;
    expect(formatDayHours(week.find((d) => d.short === "Wed")!)).toBeNull();
  });
});

describe("getTodayHours", () => {
  it("picks the row matching the injected clock", () => {
    const week = parseStoreTiming(CANONICAL)!;
    // 2026-07-17 is a Friday.
    expect(getTodayHours(week, new Date(2026, 6, 17))?.short).toBe("Fri");
  });

  it("returns the closed row on a closed day", () => {
    const week = parseStoreTiming(CANONICAL)!;
    // 2026-07-19 is a Sunday — present in the week, but shut.
    const today = getTodayHours(week, new Date(2026, 6, 19));
    expect(today?.short).toBe("Sun");
    expect(today?.opensAt).toBeNull();
  });
});

describe("hasUniformHours", () => {
  it("is true when every open day shares the same range", () => {
    expect(hasUniformHours(parseStoreTiming(CANONICAL)!)).toBe(true);
  });

  it("is false when one day differs", () => {
    const week = parseStoreTiming("Mon: 09:00-21:00, Tue: 10:00-18:00")!;
    expect(hasUniformHours(week)).toBe(false);
  });

  it("is false for a single open day — nothing to collapse", () => {
    expect(hasUniformHours(parseStoreTiming("Mon: 09:00-21:00")!)).toBe(false);
  });
});
