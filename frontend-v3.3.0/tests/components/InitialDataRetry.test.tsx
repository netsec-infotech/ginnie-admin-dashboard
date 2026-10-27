import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string, options?: { defaultValue?: string }) =>
      options?.defaultValue ?? key,
  }),
}));

import InitialDataRetry from "@/components/Functional/InitialDataRetry";

describe("InitialDataRetry", () => {
  it("shows a customer-safe message and retries on demand", () => {
    const onRetry = vi.fn();

    render(<InitialDataRetry onRetry={onRetry} />);

    expect(
      screen.getByText("This content is taking a moment to load."),
    ).toBeInTheDocument();

    fireEvent.click(
      screen.getByRole("button", {
        name: "Try again: This content is taking a moment to load.",
      }),
    );

    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it("disables repeated retries while validation is in progress", () => {
    render(<InitialDataRetry onRetry={vi.fn()} isRetrying />);

    expect(
      screen.getByRole("button", {
        name: "Try again: This content is taking a moment to load.",
      }),
    ).toBeDisabled();
  });
});
