import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import StoreTiming from "@/components/StoreTiming";

const CANONICAL =
  "Mon: 09:00-21:00, Tue: 09:00-21:00, Thu: 09:00-21:00, Fri: 09:00-21:00, Sat: 09:00-21:00";

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
    expect(
      screen.getByTestId("store-timing-trigger"),
    ).toHaveTextContent("Today · 9:00 AM – 9:00 PM");
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
    expect(screen.queryByTestId("store-timing-trigger")).not.toBeInTheDocument();
  });
});
