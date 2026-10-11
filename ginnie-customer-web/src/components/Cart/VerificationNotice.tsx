import { FC } from "react";
import { Button, Card, CardBody } from "@heroui/react";
import { ArrowRight, ShieldAlert } from "lucide-react";
import { useRouter } from "next/router";
import { useSelector } from "react-redux";
import { useTranslation } from "react-i18next";

import { RootState } from "@/lib/redux/store";
import {
  getVerificationMessageKey,
  getVerificationStatus,
} from "@/helpers/verification";

/**
 * Warns that the account isn't verified, before the customer reaches payment.
 *
 * The backend refuses to create the order otherwise, but only says so in the
 * createOrder response — so without this the first they hear of it is a toast
 * after picking a payment method, with nothing on screen explaining how to fix
 * it.
 *
 * Deliberately a WARNING, not a gate: the server owns the rule and may reject
 * for reasons this component can't see, so blocking the pay button here would
 * risk locking out a customer the backend would have accepted.
 */
const VerificationNotice: FC = () => {
  const { t } = useTranslation();
  const router = useRouter();
  const user = useSelector((state: RootState) => state.auth.user);

  const status = getVerificationStatus(user);
  const pendingKey = getVerificationMessageKey(status);

  // Nothing outstanding, or nobody signed in yet.
  if (!pendingKey) return null;

  const title = t(
    `cart.verification.${pendingKey}`,
    pendingKey === "both"
      ? "Verify your email and mobile to place an order"
      : pendingKey === "email"
        ? "Verify your email to place an order"
        : "Verify your mobile to place an order",
  );

  return (
    <Card
      className="w-full border border-warning-200 bg-warning-50 dark:bg-warning-100/10"
      radius="md"
      shadow="none"
      data-testid="cart-verification-notice"
    >
      <CardBody>
        <div className="flex items-center justify-between gap-3">
          <div className="flex min-w-0 items-center gap-3">
            {/* dark: override because the theme pins warning's dark scale to
                the same un-inverted values as light — see ScheduledDelivery. */}
            <div className="rounded-lg bg-warning-100 p-2 dark:bg-warning-100/20">
              <ShieldAlert className="h-5 w-5 text-warning-600 dark:text-warning" />
            </div>

            <div className="min-w-0">
              <p className="text-xs font-semibold md:text-small">{title}</p>
              <p className="text-xxs text-default-600 md:text-xs">
                {t(
                  "cart.verification.description",
                  "Complete verification in your account to avoid problems at checkout",
                )}
              </p>
            </div>
          </div>

          <Button
            size="sm"
            color="warning"
            variant="flat"
            // Explicit text-xs: HeroUI's size="sm" applies text-tiny, which
            // this theme pins to 7px and renders unreadable.
            className="h-7 shrink-0 text-xs font-medium"
            endContent={<ArrowRight className="h-3.5 w-3.5" />}
            onPress={() => router.push("/my-account")}
            data-testid="cart-verification-cta"
          >
            {t("cart.verification.cta", "Verify")}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
};

export default VerificationNotice;
