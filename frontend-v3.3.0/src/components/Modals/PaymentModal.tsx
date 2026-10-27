import { formatMoney, money, isCheckoutPaymentMethodAvailable, paymentUnavailableMessage } from "@/helpers/currency";
import type { CartResponse } from "@/types/ApiResponse";
import { useSettings } from "@/contexts/SettingsContext";
import { handleCheckout } from "@/helpers/functionalHelpers";
import { setPromoCode } from "@/lib/redux/slices/checkoutSlice";
import { RootState } from "@/lib/redux/store";
import {
  addToast,
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
} from "@heroui/react";
import { FC, useState } from "react";
import { useTranslation } from "react-i18next";
import { useDispatch, useSelector } from "react-redux";
import { updateCartData } from "@/helpers/updators";
import FlutterwavePayment from "../PaymentGateway/FlutterwavePayment";
import PayStack from "../PaymentGateway/Paystack";
import RazorPay from "../PaymentGateway/RazorPay";
import Stripe from "../PaymentGateway/Stripe";
import XenditPayment from "../PaymentGateway/XenditPayment";
import MercadoPagoPayment from "../PaymentGateway/MercadoPagoPayment";
import PaymentMethods from "../PaymentMethods";
import { CreditCard, LockKeyhole } from "lucide-react";
import { getCheckoutCartFromRedux, getUserDataFromRedux } from "@/helpers/getters";
import {
  hasCompetingPaymentAttempt,
  paymentAttemptScope,
  recoverAbandonedPaymentAttempt,
} from "@/helpers/paymentReturn";
import { showPaymentInProgressToast } from "@/components/PaymentGateway/PaymentInProgressToast";

interface PaymentModalProps {
  open: boolean;
  onOpenChange: (isOpen: boolean) => void;
}

const PaymentModal: FC<PaymentModalProps> = ({ open, onOpenChange }) => {
  const [selection, setSelection] = useState<{ method: string; cart: CartResponse | null } | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const { t } = useTranslation();
  const dispatch = useDispatch();
  const { currencySymbol, paymentSettings } = useSettings();

  const cartData = useSelector((state: RootState) => state.cart.cartData);
  const cartBlocked = useSelector((state: RootState) =>
    state.cart.isLoading || !!state.cart.error || !!state.cart.deliveryLocationNeedsSelection,
  );
  const paymentSummary = cartData?.payment_summary;
  const hasRiderTip = Number(paymentSummary?.rider_tip_amount || 0) > 0;
  // A refreshed quote can change eligibility or totals. Require a new choice
  // rather than retaining a gateway selected against a previous response.
  const selectedPayment = selection?.cart === cartData &&
    isCheckoutPaymentMethodAvailable(cartData, selection?.method || "", paymentSettings) &&
    !(selection?.method === "cod" && hasRiderTip)
      ? selection?.method || "" : "";
  const setSelectedPayment = (method: string) => setSelection(method ? { method, cart: cartData } : null);
  const totals = { ...cartData, ...paymentSummary };
  const paymentAllowed = !cartBlocked && isCheckoutPaymentMethodAvailable(cartData, selectedPayment, paymentSettings);
  const userData = getUserDataFromRedux();
  const attemptScope = paymentAttemptScope({
    usageType: "order",
    userId: userData?.id,
    cartId: cartData?.uuid || cartData?.id,
  });

  const selectPayment = async (payment: string) => {
    await recoverAbandonedPaymentAttempt(attemptScope);
    if (hasCompetingPaymentAttempt(attemptScope)) {
      setSelectedPayment("");
      showPaymentInProgressToast(attemptScope, t);
      return;
    }
    if (cartBlocked || getCheckoutCartFromRedux() !== cartData || !isCheckoutPaymentMethodAvailable(cartData, payment, paymentSettings) || (payment === "cod" && hasRiderTip)) {
      addToast({ title: paymentUnavailableMessage(cartData, payment) || t("multiCurrency.paymentUnavailable"), color: "warning" });
      setSelectedPayment("");
      return;
    }
    setSelectedPayment(payment);
  };

  const handleContinue = async () => {
    if (!paymentAllowed) {
      return addToast({ title: t("multiCurrency.paymentUnavailable"), color: "warning" });
    }
    if (!selectedPayment) {
      return addToast({
        title: t("please_select_payment_method"),
        color: "warning",
      });
    }

    // COD can be selected, then a tip added, then the modal reopened — the
    // stale selection must not slip through just because the option is
    // hidden now.
    if (selectedPayment === "cod" && hasRiderTip) {
      return addToast({
        title: t("tip.codUnavailable"),
        color: "warning",
      });
    }

    if (selectedPayment === "cod") {
      setIsLoading(true);
      try {
        const res = await handleCheckout("cod", {}, { paymentSettings });
        if (res?.success) {
          onOpenChange(false);
          dispatch(setPromoCode(""));
        }
      } finally {
        setIsLoading(false);
        updateCartData(true, false);
      }
    } else if (selectedPayment === "directBankTransfer") {
      document.getElementById("bank_transfer_modal_btn")?.click();
    }
  };

  const handlePaymentSuccess = async () => {
    onOpenChange(false);
  };

  const handleError = () => {
    // onOpenChange(false);
  };

  return (
    <>
      <Modal
        isOpen={open}
        onOpenChange={onOpenChange}
        backdrop="blur"
        size="xl"
        placement="top-center"
        scrollBehavior="inside"
        classNames={{ base: "sm:my-10 rounded-2xl", header: "px-6 pt-6 pb-3", body: "px-6 pb-6 gap-4" }}
      >
        <ModalContent>
          <ModalHeader>
            <h2 className="font-semibold">{t(selectedPayment === "stripePayment" ? "stripe_checkout_title" : "select_payment_method")}</h2>
          </ModalHeader>

          <ModalBody>
            {hasRiderTip && (
              <p className="text-sm text-warning-600">
                {t("tip.codUnavailable")}
              </p>
            )}
            {selectedPayment === "stripePayment" && paymentAllowed ? (
              <>
                <div className="rounded-2xl border border-default-200 bg-default-50 p-4">
                  <div className="flex items-center justify-between gap-4">
                    <div>
                      <p className="text-xs text-foreground/60">{t("stripe_total_due")}</p>
                      <p className="mt-1 text-2xl font-semibold tracking-tight">{money(totals, "payable_amount", currencySymbol, totals)}</p>
                    </div>
                    <span className="flex items-center gap-1.5 text-xs text-foreground/60"><LockKeyhole size={14} aria-hidden="true" />{t("stripe_secure_payment")}</span>
                  </div>
                  <div className="mt-4 flex items-center justify-between border-t border-default-200 pt-3">
                    <span className="flex items-center gap-2 text-sm font-medium"><CreditCard size={16} aria-hidden="true" />Stripe</span>
                    <Button variant="light" color="primary" isDisabled={isLoading} onPress={() => setSelectedPayment("")}>
                      {t("change")}
                    </Button>
                  </div>
                </div>
                <Stripe
                  isActive={open}
                  onSuccess={handlePaymentSuccess}
                  onError={handleError}
                  isLoading={isLoading}
                  setIsLoading={setIsLoading}
                />
              </>
            ) : (
              <PaymentMethods
                currencyContext={cartData}
                includeMercadoPago
                scrollable={false}
                selectedPayment={selectedPayment}
                setSelectedPayment={selectPayment}
                hideCOD={hasRiderTip}
                isLoading={isLoading || cartBlocked}
              />
            )}

            {selectedPayment === "cod" && paymentAllowed && paymentSummary && (
              <div className="mt-3 p-3.5 bg-amber-50 dark:bg-amber-950/30 rounded-xl border border-amber-200 dark:border-amber-900/50 space-y-2 text-xs text-amber-900 dark:text-amber-200">
                <div className="font-semibold text-xs text-amber-950 dark:text-amber-100 border-b border-amber-200 dark:border-amber-900/50 pb-1.5 flex items-center justify-between">
                  <span>{t("paymentDetails")}</span>
                  <span className="text-[10px] text-amber-700 dark:text-amber-400 font-normal">
                    {t("payments.cod.name")}
                  </span>
                </div>

                {/* Items Total */}
                <div className="flex justify-between">
                  <span className="opacity-80">{t("checkout.itemsTotal")}</span>
                  <span>
                    {money(totals, "items_total", currencySymbol, totals)}
                  </span>
                </div>

                {/* Delivery Charges */}
                {Number(paymentSummary.total_delivery_charges || 0) > 0 && (
                  <div className="flex justify-between">
                    <span className="opacity-80">
                      {t("checkout.deliveryCharges")}
                    </span>
                    <span>
                      {money(totals, "total_delivery_charges", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* Handling Charges */}
                {Number(paymentSummary.handling_charges || 0) > 0 && (
                  <div className="flex justify-between">
                    <span className="opacity-80">
                      {t("checkout.handlingCharges")}
                    </span>
                    <span>
                      {money(totals, "handling_charges", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* Drop-off Fee */}
                {Number(paymentSummary.per_store_drop_off_fee || 0) > 0 && (
                  <div className="flex justify-between">
                    <span className="opacity-80">
                      {t("checkout.dropOffFee")}
                    </span>
                    <span>
                      {money(totals, "per_store_drop_off_fee", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* Rider Tip */}
                {Number(paymentSummary.rider_tip_amount || 0) > 0 && (
                  <div className="flex justify-between">
                    <span className="opacity-80">{t("tip.riderTip")}</span>
                    <span>
                      {money(totals, "rider_tip_amount", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* Promo Discount */}
                {Number(paymentSummary.promo_discount || 0) > 0 && (
                  <div className="flex justify-between text-green-700 dark:text-green-400 font-medium">
                    <span>
                      {t("checkout.promo")}{" "}
                      {paymentSummary.promo_code
                        ? `(${paymentSummary.promo_code})`
                        : ""}
                    </span>
                    <span>
                      -{money(totals, "promo_discount", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* Loyalty Points Discount */}
                {Number(paymentSummary.loyalty_points_discount_amount || 0) >
                  0 && (
                  <div className="flex justify-between text-green-700 dark:text-green-400 font-medium">
                    <span>{t("userLayout.loyaltyPoints")}</span>
                    <span>
                      -{money(totals, "loyalty_points_discount_amount", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* Wallet Amount Used */}
                {Number(paymentSummary.wallet_amount_used || 0) > 0 && (
                  <div className="flex justify-between text-green-700 dark:text-green-400 font-medium">
                    <span>{t("checkout.walletAmountUsed")}</span>
                    <span>
                      -{money(totals, "wallet_amount_used", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* COD Fee */}
                {Number(paymentSummary.cod_charges || 0) > 0 && (
                  <div className="flex justify-between font-semibold text-amber-950 dark:text-amber-100 pt-0.5 border-t border-amber-200/60 dark:border-amber-900/40">
                    <span>{t("checkout.codFee")}</span>
                    <span>
                      +{money(totals, "cod_charges", currencySymbol, totals)}
                    </span>
                  </div>
                )}

                {/* Total Payable on Delivery */}
                <div className="border-t border-amber-200 dark:border-amber-900/50 pt-2 flex justify-between font-bold text-sm text-amber-950 dark:text-amber-100">
                  <span>{t("checkout.totalPayableOnDelivery")}</span>
                  <span>
                    {formatMoney(Number(paymentSummary.payable_amount || 0) +
                        Number(paymentSummary.cod_charges || 0), totals, currencySymbol)}
                  </span>
                </div>
              </div>
            )}
          </ModalBody>

          {selectedPayment && paymentAllowed && selectedPayment !== "stripePayment" && <ModalFooter>
            {(selectedPayment === "cod" ||
              selectedPayment === "directBankTransfer") && (
              <Button
                color="primary"
                onPress={handleContinue}
                isLoading={isLoading}
                className="w-full"
              >
                {t("continue")}
              </Button>
            )}

            {selectedPayment === "razorpayPayment" && (
              <RazorPay
                onSuccess={handlePaymentSuccess}
                onError={handleError}
                isLoading={isLoading}
                setIsLoading={setIsLoading}
              />
            )}

            {selectedPayment === "paystackPayment" && (
              <PayStack
                onSuccess={handlePaymentSuccess}
                onError={handleError}
                setIsLoading={setIsLoading}
                isLoading={isLoading}
                usageType="order"
              />
            )}

            {selectedPayment === "flutterwavePayment" && (
              <FlutterwavePayment
                onSuccess={handlePaymentSuccess}
                onError={handleError}
                isLoading={isLoading}
                setIsLoading={setIsLoading}
              />
            )}

            {selectedPayment === "xenditPayment" && (
              <XenditPayment
                onSuccess={handlePaymentSuccess}
                onError={handleError}
                isLoading={isLoading}
                setIsLoading={setIsLoading}
              />
            )}
            {selectedPayment === "mercadoPagoPayment" && (
              <MercadoPagoPayment
                onError={handleError}
                isLoading={isLoading}
                setIsLoading={setIsLoading}
              />
            )}
          </ModalFooter>}
        </ModalContent>
      </Modal>
    </>
  );
};

export default PaymentModal;
