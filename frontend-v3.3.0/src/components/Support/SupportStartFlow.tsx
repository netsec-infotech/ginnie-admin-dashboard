import { useState } from "react";
import { Button } from "@heroui/react";
import {
  ArrowLeft,
  ChevronRight,
  Info,
  MessageCircle,
  Package,
  Store,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { supportTopicsFor } from "@/helpers/support";
import { useSupportOrders } from "@/hooks/useSupportOrders";
import type { SupportOrder, SupportTopic } from "@/types/support";
import SupportComposer, { type SupportComposerProps } from "./SupportComposer";

export default function SupportStartFlow({
  orders,
  topics,
  composer,
  onStart,
  onRetry,
}: {
  orders: SupportOrder[];
  topics: SupportTopic[];
  composer: Omit<SupportComposerProps, "onSubmit">;
  onStart: (topicId: number, orderId?: number) => void;
  onRetry: () => void;
}) {
  const { t, i18n } = useTranslation();
  const [step, setStep] = useState<"order" | "topic" | "describe">("order");
  const [orderId, setOrderId] = useState<number | null>(null);
  const [topicId, setTopicId] = useState<number | null>(null);
  const older = useSupportOrders();
  const displayedOrders = [
    ...new Map(
      [...orders, ...older.orders].map((order) => [order.id, order]),
    ).values(),
  ].sort(
    (a, b) =>
      (Date.parse(b.created_at) || 0) - (Date.parse(a.created_at) || 0) ||
      b.id - a.id,
  );
  const choices = supportTopicsFor(topics, orderId);
  const topic = choices.find((item) => item.id === topicId);
  const selectedOrder = displayedOrders.find((item) => item.id === orderId);
  const selectOrder = (id: number | null) => {
    setOrderId(id);
    setTopicId(null);
    setStep("topic");
  };
  const orderDate = (value: string) => {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? ""
      : date.toLocaleDateString(i18n.language, {
          year: "numeric",
          month: "short",
          day: "numeric",
        });
  };

  return (
    <section
      className="mx-auto w-full max-w-4xl py-6 sm:py-8"
      aria-label={t("supportChat.startChat")}
    >
      <div className="mb-4 flex items-center gap-2">
        {step !== "order" && (
          <Button
            className="text-xs"
            variant="light"
            size="sm"
            isIconOnly
            aria-label={t("supportChat.back")}
            isDisabled={composer.busy}
            onPress={() => setStep(step === "describe" ? "topic" : "order")}
          >
            <ArrowLeft size={18} className="rtl:rotate-180" />
          </Button>
        )}
        <h2 className="text-lg font-semibold sm:text-xl">
          {t(
            `supportChat.${step === "order" ? "selectOrder" : step === "topic" ? "selectTopic" : "describeIssue"}`,
          )}
        </h2>
      </div>
      {step !== "order" && (
        <div className="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-primary/15 bg-primary/5 px-4 py-3 text-sm">
          {selectedOrder ? (
            <Package size={19} className="text-primary" />
          ) : (
            <MessageCircle size={19} className="text-primary" />
          )}
          <span className="font-semibold">
            {selectedOrder?.number || t("supportChat.generalHelp")}
          </span>
          {selectedOrder && (
            <span className="text-xs text-default-500">
              {orderDate(selectedOrder.created_at)} ·{" "}
              {selectedOrder.currency_code} {selectedOrder.total}
            </span>
          )}
          {step === "describe" && topic && (
            <span className="basis-full text-xs text-default-600">
              {topic.title}
            </span>
          )}
        </div>
      )}
      {step === "order" && (
        <div className="flex flex-col gap-3">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {displayedOrders.map((order, index) => (
              <button
                key={order.id}
                type="button"
                disabled={composer.busy}
                onClick={() => selectOrder(order.id)}
                className={`group flex w-full flex-col gap-4 rounded-2xl border p-4 text-start transition-colors hover:border-primary hover:bg-primary/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50 sm:p-5 ${index === 0 ? "border-primary/40 bg-primary/5" : "border-divider bg-content1"}`}
              >
                <span className="flex w-full items-center justify-between gap-2">
                  <span
                    className={`rounded-xl p-2.5 ${index === 0 ? "bg-primary/10 text-primary" : "bg-default-100 text-default-500"}`}
                  >
                    <Package size={21} strokeWidth={1.5} />
                  </span>
                  {index === 0 && (
                    <span className="text-[11px] font-semibold text-primary">
                      {t("supportChat.latestOrder")}
                    </span>
                  )}
                  <ChevronRight
                    size={17}
                    className="ms-auto shrink-0 text-default-400 group-hover:text-primary rtl:rotate-180"
                  />
                </span>
                <span className="w-full min-w-0">
                  <span className="flex flex-wrap items-center justify-between gap-2">
                    <strong className="text-base">{order.number}</strong>
                    <span
                      className={`rounded-full px-2 py-1 text-[10px] font-medium ${order.status === "delivered" ? "bg-success/10 text-success-700 dark:text-success-400" : order.status === "cancelled" ? "bg-default-100 text-default-500" : "bg-warning/10 text-warning-700 dark:text-warning-400"}`}
                    >
                      {order.status.replaceAll("_", " ")}
                    </span>
                  </span>
                  {order.store_names && order.store_names.length > 0 && (
                    <span className="mt-3 flex items-center gap-1.5 text-xs text-default-500">
                      <Store size={13} className="shrink-0" />
                      <span className="truncate">
                        {order.store_names.join(", ")}
                      </span>
                    </span>
                  )}
                  {order.items && order.items.length > 0 && (
                    <span className="mt-2 block">
                      <span className="line-clamp-2 text-xs leading-5 text-default-600">
                        {order.items
                          .map(
                            (item) =>
                              `${item.quantity} × ${item.title}${item.variant_title ? ` (${item.variant_title})` : ""}`,
                          )
                          .join(", ")}
                      </span>
                      <span className="mt-1 block text-[11px] text-default-500">
                        {t("itemsCount", {
                          count: order.items.reduce(
                            (count, item) => count + Number(item.quantity),
                            0,
                          ),
                        })}
                      </span>
                    </span>
                  )}
                  {(order.payment_method || order.payment_status) && (
                    <span className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-default-500">
                      {order.payment_method && (
                        <span>
                          {t("paymentMethod")}:{" "}
                          <span className="capitalize">
                            {order.payment_method.replaceAll("_", " ")}
                          </span>
                        </span>
                      )}
                      {order.payment_status && (
                        <span>
                          {t("paymentStatus")}:{" "}
                          <span className="capitalize">
                            {order.payment_status.replaceAll("_", " ")}
                          </span>
                        </span>
                      )}
                    </span>
                  )}
                  <span className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-divider/60 pt-3 text-xs text-default-500">
                    <span>{orderDate(order.created_at)}</span>
                    <span className="font-medium text-foreground">
                      {order.currency_code} {order.total}
                    </span>
                  </span>
                </span>
              </button>
            ))}
          </div>
          {displayedOrders.length === 0 && (
            <p className="rounded-2xl border border-dashed border-divider p-6 text-center text-sm text-default-500">
              {t("supportChat.noOrders")}
            </p>
          )}
          {older.error && (
            <p role="alert" className="text-xs text-danger">
              {t("supportChat.ordersError")}
            </p>
          )}
          {(older.hasMore || older.error) && (
            <Button
              className="text-xs"
              variant="light"
              size="sm"
              isLoading={older.loading}
              onPress={() => void older.load()}
            >
              {t(older.error ? "supportChat.retry" : "supportChat.olderOrders")}
            </Button>
          )}
          <Button
            variant="light"
            startContent={
              <MessageCircle size={18} className="shrink-0 text-primary" />
            }
            endContent={
              <ChevronRight size={16} className="shrink-0 rtl:rotate-180" />
            }
            className="mt-2 h-auto min-h-14 justify-between gap-3 whitespace-normal rounded-xl border border-dashed border-divider px-4 py-3 text-start text-sm"
            isDisabled={composer.busy}
            onPress={() => selectOrder(null)}
          >
            {t("supportChat.generalHelp")}
          </Button>
        </div>
      )}
      {step === "topic" && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {choices.map((item) => (
            <button
              key={item.id}
              type="button"
              aria-label={item.title}
              disabled={composer.busy}
              onClick={() => {
                setTopicId(item.id);
                setStep("describe");
              }}
              className="group rounded-2xl border border-divider bg-content1 p-5 text-start transition-colors hover:border-primary hover:bg-primary/5 focus-visible:outline-primary disabled:opacity-50"
            >
              <span className="flex items-center justify-between gap-3 text-sm font-semibold">
                {item.title}
                <ChevronRight
                  size={16}
                  className="shrink-0 text-default-400 group-hover:text-primary rtl:rotate-180"
                />
              </span>
              {item.guidance && (
                <span className="mt-1 block text-xs leading-5 text-default-500">
                  {item.guidance}
                </span>
              )}
            </button>
          ))}
          {choices.length === 0 && (
            <div className="sm:col-span-2 text-center text-sm text-default-500">
              <p>{t("supportChat.noTopics")}</p>
              <Button
                className="text-xs"
                size="sm"
                variant="light"
                onPress={onRetry}
              >
                {t("supportChat.retry")}
              </Button>
            </div>
          )}
        </div>
      )}
      {step === "describe" && topic && (
        <div className="space-y-5">
          {topic.guidance && (
            <p className="flex items-start gap-2 text-sm leading-6 text-default-600">
              <Info size={17} className="mt-1 shrink-0 text-primary" />
              {topic.guidance}
            </p>
          )}
          {topic.quick_replies.length > 0 && (
            <div className="space-y-2">
              <p className="text-xs font-semibold text-default-500">
                {t("supportChat.quickReplies", {
                  defaultValue: "Choose a starting point (optional)",
                })}
              </p>
              <div className="flex flex-wrap gap-2">
                {topic.quick_replies.map((reply, index) => (
                  <Button
                    key={`${reply}-${index}`}
                    size="sm"
                    variant={composer.message === reply ? "flat" : "bordered"}
                    color={composer.message === reply ? "primary" : "default"}
                    radius="full"
                    aria-pressed={composer.message === reply}
                    className="h-auto min-h-8 whitespace-normal px-3 py-1.5 text-start text-xs"
                    isDisabled={composer.busy}
                    onPress={() => composer.onMessage(reply)}
                  >
                    {reply}
                  </Button>
                ))}
              </div>
            </div>
          )}
          <div className="space-y-2">
            <p className="text-sm font-semibold">
              {t("supportChat.describePrompt", {
                defaultValue: "Tell us what happened",
              })}
            </p>
            <SupportComposer
              {...composer}
              start
              onBack={() => setStep("topic")}
              onSubmit={() => onStart(topic.id, orderId ?? undefined)}
            />
          </div>
        </div>
      )}
    </section>
  );
}
