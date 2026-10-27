import { useEffect, useRef, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import { useRouter } from "next/router";
import useSWR from "swr";
import { Button } from "@heroui/react";
import {
  CheckCircle2,
  Clock3,
  LoaderCircle,
  TriangleAlert,
  X,
} from "lucide-react";
import { RootState, type AppDispatch } from "@/lib/redux/store";
import { clearCart } from "@/lib/redux/slices/cartSlice";
import { checkPaymentTransactionStatus, getSpecificOrders } from "@/routes/api";
import type {
  Order,
  PaymentTransactionStatusResponse,
} from "@/types/ApiResponse";
import { trackPurchase } from "@/lib/analytics";
import {
  clearPaymentReturn,
  paymentReturnState,
  paymentTransactionReturnState,
  readPaymentReturn,
  type PaymentReturnAttempt,
} from "@/helpers/paymentReturn";

export default function PaymentReturnBanner({
  orderSlug,
}: {
  orderSlug?: string;
}) {
  const userId = useSelector((state: RootState) => state.auth.user?.id);
  const [attempt, setAttempt] = useState<PaymentReturnAttempt | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => {
      const saved = userId ? readPaymentReturn(String(userId)) : null;
      setAttempt(
        saved && (!orderSlug || saved.slug === orderSlug) ? saved : null,
      );
    }, 0);
    return () => clearTimeout(timer);
  }, [userId, orderSlug]);

  if (!attempt || attempt.userId !== String(userId)) return null;

  const dismiss = () => {
    clearPaymentReturn(attempt);
    setAttempt(null);
  };

  return attempt.transactionUuid ? (
    <TransactionPaymentReturnStatus
      key={`${attempt.transactionUuid}-${attempt.createdAt}`}
      attempt={attempt as PaymentReturnAttempt & { transactionUuid: string }}
      onDismiss={dismiss}
    />
  ) : attempt.orderId && attempt.slug ? (
    <LegacyOrderPaymentReturnStatus
      key={`${attempt.orderId}-${attempt.createdAt}`}
      attempt={
        attempt as PaymentReturnAttempt & { orderId: string; slug: string }
      }
      onDismiss={dismiss}
    />
  ) : null;
}

function TransactionPaymentReturnStatus({
  attempt,
  onDismiss,
}: {
  attempt: PaymentReturnAttempt & { transactionUuid: string };
  onDismiss: () => void;
}) {
  const { t } = useTranslation();
  const router = useRouter();
  const dispatch = useDispatch<AppDispatch>();
  const handled = useRef(false);
  const [delayed, setDelayed] = useState(false);
  const { data, error } = useSWR(
    `/api/payment-transactions/${attempt.transactionUuid}/status`,
    async (): Promise<{
      transaction: PaymentTransactionStatusResponse;
      verifiedOrder?: Order;
    }> => {
      const response = await checkPaymentTransactionStatus(
        attempt.transactionUuid,
      );
      if (!response.success || !response.data) {
        throw new Error("Payment status unavailable");
      }

      const transaction = response.data;
      if (paymentTransactionReturnState(transaction) !== "paid") {
        return { transaction };
      }

      const slug = transaction.order?.slug;
      if (!slug) throw new Error("Completed payment has no order");

      const orderResponse = await getSpecificOrders({ slug });
      if (
        !orderResponse.success ||
        !orderResponse.data ||
        orderResponse.data.slug !== slug
      ) {
        throw new Error("Created order is not available yet");
      }
      return { transaction, verifiedOrder: orderResponse.data };
    },
    {
      refreshInterval: (result) =>
        paymentTransactionReturnState(result?.transaction) === "pending"
          ? 4000
          : 0,
      revalidateOnMount: true,
      revalidateOnFocus: true,
      revalidateOnReconnect: true,
      errorRetryCount: 3,
      errorRetryInterval: 4000,
    },
  );
  const state = paymentTransactionReturnState(data?.transaction);

  useEffect(() => {
    const timer = setTimeout(() => setDelayed(true), 90000);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    const order = data?.verifiedOrder;
    if (!order || handled.current) return;
    handled.current = true;
    trackPurchase(
      String(order.id),
      Number(order.final_total) || 0,
      order.currency_code || "",
      order.promo_code || "",
      order.delivery_charge,
      order.items || [],
    );
    dispatch(clearCart());
    clearPaymentReturn(attempt);
    void router.replace(`/my-account/orders/${encodeURIComponent(order.slug)}`);
  }, [attempt, data?.verifiedOrder, dispatch, router]);

  useEffect(() => {
    if (state === "failed") clearPaymentReturn(attempt);
  }, [state, attempt]);

  return (
    <PaymentStatus
      state={state}
      waiting={delayed || !!error}
      error={!!error}
      label={t("paymentReturn.transaction")}
      onDismiss={onDismiss}
    />
  );
}

function LegacyOrderPaymentReturnStatus({
  attempt,
  onDismiss,
}: {
  attempt: PaymentReturnAttempt & { orderId: string; slug: string };
  onDismiss: () => void;
}) {
  const { t } = useTranslation();
  const [delayed, setDelayed] = useState(false);
  const { data, error } = useSWR(
    `/api/orders/detail/${attempt.slug}`,
    async () => {
      const response = await getSpecificOrders({ slug: attempt.slug });
      if (
        !response.success ||
        !response.data ||
        String(response.data.id) !== attempt.orderId
      ) {
        throw new Error("Payment status unavailable");
      }
      return response.data;
    },
    {
      refreshInterval: (order) =>
        paymentReturnState(order?.payment_status) === "pending" ? 10000 : 0,
      revalidateOnMount: true,
      revalidateOnFocus: true,
      revalidateOnReconnect: true,
      errorRetryCount: 3,
    },
  );
  const state = paymentReturnState(data?.payment_status);

  useEffect(() => {
    const timer = setTimeout(() => setDelayed(true), 90000);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    if (state !== "pending") clearPaymentReturn(attempt);
  }, [state, attempt]);

  return (
    <PaymentStatus
      state={state}
      waiting={delayed || !!error}
      error={!!error}
      label={t("paymentReturn.order", { id: attempt.orderId })}
      onDismiss={onDismiss}
    />
  );
}

function PaymentStatus({
  state,
  waiting,
  error,
  label,
  onDismiss,
}: {
  state: "paid" | "failed" | "pending";
  waiting: boolean;
  error: boolean;
  label: string;
  onDismiss: () => void;
}) {
  const { t } = useTranslation();
  const Icon =
    state === "paid"
      ? CheckCircle2
      : state === "failed"
        ? TriangleAlert
        : waiting
          ? Clock3
          : LoaderCircle;
  const tone =
    state === "paid"
      ? "border-success-200 bg-success-50 text-success-700"
      : state === "failed"
        ? "border-warning-200 bg-warning-50 text-warning-700"
        : "border-default-200 bg-default-50 text-foreground";
  const message =
    state === "paid"
      ? t("paymentReturn.paid")
      : state === "failed"
        ? t("paymentReturn.failed")
        : error
          ? t("paymentReturn.unavailable")
          : waiting
            ? t("paymentReturn.pending")
            : t("paymentReturn.checking");

  return (
    <div
      className={`mb-4 flex items-center gap-3 rounded-2xl border px-4 py-3 ${tone}`}
    >
      <Icon
        aria-hidden="true"
        size={18}
        className={`shrink-0 ${state === "pending" && !waiting ? "motion-safe:animate-spin" : ""}`}
      />
      <div
        role="status"
        aria-live="polite"
        aria-atomic="true"
        className="min-w-0 flex-1"
      >
        <p className="text-xs text-foreground/60">{label}</p>
        <p className="text-sm font-medium">{message}</p>
      </div>
      <Button
        isIconOnly
        size="sm"
        variant="light"
        radius="full"
        aria-label={t("paymentReturn.dismiss")}
        onPress={onDismiss}
      >
        <X size={16} aria-hidden="true" />
      </Button>
    </div>
  );
}
