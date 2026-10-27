import type { ReactNode } from "react";
import { fireEvent, render, screen } from "@testing-library/react";
import { parseDate } from "@internationalized/date";
import { describe, expect, it, vi } from "vitest";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));

type MockDate = { toString: () => string };
type MockRange = { start: MockDate; end: MockDate };

vi.mock("@heroui/react", () => ({
  DateRangePicker: ({
    value,
    onChange,
    startContent,
    selectorIcon,
    CalendarTopContent,
    CalendarBottomContent,
    popoverProps,
  }: {
    value: MockRange | null;
    onChange: (range: MockRange | null) => void;
    startContent?: ReactNode;
    selectorIcon?: ReactNode;
    CalendarTopContent?: ReactNode;
    CalendarBottomContent?: ReactNode;
    popoverProps?: {
      shouldCloseOnScroll?: boolean;
      shouldBlockScroll?: boolean;
      isDismissable?: boolean;
      isKeyboardDismissDisabled?: boolean;
    };
  }) => (
    <div
      data-testid="date-range-root"
      data-close-on-scroll={String(popoverProps?.shouldCloseOnScroll)}
      data-block-scroll={String(popoverProps?.shouldBlockScroll)}
      data-dismissible={String(popoverProps?.isDismissable)}
      data-keyboard-dismiss-disabled={String(
        popoverProps?.isKeyboardDismissDisabled,
      )}
    >
      <button
        data-testid="date-range-picker"
        data-start={value?.start.toString()}
        data-end={value?.end.toString()}
        data-has-start-icon={Boolean(startContent)}
        data-has-selector-icon={Boolean(selectorIcon)}
        onClick={() =>
          onChange({
            start: { toString: () => "2026-08-01" },
            end: { toString: () => "2026-08-12" },
          })
        }
      >
        Select dates
      </button>
      {CalendarTopContent}
      {CalendarBottomContent}
    </div>
  ),
  Button: ({
    children,
    onPress,
    isDisabled,
    "aria-label": ariaLabel,
    "aria-hidden": ariaHidden,
  }: {
    children: ReactNode;
    onPress?: () => void;
    isDisabled?: boolean;
    "aria-label"?: string;
    "aria-hidden"?: boolean;
  }) => (
    <button
      aria-label={ariaLabel}
      aria-hidden={ariaHidden}
      disabled={isDisabled}
      onClick={onPress}
    >
      {children}
    </button>
  ),
  Tooltip: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

import DateRangeFilter, {
  DateRangeClearButton,
} from "@/components/Filters/DateRangeFilter";

describe("DateRangeFilter", () => {
  it("displays the current range and emits a newly selected range", () => {
    const onChange = vi.fn();
    render(
      <DateRangeFilter
        from="2026-07-01"
        to="2026-07-31"
        onChange={onChange}
      />,
    );

    expect(screen.getByTestId("date-range-picker")).toHaveAttribute(
      "data-start",
      "2026-07-01",
    );
    expect(screen.getByTestId("date-range-picker")).toHaveAttribute(
      "data-end",
      "2026-07-31",
    );
    expect(screen.getByTestId("date-range-picker")).toHaveAttribute(
      "data-has-start-icon",
      "false",
    );
    expect(screen.getByTestId("date-range-picker")).toHaveAttribute(
      "data-has-selector-icon",
      "true",
    );

    fireEvent.click(screen.getByTestId("date-range-picker"));
    expect(onChange).toHaveBeenCalledWith({
      from: "2026-08-01",
      to: "2026-08-12",
    });
  });

  it("clears both ends of the selected range", () => {
    const onClear = vi.fn();
    render(
      <DateRangeClearButton isVisible onClear={onClear} />,
    );

    fireEvent.click(
      screen.getByRole("button", { name: "filters.clearDateRange" }),
    );
    expect(onClear).toHaveBeenCalledOnce();
  });

  it("offers guidance and applies a seven-day quick range", () => {
    const onChange = vi.fn();
    render(<DateRangeFilter onChange={onChange} />);

    expect(screen.getByText("filters.dateRangeHint")).toBeInTheDocument();
    fireEvent.click(
      screen.getByRole("button", { name: "filters.last_7_days" }),
    );

    const selectedRange = onChange.mock.calls[0][0] as {
      from: string;
      to: string;
    };
    expect(parseDate(selectedRange.from).add({ days: 6 }).toString()).toBe(
      selectedRange.to,
    );
  });

  it("stays open during scrolling while preserving normal dismissal", () => {
    render(<DateRangeFilter onChange={vi.fn()} />);

    expect(screen.getByTestId("date-range-root")).toHaveAttribute(
      "data-close-on-scroll",
      "false",
    );
    expect(screen.getByTestId("date-range-root")).toHaveAttribute(
      "data-block-scroll",
      "false",
    );
    expect(screen.getByTestId("date-range-root")).toHaveAttribute(
      "data-dismissible",
      "true",
    );
    expect(screen.getByTestId("date-range-root")).toHaveAttribute(
      "data-keyboard-dismiss-disabled",
      "false",
    );
  });

  it("disables the reserved clear action when no range is selected", () => {
    render(<DateRangeClearButton isVisible={false} onClear={vi.fn()} />);

    expect(
      screen.getByRole("button", { hidden: true }),
    ).toBeDisabled();
  });
});
