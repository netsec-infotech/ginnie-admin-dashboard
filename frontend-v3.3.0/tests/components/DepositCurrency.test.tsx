import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import type { ComponentProps, PropsWithChildren } from "react";
import { beforeEach, expect, it, vi } from "vitest";
import DepositModal from "@/components/Modals/DepositModal";

const mocks = vi.hoisted(() => ({
  prepare: vi.fn(),
  wallet: {
    currency_code: "AED",
    balance: 200,
    available_payment_methods: [
      { key: "razorpay", is_enabled: true, is_available: true },
    ],
  },
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("react-redux", () => ({
  useSelector: () => ({ wallet: mocks.wallet }),
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ currencySymbol: "₹" }),
}));
vi.mock("@/routes/api", () => ({ prepareWalletRecharge: mocks.prepare }));
vi.mock("@/helpers/functionalHelpers", () => ({
  updateUserDataInRedux: vi.fn(),
}));
vi.mock("@/components/PaymentMethods", () => ({
  default: ({
    setSelectedPayment,
  }: {
    setSelectedPayment: (method: string) => void;
  }) => (
    <button type="button" onClick={() => setSelectedPayment("razorpayPayment")}>
      Choose Razorpay
    </button>
  ),
}));
vi.mock("@/components/PaymentGateway/RazorPay", () => ({
  default: () => null,
}));
vi.mock("@/components/PaymentGateway/Stripe", () => ({ default: () => null }));
vi.mock("@/components/PaymentGateway/Paystack", () => ({
  default: () => null,
}));
vi.mock("@heroui/react", () => {
  const Box = ({ children }: PropsWithChildren) => <div>{children}</div>;
  return {
    Modal: Box,
    ModalContent: Box,
    ModalHeader: Box,
    ModalBody: Box,
    ModalFooter: Box,
    Form: ({ children, onSubmit, id }: ComponentProps<"form">) => (
      <form id={id} onSubmit={onSubmit}>
        {children}
      </form>
    ),
    Input: ({
      label,
      value,
      onChange,
      name,
      inputMode,
      startContent,
    }: ComponentProps<"input"> & {
      label: string;
      startContent: React.ReactNode;
    }) => (
      <label>
        {label}
        {startContent}
        <input
          name={name}
          value={value}
          onChange={onChange}
          inputMode={inputMode}
        />
      </label>
    ),
    Textarea: ({ label, name }: { label: string; name: string }) => (
      <label>
        {label}
        <textarea name={name} />
      </label>
    ),
    Button: ({
      children,
      type,
      form,
      isDisabled,
    }: PropsWithChildren<{
      type?: "button" | "submit";
      form?: string;
      isDisabled?: boolean;
    }>) => (
      <button type={type || "button"} form={form} disabled={isDisabled}>
        {children}
      </button>
    ),
    addToast: vi.fn(),
    useDisclosure: () => ({
      isOpen: true,
      onOpen: vi.fn(),
      onOpenChange: vi.fn(),
    }),
  };
});

beforeEach(() => {
  mocks.prepare
    .mockReset()
    .mockResolvedValue({ success: false, message: "Backend rejected" });
  mocks.wallet.available_payment_methods = [
    { key: "razorpay", is_enabled: true, is_available: true },
  ];
});

const enterDeposit = () => {
  render(<DepositModal />);
  const amount = screen.getByLabelText(/deposit.input.amount.label/);
  fireEvent.change(amount, { target: { value: "25.50" } });
  fireEvent.change(screen.getByLabelText("deposit.input.description.label"), {
    target: { value: "Recharge wallet" },
  });
  fireEvent.click(screen.getByText("Choose Razorpay"));
  return amount;
};

it("keeps decimal deposit amounts and displays the wallet currency", async () => {
  const amount = enterDeposit();
  expect(amount).toHaveValue("25.50");
  expect(screen.getByText("AED")).toBeInTheDocument();
  fireEvent.submit(amount.closest("form")!);
  await waitFor(() =>
    expect(mocks.prepare).toHaveBeenCalledWith(
      expect.objectContaining({
        amount: 25.5,
        payment_method: "razorpayPayment",
      }),
    ),
  );
});

it("checks fresh wallet availability again before starting a deposit", async () => {
  const amount = enterDeposit();
  mocks.wallet.available_payment_methods = [];
  fireEvent.submit(amount.closest("form")!);
  await waitFor(() =>
    expect(screen.getByText("deposit.submitButton")).toBeEnabled(),
  );
  expect(mocks.prepare).not.toHaveBeenCalled();
});
