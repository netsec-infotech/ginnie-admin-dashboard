import type { ReactNode } from "react";
import { readFileSync } from "node:fs";
import { StrictMode, useState } from "react";
import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
import { addToast } from "@heroui/react";
import Stripe from "@/components/PaymentGateway/Stripe";
import {
  acquirePaymentAttempt,
  paymentAttemptScope,
} from "@/helpers/paymentReturn";

const mocks = vi.hoisted(() => ({
  confirmPayment: vi.fn(),
  retrievePaymentIntent: vi.fn(),
  checkout: vi.fn(),
  intent: vi.fn(),
  amount: 10,
  available: true,
  cartReady: true,
  hasMethods: true,
  settings: { stripePayment: false, stripePublishableKey: "pk_test_mock", stripeCurrencyCode: "usd" },
  elements: {},
}));
vi.mock("@stripe/stripe-js", () => ({ loadStripe: () => Promise.resolve({}) }));
vi.mock("@stripe/react-stripe-js", () => ({
  Elements: ({ children, options }: { children: ReactNode; options: { clientSecret: string } }) => <div data-testid="stripe-elements" data-secret={options.clientSecret}>{children}</div>,
  useStripe: () => ({ confirmPayment: mocks.confirmPayment, retrievePaymentIntent: mocks.retrievePaymentIntent }),
  useElements: () => mocks.elements,
  PaymentElement: ({ onReady, onChange, onLoadError }: {
    onReady: () => void;
    onChange: (event: { complete: boolean }) => void;
    onLoadError: (event: { error: { message: string } }) => void;
  }) => <div>
    <button type="button" onClick={onReady}>Form ready</button>
    <button type="button" onClick={() => onChange({ complete: true })}>Complete details</button>
    <button type="button" onClick={() => onChange({ complete: false })}>Clear details</button>
    <button type="button" onClick={() => onLoadError({ error: { message: "Form failed to load" } })}>Load error</button>
  </div>,
}));
vi.mock("@heroui/react", () => ({
  addToast: vi.fn(),
  Button: ({ children, isDisabled, type, onPress }: { children: ReactNode; isDisabled?: boolean; type?: "submit" | "button"; onPress?: () => void }) =>
    <button type={type} disabled={isDisabled} onClick={onPress}>{children}</button>,
}));
vi.mock("@/contexts/SettingsContext", () => ({ useSettings: () => ({ paymentSettings: mocks.settings }) }));
vi.mock("@/helpers/getters", () => ({
  getCartDataFromRedux: () => ({ id: 10, uuid: "cart-10", payment_summary: { payable_amount: mocks.amount } }),
  getCheckoutCartFromRedux: () => mocks.cartReady ? ({ id: 10, uuid: "cart-10", available_payment_methods: mocks.hasMethods ? [{key: "stripePayment", is_enabled: true, is_available: mocks.available}] : undefined, payment_summary: { payable_amount: mocks.amount } }) : null,
  getUserDataFromRedux: () => ({ id: 1, email: "" }),
}));
vi.mock("@/helpers/functionalHelpers", () => ({ handleCheckout: mocks.checkout }));
vi.mock("@/routes/api", () => ({ createStripeIntent: mocks.intent }));
vi.mock("react-redux", () => ({ useDispatch: () => vi.fn() }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: translate }) }));
const translate = (key: string) =>
  ({
    "paymentAttempt.inProgressTitle": "Payment already in progress",
    "paymentAttempt.inProgressDescription":
      "Another tab is already paying for this cart. Do not pay again.",
    "paymentAttempt.goToTab": "Go to payment tab",
  })[key] || key;

async function submitReadyForm() {
  await screen.findByText("Form ready");
  fireEvent.click(screen.getByText("Form ready"));
  fireEvent.click(screen.getByText("Complete details"));
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Pay Now" })); });
  await waitFor(() =>
    expect(
      screen.queryByRole("button", { name: "Processing..." }),
    ).not.toBeInTheDocument(),
  );
}

const Checkout = ({ initiallyLoading = false }: { initiallyLoading?: boolean }) => {
  const [loading, setLoading] = useState(initiallyLoading);
  return <>
    <span data-testid="modal-loading">{String(loading)}</span>
    <Stripe onSuccess={vi.fn()} onError={vi.fn()} isLoading={loading} setIsLoading={setLoading} />
  </>;
};
beforeEach(() => {
  vi.clearAllMocks();
  localStorage.clear();
  sessionStorage.clear();
  window.name = "";
  mocks.amount = 10;
  mocks.available = true;
  mocks.cartReady = true;
  mocks.hasMethods = true;
  mocks.settings.stripePayment = false;
  mocks.retrievePaymentIntent.mockReset();
  mocks.confirmPayment.mockReset();
  mocks.checkout.mockReset();
  mocks.retrievePaymentIntent.mockResolvedValue({ paymentIntent: { id: "pi_test", status: "requires_payment_method" } });
  mocks.intent.mockResolvedValue({ success: true, data: { clientSecret: "test_secret" } });
});

it("does not initialize Stripe while another gateway owns the cart", async () => {
  const lease = await acquirePaymentAttempt(
    paymentAttemptScope({
      usageType: "order",
      userId: 1,
      cartId: "cart-10",
    }),
  );
  expect(lease).not.toBeNull();
  sessionStorage.clear();
  window.name = "competing-tab";

  render(<Checkout initiallyLoading />);

  expect(
    await screen.findByRole("alert"),
  ).toHaveTextContent("Payment already in progress");
  expect(screen.getByRole("button", { name: "Go to payment tab" })).toBeInTheDocument();
  expect(mocks.intent).not.toHaveBeenCalled();
  expect(mocks.retrievePaymentIntent).not.toHaveBeenCalled();
  await waitFor(() =>
    expect(screen.getByTestId("modal-loading")).toHaveTextContent("false"),
  );
});

it("initializes and confirms with the original settings when the cart omits its payment list", async () => {
  mocks.hasMethods = false;
  mocks.settings.stripePayment = true;
  mocks.confirmPayment.mockResolvedValue({paymentIntent: {id: "pi_paid", status: "succeeded"}});
  mocks.checkout.mockResolvedValue({success: true});
  render(<Checkout />);
  await submitReadyForm();
  expect(mocks.intent).toHaveBeenCalledOnce();
  expect(mocks.confirmPayment).toHaveBeenCalledOnce();
  expect(mocks.checkout).toHaveBeenCalledWith("stripePayment", {transaction_id: "pi_paid"}, expect.objectContaining({paymentSettings: mocks.settings}));
});

it("does not initialize a gateway the cart marks unavailable", async () => {
  mocks.available = false;
  render(<Checkout />);
  expect(await screen.findByRole("alert")).toHaveTextContent("multiCurrency.paymentUnavailable");
  expect(mocks.intent).not.toHaveBeenCalled();
});

it("does not initialize an inactive checkout", () => {
  render(<Stripe isActive={false} isLoading={false} setIsLoading={vi.fn()} onSuccess={vi.fn()} onError={vi.fn()} />);
  expect(mocks.intent).not.toHaveBeenCalled();
});

it("rechecks cart availability before confirming an already mounted form", async () => {
  render(<Checkout />);
  await screen.findByText("Form ready");
  mocks.available = false;
  await submitReadyForm();
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
  expect(mocks.checkout).not.toHaveBeenCalled();
});

it("does not confirm an old intent when the cart amount changes before submission", async () => {
  render(<Checkout />);
  await screen.findByText("Form ready");
  mocks.amount = 20;
  await submitReadyForm();
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
  expect(mocks.checkout).not.toHaveBeenCalled();
});

it("does not confirm when cart refresh starts during the intent status check", async () => {
  mocks.retrievePaymentIntent.mockImplementation(async () => {
    mocks.cartReady = false;
    return {paymentIntent: {id: "pi_test", status: "requires_payment_method"}};
  });
  render(<Checkout />);
  await submitReadyForm();
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
  expect(mocks.checkout).not.toHaveBeenCalled();
});

it.each(["succeeded", "processing", "requires_capture", "canceled"])("never confirms an intent already in %s", async (status) => {
  mocks.retrievePaymentIntent.mockResolvedValue({ paymentIntent: { id: "pi_test", status } });
  render(<Checkout />);
  await submitReadyForm();
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
  expect(mocks.checkout).not.toHaveBeenCalled();
  expect(screen.getByRole("button", { name: "Pay Now" })).toBeDisabled();
  expect(addToast).toHaveBeenCalledWith({ title: status === "succeeded" ? "stripe_already_paid" : status === "canceled" ? "stripe_payment_canceled" : "stripe_payment_pending", color: "danger" });
});

it("allows only one tab to confirm payment for the same cart", async () => {
  mocks.confirmPayment.mockResolvedValue({
    paymentIntent: { id: "pi_paid", status: "succeeded" },
  });
  mocks.checkout.mockResolvedValue({ success: true });
  render(
    <>
      <Checkout />
      <Checkout />
    </>,
  );
  const ready = await screen.findAllByText("Form ready");
  const complete = screen.getAllByText("Complete details");
  ready.forEach((button) => fireEvent.click(button));
  complete.forEach((button) => fireEvent.click(button));
  const pay = screen.getAllByRole("button", { name: "Pay Now" });

  act(() => {
    fireEvent.click(pay[0]);
    fireEvent.click(pay[1]);
  });

  await waitFor(() => expect(mocks.confirmPayment).toHaveBeenCalledOnce());
  expect(addToast).toHaveBeenCalledWith(
    expect.objectContaining({ title: "Payment already in progress" }),
  );
});

it("does not reconfirm or create another order after paid checkout fails", async () => {
  mocks.confirmPayment.mockResolvedValue({ paymentIntent: { id: "pi_paid", status: "succeeded" } });
  mocks.checkout.mockResolvedValue({ success: false, message: "Order failed" });
  render(<Checkout />);
  await submitReadyForm();
  expect(addToast).toHaveBeenCalledWith({ title: "stripe_paid_order_failed", color: "danger" });
  const pay = screen.getByRole("button", { name: "Pay Now" });
  expect(pay).toBeDisabled();
  fireEvent.submit(pay.closest("form")!);
  expect(mocks.confirmPayment).toHaveBeenCalledOnce();
  expect(mocks.checkout).toHaveBeenCalledExactlyOnceWith(
    "stripePayment",
    { transaction_id: "pi_paid" },
    { paymentAttemptLease: expect.objectContaining({ owner: expect.any(String) }), paymentSettings: mocks.settings },
  );
});

it("does not place an order when confirmation returns processing", async () => {
  mocks.confirmPayment.mockResolvedValue({ paymentIntent: { id: "pi_pending", status: "processing" } });
  render(<Checkout />);
  await submitReadyForm();
  expect(mocks.checkout).not.toHaveBeenCalled();
  expect(addToast).toHaveBeenCalledWith({ title: "stripe_payment_pending", color: "danger" });
  expect(screen.getByRole("button", { name: "Pay Now" })).toBeDisabled();
});

it("completes a successful payment once and keeps confirmation locked", async () => {
  mocks.confirmPayment.mockResolvedValue({ paymentIntent: { id: "pi_paid", status: "succeeded" } });
  mocks.checkout.mockResolvedValue({ success: true });
  const onSuccess = vi.fn();
  render(<Stripe onSuccess={onSuccess} onError={vi.fn()} isLoading={false} setIsLoading={vi.fn()} />);
  await submitReadyForm();
  expect(onSuccess).toHaveBeenCalledOnce();
  expect(mocks.checkout).toHaveBeenCalledExactlyOnceWith(
    "stripePayment",
    { transaction_id: "pi_paid" },
    { paymentAttemptLease: expect.objectContaining({ owner: expect.any(String) }), paymentSettings: mocks.settings },
  );
  const pay = screen.getByRole("button", { name: "Pay Now" });
  expect(pay).toBeDisabled();
  fireEvent.submit(pay.closest("form")!);
  expect(mocks.confirmPayment).toHaveBeenCalledOnce();
});

it("rechecks unexpected state and reports an already completed payment", async () => {
  mocks.retrievePaymentIntent.mockResolvedValueOnce({ paymentIntent: { id: "pi_test", status: "requires_payment_method" } })
    .mockResolvedValueOnce({ paymentIntent: { id: "pi_test", status: "succeeded" } });
  mocks.confirmPayment.mockResolvedValue({ error: { code: "payment_intent_unexpected_state", message: "A processing error occurred." } });
  render(<Checkout />);
  await submitReadyForm();
  expect(addToast).toHaveBeenCalledWith({ title: "stripe_already_paid", color: "danger" });
  expect(mocks.checkout).not.toHaveBeenCalled();
  expect(screen.getByRole("button", { name: "Pay Now" })).toBeDisabled();
});

it("shows a generic decline for the fraudulent test card and allows another method", async () => {
  mocks.confirmPayment.mockResolvedValue({ error: { code: "card_declined", decline_code: "fraudulent", message: "A processing error occurred." } });
  render(<Checkout />);
  await submitReadyForm();
  expect(addToast).toHaveBeenCalledWith({ title: "stripe_card_declined", color: "danger" });
  expect(screen.getByRole("button", { name: "Pay Now" })).toBeEnabled();
  expect(mocks.checkout).not.toHaveBeenCalled();
  expect(addToast).toHaveBeenCalledOnce();
  expect(screen.queryByRole("alert")).not.toBeInTheDocument();
});

it("does not confirm when the current payment status cannot be retrieved", async () => {
  mocks.retrievePaymentIntent.mockResolvedValue({ error: { message: "Network unavailable" } });
  render(<Checkout />);
  await submitReadyForm();
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
  expect(addToast).toHaveBeenCalledWith({ title: "stripe_status_unavailable", color: "danger" });
});

it("waits for Stripe readiness and complete details without starting payment", async () => {
  render(<Checkout />);
  await screen.findByText("Form ready");
  expect(screen.queryByRole("button", { name: "Pay Now" })).not.toBeInTheDocument();
  fireEvent.click(screen.getByText("Form ready"));
  const pay = screen.getByRole("button", { name: "Pay Now" });
  expect(pay).toBeDisabled();
  fireEvent.click(screen.getByText("Form ready"));
  expect(pay).toBeDisabled();
  fireEvent.click(screen.getByText("Complete details"));
  expect(pay).toBeEnabled();
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
  fireEvent.click(screen.getByText("Clear details"));
  expect(pay).toBeDisabled();
});

it("shows processing only on submission and prevents duplicate confirmation", async () => {
  let finish!: (value: unknown) => void;
  mocks.confirmPayment.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
  render(<Checkout />);
  await screen.findByText("Form ready");
  expect(screen.queryByRole("button", { name: "Pay Now" })).not.toBeInTheDocument();
  fireEvent.click(screen.getByText("Form ready"));
  const pay = screen.getByRole("button", { name: "Pay Now" });
  fireEvent.click(screen.getByText("Form ready"));
  fireEvent.click(screen.getByText("Complete details"));
  fireEvent.click(pay);
  expect(screen.getByRole("button", { name: "Processing..." })).toBeDisabled();
  fireEvent.submit(pay.closest("form")!);
  await waitFor(() => expect(mocks.confirmPayment).toHaveBeenCalledOnce());
  await act(async () => { finish({ error: { message: "Try another card" } }); });
  expect(screen.getByRole("button", { name: "Pay Now" })).toBeEnabled();
  expect(mocks.checkout).not.toHaveBeenCalled();
});

it("shows a load failure and keeps payment disabled", async () => {
  render(<Checkout />);
  await screen.findByText("Form ready");
  expect(screen.queryByRole("button", { name: "Pay Now" })).not.toBeInTheDocument();
  fireEvent.click(screen.getByText("Form ready"));
  const pay = screen.getByRole("button", { name: "Pay Now" });
  fireEvent.click(screen.getByText("Load error"));
  expect(addToast).toHaveBeenCalledWith({ title: "Form failed to load", color: "danger" });
  expect(pay).toBeDisabled();
});

it("clears stale modal loading when Stripe is selected without starting payment", async () => {
  render(<Checkout initiallyLoading />);
  await screen.findByText("Form ready");
  expect(screen.queryByRole("button", { name: "Pay Now" })).not.toBeInTheDocument();
  fireEvent.click(screen.getByText("Form ready"));
  const pay = screen.getByRole("button", { name: "Pay Now" });
  expect(screen.queryByRole("button", { name: "Processing..." })).not.toBeInTheDocument();
  await waitFor(() => expect(screen.getByTestId("modal-loading")).toHaveTextContent("false"));
  fireEvent.click(screen.getByText("Form ready"));
  fireEvent.click(screen.getByText("Complete details"));
  expect(pay).toBeEnabled();
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
});

it("recovers submission state when Stripe throws", async () => {
  mocks.confirmPayment.mockRejectedValue(new Error("Connection failed"));
  render(<Checkout />);
  await screen.findByText("Form ready");
  expect(screen.queryByRole("button", { name: "Pay Now" })).not.toBeInTheDocument();
  fireEvent.click(screen.getByText("Form ready"));
  const pay = screen.getByRole("button", { name: "Pay Now" });
  fireEvent.click(screen.getByText("Form ready"));
  fireEvent.click(screen.getByText("Complete details"));
  await act(async () => { fireEvent.click(pay); });
  await waitFor(() =>
    expect(screen.getByRole("button", { name: "Pay Now" })).toBeEnabled(),
  );
  expect(screen.getByTestId("modal-loading")).toHaveTextContent("false");
});

it("hides only the testing assistant when the Stripe dialog closes and restores it on reopen", async () => {
  // Exercise the actual stylesheet selector against Stripe's frame URL shape.
  const css = readFileSync("src/styles/index.css", "utf8");
  const selector = css.match(/body:not\(:has\(\[data-stripe-checkout-active\]\)\)[^{]+/)![0].trim();
  const assistant = document.createElement("iframe");
  assistant.src = "https://js.stripe.com/v3/elements-inner-easel-test.html";
  const payment = document.createElement("iframe");
  payment.src = "https://js.stripe.com/v3/elements-inner-payment-test.html";
  const authentication = document.createElement("iframe");
  authentication.src = "https://js.stripe.com/v3/3ds2-challenge-test.html";
  document.body.append(assistant, payment, authentication);
  const props = { onSuccess: vi.fn(), onError: vi.fn(), setIsLoading: vi.fn(), isLoading: false };
  try {
    const { rerender, unmount } = render(<Stripe {...props} isActive />);
    await screen.findByText("Form ready");
    expect(assistant.matches(selector)).toBe(false);
    rerender(<Stripe {...props} isActive={false} />);
    expect(assistant.matches(selector)).toBe(true);
    expect(payment.matches(selector)).toBe(false);
    expect(authentication.matches(selector)).toBe(false);
    rerender(<Stripe {...props} isActive />);
    expect(assistant.matches(selector)).toBe(false);
    unmount();
    expect(assistant.matches(selector)).toBe(true);
  } finally {
    assistant.remove();
    payment.remove();
    authentication.remove();
  }
});

it("keeps the loading placeholder through intent creation until Stripe fields are ready", async () => {
  let finish!: (value: unknown) => void;
  mocks.intent.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
  render(<StrictMode><Checkout /></StrictMode>);
  expect(screen.getByRole("status")).toHaveTextContent("stripe_form_loading");
  expect(mocks.intent).toHaveBeenCalledOnce();
  await act(async () => { finish({ success: true, data: { clientSecret: "delayed_secret" } }); });
  expect(screen.getByRole("status")).toHaveTextContent("stripe_form_loading");
  expect(screen.queryByRole("button", { name: "Pay Now" })).not.toBeInTheDocument();
  fireEvent.click(screen.getByText("Form ready"));
  expect(screen.queryByRole("status")).not.toBeInTheDocument();
  expect(mocks.intent).toHaveBeenCalledOnce();
});

it("offers retry after initialization fails and recovers without closing the modal", async () => {
  mocks.intent.mockResolvedValueOnce({ success: false, message: "Unable to initialize" })
    .mockResolvedValueOnce({ success: true, data: { clientSecret: "retried_secret" } });
  render(<Checkout />);
  expect(await screen.findByRole("alert")).toHaveTextContent("Unable to initialize");
  fireEvent.click(screen.getByRole("button", { name: "stripe_form_retry" }));
  await screen.findByText("Form ready");
  expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  expect(mocks.intent).toHaveBeenCalledTimes(2);
  expect(mocks.confirmPayment).not.toHaveBeenCalled();
});

it("ignores a late initialization response for an outdated amount", async () => {
  let finishOld!: (value: unknown) => void;
  mocks.intent.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve; }))
    .mockResolvedValueOnce({ success: true, data: { clientSecret: "current_amount_secret" } });
  const { rerender } = render(<Checkout />);
  mocks.amount = 20;
  rerender(<Checkout />);
  await screen.findByText("Form ready");
  expect(screen.getByTestId("stripe-elements")).toHaveAttribute("data-secret", "current_amount_secret");
  await act(async () => { finishOld({ success: true, data: { clientSecret: "outdated_secret" } }); });
  expect(screen.getByTestId("stripe-elements")).toHaveAttribute("data-secret", "current_amount_secret");
  expect(mocks.intent).toHaveBeenLastCalledWith({ amount: 20, currency: "usd" });
});
