import { describe, it, expect } from "vitest";
import {
  CLOSE_HOUR,
  OPEN_HOUR,
  SCHEDULE_DAYS,
  ScheduleDate,
  findMonthIndex,
  formatFullDate,
  formatScheduleSummary,
  formatSlotLabel,
  fromDateKey,
  getCalendarMonths,
  getFirstSelectableDate,
  getScheduleDates,
  getSlotsForDate,
  groupSlotsByPeriod,
  toClosedWeekdays,
  toDateKey,
} from "@/helpers/deliverySlots";

// Fixed clock: Sat 18 Jul 2026, 10:00 local. All assertions are deterministic.
const at = (h: number, m = 0) => new Date(2026, 6, 18, h, m);
const TODAY = "2026-07-18";

describe("toDateKey / fromDateKey", () => {
  it("uses local time, not UTC (no midnight date-shift)", () => {
    // 00:30 local would be the previous day under toISOString() in +TZ offsets.
    expect(toDateKey(new Date(2026, 6, 18, 0, 30))).toBe("2026-07-18");
    expect(toDateKey(new Date(2026, 6, 18, 23, 30))).toBe("2026-07-18");
  });

  it("round-trips a date key", () => {
    const d = fromDateKey("2026-07-19");
    expect(d.getFullYear()).toBe(2026);
    expect(d.getMonth()).toBe(6);
    expect(d.getDate()).toBe(19);
  });
});

describe("formatSlotLabel", () => {
  it("collapses a repeated meridiem", () => {
    expect(formatSlotLabel(8, 9)).toBe("8 – 9 AM");
    expect(formatSlotLabel(20, 21)).toBe("8 – 9 PM");
  });

  it("keeps both when the meridiem changes", () => {
    expect(formatSlotLabel(11, 12)).toBe("11 AM – 12 PM");
  });

  it("renders noon and afternoon correctly", () => {
    expect(formatSlotLabel(12, 13)).toBe("12 – 1 PM");
  });
});

describe("getSlotsForDate", () => {
  it("generates one slot per hour across the delivery window", () => {
    const slots = getSlotsForDate("2026-07-19", at(10));
    expect(slots).toHaveLength(CLOSE_HOUR - OPEN_HOUR); // 8am–9pm => 13
    expect(slots[0].startHour).toBe(OPEN_HOUR);
    expect(slots[slots.length - 1].endHour).toBe(CLOSE_HOUR);
  });

  it("marks every slot available on a future date", () => {
    const slots = getSlotsForDate("2026-07-19", at(10));
    expect(slots.every((s) => s.isAvailable)).toBe(true);
  });

  it("disables today's past slots but keeps them visible", () => {
    const slots = getSlotsForDate(TODAY, at(10));
    const eight = slots.find((s) => s.startHour === 8)!;
    const nine = slots.find((s) => s.startHour === 9)!;

    expect(eight.isAvailable).toBe(false); // already gone
    expect(nine.isAvailable).toBe(false);
    // still returned, so the grid greys them rather than shrinking
    expect(slots).toHaveLength(CLOSE_HOUR - OPEN_HOUR);
  });

  it("enforces the lead-time buffer (no booking a slot starting in 10 min)", () => {
    // 10:50 now -> the 11am slot starts in 10 minutes: too soon with a 60m lead.
    const slots = getSlotsForDate(TODAY, at(10, 50));
    expect(slots.find((s) => s.startHour === 11)!.isAvailable).toBe(false);
    expect(slots.find((s) => s.startHour === 12)!.isAvailable).toBe(true);
  });

  it("respects a custom lead time", () => {
    const slots = getSlotsForDate(TODAY, at(10, 50), 0);
    expect(slots.find((s) => s.startHour === 11)!.isAvailable).toBe(true);
  });

  it("leaves nothing available late at night", () => {
    const slots = getSlotsForDate(TODAY, at(23));
    expect(slots.every((s) => !s.isAvailable)).toBe(true);
  });

  it("tags periods correctly", () => {
    const slots = getSlotsForDate("2026-07-19", at(10));
    expect(slots.find((s) => s.startHour === 8)!.period).toBe("morning");
    expect(slots.find((s) => s.startHour === 13)!.period).toBe("afternoon");
    expect(slots.find((s) => s.startHour === 19)!.period).toBe("evening");
  });

  it("produces stable, unique ids", () => {
    const slots = getSlotsForDate("2026-07-19", at(10));
    expect(slots[0].id).toBe("2026-07-19-08");
    expect(new Set(slots.map((s) => s.id)).size).toBe(slots.length);
  });
});

describe("getScheduleDates", () => {
  it("returns today plus the next 6 days", () => {
    const dates = getScheduleDates(at(10));
    expect(dates).toHaveLength(SCHEDULE_DAYS);
    expect(dates[0].date).toBe(TODAY);
    expect(dates[0].isToday).toBe(true);
    expect(dates[1].isTomorrow).toBe(true);
    expect(dates[6].date).toBe("2026-07-24");
  });

  it("honours a custom window length", () => {
    expect(getScheduleDates(at(10), 5)).toHaveLength(5);
  });

  it("counts today's remaining slots, not the full day", () => {
    // No closures: this asserts lead-time maths, not the store's opening days.
    const [today, tomorrow] = getScheduleDates(at(10), 7, 60, []);
    // At 10:00 with a 60m lead, 11:00 sits exactly on the boundary and is
    // bookable, so 11:00–20:00 remain => 10 (vs 13 for an untouched day).
    expect(today.availableCount).toBe(10);
    expect(tomorrow.availableCount).toBe(CLOSE_HOUR - OPEN_HOUR);
  });

  it("treats a slot exactly one lead-time away as bookable (boundary)", () => {
    const slots = getSlotsForDate(TODAY, at(10));
    expect(slots.find((s) => s.startHour === 11)!.isAvailable).toBe(true);
    expect(slots.find((s) => s.startHour === 10)!.isAvailable).toBe(false);
  });

  it("reports today as full late at night", () => {
    expect(getScheduleDates(at(23))[0].availableCount).toBe(0);
  });

  it("rolls into the next month correctly", () => {
    const dates = getScheduleDates(new Date(2026, 6, 29, 10));
    expect(dates.map((d) => d.date)).toContain("2026-08-01");
    expect(dates[dates.length - 1].monthShort).toBe("Aug");
  });
});

describe("getScheduleDates — store closed days", () => {
  // 18 Jul 2026 is a Saturday, 19 Jul a Sunday.
  it("marks closed weekdays and zeroes their slots", () => {
    const dates = getScheduleDates(at(10), 7, 60, [0]); // Sundays closed
    const sunday = dates.find((d) => d.date === "2026-07-19")!;

    expect(sunday.isClosed).toBe(true);
    expect(sunday.availableCount).toBe(0);
  });

  it("leaves open days untouched", () => {
    const dates = getScheduleDates(at(10), 7, 60, [0]);
    const monday = dates.find((d) => d.date === "2026-07-20")!;

    expect(monday.isClosed).toBe(false);
    expect(monday.availableCount).toBe(CLOSE_HOUR - OPEN_HOUR);
  });

  it("supports a weekend closure (Sat + Sun)", () => {
    const dates = getScheduleDates(at(10), 7, 60, [0, 6]);
    const closed = dates.filter((d) => d.isClosed).map((d) => d.date);

    // A 7-day window from Sat 18 covers 18–24, so only Sat 18 + Sun 19 fall in it.
    expect(closed).toEqual(["2026-07-18", "2026-07-19"]);
    expect(dates.filter((d) => d.isClosed).every((d) => d.availableCount === 0)).toBe(
      true,
    );
  });

  it("opens every day when nothing is closed", () => {
    const dates = getScheduleDates(at(10), 7, 60, []);
    expect(dates.every((d) => !d.isClosed)).toBe(true);
  });

  it("still occupies its calendar cell so the grid doesn't shift", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 7, 60, [0]));
    const sunday = months
      .flatMap((m) => m.weeks.flat())
      .find((c) => c.date === "2026-07-19");

    expect(sunday?.dayOfMonth).toBe(19);
    expect(sunday?.schedule?.isClosed).toBe(true);
  });

  it("skips a closed day when picking the first selectable date", () => {
    // Today (Sat 18) closed -> must land on Mon 20, not the closed Sun 19.
    const first = getFirstSelectableDate(getScheduleDates(at(10), 7, 60, [6, 0]));
    expect(first?.date).toBe("2026-07-20");
  });
});

describe("getFirstSelectableDate", () => {
  it("picks today while it still has capacity", () => {
    expect(getFirstSelectableDate(getScheduleDates(at(10)))?.date).toBe(TODAY);
  });

  it("skips a full today and lands on tomorrow", () => {
    // Opening the picker at 11pm must not dump the user on a dead day.
    // No closures, so "tomorrow" is purely about the lead-time rollover.
    const first = getFirstSelectableDate(getScheduleDates(at(23), 7, 60, []));
    expect(first?.date).toBe("2026-07-19");
    expect(first?.isTomorrow).toBe(true);
  });

  it("handles an empty list", () => {
    expect(getFirstSelectableDate([])).toBeNull();
  });
});

describe("groupSlotsByPeriod", () => {
  it("splits the day into morning/afternoon/evening", () => {
    const g = groupSlotsByPeriod(getSlotsForDate("2026-07-19", at(10)));
    expect(g.morning.map((s) => s.startHour)).toEqual([8, 9, 10, 11]);
    expect(g.afternoon.map((s) => s.startHour)).toEqual([12, 13, 14, 15, 16]);
    expect(g.evening.map((s) => s.startHour)).toEqual([17, 18, 19, 20]);
  });

  it("returns empty buckets for no slots", () => {
    expect(groupSlotsByPeriod([])).toEqual({
      morning: [],
      afternoon: [],
      evening: [],
    });
  });
});

describe("getCalendarMonths", () => {
  const cellFor = (months: ReturnType<typeof getCalendarMonths>, key: string) =>
    months.flatMap((m) => m.weeks.flat()).find((c) => c.date === key);

  it("returns [] for an empty window", () => {
    expect(getCalendarMonths([])).toEqual([]);
  });

  // Window length is passed explicitly: SCHEDULE_DAYS is a tunable default
  // (and will come from the backend), so tests must not depend on its value.
  it("builds a single month for a 7-day window", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 7));
    expect(months).toHaveLength(1);
    expect(months[0].key).toBe("2026-07");
    expect(months[0].label).toBe("July 2026");
  });

  it("spans multiple months for a 30-day window", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 30));
    // 18 Jul + 30 days crosses into August
    expect(months.map((m) => m.key)).toEqual(["2026-07", "2026-08"]);
  });

  it("gives every week exactly 7 cells, padding the edges", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 30));
    for (const month of months) {
      for (const week of month.weeks) expect(week).toHaveLength(7);
    }
  });

  it("aligns the 1st of the month to the right weekday column", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 30));
    const july = months[0];
    // 1 Jul 2026 is a Wednesday -> index 3, so 3 leading blanks.
    expect(july.weeks[0].slice(0, 3).every((c) => c.date === null)).toBe(true);
    expect(july.weeks[0][3].dayOfMonth).toBe(1);
  });

  it("renders every day of the month, bookable or not", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 30));
    const julyDays = months[0].weeks.flat().filter((c) => c.date !== null);
    expect(julyDays).toHaveLength(31);
  });

  it("marks days outside the window as unbookable (schedule: null)", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 7));
    // In-window
    expect(cellFor(months, "2026-07-18")?.schedule).not.toBeNull();
    // Before today, and beyond the 7-day window
    expect(cellFor(months, "2026-07-01")?.schedule).toBeNull();
    expect(cellFor(months, "2026-07-31")?.schedule).toBeNull();
  });

  it("handles a NON-CONTIGUOUS window (e.g. store closed on Sundays)", () => {
    // The whole reason for a calendar: the backend can send gaps.
    const sparse: ScheduleDate[] = getScheduleDates(at(10)).filter(
      (d) => fromDateKey(d.date).getDay() !== 0, // drop Sundays
    );
    const months = getCalendarMonths(sparse);

    expect(cellFor(months, "2026-07-19")?.schedule).toBeNull(); // Sunday: gap
    expect(cellFor(months, "2026-07-20")?.schedule).not.toBeNull(); // Monday: open
    // The gap doesn't shift the grid — the day still occupies its cell.
    expect(cellFor(months, "2026-07-19")?.dayOfMonth).toBe(19);
  });

  it("carries each day's availableCount through to its cell", () => {
    const months = getCalendarMonths(getScheduleDates(at(10), 7, 60, []));
    // Today is partly gone (10 left), tomorrow is untouched (13).
    expect(cellFor(months, "2026-07-18")?.schedule?.availableCount).toBe(10);
    expect(cellFor(months, "2026-07-19")?.schedule?.availableCount).toBe(
      CLOSE_HOUR - OPEN_HOUR,
    );
  });

  it("flags today", () => {
    const months = getCalendarMonths(getScheduleDates(at(10)));
    expect(cellFor(months, "2026-07-18")?.schedule?.isToday).toBe(true);
    expect(cellFor(months, "2026-07-19")?.schedule?.isToday).toBe(false);
  });
});

describe("findMonthIndex", () => {
  const months = getCalendarMonths(getScheduleDates(at(10), 30));

  it("finds the month holding the active date", () => {
    expect(findMonthIndex(months, "2026-08-05")).toBe(1);
    expect(findMonthIndex(months, "2026-07-18")).toBe(0);
  });

  it("falls back to the first month for null/unknown dates", () => {
    expect(findMonthIndex(months, null)).toBe(0);
    expect(findMonthIndex(months, "2027-01-01")).toBe(0);
    expect(findMonthIndex([], "2026-07-18")).toBe(0);
  });
});

describe("formatFullDate", () => {
  it("spells out the weekday and month", () => {
    expect(formatFullDate("2026-07-18")).toBe("Saturday, 18 July");
    expect(formatFullDate("2026-07-20")).toBe("Monday, 20 July");
  });

  it("handles a month boundary", () => {
    expect(formatFullDate("2026-08-01")).toBe("Saturday, 1 August");
  });
});

describe("formatScheduleSummary", () => {
  it("says Today / Tomorrow rather than a raw date", () => {
    expect(formatScheduleSummary(TODAY, "8 – 9 PM", at(10))).toBe(
      "Today · 8 – 9 PM",
    );
    expect(formatScheduleSummary("2026-07-19", "8 – 9 AM", at(10))).toBe(
      "Tomorrow · 8 – 9 AM",
    );
  });

  it("falls back to a weekday+date for later days", () => {
    expect(formatScheduleSummary("2026-07-22", "1 – 2 PM", at(10))).toBe(
      "Wed, 22 Jul · 1 – 2 PM",
    );
  });
});

describe("toClosedWeekdays", () => {
  /**
   * Real value from /api/stores/amits-beauty-essentials — the store's
   * scheduled_delivery_info.available_days.
   */
  const AVAILABLE = ["Monday", "Tuesday", "Thursday", "Friday", "Saturday"];

  it("inverts the store's open days into closed ones", () => {
    // Wednesday (3) and Sunday (0) are absent, so they're shut.
    expect(toClosedWeekdays(AVAILABLE)).toEqual([0, 3]);
  });

  it("is case-insensitive and tolerates padding", () => {
    expect(toClosedWeekdays([" monday ", "TUESDAY"])).toEqual([0, 3, 4, 5, 6]);
  });

  it("returns an empty list when the store is open all week", () => {
    const week = [
      "Sunday", "Monday", "Tuesday", "Wednesday",
      "Thursday", "Friday", "Saturday",
    ];
    expect(toClosedWeekdays(week)).toEqual([]);
  });

  /**
   * null means "no answer, keep the defaults" — distinct from [] meaning "open
   * every day". Collapsing the two would let a missing field read as always-open.
   */
  it.each([
    ["null", null],
    ["undefined", undefined],
    // Only ever seen alongside is_enabled:false; honouring it literally would
    // strike out all seven days and leave nothing to click.
    ["an empty list", []],
    ["a non-array", "Monday" as unknown as string[]],
  ])("returns null for %s", (_label, input) => {
    expect(toClosedWeekdays(input as string[] | null)).toBeNull();
  });

  it("returns null if any day name is unrecognised", () => {
    // Partial trust is the hazard: silently dropping "Funday" would mark real
    // opening days closed.
    expect(toClosedWeekdays(["Monday", "Funday"])).toBeNull();
  });

  it("feeds getScheduleDates so the store's closed days are unbookable", () => {
    const closed = toClosedWeekdays(AVAILABLE)!;
    const dates = getScheduleDates(at(10), 7, 60, closed);

    const byDay = (key: string) => dates.find((d) => d.date === key)!;
    // Sun 19 Jul and Wed 22 Jul 2026.
    expect(byDay("2026-07-19").isClosed).toBe(true);
    expect(byDay("2026-07-19").availableCount).toBe(0);
    expect(byDay("2026-07-22").isClosed).toBe(true);
    // Thu 23 Jul is an open day.
    expect(byDay("2026-07-23").isClosed).toBe(false);
    expect(byDay("2026-07-23").availableCount).toBeGreaterThan(0);
  });
});
