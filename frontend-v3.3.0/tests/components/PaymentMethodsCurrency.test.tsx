import { render, screen } from "@testing-library/react";
import type { PropsWithChildren } from "react";
import { expect, it, vi } from "vitest";
import PaymentMethods from "@/components/PaymentMethods";

vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    currencySymbol: "₹",
    paymentSettings: {
      razorpayPayment: true,
      stripePayment: true,
      paystackPayment: false,
    },
  }),
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("react-redux", () => ({
  useSelector: (selector: (state: unknown) => unknown) =>
    selector({ cart: { cartData: null } }),
}));
vi.mock("@heroui/react", () => ({
  Radio: ({ children }: PropsWithChildren) => <div>{children}</div>,
  RadioGroup: ({ children }: PropsWithChildren) => <div>{children}</div>,
  ScrollShadow: ({ children }: PropsWithChildren) => <div>{children}</div>,
  Image: () => null,
}));

it("allows Razorpay for AED when the backend permits it and hides unavailable gateways", () => {
  render(
    <PaymentMethods
      selectedPayment=""
      setSelectedPayment={vi.fn()}
      hideCOD
      isLoading={false}
      currencyContext={{
        currency_code: "AED",
        currency_codes: ["AED"],
        available_payment_methods: [
          { key: "razorpay", is_enabled: true, is_available: true },
          { key: "stripe", is_enabled: true, is_available: false },
          { key: "paystack", is_enabled: true, is_available: true },
        ],
      }}
    />,
  );
  expect(screen.getByText("payments.razorpay.name")).toBeInTheDocument();
  expect(screen.queryByText("payments.stripe.name")).not.toBeInTheDocument();
  expect(screen.getByText("payments.paystack.name")).toBeInTheDocument();
});

it("does not fall back to globally enabled gateways when the contextual list is empty", () => {
  render(
    <PaymentMethods
      selectedPayment=""
      setSelectedPayment={vi.fn()}
      hideCOD
      isLoading={false}
      currencyContext={{ currency_code: "AED", currency_codes: ["AED"], available_payment_methods: [] }}
    />,
  );
  expect(screen.queryByText("payments.razorpay.name")).not.toBeInTheDocument();
  expect(screen.queryByText("payments.stripe.name")).not.toBeInTheDocument();
  expect(screen.getByText("payments.noMethodsAvailable")).toBeInTheDocument();
});

it("falls back to the original settings when the cart omits its payment list", () => {
  render(<PaymentMethods selectedPayment="" setSelectedPayment={vi.fn()} hideCOD={false} isLoading={false} currencyContext={{}} />);
  expect(screen.getByText("payments.stripe.name")).toBeInTheDocument();
  expect(screen.getByText("payments.razorpay.name")).toBeInTheDocument();
  expect(screen.queryByText("payments.paystack.name")).not.toBeInTheDocument();
});

it("uses wallet eligibility separately from the cart contract", () => {
  render(<PaymentMethods usageType="wallet" selectedPayment="" setSelectedPayment={vi.fn()} hideCOD isLoading={false} currencyContext={{currency_code: "AED", available_payment_methods: [{key: "paystackPayment", is_enabled: true, is_available: true}]}} />);
  expect(screen.getByText("payments.paystack.name")).toBeInTheDocument();
  expect(screen.queryByText("payments.razorpay.name")).not.toBeInTheDocument();
});
