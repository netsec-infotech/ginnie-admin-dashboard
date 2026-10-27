import type { ReactNode } from "react";
import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { Order } from "@/types/ApiResponse";
import orderWithReturn from "../fixtures/orderWithReturn.json";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string) =>
      ({ deliveryOtp: "Delivery OTP", returnPickupOtp: "Return pickup OTP" })[
        key
      ] || key,
  }),
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ currencySymbol: "$" }),
}));
vi.mock("@/helpers/getters", () => ({
  getFormattedDate: () => "Order date",
  getOrderStatusBtnConfig: () => ({}),
  isStatusBeforeOrAt: () => true,
}));
vi.mock("@/routes/api", () => ({ reorderOrder: vi.fn() }));
vi.mock("@/helpers/updators", () => ({ updateCartData: vi.fn() }));
vi.mock("@/components/Modals/CancelOrderItemModal", () => ({
  default: () => null,
}));
vi.mock("@/components/Modals/ReturnOrderItemModal", () => ({
  default: () => null,
}));
vi.mock("@/components/Modals/RatingModal", () => ({ default: () => null }));
vi.mock("@/components/Modals/OrderItemReviewCard", () => ({
  default: () => null,
}));
vi.mock("@/components/FilePreview", () => ({ default: () => null }));
vi.mock("next/dynamic", () => ({ default: () => () => null }));
vi.mock("yet-another-react-lightbox", () => ({ default: () => null }));
vi.mock("@heroui/react", () => {
  const Box = ({ children }: { children?: ReactNode }) => <div>{children}</div>;
  return {
    Chip: Box,
    Card: Box,
    CardBody: Box,
    CardHeader: Box,
    CardFooter: Box,
    Accordion: Box,
    AccordionItem: Box,
    ScrollShadow: Box,
    Button: Box,
    Divider: () => null,
    Image: () => null,
    addToast: vi.fn(),
    useDisclosure: () => ({ isOpen: false, onOpen: vi.fn(), onClose: vi.fn() }),
  };
});

import OrderCard from "@/components/Cards/OrderCard";
import OrderItems from "@/views/OrderDetailView/OrderItems";

const makeOrder = (): Order => {
  const base = orderWithReturn.data as unknown as Order;
  const item = base.items[0];
  return {
    ...base,
    delivery_otp_mode: "order_wise",
    delivery_otp_required: true,
    delivery_otp: "012345",
    delivery_otp_verified: false,
    items: [
      { ...item, id: 1, otp: "111111", otp_verified: 0, returns: [] },
      { ...item, id: 2, otp: "222222", otp_verified: 0, returns: [] },
    ],
  };
};
const detail = (order: Order) => (
  <OrderItems
    order={order}
    currencySymbol="$"
    onOpen={vi.fn()}
    handleProductReview={vi.fn()}
  />
);

describe.each([
  ["orders list", (order: Order) => <OrderCard order={order} />],
  ["order details", detail],
] as const)("delivery OTP rendering: %s", (_name, view) => {
  it("shows the order code exactly once and never repeats item codes", () => {
    render(view(makeOrder()));
    expect(screen.getAllByText("Delivery OTP: 012345")).toHaveLength(1);
    expect(screen.queryByText("Delivery OTP: 111111")).not.toBeInTheDocument();
    expect(screen.queryByText("Delivery OTP: 222222")).not.toBeInTheDocument();
  });
  it("updates from order-wise to item-wise and hides verified codes on refreshed data", () => {
    const order = makeOrder();
    const { rerender } = render(view(order));
    const itemWise = { ...order, delivery_otp_mode: "item_wise" as const };
    rerender(view(itemWise));
    expect(screen.queryByText("Delivery OTP: 012345")).not.toBeInTheDocument();
    expect(screen.getByText("Delivery OTP: 111111")).toBeInTheDocument();
    expect(screen.getByText("Delivery OTP: 222222")).toBeInTheDocument();
    rerender(
      view({
        ...itemWise,
        items: itemWise.items.map((item) => ({ ...item, otp_verified: 1 })),
      }),
    );
    expect(screen.queryByText(/^Delivery OTP:/)).not.toBeInTheDocument();
    rerender(view({ ...order, delivery_otp_verified: true }));
    expect(screen.queryByText(/^Delivery OTP:/)).not.toBeInTheDocument();
  });
  it("keeps legacy item OTPs and hides delivery codes when explicitly disabled", () => {
    const order = { ...makeOrder(), delivery_otp_mode: undefined };
    const { rerender } = render(view(order));
    expect(screen.getByText("Delivery OTP: 111111")).toBeInTheDocument();
    rerender(view({ ...order, delivery_otp_required: false }));
    expect(screen.queryByText(/^Delivery OTP:/)).not.toBeInTheDocument();
  });
});

it("keeps return pickup OTPs visible beside their items in order-wise delivery mode", () => {
  const order = makeOrder();
  const returnedItem = orderWithReturn.data
    .items[0] as unknown as Order["items"][number];
  order.items[0] = { ...order.items[0], returns: returnedItem.returns };
  render(detail(order));
  expect(screen.getByText("Delivery OTP: 012345")).toBeInTheDocument();
  expect(screen.getByText("Return pickup OTP: 811614")).toBeInTheDocument();
});
