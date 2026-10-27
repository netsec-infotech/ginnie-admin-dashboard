import type { PropsWithChildren } from "react";
import { act, fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
import type { CartResponse } from "@/types/ApiResponse";

const mocks = vi.hoisted(() => ({
  cart: null as CartResponse | null,
  refreshing: false,
  error: null as string | null,
  checkout: vi.fn(),
  settings: {cod: false, stripePayment: false},
}));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key: string) => key }) }));
vi.mock("react-redux", () => ({
  useDispatch: () => vi.fn(),
  useSelector: (select: (state: unknown) => unknown) => select({ cart: {cartData: mocks.cart, isLoading: mocks.refreshing, error: mocks.error} }),
}));
vi.mock("@/contexts/SettingsContext", () => ({ useSettings: () => ({currencySymbol: "$", paymentSettings: mocks.settings}) }));
vi.mock("@/helpers/getters", () => ({
  getCheckoutCartFromRedux: () => mocks.refreshing || mocks.error ? null : mocks.cart,
  getUserDataFromRedux: () => ({ id: 1 }),
}));
vi.mock("@/helpers/functionalHelpers", () => ({ handleCheckout: mocks.checkout }));
vi.mock("@/helpers/updators", () => ({ updateCartData: vi.fn() }));
vi.mock("@/helpers/paymentReturn", () => ({
  paymentAttemptScope: () => "cart-scope",
  recoverAbandonedPaymentAttempt: vi.fn(),
  hasCompetingPaymentAttempt: () => false,
}));
vi.mock("@/components/PaymentGateway/PaymentInProgressToast", () => ({showPaymentInProgressToast: vi.fn()}));
vi.mock("@/components/PaymentGateway/Stripe", () => ({default: () => <div>Stripe form</div>}));
vi.mock("@/components/PaymentGateway/RazorPay", () => ({default: () => null}));
vi.mock("@/components/PaymentGateway/Paystack", () => ({default: () => null}));
vi.mock("@/components/PaymentGateway/FlutterwavePayment", () => ({default: () => null}));
vi.mock("@/components/PaymentGateway/XenditPayment", () => ({default: () => null}));
vi.mock("@/components/PaymentGateway/MercadoPagoPayment", () => ({default: () => null}));
vi.mock("@/components/PaymentMethods", () => ({default: ({setSelectedPayment, isLoading}: {setSelectedPayment: (value: string) => void; isLoading: boolean}) => <>
  <button disabled={isLoading} onClick={() => setSelectedPayment("cod")}>Choose COD</button>
  <button disabled={isLoading} onClick={() => setSelectedPayment("stripePayment")}>Choose Stripe</button>
</>}));
vi.mock("@heroui/react", () => {
  const Box = ({children}: PropsWithChildren) => <div>{children}</div>;
  return {
    Modal: Box, ModalBody: Box, ModalContent: Box, ModalFooter: Box, ModalHeader: Box,
    addToast: vi.fn(),
    Button: ({children, onPress, isDisabled}: PropsWithChildren<{onPress?: () => void; isDisabled?: boolean}>) => <button disabled={isDisabled} onClick={onPress}>{children}</button>,
  };
});
import PaymentModal from "@/components/Modals/PaymentModal";

beforeEach(() => {
  vi.clearAllMocks();
  mocks.refreshing = false;
  mocks.error = null;
  mocks.settings.cod = false;
  mocks.settings.stripePayment = false;
  mocks.cart = {
    id: 1, uuid: "cart-1", currency_code: "USD", currency_codes: ["USD"],
    available_payment_methods: ["cod", "stripePayment"].map(key => ({key, is_enabled: true, is_available: true})),
    payment_summary: {payable_amount: 10},
  } as CartResponse;
  mocks.checkout.mockResolvedValue({success: true});
});

it("lets the cart authorize COD even when global settings disable it", async () => {
  render(<PaymentModal open onOpenChange={vi.fn()} />);
  await act(async () => fireEvent.click(screen.getByText("Choose COD")));
  await act(async () => fireEvent.click(screen.getByText("continue")));
  expect(mocks.checkout).toHaveBeenCalledExactlyOnceWith("cod", {}, {paymentSettings: mocks.settings});
});

it("allows settings-based COD when the cart omits the payment list", async () => {
  mocks.settings.cod = true;
  mocks.cart = {...mocks.cart!, available_payment_methods: undefined};
  render(<PaymentModal open onOpenChange={vi.fn()} />);
  await act(async () => fireEvent.click(screen.getByText("Choose COD")));
  await act(async () => fireEvent.click(screen.getByText("continue")));
  expect(mocks.checkout).toHaveBeenCalledExactlyOnceWith("cod", {}, {paymentSettings: mocks.settings});
});

it("does not enable settings-based COD when the cart explicitly disallows it", async () => {
  mocks.settings.cod = true;
  mocks.cart = {...mocks.cart!, available_payment_methods: []};
  render(<PaymentModal open onOpenChange={vi.fn()} />);
  await act(async () => fireEvent.click(screen.getByText("Choose COD")));
  expect(screen.queryByText("continue")).not.toBeInTheDocument();
  expect(mocks.checkout).not.toHaveBeenCalled();
});

it("clears a previous choice when a new quote arrives, even if the method remains available", async () => {
  const {rerender} = render(<PaymentModal open onOpenChange={vi.fn()} />);
  await act(async () => fireEvent.click(screen.getByText("Choose COD")));
  expect(screen.getByText("continue")).toBeInTheDocument();
  mocks.cart = {...mocks.cart!};
  rerender(<PaymentModal open onOpenChange={vi.fn()} />);
  expect(screen.queryByText("continue")).not.toBeInTheDocument();
  expect(mocks.checkout).not.toHaveBeenCalled();
});

it("unmounts Stripe when a new cart no longer permits it", async () => {
  const {rerender} = render(<PaymentModal open onOpenChange={vi.fn()} />);
  await act(async () => fireEvent.click(screen.getByText("Choose Stripe")));
  expect(screen.getByText("Stripe form")).toBeInTheDocument();
  mocks.cart = {...mocks.cart!, available_payment_methods: []};
  rerender(<PaymentModal open onOpenChange={vi.fn()} />);
  expect(screen.queryByText("Stripe form")).not.toBeInTheDocument();
});

it.each(["refreshing", "error"])("blocks selection while the cart is %s", async (state) => {
  mocks.refreshing = state === "refreshing";
  mocks.error = state === "error" ? "failed" : null;
  render(<PaymentModal open onOpenChange={vi.fn()} />);
  expect(screen.getByText("Choose COD")).toBeDisabled();
  fireEvent.click(screen.getByText("Choose COD"));
  expect(screen.queryByText("continue")).not.toBeInTheDocument();
});
