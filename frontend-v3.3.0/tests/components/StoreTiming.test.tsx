import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import StoreTiming from "@/components/StoreTiming";

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (
        key: string,
        options?: string | Record<string, string | number | undefined>,
      ) => {
        const template =
          typeof options === "string"
            ? options
            : String(options?.defaultValue ?? key);
        if (!options || typeof options === "string") return template;
        return template.replace(/{{(\w+)}}/g, (_match, name: string) =>
          String(options[name] ?? `{{${name}}}`),
        );
      },
    }),
  };
});

const CANONICAL =
  "Mon: 09:00-21:00, Tue: 09:00-21:00, Thu: 09:00-21:00, Fri: 09:00-21:00, Sat: 09:00-21:00";

const METADATA = {
  operating_hours: {
    monday: {
      is_open: "1",
      open_time: "09:00",
      close_time: "21:00",
      has_break: "0",
    },
    wednesday: {
      is_open: "1",
      open_time: "09:00",
      close_time: "16:00",
      has_break: "1",
      break_start_time: "13:00",
      break_end_time: "14:00",
    },
    thursday: { is_open: "0" },
    friday: {
      is_open: "1",
      open_time: "11:00",
      close_time: "21:00",
      has_break: "0",
    },
  },
};

describe("StoreTiming", () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  /** 2026-07-17 is a Friday — an open day for the canonical fixture. */
  const onFriday = () => vi.setSystemTime(new Date(2026, 6, 17, 12));
  /** 2026-07-19 is a Sunday — closed in the canonical fixture. */
  const onSunday = () => vi.setSystemTime(new Date(2026, 6, 19, 12));

  it("renders nothing when the store has no timing at all", () => {
    const { container } = render(<StoreTiming timing={null} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("leads with today's hours", () => {
    onFriday();
    render(<StoreTiming timing={CANONICAL} />);
    expect(screen.getByTestId("store-timing-trigger")).toHaveTextContent(
      "Today · 9:00 AM – 9:00 PM",
    );
  });

  it("says so when the store is shut today", () => {
    onSunday();
    render(<StoreTiming timing={CANONICAL} />);
    expect(screen.getByTestId("store-timing-trigger")).toHaveTextContent(
      "Closed today",
    );
  });

  it("collapses uniform hours into the subtitle", () => {
    onFriday();
    render(<StoreTiming timing={CANONICAL} />);
    expect(screen.getByTestId("store-timing-trigger")).toHaveTextContent(
      "5 days a week · 9:00 AM – 9:00 PM",
    );
  });

  it("offers the full week when hours vary by day", () => {
    onFriday();
    render(<StoreTiming timing="Mon: 09:00-21:00, Tue: 10:00-18:00" />);
    expect(screen.getByTestId("store-timing-trigger")).toHaveTextContent(
      "View all hours",
    );
  });

  it("shows every day, marking closed ones, once opened", async () => {
    const user = userEvent.setup();
    onFriday();
    render(<StoreTiming timing={CANONICAL} />);

    await user.click(screen.getByTestId("store-timing-trigger"));

    await waitFor(() =>
      expect(screen.getByTestId("store-timing-week")).toBeInTheDocument(),
    );
    expect(screen.getByTestId("timing-row-Mon")).toHaveTextContent(
      "9:00 AM – 9:00 PM",
    );
    expect(screen.getByTestId("timing-row-Wed")).toHaveTextContent("Closed");
    expect(screen.getByTestId("timing-row-Sun")).toHaveTextContent("Closed");
  });

  it("prefers metadata and clearly shows the current open state", () => {
    onFriday();
    render(<StoreTiming metadata={METADATA} timing="MON-SUN 9 to 5 pm" />);

    const trigger = screen.getByTestId("store-timing-trigger");
    expect(trigger).toHaveAttribute("data-status", "open");
    expect(trigger).toHaveTextContent("Open now · Closes at 9:00 PM");
    expect(trigger).toHaveTextContent("Today: 11:00 AM – 9:00 PM");
    expect(screen.queryByText("MON-SUN 9 to 5 pm")).not.toBeInTheDocument();
  });

  it("explains a scheduled break and when service resumes", () => {
    vi.setSystemTime(new Date(2026, 6, 15, 13, 30)); // Wednesday
    render(<StoreTiming metadata={METADATA} timing={null} />);

    const trigger = screen.getByTestId("store-timing-trigger");
    expect(trigger).toHaveAttribute("data-status", "break");
    expect(trigger).toHaveTextContent("On break · Reopens at 2:00 PM");
    expect(trigger).toHaveTextContent("Break 1:00 PM – 2:00 PM");
  });

  it("shows the next opening when metadata marks today closed", () => {
    vi.setSystemTime(new Date(2026, 6, 16, 12)); // Thursday
    render(<StoreTiming metadata={METADATA} timing="MON-SUN 9 to 5 pm" />);

    const trigger = screen.getByTestId("store-timing-trigger");
    expect(trigger).toHaveAttribute("data-status", "closed");
    expect(trigger).toHaveTextContent("Closed today");
    expect(trigger).toHaveTextContent("Opens tomorrow at 11:00 AM");
  });

  it("shows metadata break details in the full-week popover", async () => {
    const user = userEvent.setup();
    vi.setSystemTime(new Date(2026, 6, 15, 12));
    render(<StoreTiming metadata={METADATA} timing={null} />);

    await user.click(screen.getByTestId("store-timing-trigger"));
    await waitFor(() =>
      expect(screen.getByTestId("store-timing-week")).toBeInTheDocument(),
    );
    expect(screen.getByTestId("timing-row-Wed")).toHaveTextContent(
      "9:00 AM – 4:00 PMBreak 1:00 PM – 2:00 PM",
    );
    expect(screen.getByTestId("timing-row-Thu")).toHaveTextContent("Closed");
  });

  /**
   * The regression that matters: legacy stores hold prose or junk in `timing`.
   * They must keep rendering exactly what the seller typed, with no popover and
   * no invented schedule.
   */
  it.each([
    ["11 AM – 8 PM (Mon–Sat)"],
    ["MON-SUN 9 to 5 pm"],
    ["Mon-Fri"],
    ["aaaaaaaaaaaaaa"],
  ])("falls back to the raw string for %s", (raw) => {
    onFriday();
    render(<StoreTiming timing={raw} />);

    expect(screen.getByText(raw)).toBeInTheDocument();
    expect(
      screen.queryByTestId("store-timing-trigger"),
    ).not.toBeInTheDocument();
  });
});
