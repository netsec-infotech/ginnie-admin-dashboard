import { FC, useMemo, useSyncExternalStore } from "react";
import { Popover, PopoverContent, PopoverTrigger } from "@heroui/react";
import { ChevronDown, Clock } from "lucide-react";
import { useTranslation } from "react-i18next";

import {
  StoreDayHours,
  formatDayHours,
  hasUniformHours,
  parseStoreTiming,
} from "@/helpers/storeTiming";

/**
 * The weekday, read on the CLIENT only.
 *
 * This app static-exports (`output: "export"`), so anything read from the clock
 * during render is frozen at BUILD time — "today" would be whichever day the
 * bundle was built, forever. `useSyncExternalStore` returns null for the server
 * snapshot and the real day after hydration, which sidesteps that and the
 * hydration mismatch in one go. The store never changes during a session, so
 * `subscribe` is a no-op.
 */
const subscribe = () => () => {};
const getClientWeekday = () => new Date().getDay();
const getServerWeekday = () => null;

interface StoreTimingProps {
  timing: string | null;
}

const DayRow: FC<{ day: StoreDayHours; isToday: boolean }> = ({
  day,
  isToday,
}) => {
  const { t } = useTranslation();
  const hours = formatDayHours(day);

  return (
    <div
      className={`flex items-center justify-between gap-6 rounded-md px-2 py-1.5 text-sm ${
        isToday ? "bg-default-100 font-semibold" : ""
      }`}
      data-testid={`timing-row-${day.short}`}
    >
      <span className={isToday ? "" : "text-foreground/70"}>
        {t(`schedule.weekday.${day.short.toLowerCase()}`, day.short)}
      </span>
      {hours ? (
        <span className="tabular-nums">{hours}</span>
      ) : (
        // Muted, but never the invisible text-default-300 — a closed day is
        // information the customer needs, not a throwaway.
        <span className="text-default-500">{t("schedule.closed", "Closed")}</span>
      )}
    </div>
  );
};

/**
 * Store opening hours: today's line up front, the full week a click away.
 *
 * Falls back to the raw string when `timing` isn't the canonical format — see
 * `parseStoreTiming`, the field is free text and legacy rows hold prose.
 */
const StoreTiming: FC<StoreTimingProps> = ({ timing }) => {
  const { t } = useTranslation();

  const week = useMemo(() => parseStoreTiming(timing), [timing]);
  const weekday = useSyncExternalStore(
    subscribe,
    getClientWeekday,
    getServerWeekday,
  );

  if (!timing) return null;

  // Legacy / seller-typed prose. Show it verbatim rather than guessing — this
  // is exactly what the page did before, so those stores don't regress.
  if (!week) {
    return (
      <div className="flex items-center gap-3 text-foreground/70">
        <Clock className="h-5 w-5 shrink-0" />
        <p className="text-sm">{timing}</p>
      </div>
    );
  }

  const today = week.find((day) => day.weekday === weekday) ?? null;
  const todayHours = today ? formatDayHours(today) : null;

  // Before hydration `weekday` is null, so there's no honest "today" to state.
  const summary = !today
    ? t("store.openingHours", "Opening hours")
    : todayHours
      ? `${t("store.today", "Today")} · ${todayHours}`
      : t("store.closedToday", "Closed today");

  const openDays = week.filter((day) => day.opensAt !== null);
  // "Mon–Sat · 9:00 AM – 9:00 PM" collapses the common case into the subtitle,
  // so the popover is for the exceptions rather than the default read.
  const subtitle =
    hasUniformHours(week) && openDays.length > 0
      ? `${openDays.length} ${t("store.daysAWeek", "days a week")} · ${formatDayHours(openDays[0])}`
      : t("store.viewAllHours", "View all hours");

  return (
    <Popover placement="bottom-start" showArrow offset={8}>
      <PopoverTrigger>
        <button
          type="button"
          className="flex cursor-pointer items-center gap-3 rounded-md text-start text-foreground/70 transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
          data-testid="store-timing-trigger"
        >
          <Clock className="h-5 w-5 shrink-0" />
          <span className="min-w-0">
            <span className="flex items-center gap-1">
              <span className="truncate text-sm font-medium">{summary}</span>
              <ChevronDown className="h-3.5 w-3.5 shrink-0" />
            </span>
            <span className="block truncate text-xs text-foreground/50">
              {subtitle}
            </span>
          </span>
        </button>
      </PopoverTrigger>

      <PopoverContent className="px-3 py-3">
        <div className="w-60" data-testid="store-timing-week">
          <p className="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-foreground/50">
            {t("store.openingHours", "Opening hours")}
          </p>
          {week.map((day) => (
            <DayRow
              key={day.short}
              day={day}
              isToday={day.weekday === weekday}
            />
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
};

export default StoreTiming;
