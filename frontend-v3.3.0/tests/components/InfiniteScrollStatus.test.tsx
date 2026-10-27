import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string, options?: { defaultValue?: string }) =>
      options?.defaultValue ?? key,
  }),
}));

import InfiniteScrollStatus from "@/components/Functional/InfiniteScrollStatus";

describe("InfiniteScrollStatus", () => {
  it("uses a complete singular message for one result", () => {
    render(<InfiniteScrollStatus entityType="store" total={1} hasMore={false} />);
    expect(screen.getByText("end_reached_single")).toBeInTheDocument();
  });

  it("uses the multiple-results message for larger lists", () => {
    render(<InfiniteScrollStatus entityType="store" total={2} hasMore={false} />);
    expect(screen.getByText("end_reached_multiple")).toBeInTheDocument();
  });

  it("prioritizes an actionable load-more error over the normal status", () => {
    const onRetry = vi.fn();

    render(
      <InfiniteScrollStatus
        entityType="product"
        total={0}
        hasMore={false}
        error={new Error("Load more failed")}
        onRetry={onRetry}
      />,
    );

    expect(
      screen.getByText("More items are taking a moment to load."),
    ).toBeInTheDocument();

    fireEvent.click(
      screen.getByRole("button", {
        name: "Try again: More items are taking a moment to load.",
      }),
    );

    expect(onRetry).toHaveBeenCalledTimes(1);
  });
});
