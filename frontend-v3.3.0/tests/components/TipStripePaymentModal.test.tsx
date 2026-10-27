import type { ReactNode } from "react";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";

import TipStripePaymentModal from "@/components/Modals/TipStripePaymentModal";

const mocks = vi.hoisted(() => ({
  confirmPayment: vi.fn(),
  createIntent: vi.fn(),
  toast: vi.fn(),
}));

vi.mock("@stripe/react-stripe-js", () => ({
  Elements: ({ children }: { children: ReactNode }) => <>{children}</>,
  PaymentElement: () => <div>Tip card details</div>,
  useElements: () => ({}),
  useStripe: () => ({ confirmPayment: mocks.confirmPayment }),
}));
vi.mock("@/lib/stripe", () => ({
  getStripeClient: () => Promise.resolve({}),
}));
vi.mock("@/routes/api", () => ({
  createStripeIntent: mocks.createIntent,
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    currencySymbol: "$",
    paymentSettings: {
      stripePublishableKey: "pk_test",
      stripeCurrencyCode: "usd",
    },
  }),
}));
vi.mock("@/helpers/getters", () => ({
  getUserDataFromRedux: () => ({ email: "customer@example.com" }),
}));
vi.mock("@/helpers/functionalHelpers", () => ({
  formatAmount: (value: number) => value.toFixed(2),
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@heroui/react", () => ({
  addToast: mocks.toast,
  Button: ({
    children,
    type,
    isDisabled,
  }: {
    children: ReactNode;
    type?: "button" | "submit";
    isDisabled?: boolean;
  }) => (
    <button type={type} disabled={isDisabled}>
      {children}
    </button>
  ),
  Modal: ({ children }: { children: ReactNode }) => <>{children}</>,
  ModalBody: ({ children }: { children: ReactNode }) => <>{children}</>,
  ModalContent: ({ children }: { children: ReactNode }) => <>{children}</>,
  ModalHeader: ({ children }: { children: ReactNode }) => <>{children}</>,
  Spinner: () => <span>Loading</span>,
}));

beforeEach(() => {
  vi.clearAllMocks();
  mocks.createIntent.mockResolvedValue({
    success: true,
    data: { clientSecret: "tip_secret" },
  });
});

it("does not record a Stripe tip while its payment is still processing", async () => {
  mocks.confirmPayment.mockResolvedValue({
    paymentIntent: { id: "pi_tip", status: "processing" },
  });
  const onPaid = vi.fn();

  render(
    <TipStripePaymentModal
      isOpen
      amount={10}
      onPaid={onPaid}
      onCancel={vi.fn()}
    />,
  );

  fireEvent.click(await screen.findByRole("button", { name: "tip.payNow" }));

  await waitFor(() => expect(mocks.confirmPayment).toHaveBeenCalledOnce());
  expect(onPaid).not.toHaveBeenCalled();
  expect(mocks.toast).toHaveBeenCalledWith({
    title: "tip.paymentPending",
    color: "warning",
  });
});

it("confirms and records a succeeded Stripe tip only once", async () => {
  mocks.confirmPayment.mockResolvedValue({
    paymentIntent: { id: "pi_tip", status: "succeeded" },
  });
  const onPaid = vi.fn().mockResolvedValue(undefined);

  render(
    <TipStripePaymentModal
      isOpen
      amount={10}
      onPaid={onPaid}
      onCancel={vi.fn()}
    />,
  );

  const pay = await screen.findByRole("button", { name: "tip.payNow" });
  fireEvent.click(pay);
  fireEvent.click(pay);

  await waitFor(() => expect(onPaid).toHaveBeenCalledExactlyOnceWith("pi_tip"));
  expect(mocks.confirmPayment).toHaveBeenCalledOnce();
  expect(pay).toBeDisabled();
});
