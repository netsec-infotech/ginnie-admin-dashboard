import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { PropsWithChildren } from "react";
import ExpressDelivery from "@/components/Cart/ExpressDelivery";

const mocks = vi.hoisted(() => ({ state: vi.fn() }));
vi.mock("react-redux", () => ({
  useDispatch: () => vi.fn(),
  useSelector: (select: (state: unknown) => unknown) => select(mocks.state()),
}));
vi.mock("@/helpers/updators", () => ({ updateCartData: vi.fn() }));
vi.mock("@/helpers/functionalHelpers", () => ({ formatAmount: (amount: number) => amount.toFixed(2) }));
vi.mock("@/contexts/SettingsContext", () => ({ useSettings: () => ({ currencySymbol: "₹" }) }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({
  t: (key: string, params?: { amount?: string }) => params?.amount ? `Delivery charge: ${params.amount}` : key,
}) }));
vi.mock("@heroui/react", () => {
  const Box = ({ children }: PropsWithChildren) => <div>{children}</div>;
  return { Card: Box, CardBody: Box,
    Switch: ({ isSelected }: { isSelected: boolean }) => <input aria-label="Rush" type="checkbox" checked={isSelected} readOnly />,
  };
});

beforeEach(() => {
  mocks.state.mockReturnValue({
    checkout: { selectedAddress: { id: 1 }, scheduledDelivery: null },
    cart: { isLoading: false, deliveryLocationNeedsSelection: false, cartData: {
      payment_summary: { rush_delivery_charge: 45, is_rush_delivery_available: true },
    } },
  });
});

describe("rush delivery quote", () => {
  it("shows the quoted charge before the toggle is enabled", () => {
    render(<ExpressDelivery />);
    expect(screen.getByText("Delivery charge: ₹45.00")).toBeInTheDocument();
    expect(screen.getByRole("checkbox", { name: "Rush" })).not.toBeChecked();
  });

  it("shows a genuine zero quote when rush is available", () => {
    mocks.state().cart.cartData.payment_summary.rush_delivery_charge = 0;
    render(<ExpressDelivery />);
    expect(screen.getByText("Delivery charge: ₹0.00")).toBeInTheDocument();
  });

  it("does not show a placeholder zero as free delivery when rush is unavailable", () => {
    mocks.state().cart.cartData.payment_summary = { rush_delivery_charge: 0, is_rush_delivery_available: false };
    render(<ExpressDelivery />);
    expect(screen.queryByText(/Delivery charge:/)).not.toBeInTheDocument();
    expect(screen.getByText("express_rushDeliveryChargeUnavailable")).toBeInTheDocument();
  });

  it("asks for an address instead of displaying an unverified quote", () => {
    mocks.state().cart.deliveryLocationNeedsSelection = true;
    render(<ExpressDelivery />);
    expect(screen.queryByText(/Delivery charge:/)).not.toBeInTheDocument();
    expect(screen.getByText("express_rushDeliveryChargePending")).toBeInTheDocument();
  });
});
