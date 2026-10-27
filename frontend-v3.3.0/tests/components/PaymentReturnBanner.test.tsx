import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { SWRConfig } from "swr";
import type { ReactNode } from "react";
import PaymentReturnBanner from "@/components/PaymentGateway/PaymentReturnBanner";
import {
  readPaymentReturn,
  rememberPaymentReturn,
} from "@/helpers/paymentReturn";
import { clearCart } from "@/lib/redux/slices/cartSlice";

const mocks = vi.hoisted(() => ({
  getOrder: vi.fn(),
  getTransaction: vi.fn(),
  dispatch: vi.fn(),
  replace: vi.fn(),
  trackPurchase: vi.fn(),
}));
vi.mock("@/routes/api", () => ({
  getSpecificOrders: mocks.getOrder,
  checkPaymentTransactionStatus: mocks.getTransaction,
}));
vi.mock("react-redux", () => ({
  useSelector: () => 7,
  useDispatch: () => mocks.dispatch,
}));
vi.mock("next/router", () => ({
  useRouter: () => ({ replace: mocks.replace }),
}));
vi.mock("@/lib/analytics", () => ({ trackPurchase: mocks.trackPurchase }));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@heroui/react", () => ({
  Button: ({
    children,
    onPress,
    "aria-label": label,
  }: {
    children: ReactNode;
    onPress: () => void;
    "aria-label": string;
  }) => (
    <button onClick={onPress} aria-label={label}>
      {children}
    </button>
  ),
}));

function mount(slug?: string) {
  return render(
    <SWRConfig value={{ provider: () => new Map(), shouldRetryOnError: false }}>
      <PaymentReturnBanner orderSlug={slug} />
    </SWRConfig>,
  );
}
beforeEach(() => {
  sessionStorage.clear();
  vi.clearAllMocks();
});

describe("payment return banner", () => {
  it("does not fetch or render on ordinary visits", async () => {
    mount();
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
    expect(mocks.getOrder).not.toHaveBeenCalled();
  });
  it("uses the exact order's backend payment status and dismisses persistently", async () => {
    rememberPaymentReturn(42, "order-42", 7);
    mocks.getOrder.mockResolvedValue({
      success: true,
      data: { id: 42, payment_status: "pending" },
    });
    mount();
    expect(
      await screen.findByText("paymentReturn.checking"),
    ).toBeInTheDocument();
    await waitFor(() =>
      expect(mocks.getOrder).toHaveBeenCalledWith({ slug: "order-42" }),
    );
    fireEvent.click(
      screen.getByRole("button", { name: "paymentReturn.dismiss" }),
    );
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
    expect(readPaymentReturn("7")).toBeNull();
  });
  it("shows confirmed success and consumes the return context", async () => {
    rememberPaymentReturn(42, "order-42", 7);
    mocks.getOrder.mockResolvedValue({
      success: true,
      data: { id: 42, payment_status: "completed" },
    });
    mount();
    expect(await screen.findByText("paymentReturn.paid")).toBeInTheDocument();
    await waitFor(() => expect(readPaymentReturn("7")).toBeNull());
  });
  it("does not mistake an API error for a failed payment", async () => {
    rememberPaymentReturn(42, "order-42", 7);
    mocks.getOrder.mockResolvedValue({ success: false });
    mount();
    expect(
      await screen.findByText("paymentReturn.unavailable"),
    ).toBeInTheDocument();
    expect(screen.queryByText("paymentReturn.failed")).not.toBeInTheDocument();
    expect(readPaymentReturn("7")).not.toBeNull();
  });
  it("reports failure only when confirmed by the backend", async () => {
    rememberPaymentReturn(42, "order-42", 7);
    mocks.getOrder.mockResolvedValue({
      success: true,
      data: { id: 42, payment_status: "failed" },
    });
    mount();
    expect(await screen.findByText("paymentReturn.failed")).toBeInTheDocument();
  });
  it("rejects a response for a different order", async () => {
    rememberPaymentReturn(42, "order-42", 7);
    mocks.getOrder.mockResolvedValue({
      success: true,
      data: { id: 99, payment_status: "paid" },
    });
    mount();
    expect(
      await screen.findByText("paymentReturn.unavailable"),
    ).toBeInTheDocument();
    expect(screen.queryByText("paymentReturn.paid")).not.toBeInTheDocument();
  });
  it("does not show an attempt on a different order's page", async () => {
    rememberPaymentReturn(42, "order-42", 7);
    mount("order-43");
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
    expect(mocks.getOrder).not.toHaveBeenCalled();
  });

  it("verifies a completed transaction's order before navigating", async () => {
    rememberPaymentReturn("transaction-42", 7);
    mocks.getTransaction.mockResolvedValue({
      success: true,
      data: {
        transaction_uuid: "transaction-42",
        status: "order_created",
        payment_status: "completed",
        order: { slug: "order-42" },
      },
    });
    mocks.getOrder.mockResolvedValue({
      success: true,
      data: {
        id: 42,
        slug: "order-42",
        final_total: "120.00",
        currency_code: "COP",
        promo_code: null,
        delivery_charge: "10.00",
        items: [],
      },
    });

    mount();

    await waitFor(() =>
      expect(mocks.getTransaction).toHaveBeenCalledWith("transaction-42"),
    );
    await waitFor(() =>
      expect(mocks.getOrder).toHaveBeenCalledWith({ slug: "order-42" }),
    );
    expect(mocks.dispatch).toHaveBeenCalledWith(clearCart());
    expect(mocks.trackPurchase).toHaveBeenCalledOnce();
    expect(mocks.replace).toHaveBeenCalledWith("/my-account/orders/order-42");
    expect(readPaymentReturn("7")).toBeNull();
  });

  it("stops a failed transaction without resolving an order", async () => {
    rememberPaymentReturn("transaction-42", 7);
    mocks.getTransaction.mockResolvedValue({
      success: true,
      data: {
        transaction_uuid: "transaction-42",
        status: "failed",
        payment_status: "failed",
        order: null,
      },
    });

    mount();

    expect(
      await screen.findByText("paymentReturn.failed"),
    ).toBeInTheDocument();
    expect(mocks.getOrder).not.toHaveBeenCalled();
    expect(mocks.dispatch).not.toHaveBeenCalled();
    expect(mocks.replace).not.toHaveBeenCalled();
    await waitFor(() => expect(readPaymentReturn("7")).toBeNull());
  });
});
