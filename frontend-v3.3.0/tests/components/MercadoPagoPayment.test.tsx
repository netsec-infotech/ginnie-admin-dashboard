import type { ReactNode } from "react";
import { act, fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
const mocks = vi.hoisted(() => ({ checkout: vi.fn(), cart: vi.fn(), settings: {mercadoPagoPayment: true} }));
vi.mock("@/contexts/SettingsContext", () => ({useSettings: () => ({paymentSettings: mocks.settings})}));
vi.mock("@/helpers/functionalHelpers", () => ({
  handleCheckout: mocks.checkout,
}));
vi.mock("@/helpers/getters", () => ({ getCheckoutCartFromRedux: mocks.cart }));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: () => "Pay with Mercado Pago" }),
}));
vi.mock("@heroui/react", () => ({
  Button: ({
    children,
    onPress,
    isDisabled,
  }: {
    children: ReactNode;
    onPress: () => void;
    isDisabled: boolean;
  }) => (
    <button disabled={isDisabled} onClick={onPress}>
      {children}
    </button>
  ),
}));
import MercadoPagoPayment from "@/components/PaymentGateway/MercadoPagoPayment";

beforeEach(() => {
  vi.clearAllMocks();
  mocks.settings.mercadoPagoPayment = true;
  mocks.cart.mockReturnValue({
    available_payment_methods: [{key: "mercadoPagoPayment", is_enabled: true, is_available: true}],
  });
});
describe("Mercado Pago payment button", () => {
  it("prevents duplicate submits while placing the order and keeps loading during redirect", async () => {
    let complete!: (value: unknown) => void;
    mocks.checkout.mockReturnValue(
      new Promise((resolve) => {
        complete = resolve;
      }),
    );
    const setLoading = vi.fn();
    const onError = vi.fn();
    render(
      <MercadoPagoPayment
        isLoading={false}
        setIsLoading={setLoading}
        onError={onError}
      />,
    );
    const button = screen.getByRole("button");
    fireEvent.click(button);
    fireEvent.click(button);
    expect(mocks.checkout).toHaveBeenCalledExactlyOnceWith(
      "mercadoPagoPayment",
      {},
      { paymentSettings: mocks.settings },
    );
    await act(async () => complete({ success: true, message: "Redirected" }));
    fireEvent.click(button);
    expect(mocks.checkout).toHaveBeenCalledOnce();
    expect(setLoading.mock.calls).toEqual([[true]]);
    expect(onError).not.toHaveBeenCalled();
  });

  it("unlocks after failure without treating it as successful payment", async () => {
    mocks.checkout.mockResolvedValue({ success: false });
    const setLoading = vi.fn();
    const onError = vi.fn();
    render(
      <MercadoPagoPayment
        isLoading={false}
        setIsLoading={setLoading}
        onError={onError}
      />,
    );
    await act(async () => fireEvent.click(screen.getByRole("button")));
    expect(onError).toHaveBeenCalledOnce();
    expect(setLoading.mock.calls).toEqual([[true], [false]]);
  });

  it("blocks stale selections when the cart no longer allows the gateway", () => {
    mocks.cart.mockReturnValue({
      available_payment_methods: [{key: "mercadoPagoPayment", is_enabled: true, is_available: false}],
    });
    render(
      <MercadoPagoPayment
        isLoading={false}
        setIsLoading={vi.fn()}
        onError={vi.fn()}
      />,
    );
    expect(screen.getByRole("button")).toBeDisabled();
    fireEvent.click(screen.getByRole("button"));
    expect(mocks.checkout).not.toHaveBeenCalled();
  });

  it("rechecks cart eligibility when clicked after rendering", () => {
    render(<MercadoPagoPayment isLoading={false} setIsLoading={vi.fn()} onError={vi.fn()} />);
    expect(screen.getByRole("button")).toBeEnabled();
    mocks.cart.mockReturnValue({ available_payment_methods: [] });
    fireEvent.click(screen.getByRole("button"));
    expect(mocks.checkout).not.toHaveBeenCalled();
  });

  it.each([true, false])("uses settings (%s) when the cart omits the payment list", async (enabled) => {
    mocks.settings.mercadoPagoPayment = enabled;
    mocks.cart.mockReturnValue({});
    mocks.checkout.mockResolvedValue({success: false});
    render(<MercadoPagoPayment isLoading={false} setIsLoading={vi.fn()} onError={vi.fn()} />);
    expect((screen.getByRole("button") as HTMLButtonElement).disabled).toBe(!enabled);
    await act(async () => fireEvent.click(screen.getByRole("button")));
    expect(mocks.checkout).toHaveBeenCalledTimes(enabled ? 1 : 0);
  });
});
