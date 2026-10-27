import { FC, useMemo, useSyncExternalStore } from "react";
import { Popover, PopoverContent, PopoverTrigger } from "@heroui/react";
import { ChevronDown, Clock } from "lucide-react";
import { useTranslation } from "react-i18next";

import {
  StoreDayHours,
  Weekday,
  formatBreakHours,
  formatDayHours,
  formatMinutes,
  getNextStoreOpening,
  getStoreHoursState,
  hasUniformHours,
  parseStoreOperatingHours,
  parseStoreTiming,
} from "@/helpers/storeTiming";

/**
 * A minute-resolution client clock. The server snapshot stays null so a static
 * export never freezes "today" or "open now" at build time.
 */
const subscribe = (onStoreChange: () => void) => {
  const interval = window.setInterval(onStoreChange, 60_000);
  return () => window.clearInterval(interval);
};
const getClientMinute = () => Math.floor(Date.now() / 60_000);
const getServerMinute = () => null;

interface StoreTimingProps {
  timing: string | null;
  metadata?: unknown;
}

const DayRow: FC<{ day: StoreDayHours; isToday: boolean }> = ({
  day,
  isToday,
}) => {
  const { t } = useTranslation();
  const hours = formatDayHours(day);
  const breakHours = formatBreakHours(day);

  return (
    <div
      className={`flex items-start justify-between gap-6 rounded-md px-2 py-1.5 text-sm ${
        isToday ? "bg-default-100 font-semibold" : ""
      }`}
      data-testid={`timing-row-${day.short}`}
    >
      <span className={isToday ? "" : "text-foreground/70"}>
        {t(`schedule.weekday.${day.short.toLowerCase()}`, day.short)}
      </span>
      {hours ? (
        <span className="text-end tabular-nums">
          <span className="block">{hours}</span>
          {breakHours && (
            <span className="block text-xs font-normal text-warning-600 dark:text-warning-400">
              {t("store.breakHours", {
                hours: breakHours,
                defaultValue: "Break {{hours}}",
              })}
            </span>
          )}
        </span>
      ) : (
        <span className="text-default-500">
          {t("schedule.closed", "Closed")}
        </span>
      )}
    </div>
  );
};

/** Metadata hours first; the legacy free-text `timing` remains a fallback. */
const StoreTiming: FC<StoreTimingProps> = ({ timing, metadata }) => {
  const { t } = useTranslation();

  const metadataWeek = useMemo(
    () => parseStoreOperatingHours(metadata),
    [metadata],
  );
  const legacyWeek = useMemo(() => parseStoreTiming(timing), [timing]);
  const week = metadataWeek ?? legacyWeek;
  const clockMinute = useSyncExternalStore(
    subscribe,
    getClientMinute,
    getServerMinute,
  );

  if (!week && !timing) return null;

  // Keep old seller-entered prose visible only when structured metadata is
  // unavailable. Guessing from prose can mark a store open when it is closed.
  if (!week) {
    return (
      <div className="flex min-w-0 items-center gap-2 text-foreground/70 sm:gap-3">
        <Clock className="h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
        <p className="truncate text-xs font-medium sm:text-sm">{timing}</p>
      </div>
    );
  }

  const now = clockMinute === null ? null : new Date(clockMinute * 60_000);
  const weekday = (now?.getDay() ?? null) as Weekday | null;
  const minute = now ? now.getHours() * 60 + now.getMinutes() : null;
  const today = week.find((day) => day.weekday === weekday) ?? null;
  const todayHours = today ? formatDayHours(today) : null;
  const todayBreak = today ? formatBreakHours(today) : null;

  let summary = t("store.openingHours", "Opening hours");
  let subtitle = t("store.viewAllHours", "View all hours");
  let status: "open" | "break" | "closed" | "unknown" = "unknown";

  if (metadataWeek && weekday !== null && minute !== null) {
    status = getStoreHoursState(today, minute);

    if (status === "open" && today?.closesAt != null) {
      summary = `${t("store.openNow", "Open now")} · ${t("store.closesAt", {
        time: formatMinutes(today.closesAt),
        defaultValue: "Closes at {{time}}",
      })}`;
    } else if (status === "break" && today?.breakEndsAt != null) {
      summary = `${t("store.onBreak", "On break")} · ${t("store.reopensAt", {
        time: formatMinutes(today.breakEndsAt),
        defaultValue: "Reopens at {{time}}",
      })}`;
    } else if (
      today?.opensAt !== null &&
      today?.opensAt !== undefined &&
      minute < today.opensAt
    ) {
      summary = `${t("store.closedNow", "Closed now")} · ${t("store.opensAt", {
        time: formatMinutes(today.opensAt),
        defaultValue: "Opens at {{time}}",
      })}`;
    } else {
      summary = todayHours
        ? t("store.closedForToday", "Closed for today")
        : t("store.closedToday", "Closed today");
    }

    if (todayHours && status !== "closed") {
      subtitle = t("store.todayHours", {
        hours: todayHours,
        defaultValue: "Today: {{hours}}",
      });
      if (todayBreak) {
        subtitle += ` · ${t("store.breakHours", {
          hours: todayBreak,
          defaultValue: "Break {{hours}}",
        })}`;
      }
    } else if (todayHours && minute < (today?.opensAt ?? 0)) {
      subtitle = t("store.todayHours", {
        hours: todayHours,
        defaultValue: "Today: {{hours}}",
      });
    } else {
      const nextOpening = getNextStoreOpening(week, weekday, minute);
      if (nextOpening?.day.opensAt != null) {
        const time = formatMinutes(nextOpening.day.opensAt);
        subtitle =
          nextOpening.daysAway === 1
            ? t("store.opensTomorrowAt", {
                time,
                defaultValue: "Opens tomorrow at {{time}}",
              })
            : t("store.opensDayAt", {
                day: t(
                  `schedule.weekday.${nextOpening.day.short.toLowerCase()}`,
                  nextOpening.day.short,
                ),
                time,
                defaultValue: "Opens {{day}} at {{time}}",
              });
      }
    }
  } else if (weekday !== null) {
    // Legacy canonical timing keeps its existing presentation and semantics.
    summary = todayHours
      ? `${t("store.today", "Today")} · ${todayHours}`
      : t("store.closedToday", "Closed today");

    const openDays = week.filter((day) => day.opensAt !== null);
    subtitle =
      hasUniformHours(week) && openDays.length > 0
        ? `${openDays.length} ${t("store.daysAWeek", "days a week")} · ${formatDayHours(openDays[0])}`
        : t("store.viewAllHours", "View all hours");
  }

  const statusColor =
    status === "open"
      ? "text-success-600 dark:text-success-400"
      : status === "break"
        ? "text-warning-600 dark:text-warning-400"
        : status === "closed"
          ? "text-danger-600 dark:text-danger-400"
          : "text-foreground/70";

  return (
    <Popover placement="bottom-start" showArrow offset={8}>
      <PopoverTrigger>
        <button
          type="button"
          className="flex min-w-0 cursor-pointer items-center gap-2 rounded-md text-start text-foreground/70 transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary sm:gap-3"
          data-status={status}
          data-testid="store-timing-trigger"
        >
          <Clock className={`h-4 w-4 shrink-0 sm:h-5 sm:w-5 ${statusColor}`} />
          <span className="min-w-0">
            <span className="flex items-center gap-1">
              <span
                className={`truncate text-xs font-semibold sm:text-sm ${statusColor}`}
              >
                {summary}
              </span>
              <ChevronDown className="h-3.5 w-3.5 shrink-0" />
            </span>
            <span className="hidden truncate text-xs text-foreground/50 sm:block">
              {subtitle}
            </span>
          </span>
        </button>
      </PopoverTrigger>

      <PopoverContent className="px-3 py-3">
        <div className="w-72" data-testid="store-timing-week">
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
