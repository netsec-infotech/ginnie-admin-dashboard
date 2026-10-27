import { render, screen } from "@testing-library/react";
import type { PropsWithChildren } from "react";
import { expect, it, vi } from "vitest";
import type { CartResponse } from "@/types/ApiResponse";
import CheckoutSection from "@/views/CartPageView/CheckoutSection";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("next/router", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("react-redux", () => ({
  useDispatch: () => vi.fn(),
  useSelector: (selector: (state: unknown) => unknown) =>
    selector({
      auth: { user: null },
      cart: { isLoading: false },
      checkout: { selectedAddress: { id: 1 }, useLoyaltyPoints: false },
    }),
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    currencySymbol: "$",
    paymentSettings: {},
    systemSettings: {},
    isSingleVendor: false,
  }),
}));
vi.mock("@/helpers/functionalHelpers", () => ({
  formatAmount: (value: number) => value.toFixed(2),
  handleCheckout: vi.fn(),
  validateCheckoutAddress: vi.fn(),
}));
vi.mock("@/helpers/updators", () => ({ updateCartData: vi.fn() }));
vi.mock("@/routes/api", () => ({ clearCart: vi.fn() }));
vi.mock("@/components/Modals/PaymentModal", () => ({ default: () => null }));
vi.mock("@/components/Modals/ConfirmationModal", () => ({
  default: () => null,
}));
vi.mock("@/components/Cart/ShareCartButton", () => ({ default: () => null }));
vi.mock("@/views/CartPageView/CartItems", () => ({
  default: () => <div>Cart contents</div>,
}));
vi.mock("@heroui/react", () => {
  const Box = ({ children }: PropsWithChildren) => <div>{children}</div>;
  return {
    Card: Box,
    CardBody: Box,
    CardHeader: Box,
    CardFooter: Box,
    Divider: () => null,
    Alert: ({ description }: { description: string }) => (
      <p role="alert">{description}</p>
    ),
    Button: ({
      children,
      isDisabled,
    }: PropsWithChildren<{ isDisabled?: boolean }>) => (
      <button disabled={isDisabled}>{children}</button>
    ),
    Switch: ({children}: PropsWithChildren) => <label>{children}</label>,
    addToast: vi.fn(),
    useDisclosure: () => ({
      isOpen: false,
      onOpen: vi.fn(),
      onOpenChange: vi.fn(),
    }),
  };
});

it("keeps mixed-currency contents visible while disabling checkout and hiding combined totals", () => {
  const cart = {
    currency_code: null,
    currency_codes: ["AED", "USD"],
    items: [],
    total_quantity: 2,
    payment_summary: {
      use_wallet: false,
      items_total: 55,
      payable_amount: 55,
      wallet_balance: 0,
    },
  } as unknown as CartResponse;
  render(<CheckoutSection cart={cart} />);
  expect(screen.getByText("Cart contents")).toBeInTheDocument();
  expect(screen.getByRole("alert")).toHaveTextContent(
    "multiCurrency.mixedCart",
  );
  expect(
    screen.getByRole("button", { name: "checkout.proceedToCheckout" }),
  ).toBeDisabled();
  expect(screen.queryByText("checkout.itemsTotal")).not.toBeInTheDocument();
  expect(screen.queryByText(/55\.00/)).not.toBeInTheDocument();
});

it.each([true, false])("uses cart wallet availability (%s) even without global wallet settings", (available) => {
  const cart = {
    currency_code: "USD", currency_codes: ["USD"], items: [], total_quantity: 1,
    available_payment_methods: [{key: "wallet", is_enabled: true, is_available: available}],
    payment_summary: {use_wallet: false, items_total: 10, payable_amount: 10, wallet_balance: 50},
  } as unknown as CartResponse;
  render(<CheckoutSection cart={cart} />);
  expect(!!screen.queryByText("checkout.useWalletBalance")).toBe(available);
});
