import { Button, DateRangePicker, Tooltip } from "@heroui/react";
import {
  getLocalTimeZone,
  parseDate,
  today,
  type CalendarDate,
} from "@internationalized/date";
import { CalendarRange, MousePointerClick, X } from "lucide-react";
import { useMemo, useState } from "react";
import { useTranslation } from "react-i18next";

interface DateRangeFilterProps {
  from?: string;
  to?: string;
  onChange: (range: { from: string; to: string }) => void;
  className?: string;
}

interface DateRangeClearButtonProps {
  isVisible: boolean;
  onClear: () => void;
  className?: string;
}

const parseRangeDate = (value?: string): CalendarDate | null => {
  if (!value) return null;

  try {
    return parseDate(value);
  } catch {
    return null;
  }
};

const DateRangeFilter = ({
  from,
  to,
  onChange,
  className = "",
}: DateRangeFilterProps) => {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const value = useMemo(() => {
    const start = parseRangeDate(from);
    const end = parseRangeDate(to);

    return start && end ? { start, end } : null;
  }, [from, to]);

  const applyPreset = (preset: "today" | "last7" | "last30" | "month") => {
    const end = today(getLocalTimeZone());
    const start =
      preset === "last7"
        ? end.subtract({ days: 6 })
        : preset === "last30"
          ? end.subtract({ days: 29 })
          : preset === "month"
            ? end.subtract({ days: end.day - 1 })
            : end;

    onChange({ from: start.toString(), to: end.toString() });
    setIsOpen(false);
  };

  const calendarTopContent = (
    <div className="flex items-start gap-2 border-b border-divider bg-primary/5 px-4 py-3 text-start">
      <MousePointerClick className="mt-0.5 size-4 shrink-0 text-primary" />
      <p className="text-xs leading-5 text-default-600">
        {t("filters.dateRangeHint")}
      </p>
    </div>
  );

  const calendarBottomContent = (
    <div className="border-t border-divider px-3 py-3">
      <p className="mb-2 text-xs font-semibold text-default-600">
        {t("filters.quickRanges")}
      </p>
      <div className="grid grid-cols-2 gap-2">
        <Button
          size="sm"
          variant="flat"
          className="h-8 cursor-pointer text-xs"
          onPress={() => applyPreset("today")}
        >
          {t("filters.today")}
        </Button>
        <Button
          size="sm"
          variant="flat"
          className="h-8 cursor-pointer text-xs"
          onPress={() => applyPreset("last7")}
        >
          {t("filters.last_7_days")}
        </Button>
        <Button
          size="sm"
          variant="flat"
          className="h-8 cursor-pointer text-xs"
          onPress={() => applyPreset("last30")}
        >
          {t("filters.last_30_days")}
        </Button>
        <Button
          size="sm"
          variant="flat"
          className="h-8 cursor-pointer text-xs"
          onPress={() => applyPreset("month")}
        >
          {t("filters.thisMonth")}
        </Button>
      </div>
    </div>
  );

  return (
    <DateRangePicker
      aria-label={t("filters.dateRange")}
      isOpen={isOpen}
      onOpenChange={setIsOpen}
      value={value}
      onChange={(range) =>
        onChange({
          from: range?.start.toString() ?? "",
          to: range?.end.toString() ?? "",
        })
      }
      variant="flat"
      selectorIcon={<CalendarRange className="size-4" />}
      selectorButtonProps={{
        "aria-label": t("filters.openDateRange"),
        title: t("filters.openDateRange"),
        className: "cursor-pointer",
      }}
      showMonthAndYearPickers
      calendarWidth={300}
      CalendarTopContent={calendarTopContent}
      CalendarBottomContent={calendarBottomContent}
      popoverProps={{
        shouldCloseOnScroll: false,
        shouldBlockScroll: false,
        isDismissable: true,
        isKeyboardDismissDisabled: false,
      }}
      className={`w-full min-w-0 sm:w-[15.5rem] ${className}`}
      classNames={{
        inputWrapper:
          "h-9 min-h-9 cursor-pointer gap-1.5 border border-transparent bg-default-100 transition-[background-color,border-color,box-shadow] duration-200 ease-out hover:bg-default-200/70 focus-within:border-primary/40 focus-within:bg-default-100 focus-within:ring-2 focus-within:ring-primary/10 group-data-[invalid=true]:!border-danger/40 group-data-[invalid=true]:!bg-danger/10 group-data-[invalid=true]:hover:!bg-danger/15 group-data-[invalid=true]:focus-within:!bg-danger/10 group-data-[invalid=true]:focus-within:!ring-danger/15",
        segment:
          "cursor-pointer text-xs transition-colors duration-200 ease-out focus:bg-primary/15 data-[editable=true]:focus:text-primary data-[invalid=true]:focus:!bg-danger/15 dark:focus:bg-primary/20",
        separator: "mx-0.5 text-default-400",
        selectorButton:
          "size-7 min-w-7 text-default-500 transition-colors duration-200 data-[hover=true]:bg-default-200 data-[hover=true]:text-foreground",
        selectorIcon:
          "size-4 text-current transition-colors duration-200 group-data-[invalid=true]:text-danger",
        helperWrapper: "px-1 pb-0 pt-1.5",
        errorMessage: "text-xs font-medium leading-4 text-danger",
        popoverContent:
          "border border-divider bg-content1 text-foreground shadow-large",
      }}
      calendarProps={{
        classNames: {
          base: "bg-content1 text-foreground",
          prevButton: "cursor-pointer",
          nextButton: "cursor-pointer",
          headerWrapper: "bg-content1 px-3",
          header: "cursor-pointer",
          gridHeader: "bg-content1",
          gridHeaderCell: "w-9",
          pickerItem: "cursor-pointer",
          cellButton:
            "size-9 cursor-pointer !duration-200 ease-out before:transition-[background-color,box-shadow] before:duration-200 before:ease-out data-[hover=true]:bg-primary/10 data-[hover=true]:text-primary data-[pressed=true]:scale-95 data-[selected=true]:data-[range-selection=true]:before:!bg-primary/20 data-[selected=true]:data-[range-selection=true]:before:shadow-sm data-[selected=true]:data-[range-selection=true]:before:shadow-primary/20 dark:data-[selected=true]:data-[range-selection=true]:before:!bg-primary/25 motion-reduce:transition-none motion-reduce:transform-none motion-reduce:before:transition-none",
        },
      }}
    />
  );
};

export const DateRangeClearButton = ({
  isVisible,
  onClear,
  className = "",
}: DateRangeClearButtonProps) => {
  const { t } = useTranslation();

  return (
    <div className={`size-9 shrink-0 ${className}`}>
      <Tooltip
        content={t("filters.clearDateRange")}
        placement="top"
        delay={250}
        closeDelay={0}
        isDisabled={!isVisible}
        showArrow
      >
        <Button
          isIconOnly
          size="sm"
          variant="flat"
          aria-label={t("filters.clearDateRange")}
          aria-hidden={!isVisible}
          title={t("filters.clearDateRange")}
          isDisabled={!isVisible}
          onPress={onClear}
          className={`size-9 min-w-9 cursor-pointer bg-default-100 text-default-500 transition-[opacity,transform,background-color,color] duration-200 ease-out data-[hover=true]:bg-default-200 data-[hover=true]:text-foreground motion-reduce:transition-none ${
            isVisible
              ? "scale-100 opacity-100"
              : "pointer-events-none scale-90 opacity-0"
          }`}
        >
          <X className="size-4" />
        </Button>
      </Tooltip>
    </div>
  );
};

export default DateRangeFilter;
