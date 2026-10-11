/**
 * Store opening-hours parsing.
 *
 * `store.timing` is a FREE-TEXT field, not structured data. The admin panel
 * generates a canonical "Mon: 09:00-21:00, Tue: 09:00-21:00" string for newer
 * stores, but the column also holds legacy hand-typed values, and there is
 * nothing stopping a seller typing anything at all. Live examples:
 *
 *   "Mon: 09:00-21:00, Tue: 09:00-21:00, …"   ← canonical, parseable
 *   "11 AM – 8 PM (Mon–Sat)"                  ← legacy free text
 *   "MON-SUN 9 to 5 pm"                       ← legacy free text
 *   "aaaaaaaaaaaaaa"                          ← genuinely present in the data
 *   null
 *
 * So `parseStoreTiming` is deliberately ALL-OR-NOTHING: unless every segment
 * matches the canonical shape it returns null and the caller shows the raw
 * string untouched. Half-parsing "11 AM – 8 PM (Mon–Sat)" into a table with
 * five "Closed" rows would state something false; the ugly-but-honest raw text
 * is better than a confident lie.
 *
 * Pure + deterministic: `now` is always injected, never read from the clock.
 */

/** 0 = Sun … 6 = Sat, matching Date#getDay. */
export type Weekday = 0 | 1 | 2 | 3 | 4 | 5 | 6;

export interface StoreDayHours {
  weekday: Weekday;
  /** "Mon" */
  short: string;
  /** Minutes from midnight. Both null when the store is closed that day. */
  opensAt: number | null;
  closesAt: number | null;
}

const DAY_SHORT: Record<string, Weekday> = {
  sun: 0,
  mon: 1,
  tue: 2,
  wed: 3,
  thu: 4,
  fri: 5,
  sat: 6,
};

const SHORT_BY_WEEKDAY = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

/**
 * Display order. Monday-first is the convention for opening hours, and the
 * backend emits the canonical string Monday-first too — note this differs from
 * the delivery calendar's Sunday-first grid, which follows a different
 * convention entirely.
 */
export const WEEK_ORDER: Weekday[] = [1, 2, 3, 4, 5, 6, 0];

/** "Mon: 09:00-21:00" — the whole segment must match, anchored. */
const SEGMENT = /^([a-z]{3})\s*:\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/i;

/**
 * Parse the canonical timing string into a full week.
 *
 * Returns null the moment anything is off-format — the caller must fall back to
 * rendering the raw string. Days missing from the string are closed: verified
 * against the same store's `scheduled_delivery_info.available_days`, which
 * lists exactly the days the timing string names.
 */
export const parseStoreTiming = (
  raw: string | null | undefined,
): StoreDayHours[] | null => {
  if (!raw || !raw.trim()) return null;

  const segments = raw.split(",").map((s) => s.trim());
  if (segments.length === 0) return null;

  const hours = new Map<Weekday, { opensAt: number; closesAt: number }>();

  for (const segment of segments) {
    const match = SEGMENT.exec(segment);
    if (!match) return null;

    const weekday = DAY_SHORT[match[1].toLowerCase()];
    if (weekday === undefined) return null;

    const openH = Number(match[2]);
    const openM = Number(match[3]);
    const closeH = Number(match[4]);
    const closeM = Number(match[5]);

    // A real clock time, or we don't trust the string at all.
    if (openH > 23 || closeH > 23 || openM > 59 || closeM > 59) return null;

    // Last one wins on a duplicated day — arbitrary, but the alternative is
    // rejecting the whole string over a seller listing Monday twice.
    hours.set(weekday, {
      opensAt: openH * 60 + openM,
      closesAt: closeH * 60 + closeM,
    });
  }

  if (hours.size === 0) return null;

  return WEEK_ORDER.map((weekday) => {
    const match = hours.get(weekday);
    return {
      weekday,
      short: SHORT_BY_WEEKDAY[weekday],
      opensAt: match?.opensAt ?? null,
      closesAt: match?.closesAt ?? null,
    };
  });
};

/** 540 → "9:00 AM". Matches the 12-hour style used for delivery slots. */
export const formatMinutes = (minutes: number): string => {
  const h24 = Math.floor(minutes / 60) % 24;
  const m = minutes % 60;
  const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
  const suffix = h24 < 12 ? "AM" : "PM";
  return `${h12}:${String(m).padStart(2, "0")} ${suffix}`;
};

/** "9:00 AM – 9:00 PM", or null when closed that day. */
export const formatDayHours = (day: StoreDayHours): string | null =>
  day.opensAt === null || day.closesAt === null
    ? null
    : `${formatMinutes(day.opensAt)} – ${formatMinutes(day.closesAt)}`;

/** The row for `now`'s weekday. */
export const getTodayHours = (
  week: StoreDayHours[],
  now: Date,
): StoreDayHours | null =>
  week.find((day) => day.weekday === now.getDay()) ?? null;

/**
 * Whether every open day keeps the same hours — the common case, and worth
 * saying in one line ("Mon–Sat · 9:00 AM – 9:00 PM") instead of seven rows.
 */
export const hasUniformHours = (week: StoreDayHours[]): boolean => {
  const open = week.filter((day) => day.opensAt !== null);
  if (open.length < 2) return false;
  return open.every(
    (day) =>
      day.opensAt === open[0].opensAt && day.closesAt === open[0].closesAt,
  );
};
