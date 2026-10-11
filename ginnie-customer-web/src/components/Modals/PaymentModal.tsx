import { useSettings } from "@/contexts/SettingsContext";
import { formatAmount, handleCheckout } from "@/helpers/functionalHelpers";
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
import { useRouter } from "next/router";
import { FC, useState } from "react";
import { useTranslation } from "react-i18next";
import { useDispatch, useSelector } from "react-redux";
import { updateCartData } from "@/helpers/updators";
import FlutterwavePayment from "../PaymentGateway/FlutterwavePayment";
import PayStack from "../PaymentGateway/Paystack";
import RazorPay from "../PaymentGateway/RazorPay";
import Stripe from "../PaymentGateway/Stripe";
import XenditPayment from "../PaymentGateway/XenditPayment";
import PaymentMethods from "../PaymentMethods";

interface PaymentModalProps {
  open: boolean;
  onOpenChange: (isOpen: boolean) => void;
}

const PaymentModal: FC<PaymentModalProps> = ({ open, onOpenChange }) => {
  const [selectedPayment, setSelectedPayment] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const router = useRouter();
  const { t } = useTranslation();
  const dispatch = useDispatch();
  const { currencySymbol } = useSettings();

  const paymentSummary = useSelector(
    (state: RootState) => state.cart.cartData?.payment_summary
  );

  // Tips can't be collected in cash, so a tipped cart can't check out COD.
  const hasRiderTip = Number(paymentSummary?.rider_tip_amount || 0) > 0;

  const handleContinue = async () => {
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
        const res = await handleCheckout("cod", {});
        if (res?.success) {
          onOpenChange(false);
          await router.push("/my-account/orders");
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
    await router.push("/my-account/orders");
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
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader>
            <h2 className="font-semibold">{t("select_payment_method")}</h2>
          </ModalHeader>

          <ModalBody>
            {hasRiderTip && (
              <p className="text-sm text-warning-600">
                {t("tip.codUnavailable")}
              </p>
            )}
            <PaymentMethods
              selectedPayment={selectedPayment}
              setSelectedPayment={setSelectedPayment}
              hideCOD={hasRiderTip}
              isLoading={isLoading}
            />

            {selectedPayment === "cod" && paymentSummary && (
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
                    {currencySymbol}
                    {formatAmount(paymentSummary.items_total || 0)}
                  </span>
                </div>

                {/* Delivery Charges */}
                {Number(paymentSummary.total_delivery_charges || 0) > 0 && (
                  <div className="flex justify-between">
                    <span className="opacity-80">
                      {t("checkout.deliveryCharges")}
                    </span>
                    <span>
                      {currencySymbol}
                      {formatAmount(paymentSummary.total_delivery_charges)}
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
                      {currencySymbol}
                      {formatAmount(Number(paymentSummary.handling_charges))}
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
                      {currencySymbol}
                      {formatAmount(
                        Number(paymentSummary.per_store_drop_off_fee)
                      )}
                    </span>
                  </div>
                )}

                {/* Rider Tip */}
                {Number(paymentSummary.rider_tip_amount || 0) > 0 && (
                  <div className="flex justify-between">
                    <span className="opacity-80">{t("tip.riderTip")}</span>
                    <span>
                      {currencySymbol}
                      {formatAmount(Number(paymentSummary.rider_tip_amount))}
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
                      -{currencySymbol}
                      {formatAmount(Number(paymentSummary.promo_discount))}
                    </span>
                  </div>
                )}

                {/* Loyalty Points Discount */}
                {Number(paymentSummary.loyalty_points_discount_amount || 0) >
                  0 && (
                  <div className="flex justify-between text-green-700 dark:text-green-400 font-medium">
                    <span>{t("checkout.loyaltyPoints")}</span>
                    <span>
                      -{currencySymbol}
                      {formatAmount(
                        Number(paymentSummary.loyalty_points_discount_amount)
                      )}
                    </span>
                  </div>
                )}

                {/* Wallet Amount Used */}
                {Number(paymentSummary.wallet_amount_used || 0) > 0 && (
                  <div className="flex justify-between text-green-700 dark:text-green-400 font-medium">
                    <span>{t("checkout.walletAmountUsed")}</span>
                    <span>
                      -{currencySymbol}
                      {formatAmount(Number(paymentSummary.wallet_amount_used))}
                    </span>
                  </div>
                )}

                {/* COD Fee */}
                {Number(paymentSummary.cod_charges || 0) > 0 && (
                  <div className="flex justify-between font-semibold text-amber-950 dark:text-amber-100 pt-0.5 border-t border-amber-200/60 dark:border-amber-900/40">
                    <span>{t("checkout.codFee")}</span>
                    <span>
                      +{currencySymbol}
                      {formatAmount(Number(paymentSummary.cod_charges))}
                    </span>
                  </div>
                )}

                {/* Total Payable on Delivery */}
                <div className="border-t border-amber-200 dark:border-amber-900/50 pt-2 flex justify-between font-bold text-sm text-amber-950 dark:text-amber-100">
                  <span>{t("checkout.totalPayableOnDelivery")}</span>
                  <span>
                    {currencySymbol}
                    {formatAmount(
                      Number(paymentSummary.payable_amount || 0) +
                        Number(paymentSummary.cod_charges || 0)
                    )}
                  </span>
                </div>
              </div>
            )}
          </ModalBody>

          <ModalFooter>
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

            {selectedPayment === "stripePayment" && (
              <Stripe
                onSuccess={handlePaymentSuccess}
                onError={handleError}
                isLoading={isLoading}
                setIsLoading={setIsLoading}
              />
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
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
};

export default PaymentModal;
