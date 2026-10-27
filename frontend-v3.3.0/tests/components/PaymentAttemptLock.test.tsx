import { useEffect, useState, type ReactNode } from "react";
import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import en from "@/../public/locales/en.json";
import hi from "@/../public/locales/hi.json";
import ar from "@/../public/locales/ar.json";

const mocks = vi.hoisted(() => ({
  checkout: vi.fn(),
  confirmWalletRecharge: vi.fn(),
  paystackOptions: [] as Array<{
    onSuccess: (response: { reference: string; status: string }) => void;
    onCancel: () => void;
  }>,
  paystackOrder: vi.fn(),
  razorOptions: [] as Array<{
    handler: (response: {
      razorpay_order_id: string;
      razorpay_payment_id: string;
      razorpay_signature: string;
    }) => Promise<void>;
  }>,
  razorOrder: vi.fn(),
  success: vi.fn(),
  toast: vi.fn(),
  available: true,
  cartReady: true,
  hasMethods: true,
  settings: {razorpayKeyId: "test-key", razorpayPayment: false, paystackPayment: false},
}));

vi.mock("@heroui/react", () => ({
  addToast: mocks.toast,
  Button: ({
    children,
    className,
    isDisabled,
    isLoading,
    onPress,
  }: {
    children: ReactNode;
    className?: string;
    isDisabled?: boolean;
    isLoading?: boolean;
    onPress?: () => void;
  }) => (
    <button className={className} disabled={isDisabled || isLoading} onClick={onPress}>
      {children}
    </button>
  ),
}));
vi.mock("next/script", () => ({
  default: function ScriptMock({ onLoad }: { onLoad?: () => void }) {
    useEffect(() => onLoad?.(), [onLoad]);
    return null;
  },
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ paymentSettings: mocks.settings }),
}));
vi.mock("@/helpers/getters", () => ({
  getCartDataFromRedux: () => ({
    id: 10,
    uuid: "cart-10",
    payment_summary: { payable_amount: 10 },
  }),
  getCheckoutCartFromRedux: () => mocks.cartReady ? ({ id: 10, uuid: "cart-10", available_payment_methods: mocks.hasMethods ? ["razorpayPayment", "paystackPayment"].map(key => ({ key, is_enabled: true, is_available: mocks.available })) : undefined, payment_summary: { payable_amount: 10 } }) : null,
  getUserDataFromRedux: () => ({
    id: 1,
    name: "Customer",
    email: "customer@example.invalid",
  }),
}));
vi.mock("@/helpers/functionalHelpers", () => ({
  handleCheckout: mocks.checkout,
}));
vi.mock("@/routes/api", () => ({
  confirmWalletRecharge: mocks.confirmWalletRecharge,
  createRazorPayOrder: mocks.razorOrder,
  paystackCreateOrder: mocks.paystackOrder,
}));
vi.mock("react-redux", () => ({ useDispatch: () => vi.fn() }));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string) =>
      ({
        "paymentAttempt.inProgressTitle": "Payment already in progress",
        "paymentAttempt.inProgressDescription":
          "Another tab is already paying for this cart. Do not pay again.",
        "paymentAttempt.goToTab": "Go to payment tab",
      })[key] || key,
  }),
}));

import PayStack from "@/components/PaymentGateway/Paystack";
import RazorPay from "@/components/PaymentGateway/RazorPay";

it("provides payment-attempt messages in every supported language", () => {
  const keys = [
    "inProgressTitle",
    "inProgressDescription",
    "goToTab",
    "switchFailedTitle",
    "switchFailedDescription",
  ] as const;

  for (const locale of [en, hi, ar]) {
    for (const key of keys) {
      expect(locale.paymentAttempt[key]).toBeTruthy();
    }
  }
  expect(hi.paymentAttempt.goToTab).not.toBe(en.paymentAttempt.goToTab);
  expect(ar.paymentAttempt.goToTab).not.toBe(en.paymentAttempt.goToTab);
});

function Gateway({ kind, wallet = false }: { kind: "razorpay" | "paystack"; wallet?: boolean }) {
  const [isLoading, setIsLoading] = useState(false);
  const props = {
    isLoading,
    setIsLoading,
    onSuccess: mocks.success,
    onError: vi.fn(),
    usageType: wallet ? ("wallet" as const) : ("order" as const),
    walletOrderData: wallet
      ? {
          transaction: { id: 7, amount: 10 },
          payment_response: {
            id: "wallet-order",
            amount: 1000,
            currency: "INR",
            receipt: "wallet-receipt",
            access_code: "wallet-code",
          },
        }
      : undefined,
  };
  return kind === "razorpay" ? <RazorPay {...props} /> : <PayStack {...props} />;
}

beforeEach(() => {
  vi.clearAllMocks();
  localStorage.clear();
  window.name = "";
  mocks.razorOptions.length = 0;
  mocks.available = true;
  mocks.cartReady = true;
  mocks.hasMethods = true;
  mocks.settings.razorpayPayment = false;
  mocks.settings.paystackPayment = false;
  mocks.paystackOptions.length = 0;
  mocks.razorOrder.mockResolvedValue({
    success: true,
    data: { id: "provider-order", amount: 10, currency: "INR", receipt: "r" },
  });
  mocks.paystackOrder.mockResolvedValue({
    success: true,
    data: {
      transaction: { id: 1 },
      payment_response: { access_code: "code", reference: "reference" },
    },
  });
  mocks.checkout.mockResolvedValue({ success: false, message: "Order confirmation failed" });
  mocks.confirmWalletRecharge.mockResolvedValue({ success: true, data: {} });

  const script = document.createElement("script");
  script.id = "razorpay-sdk";
  document.body.appendChild(script);
  Object.defineProperty(window, "Razorpay", {
    configurable: true,
    value: class {
      constructor(options: (typeof mocks.razorOptions)[number]) {
        mocks.razorOptions.push(options);
      }
      open() {}
      on() {}
    },
  });
  Object.defineProperty(window, "PaystackPop", {
    configurable: true,
    value: class {
      resumeTransaction(
        _code: string,
        options: (typeof mocks.paystackOptions)[number],
      ) {
        mocks.paystackOptions.push(options);
      }
    },
  });
});

afterEach(() => document.getElementById("razorpay-sdk")?.remove());

describe.each([
  ["razorpay", "Pay with Razorpay"],
  ["paystack", "Pay with PayStack"],
] as const)("%s attempt locking", (kind, label) => {
  it("initializes from settings when the cart omits its payment list", async () => {
    mocks.hasMethods = false;
    mocks.settings.razorpayPayment = true;
    mocks.settings.paystackPayment = true;
    render(<Gateway kind={kind} />);
    const button = await screen.findByRole("button", { name: label });
    await act(async () => fireEvent.click(button));
    await waitFor(() => expect(kind === "razorpay" ? mocks.razorOptions : mocks.paystackOptions).toHaveLength(1));
    expect(kind === "razorpay" ? mocks.razorOrder : mocks.paystackOrder).toHaveBeenCalledOnce();
  });

  it("cannot initialize a gateway that the cart disallows", async () => {
    mocks.available = false;
    render(<Gateway kind={kind} />);
    const button = await screen.findByRole("button", { name: label });
    await act(async () => fireEvent.click(button));
    expect(kind === "razorpay" ? mocks.razorOrder : mocks.paystackOrder).not.toHaveBeenCalled();
    expect(kind === "razorpay" ? mocks.razorOptions : mocks.paystackOptions).toHaveLength(0);
    expect(mocks.toast).toHaveBeenCalledWith(expect.objectContaining({ title: "multiCurrency.paymentUnavailable" }));
  });

  it("cannot open a provider after availability changes during initialization", async () => {
    const orderMock = kind === "razorpay" ? mocks.razorOrder : mocks.paystackOrder;
    const response = await orderMock();
    orderMock.mockClear();
    orderMock.mockImplementation(async () => {
      mocks.available = false;
      return response;
    });
    render(<Gateway kind={kind} />);
    const button = await screen.findByRole("button", { name: label });
    await act(async () => fireEvent.click(button));
    await waitFor(() => expect(orderMock).toHaveBeenCalledOnce());
    await waitFor(() => expect(mocks.toast).toHaveBeenCalledWith(expect.objectContaining({ title: "payments.cartRefreshRequired" })));
    expect(kind === "razorpay" ? mocks.razorOptions : mocks.paystackOptions).toHaveLength(0);
  });

  it("allows only one tab to start payment for the same cart", async () => {
    render(
      <>
        <Gateway kind={kind} />
        <Gateway kind={kind} />
      </>,
    );
    const buttons = await screen.findAllByRole("button", { name: label });

    act(() => {
      fireEvent.click(buttons[0]);
      fireEvent.click(buttons[1]);
    });

    await waitFor(() =>
      expect(
        kind === "razorpay" ? mocks.razorOrder : mocks.paystackOrder,
      ).toHaveBeenCalledOnce(),
    );
    expect(
      kind === "razorpay" ? mocks.razorOptions : mocks.paystackOptions,
    ).toHaveLength(1);
    expect(mocks.toast).toHaveBeenCalledWith(
      expect.objectContaining({ title: "Payment already in progress" }),
    );
    const warning = mocks.toast.mock.calls.find(
      ([options]) => options.title === "Payment already in progress",
    )?.[0] as { description?: ReactNode; endContent?: ReactNode } | undefined;
    expect(warning?.endContent).toBeUndefined();
    render(<>{warning?.description}</>);
    expect(screen.getByText("Another tab is already paying for this cart. Do not pay again.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Go to payment tab" })).toHaveClass("w-full");
  });

  it("does not open a second provider window while an attempt is active", async () => {
    render(<Gateway kind={kind} />);
    const button = await screen.findByRole("button", { name: label });

    await act(async () => fireEvent.click(button));
    await waitFor(() => expect(button).toBeDisabled());
    await waitFor(() =>
      expect(
        kind === "razorpay" ? mocks.razorOptions : mocks.paystackOptions,
      ).toHaveLength(1),
    );
    fireEvent.click(button);

    expect(kind === "razorpay" ? mocks.razorOrder : mocks.paystackOrder).toHaveBeenCalledOnce();
    expect(kind === "razorpay" ? mocks.razorOptions : mocks.paystackOptions).toHaveLength(1);
  });

  it("stays locked when payment succeeds but order confirmation fails", async () => {
    render(<Gateway kind={kind} />);
    const button = await screen.findByRole("button", { name: label });
    await act(async () => fireEvent.click(button));
    await waitFor(() =>
      expect(
        kind === "razorpay" ? mocks.razorOptions : mocks.paystackOptions,
      ).toHaveLength(1),
    );

    await act(async () => {
      if (kind === "razorpay") {
        await mocks.razorOptions[0].handler({
          razorpay_order_id: "provider-order",
          razorpay_payment_id: "paid-1",
          razorpay_signature: "signature",
        });
      } else {
        mocks.paystackOptions[0].onSuccess({ reference: "paid-1", status: "success" });
      }
    });
    await waitFor(() => expect(mocks.checkout).toHaveBeenCalledOnce());

    expect(button).toBeDisabled();
    expect(mocks.toast).toHaveBeenCalledWith(
      expect.objectContaining({ color: "warning" }),
    );
  });
});

it("restores Paystack after another tab's Razorpay attempt blocks it", async () => {
  render(
    <>
      <Gateway kind="razorpay" />
      <Gateway kind="paystack" />
    </>,
  );
  const razorpay = await screen.findByRole("button", {
    name: "Pay with Razorpay",
  });
  const paystack = await screen.findByRole("button", {
    name: "Pay with PayStack",
  });

  fireEvent.click(razorpay);
  await waitFor(() => expect(mocks.razorOptions).toHaveLength(1));

  fireEvent.click(paystack);
  await waitFor(() =>
    expect(mocks.toast).toHaveBeenCalledWith(
      expect.objectContaining({ title: "Payment already in progress" }),
    ),
  );

  expect(mocks.paystackOrder).not.toHaveBeenCalled();
  await waitFor(() => expect(paystack).toBeEnabled());
});

it("confirms a Razorpay wallet payment with the backend before success", async () => {
  render(<Gateway kind="razorpay" wallet />);
  const button = await screen.findByRole("button", { name: "Pay with Razorpay" });
  await act(async () => fireEvent.click(button));
  await waitFor(() => expect(mocks.razorOptions).toHaveLength(1));
  await act(async () => {
    await mocks.razorOptions[0].handler({
      razorpay_order_id: "wallet-order",
      razorpay_payment_id: "wallet-payment",
      razorpay_signature: "wallet-signature",
    });
  });

  expect(mocks.confirmWalletRecharge).toHaveBeenCalledWith({
    razorpay_order_id: "wallet-order",
    razorpay_payment_id: "wallet-payment",
    razorpay_signature: "wallet-signature",
  });
  expect(mocks.success).toHaveBeenCalledOnce();
});
