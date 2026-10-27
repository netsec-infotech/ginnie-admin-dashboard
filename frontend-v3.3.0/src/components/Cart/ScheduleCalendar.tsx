import { FC, useMemo, useState } from "react";
import { Button } from "@heroui/react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslation } from "react-i18next";

import type { Variants } from "framer-motion";
import {
  ScheduleDate,
  WEEKDAYS,
  findMonthIndex,
  getCalendarMonths,
} from "@/helpers/deliverySlots";
import { isRTL } from "@/helpers/functionalHelpers";

/**
 * Shared deceleration curve. Framer's "easeOut" is a shallow quad that still
 * reads mechanical; this settles much more gently.
 */
export const EASE: [number, number, number, number] = [0.22, 1, 0.36, 1];

/**
 * The month grid travels as ONE plane.
 *
 * Two things were fighting the horizontal move before: the enter state had no
 * `x` at all (only exit did), so a month change slid out and then faded in from
 * nowhere; and 42 cells each ran their own staggered fade during the travel, so
 * the motion was driven by 43 concurrent animations instead of one transform.
 * Now a single element moves, which stays on the compositor.
 *
 * `custom` carries the signed travel distance because an exiting child's props
 * are frozen at removal — without it the old month can't know which way it's
 * leaving. `x` outlives `opacity` deliberately: the outgoing grid is invisible
 * for most of its travel, so the two never read as a double image.
 */
const gridVariants: Variants = {
  hidden: (dx: number) => ({ opacity: 0, x: dx }),
  show: {
    opacity: 1,
    x: 0,
    transition: {
      x: { duration: 0.34, ease: EASE },
      opacity: { duration: 0.2, ease: EASE },
    },
  },
  exit: (dx: number) => ({
    opacity: 0,
    x: -dx,
    // The grids overlap while crossing — don't let the dying one eat clicks.
    pointerEvents: "none",
    transition: {
      x: { duration: 0.34, ease: EASE },
      opacity: { duration: 0.16, ease: EASE },
    },
  }),
};

interface ScheduleCalendarProps {
  dates: ScheduleDate[];
  activeDate: string | null;
  onSelect: (date: string) => void;
}

/**
 * Month-grid date picker for the bookable window.
 *
 * Driven entirely by `ScheduleDate[]`, so 7 days, 30 days or a non-contiguous
 * window need no special casing: days absent from the list, closed by the store,
 * or out of capacity are simply disabled in place.
 */
const ScheduleCalendar: FC<ScheduleCalendarProps> = ({
  dates,
  activeDate,
  onSelect,
}) => {
  const { t, i18n } = useTranslation();
  const rtl = isRTL(i18n.resolvedLanguage || i18n.language);

  const reduceMotion = useReducedMotion();

  const months = useMemo(() => getCalendarMonths(dates), [dates]);
  const [monthIndex, setMonthIndex] = useState(() =>
    findMonthIndex(months, activeDate),
  );
  // +1 = moving forward, -1 = back. Drives which way the grid slides.
  const [direction, setDirection] = useState(0);

  const month = months[monthIndex];
  if (!month) return null;

  const canPrev = monthIndex > 0;
  const canNext = monthIndex < months.length - 1;

  const goToMonth = (next: number, dir: number) => {
    setDirection(dir);
    setMonthIndex(next);
  };

  // Honour the OS "reduce motion" setting — snap instead of spring/slide.
  // Softer + heavier than a stiff spring: it glides and settles rather than
  // snapping, which is what reads as "smooth" over a ~200px travel.
  const springy = reduceMotion
    ? { duration: 0 }
    : { type: "spring" as const, stiffness: 380, damping: 32, mass: 0.9 };

  // How far, and which way, the incoming month travels. Follows reading
  // direction, so it flips in RTL. Zero under reduce-motion leaves a plain
  // crossfade, which is what that setting asks for — no travel, fades are fine.
  const travel = reduceMotion ? 0 : direction * (rtl ? -24 : 24);

  // Chevrons point at the visual direction of travel, which flips under RTL.
  const PrevIcon = rtl ? ChevronRight : ChevronLeft;
  const NextIcon = rtl ? ChevronLeft : ChevronRight;

  return (
    <div data-testid="schedule-calendar">
      {/* Nav grouped with the label — spreading the chevrons to the column
          edges leaves the month title floating on its own. */}
      <div className="mb-3 flex items-center justify-center gap-1">
        <Button
          isIconOnly
          size="sm"
          variant="light"
          radius="full"
          isDisabled={!canPrev}
          aria-label={t("schedule.prevMonth", "Previous month")}
          onPress={() => goToMonth(Math.max(0, monthIndex - 1), -1)}
          data-testid="calendar-prev"
          className="h-7 w-7 min-w-7"
        >
          <PrevIcon className="h-4 w-4" />
        </Button>

        {/* Cross-fade the label so month changes read as a transition. */}
        <div className="relative min-w-28 overflow-hidden text-center">
          <AnimatePresence mode="wait" initial={false}>
            <motion.span
              key={month.key}
              initial={{ opacity: 0, y: reduceMotion ? 0 : direction * 6 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: reduceMotion ? 0 : direction * -6 }}
              transition={{ duration: reduceMotion ? 0 : 0.16, ease: EASE }}
              className="block text-xs font-semibold"
              data-testid="calendar-month"
            >
              {month.label}
            </motion.span>
          </AnimatePresence>
        </div>

        <Button
          isIconOnly
          size="sm"
          variant="light"
          radius="full"
          isDisabled={!canNext}
          aria-label={t("schedule.nextMonth", "Next month")}
          onPress={() => goToMonth(Math.min(months.length - 1, monthIndex + 1), 1)}
          data-testid="calendar-next"
          className="h-7 w-7 min-w-7"
        >
          <NextIcon className="h-4 w-4" />
        </Button>
      </div>

      <div className="grid grid-cols-7">
        {WEEKDAYS.map((day) => (
          <span
            key={day}
            className="pb-2 text-center text-xxs font-medium uppercase tracking-wide text-default-400"
          >
            {t(`schedule.weekday.${day.toLowerCase()}`, day)}
          </span>
        ))}
      </div>

      {/* Both grids are absolute so they cross THROUGH each other. `mode="wait"`
          ran exit fully before enter, which put a dead stop in the middle of
          every month change — the opposite of a continuous horizontal move.
          The fixed height is load-bearing: months are 5 or 6 rows, and with the
          grids out of flow the container would otherwise snap between the two
          mid-travel. 260px = 6 rows (6×40px cells + 5×4px gaps), the tallest
          case, and matches the slot pane beside it. */}
      <div className="relative min-h-[260px]">
        <AnimatePresence initial={false} custom={travel}>
          <motion.div
            key={month.key}
            custom={travel}
            variants={gridVariants}
            initial="hidden"
            animate="show"
            exit="exit"
            className="absolute inset-x-0 top-0 grid grid-cols-7 gap-y-1"
          >
            {month.weeks.flat().map((cell, i) => {
              // Padding cell outside this month.
              if (!cell.date) return <span key={`pad-${i}`} aria-hidden />;

              const bookable =
                !!cell.schedule && cell.schedule.availableCount > 0;
              const isSelected = cell.date === activeDate;
              const isToday = !!cell.schedule?.isToday;
              // Store closed that weekday — distinct from "sold out" or
              // "outside the window", so it gets a strike rather than dimming.
              const isClosed = !!cell.schedule?.isClosed;

              return (
                // Plain div, not motion: the grid above carries the whole
                // month's movement, and per-cell transforms would also make the
                // layoutId circle below chase a moving box.
                <div key={cell.date} className="flex justify-center py-0.5">
                  {/* A circle (not a rounded square) — matches how date pickers
                      conventionally mark a selected day, and reads far cleaner. */}
                  <button
                    type="button"
                    role="radio"
                    aria-checked={isSelected}
                    aria-label={
                      isClosed
                        ? `${cell.date} — ${t("schedule.closed", "Closed")}`
                        : (cell.date as string)
                    }
                    title={isClosed ? t("schedule.closed", "Closed") : undefined}
                    disabled={!bookable}
                    onClick={() => onSelect(cell.date as string)}
                    data-testid={`calendar-day-${cell.date}`}
                    className={`relative flex h-9 w-9 items-center justify-center rounded-full text-xs transition-colors ${
                      isSelected
                        ? "cursor-pointer font-semibold text-white"
                        : bookable
                          ? "cursor-pointer font-medium hover:bg-default-100"
                          : isClosed
                            ? "cursor-not-allowed text-default-400 line-through"
                            : // Readable-but-clearly-unavailable, never invisible.
                              "cursor-not-allowed text-default-400"
                    }`}
                  >
                    {/* Shared layout: one circle exists at a time, so it GLIDES
                        from the old day to the new instead of blinking across.
                        Scoped per month via the key, so a month change fades
                        rather than flinging the circle across the grid. */}
                    {isSelected && (
                      <motion.span
                        layoutId={`selected-day-${month.key}`}
                        className="absolute inset-0 rounded-full bg-primary"
                        transition={springy}
                      />
                    )}

                    <span className="relative z-10">{cell.dayOfMonth}</span>

                    {isToday && !isSelected && (
                      <span className="absolute bottom-0.5 h-1 w-1 rounded-full bg-primary" />
                    )}
                  </button>
                </div>
              );
            })}
          </motion.div>
        </AnimatePresence>
      </div>
    </div>
  );
};

export default ScheduleCalendar;
