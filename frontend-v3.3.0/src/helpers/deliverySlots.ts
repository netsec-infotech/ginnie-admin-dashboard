/**
 * Scheduled-delivery slot generation.
 *
 * Pure + deterministic: `now` is always injected, never read from the clock in
 * here, so the UI and the tests see identical behaviour. This module is the
 * single place to swap out when the backend exposes real slots — the component
 * and the redux slice consume these shapes and won't need to change
 * (see `delivery_time_slot_id` on Order / OrderCheckoutResponse).
 *
 * The backend now DOES expose real slots via
 * `/scheduled-delivery/stores/{id}/slots?date=` — see `adaptScheduledSlots`
 * and `getScheduleDatesFromConfig`. The local generators below
 * (`getSlotsForDate`, `getScheduleDates`) are retained for their tests and for
 * `formatScheduleSummary`, but the picker no longer invents slots: a store
 * without scheduling shows nothing rather than a window it never offered.
 */
import { CartDeliverySlot, ScheduledDeliverySlot } from "@/types/ApiResponse";

/** Delivery window: 8am → 9pm, in 1-hour slots. */
export const OPEN_HOUR = 8;
export const CLOSE_HOUR = 21;

/** How many days ahead can be booked (today + 6). */
export const SCHEDULE_DAYS = 12;

/** A slot can't be booked if it starts within this many minutes. */
export const LEAD_TIME_MINUTES = 60;

/**
 * Weekdays the store doesn't deliver (0 = Sun … 6 = Sat).
 * Static stand-in for the store's opening days — swap for the backend's value.
 * A closed day yields zero slots, so the calendar disables it automatically.
 */
export const CLOSED_WEEKDAYS: number[] = [0];

const WEEKDAY_BY_NAME: Record<string, number> = {
  sunday: 0,
  monday: 1,
  tuesday: 2,
  wednesday: 3,
  thursday: 4,
  friday: 5,
  saturday: 6,
};

/**
 * Turn the store's `scheduled_delivery_info.available_days` into the closed-day
 * list `getScheduleDates` takes.
 *
 * The API sends full day names ("Monday"), and everything NOT listed is closed
 * — cross-checked against the same store's free-text `timing`, which names
 * exactly the same days.
 *
 * Returns **null** for unknown rather than an empty array, and the distinction
 * is the point: null means "the store didn't tell us, keep the defaults", while
 * `[]` is a real answer meaning "open every day". Collapsing the two would let a
 * missing field silently read as "always open".
 *
 * An empty `available_days` is treated as unknown, not as "closed all week":
 * it only ever co-occurs with `is_enabled: false` (where the picker is blocked
 * outright anyway), so honouring it literally would strike out every day and
 * leave a dead calendar with nothing to click.
 */
export const toClosedWeekdays = (
  availableDays: string[] | null | undefined,
): number[] | null => {
  if (!Array.isArray(availableDays) || availableDays.length === 0) return null;

  const open = new Set<number>();
  for (const name of availableDays) {
    const weekday = WEEKDAY_BY_NAME[String(name).trim().toLowerCase()];
    // One unrecognised name means we can't trust the set — a partial read would
    // mark real opening days closed.
    if (weekday === undefined) return null;
    open.add(weekday);
  }

  return [0, 1, 2, 3, 4, 5, 6].filter((day) => !open.has(day));
};

export type SlotPeriod = "morning" | "afternoon" | "evening";

export interface DeliverySlot {
  /** Stable id, e.g. "2026-07-19-08". Stands in for delivery_time_slot_id. */
  id: string;
  /** The backend's real id, if available. Used for booking. */
  dbId?: number;
  date: string;
  startHour: number;
  endHour: number;
  startTime?: string;
  endTime?: string;
  /** e.g. "8 – 9 AM" */
  label: string;
  period: SlotPeriod;
  isAvailable: boolean;
  remainingCapacity?: number;
}

export interface ScheduleDate {
  /** Local YYYY-MM-DD. */
  date: string;
  weekdayShort: string;
  dayOfMonth: number;
  monthShort: string;
  isToday: boolean;
  isTomorrow: boolean;
  /** The store doesn't deliver on this weekday at all. */
  isClosed: boolean;
  availableCount: number;
}

export const WEEKDAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const WEEKDAYS_FULL = [
  "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday",
];
const MONTHS = [
  "Jan", "Feb", "Mar", "Apr", "May", "Jun",
  "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
];
const MONTHS_FULL = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];


const pad = (n: number) => String(n).padStart(2, "0");

/** Local-date key. Avoids toISOString(), which shifts across the UTC boundary. */
export const toDateKey = (date: Date): string =>
  `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

/** Parse a YYYY-MM-DD key back to local midnight. */
export const fromDateKey = (key: string): Date => {
  const [y, m, d] = key.split("-").map(Number);
  return new Date(y, (m ?? 1) - 1, d ?? 1);
};

const periodForHour = (hour: number): SlotPeriod => {
  if (hour < 12) return "morning";
  if (hour < 17) return "afternoon";
  return "evening";
};

/** "8 – 9 AM" / "11 AM – 12 PM" — compact, and only repeats the meridiem when it changes. */
export const formatSlotLabel = (startHour: number, endHour: number): string => {
  const to12 = (h: number) => (h % 12 === 0 ? 12 : h % 12);
  const suffix = (h: number) => (h < 12 || h === 24 ? "AM" : "PM");

  return suffix(startHour) === suffix(endHour)
    ? `${to12(startHour)} – ${to12(endHour)} ${suffix(endHour)}`
    : `${to12(startHour)} ${suffix(startHour)} – ${to12(endHour)} ${suffix(endHour)}`;
};

/**
 * Slots for one day. Slots in the past — or starting inside the lead-time
 * buffer — come back `isAvailable: false` rather than being dropped, so the UI
 * can show them greyed out instead of silently shrinking the grid.
 */
export const getSlotsForDate = (
  dateKey: string,
  now: Date,
  leadTimeMinutes: number = LEAD_TIME_MINUTES,
): DeliverySlot[] => {
  const day = fromDateKey(dateKey);
  const earliest = now.getTime() + leadTimeMinutes * 60_000;

  const slots: DeliverySlot[] = [];
  for (let hour = OPEN_HOUR; hour < CLOSE_HOUR; hour++) {
    const start = new Date(
      day.getFullYear(),
      day.getMonth(),
      day.getDate(),
      hour,
    );

    slots.push({
      id: `${dateKey}-${pad(hour)}`,
      date: dateKey,
      startHour: hour,
      endHour: hour + 1,
      label: formatSlotLabel(hour, hour + 1),
      period: periodForHour(hour),
      isAvailable: start.getTime() >= earliest,
    });
  }
  return slots;
};

/**
 * The bookable days: today → today + (days - 1), each with its live slot count.
 *
 * Days the store is closed come back with `isClosed: true` and zero slots, so
 * they still occupy their calendar cell (the grid must not shift) but can't be
 * selected.
 */
export const getScheduleDates = (
  now: Date,
  days: number = SCHEDULE_DAYS,
  leadTimeMinutes: number = LEAD_TIME_MINUTES,
  closedWeekdays: number[] = CLOSED_WEEKDAYS,
): ScheduleDate[] => {
  const dates: ScheduleDate[] = [];

  for (let offset = 0; offset < days; offset++) {
    const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset);
    const key = toDateKey(d);
    const isClosed = closedWeekdays.includes(d.getDay());

    dates.push({
      date: key,
      weekdayShort: WEEKDAYS[d.getDay()],
      dayOfMonth: d.getDate(),
      monthShort: MONTHS[d.getMonth()],
      isToday: offset === 0,
      isTomorrow: offset === 1,
      isClosed,
      // A closed day has no slots at all — don't bother computing them.
      availableCount: isClosed
        ? 0
        : getSlotsForDate(key, now, leadTimeMinutes).filter((s) => s.isAvailable)
            .length,
    });
  }

  return dates;
};

/**
 * Bookable dates from the store's real config.
 *
 * The `/dates` endpoint returns the RULES (which weekdays, how far ahead), not
 * a list of days — so the calendar's dates are derived here. A day the store is
 * closed still gets a cell (`isClosed`), because the month grid must not shift.
 *
 * `availableCount` is deliberately left at 0/1 as a boolean-ish flag rather
 * than a real count: only `/slots?date=` knows true availability, and calling
 * it for every day in the window just to render the grid would be 7+ requests
 * for information the user hasn't asked for yet.
 */
export const getScheduleDatesFromConfig = (
  now: Date,
  config: {
    available_days?: string[];
    max_advance_booking_days?: number;
    max_days?: number;
  },
): ScheduleDate[] => {
  const closedWeekdays = toClosedWeekdays(config.available_days) ?? [];
  // `max_days` is the effective window and reads 0 when scheduling is off;
  // `max_advance_booking_days` is the configured one. Prefer whichever is real.
  const days =
    config.max_days || config.max_advance_booking_days || SCHEDULE_DAYS;

  const dates: ScheduleDate[] = [];
  for (let offset = 0; offset < days; offset++) {
    const d = new Date(
      now.getFullYear(),
      now.getMonth(),
      now.getDate() + offset,
    );
    const isClosed = closedWeekdays.includes(d.getDay());

    dates.push({
      date: toDateKey(d),
      weekdayShort: WEEKDAYS[d.getDay()],
      dayOfMonth: d.getDate(),
      monthShort: MONTHS[d.getMonth()],
      isToday: offset === 0,
      isTomorrow: offset === 1,
      isClosed,
      availableCount: isClosed ? 0 : 1,
    });
  }

  return dates;
};

/**
 * Adapt a backend slot to the shape the picker renders.
 *
 * `label` is passed through untouched — the backend already formats it
 * ("10:00 AM - 12:00 PM"), and re-deriving it from start/end would risk
 * disagreeing with what the store configured. Real slots are irregular
 * (2h, 2h, 1h), so nothing here assumes a uniform grid.
 */
export const adaptScheduledSlot = (
  slot: ScheduledDeliverySlot,
  dateKey: string,
  index: number,
): DeliverySlot => {
  const startHour = Number(slot.start_time?.slice(0, 2) ?? 0);
  const endHour = Number(slot.end_time?.slice(0, 2) ?? 0);

  return {
    id: `${dateKey}-${slot.start_time || slot.label || index}`,
    dbId: slot.id,
    date: dateKey,
    startHour,
    endHour,
    startTime: slot.start_time,
    endTime: slot.end_time,
    label: slot.label,
    period: periodForHour(startHour),
    isAvailable: slot.is_available && !slot.max_orders_reached,
    remainingCapacity: slot.remaining_capacity,
  };
};

export const adaptScheduledSlots = (
  slots: ScheduledDeliverySlot[] | null | undefined,
  dateKey: string,
): DeliverySlot[] =>
  Array.isArray(slots)
    ? slots.map((slot, index) => adaptScheduledSlot(slot, dateKey, index))
    : [];

/**
 * Adapt a CART slot (`/cart/slots`, `/slots`) to the picker's shape.
 *
 * These carry no `id`, so one is synthesised from date + start time. That id is
 * a React key and nothing more — it must never reach `delivery_time_slot_id`,
 * which is why `slotDbId` stays undefined on this path and checkout books by
 * `scheduled_slot_start_time` / `_end_time` instead.
 *
 * `remaining_capacity` is the only availability signal on offer: there is no
 * `is_available` flag and no cutoff `reason`.
 */
export const adaptCartSlot = (
  slot: CartDeliverySlot,
  dateKey: string,
  index: number,
): DeliverySlot => {
  const startHour = Number(slot.start_time?.slice(0, 2) ?? 0);
  const endHour = Number(slot.end_time?.slice(0, 2) ?? 0);

  return {
    id: `${dateKey}-${slot.start_time || slot.formatted_time || index}`,
    dbId: undefined,
    date: dateKey,
    startHour,
    endHour,
    startTime: slot.start_time,
    endTime: slot.end_time,
    // Backend-formatted; fall back to deriving one only if it's missing.
    label: slot.formatted_time || formatSlotLabel(startHour, endHour),
    period: periodForHour(startHour),
    isAvailable: Number(slot.remaining_capacity ?? 0) > 0,
    remainingCapacity: slot.remaining_capacity,
  };
};

export const adaptCartSlots = (
  slots: CartDeliverySlot[] | null | undefined,
  dateKey: string,
): DeliverySlot[] =>
  Array.isArray(slots) ? slots.map((slot, index) => adaptCartSlot(slot, dateKey, index)) : [];

/**
 * Turn an explicit `dates[]` from `/cart/dates` into calendar days.
 *
 * The inverse of `getScheduleDatesFromConfig`: that one expands rules into days,
 * this one is handed the days directly. Anything the backend didn't list is
 * simply absent, and `getCalendarMonths` already renders gaps as disabled — so
 * closed days and holidays need no special handling here.
 */
export const getScheduleDatesFromList = (
  dates: string[] | null | undefined,
  now: Date,
): ScheduleDate[] => {
  if (!Array.isArray(dates)) return [];

  const todayKey = toDateKey(now);
  const tomorrowKey = toDateKey(
    new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1),
  );

  return dates
    .filter((key) => /^\d{4}-\d{2}-\d{2}$/.test(key))
    .sort()
    .map((key) => {
      const d = fromDateKey(key);
      return {
        date: key,
        weekdayShort: WEEKDAYS[d.getDay()],
        dayOfMonth: d.getDate(),
        monthShort: MONTHS[d.getMonth()],
        isToday: key === todayKey,
        isTomorrow: key === tomorrowKey,
        // A listed date is by definition open — the backend already excluded
        // closed days by omitting them.
        isClosed: false,
        // Only /slots?date= knows real availability; 1 marks it selectable.
        availableCount: 1,
      };
    });
};

/** Group for the Morning / Afternoon / Evening columns. */
export const groupSlotsByPeriod = (
  slots: DeliverySlot[],
): Record<SlotPeriod, DeliverySlot[]> => ({
  morning: slots.filter((s) => s.period === "morning"),
  afternoon: slots.filter((s) => s.period === "afternoon"),
  evening: slots.filter((s) => s.period === "evening"),
});

/**
 * First day that still has a slot — so opening the picker late in the evening
 * lands on tomorrow instead of a dead "today" with everything greyed out.
 */
export const getFirstSelectableDate = (dates: ScheduleDate[]): ScheduleDate | null =>
  dates.find((d) => d.availableCount > 0) ?? dates[0] ?? null;

/** One cell of a calendar grid. `date: null` = padding outside the month. */
export interface CalendarCell {
  date: string | null;
  dayOfMonth: number | null;
  /** Set only when the day falls inside the bookable window. */
  schedule: ScheduleDate | null;
}

export interface CalendarMonth {
  /** "2026-07" */
  key: string;
  year: number;
  month: number;
  /** "July 2026" */
  label: string;
  weeks: CalendarCell[][];
}

/**
 * Lay the bookable dates out as month grids (Sun-first weeks).
 *
 * Driven entirely by the supplied list, so it copes with whatever the backend
 * sends: 7 days, 30 days, or a **non-contiguous** set (closed Sundays, holidays,
 * zones with no capacity). Any day not in the list simply has `schedule: null`
 * and renders disabled — gaps need no special handling.
 */
export const getCalendarMonths = (dates: ScheduleDate[]): CalendarMonth[] => {
  if (dates.length === 0) return [];

  const byDate = new Map(dates.map((d) => [d.date, d]));
  const keys = [...byDate.keys()].sort();
  const first = fromDateKey(keys[0]);
  const last = fromDateKey(keys[keys.length - 1]);

  const months: CalendarMonth[] = [];
  const cursor = new Date(first.getFullYear(), first.getMonth(), 1);
  const end = new Date(last.getFullYear(), last.getMonth(), 1);

  while (cursor <= end) {
    const year = cursor.getFullYear();
    const month = cursor.getMonth();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const leadingBlanks = new Date(year, month, 1).getDay();

    const cells: CalendarCell[] = [];
    for (let i = 0; i < leadingBlanks; i++) {
      cells.push({ date: null, dayOfMonth: null, schedule: null });
    }
    for (let day = 1; day <= daysInMonth; day++) {
      const key = toDateKey(new Date(year, month, day));
      cells.push({
        date: key,
        dayOfMonth: day,
        schedule: byDate.get(key) ?? null,
      });
    }
    // Pad the final week so every row has 7 cells.
    while (cells.length % 7 !== 0) {
      cells.push({ date: null, dayOfMonth: null, schedule: null });
    }

    const weeks: CalendarCell[][] = [];
    for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));

    months.push({
      key: `${year}-${pad(month + 1)}`,
      year,
      month,
      label: `${MONTHS_FULL[month]} ${year}`,
      weeks,
    });

    cursor.setMonth(cursor.getMonth() + 1);
  }

  return months;
};

/**
 * "Saturday, 18 July" — the day being picked for, spelled out.
 * The slot panel needs to state which day it belongs to; the calendar's
 * highlighted circle alone is easy to lose track of.
 */
export const formatFullDate = (dateKey: string): string => {
  const d = fromDateKey(dateKey);
  return `${WEEKDAYS_FULL[d.getDay()]}, ${d.getDate()} ${MONTHS_FULL[d.getMonth()]}`;
};

/** Index of the month containing `dateKey`, else 0. */
export const findMonthIndex = (
  months: CalendarMonth[],
  dateKey: string | null,
): number => {
  if (!dateKey) return 0;
  const idx = months.findIndex((m) => m.key === dateKey.slice(0, 7));
  return idx === -1 ? 0 : idx;
};

/** "Today · 8 – 9 AM" / "Sat, 19 Jul · 8 – 9 AM" */
export const formatScheduleSummary = (
  dateKey: string,
  slotLabel: string,
  now: Date,
): string => {
  const dates = getScheduleDates(now);
  const match = dates.find((d) => d.date === dateKey);

  if (match?.isToday) return `Today · ${slotLabel}`;
  if (match?.isTomorrow) return `Tomorrow · ${slotLabel}`;

  const d = fromDateKey(dateKey);
  return `${WEEKDAYS[d.getDay()]}, ${d.getDate()} ${MONTHS[d.getMonth()]} · ${slotLabel}`;
};
