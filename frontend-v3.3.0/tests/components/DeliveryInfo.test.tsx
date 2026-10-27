import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { Order } from "@/types/ApiResponse";

vi.mock("@/helpers/getters", () => ({
  getOrderStatusBtnConfig: () => ({ deliveryTime: true }),
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@heroui/react", () => {
  const Wrapper = ({ children }: any) => <div>{children}</div>;
  return {
    Avatar: Wrapper,
    Button: Wrapper,
    Card: Wrapper,
    CardBody: Wrapper,
    CardHeader: Wrapper,
    Chip: Wrapper,
  };
});
import DeliveryInfo from "@/views/OrderDetailView/DeliveryInfo";

const order = {
  status: "awaiting_store_response",
  is_scheduled_delivery: true,
  scheduled_date: "2026-08-25",
  scheduled_time_slot_label: "12:00 PM - 1:00 PM",
  delivery_type: "delivery",
} as Order;

describe("order delivery information", () => {
  it("explains pending scheduled orders and keeps the promised window visible", () => {
    render(<DeliveryInfo order={order} onDeliveryRatingOpen={vi.fn()} />);
    expect(screen.getByRole("status")).toHaveTextContent(
      "scheduledOrderAwaitingConfirmation",
    );
    expect(screen.getByText("12:00 PM - 1:00 PM")).toBeInTheDocument();
  });
  it.each([
    { status: "delivered" },
    { is_scheduled_delivery: false },
    { reschedule_status: "needs_reschedule" },
  ])("does not show a misleading waiting note for %j", (change) => {
    render(
      <DeliveryInfo
        order={{ ...order, ...change } as Order}
        onDeliveryRatingOpen={vi.fn()}
      />,
    );
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
  });
});
