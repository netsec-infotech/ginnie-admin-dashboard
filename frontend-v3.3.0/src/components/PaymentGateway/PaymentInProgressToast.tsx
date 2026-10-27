import { addToast, Button } from "@heroui/react";
import {
  focusPaymentAttempt,
  hasActivePaymentAttempt,
} from "@/helpers/paymentReturn";
import type { TFunction } from "i18next";

const focusOwnerTab = (scope: string | null, t: TFunction): void => {
  if (focusPaymentAttempt(scope)) return;
  if (!hasActivePaymentAttempt(scope)) {
    addToast({
      title: t("paymentAttempt.staleClearedTitle"),
      description: t("paymentAttempt.staleClearedDescription"),
      color: "success",
    });
    return;
  }
  addToast({
    title: t("paymentAttempt.switchFailedTitle"),
    description: t("paymentAttempt.switchFailedDescription"),
    color: "warning",
  });
};

export const PaymentInProgressNotice = ({
  scope,
  t,
  namespace = "paymentAttempt",
}: {
  scope: string | null;
  t: TFunction;
  namespace?: "paymentAttempt" | "tip.paymentAttempt";
}) => (
  <div className="flex w-full flex-col gap-3">
    <p>{t(`${namespace}.inProgressDescription`)}</p>
    <Button
      className="w-full font-semibold"
      color="warning"
      variant="flat"
      onPress={() => focusOwnerTab(scope, t)}
    >
      {t("paymentAttempt.goToTab")}
    </Button>
  </div>
);

export const showPaymentInProgressToast = (
  scope: string | null,
  t: TFunction,
  namespace: "paymentAttempt" | "tip.paymentAttempt" = "paymentAttempt",
): void => {
  addToast({
    title: t(`${namespace}.inProgressTitle`),
    description: (
      <PaymentInProgressNotice scope={scope} t={t} namespace={namespace} />
    ),
    color: "warning",
    timeout: 10000,
    classNames: {
      content: "w-full min-w-0",
      description: "w-full",
    },
  });
};
