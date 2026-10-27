/** Store opening-hours parsing and display helpers. */

/** 0 = Sun ... 6 = Sat, matching Date#getDay. */
export type Weekday = 0 | 1 | 2 | 3 | 4 | 5 | 6;

export interface StoreDayHours {
  weekday: Weekday;
  /** "Mon" */
  short: string;
  /** Minutes from midnight. Both null when the store is closed that day. */
  opensAt: number | null;
  closesAt: number | null;
  /** Optional pause within the opening window. */
  breakStartsAt: number | null;
  breakEndsAt: number | null;
}

export type StoreHoursState = "open" | "break" | "closed";

const DAY_SHORT: Record<string, Weekday> = {
  sun: 0,
  mon: 1,
  tue: 2,
  wed: 3,
  thu: 4,
  fri: 5,
  sat: 6,
};

const DAY_NAME_BY_WEEKDAY = [
  "sunday",
  "monday",
  "tuesday",
  "wednesday",
  "thursday",
  "friday",
  "saturday",
] as const;
const SHORT_BY_WEEKDAY = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

/** Monday-first display order, matching the backend schedule editor. */
export const WEEK_ORDER: Weekday[] = [1, 2, 3, 4, 5, 6, 0];

/** "Mon: 09:00-21:00" - the whole segment must match. */
const SEGMENT = /^([a-z]{3})\s*:\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/i;
const CLOCK_TIME = /^(\d{1,2}):(\d{2})(?::\d{2})?$/;

const parseClockTime = (value: unknown): number | null => {
  if (typeof value !== "string") return null;
  const match = CLOCK_TIME.exec(value.trim());
  if (!match) return null;

  const hours = Number(match[1]);
  const minutes = Number(match[2]);
  if (hours > 23 || minutes > 59) return null;
  return hours * 60 + minutes;
};

const isEnabled = (value: unknown): boolean =>
  value === true || value === 1 || value === "1" || value === "true";

const asRecord = (value: unknown): Record<string, unknown> | null =>
  value !== null && typeof value === "object" && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null;

/**
 * Parse `metadata.operating_hours`, which is the authoritative schedule.
 * Metadata may be an object or a JSON string depending on the endpoint.
 */
export const parseStoreOperatingHours = (
  metadata: unknown,
): StoreDayHours[] | null => {
  let parsedMetadata: unknown = metadata;

  if (typeof metadata === "string") {
    try {
      parsedMetadata = JSON.parse(metadata);
    } catch {
      return null;
    }
  }

  const metadataRecord = asRecord(parsedMetadata);
  if (!metadataRecord) return null;

  let rawOperatingHours: unknown = metadataRecord.operating_hours;
  if (typeof rawOperatingHours === "string") {
    try {
      rawOperatingHours = JSON.parse(rawOperatingHours);
    } catch {
      return null;
    }
  }

  const operatingHours = asRecord(rawOperatingHours);
  if (!operatingHours) return null;

  const hasAnyDay = DAY_NAME_BY_WEEKDAY.some((day) =>
    Object.prototype.hasOwnProperty.call(operatingHours, day),
  );
  if (!hasAnyDay) return null;

  const week: StoreDayHours[] = [];

  for (const weekday of WEEK_ORDER) {
    const dayRecord = asRecord(operatingHours[DAY_NAME_BY_WEEKDAY[weekday]]);
    const closedDay: StoreDayHours = {
      weekday,
      short: SHORT_BY_WEEKDAY[weekday],
      opensAt: null,
      closesAt: null,
      breakStartsAt: null,
      breakEndsAt: null,
    };

    if (!dayRecord || !isEnabled(dayRecord.is_open)) {
      week.push(closedDay);
      continue;
    }

    const opensAt = parseClockTime(dayRecord.open_time);
    const closesAt = parseClockTime(dayRecord.close_time);
    // An open day without valid hours is unsafe to present as fact. Let the
    // caller fall back to the legacy timing instead of inventing a schedule.
    if (opensAt === null || closesAt === null) return null;

    let breakStartsAt: number | null = null;
    let breakEndsAt: number | null = null;
    if (isEnabled(dayRecord.has_break)) {
      breakStartsAt = parseClockTime(dayRecord.break_start_time);
      breakEndsAt = parseClockTime(dayRecord.break_end_time);
      // A malformed optional break should not hide otherwise valid hours.
      if (breakStartsAt === null || breakEndsAt === null) {
        breakStartsAt = null;
        breakEndsAt = null;
      }
    }

    week.push({
      weekday,
      short: SHORT_BY_WEEKDAY[weekday],
      opensAt,
      closesAt,
      breakStartsAt,
      breakEndsAt,
    });
  }

  return week;
};

/**
 * Parse the legacy canonical timing string into a full week. This parser is
 * deliberately all-or-nothing because the field also contains seller prose.
 */
export const parseStoreTiming = (
  raw: string | null | undefined,
): StoreDayHours[] | null => {
  if (!raw || !raw.trim()) return null;

  const segments = raw.split(",").map((segment) => segment.trim());
  if (segments.length === 0) return null;

  const hours = new Map<Weekday, { opensAt: number; closesAt: number }>();

  for (const segment of segments) {
    const match = SEGMENT.exec(segment);
    if (!match) return null;

    const weekday = DAY_SHORT[match[1].toLowerCase()];
    if (weekday === undefined) return null;

    const opensAt = parseClockTime(`${match[2]}:${match[3]}`);
    const closesAt = parseClockTime(`${match[4]}:${match[5]}`);
    if (opensAt === null || closesAt === null) return null;

    hours.set(weekday, { opensAt, closesAt });
  }

  if (hours.size === 0) return null;

  return WEEK_ORDER.map((weekday) => {
    const match = hours.get(weekday);
    return {
      weekday,
      short: SHORT_BY_WEEKDAY[weekday],
      opensAt: match?.opensAt ?? null,
      closesAt: match?.closesAt ?? null,
      breakStartsAt: null,
      breakEndsAt: null,
    };
  });
};

/** 540 -> "9:00 AM". Matches the 12-hour style used for delivery slots. */
export const formatMinutes = (minutes: number): string => {
  const h24 = Math.floor(minutes / 60) % 24;
  const m = minutes % 60;
  const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
  const suffix = h24 < 12 ? "AM" : "PM";
  return `${h12}:${String(m).padStart(2, "0")} ${suffix}`;
};

/** "9:00 AM - 9:00 PM", or null when closed that day. */
export const formatDayHours = (day: StoreDayHours): string | null =>
  day.opensAt === null || day.closesAt === null
    ? null
    : `${formatMinutes(day.opensAt)} – ${formatMinutes(day.closesAt)}`;

export const formatBreakHours = (day: StoreDayHours): string | null =>
  day.breakStartsAt === null || day.breakEndsAt === null
    ? null
    : `${formatMinutes(day.breakStartsAt)} – ${formatMinutes(day.breakEndsAt)}`;

/** The row for `now`'s weekday. */
export const getTodayHours = (
  week: StoreDayHours[],
  now: Date,
): StoreDayHours | null =>
  week.find((day) => day.weekday === now.getDay()) ?? null;

const isInRange = (minute: number, start: number, end: number): boolean =>
  start <= end
    ? minute >= start && minute < end
    : minute >= start || minute < end;

export const getStoreHoursState = (
  day: StoreDayHours | null,
  minute: number,
): StoreHoursState => {
  if (!day || day.opensAt === null || day.closesAt === null) return "closed";
  if (!isInRange(minute, day.opensAt, day.closesAt)) return "closed";

  return day.breakStartsAt !== null &&
    day.breakEndsAt !== null &&
    isInRange(minute, day.breakStartsAt, day.breakEndsAt)
    ? "break"
    : "open";
};

export interface NextStoreOpening {
  day: StoreDayHours;
  daysAway: number;
}

/** Find the next opening after the supplied local weekday and time. */
export const getNextStoreOpening = (
  week: StoreDayHours[],
  weekday: Weekday,
  minute: number,
): NextStoreOpening | null => {
  for (let daysAway = 0; daysAway <= 7; daysAway += 1) {
    const targetWeekday = ((weekday + daysAway) % 7) as Weekday;
    const day = week.find((entry) => entry.weekday === targetWeekday);
    if (!day || day.opensAt === null) continue;

    if (daysAway > 0 || minute < day.opensAt) return { day, daysAway };
  }
  return null;
};

/** Whether every open day shares the same hours and break. */
export const hasUniformHours = (week: StoreDayHours[]): boolean => {
  const open = week.filter((day) => day.opensAt !== null);
  if (open.length < 2) return false;
  return open.every(
    (day) =>
      day.opensAt === open[0].opensAt &&
      day.closesAt === open[0].closesAt &&
      day.breakStartsAt === open[0].breakStartsAt &&
      day.breakEndsAt === open[0].breakEndsAt,
  );
};
