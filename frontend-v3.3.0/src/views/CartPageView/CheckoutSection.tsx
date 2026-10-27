import { getCurrencyCode, formatMoney, money, canCheckoutCurrency, canUseCheckoutWallet, getWalletContext } from "@/helpers/currency";
import { FC, useEffect, useState } from "react";
import { CartResponse } from "@/types/ApiResponse";
import {
  Card,
  CardBody,
  CardFooter,
  CardHeader,
  Button,
  Switch,
  useDisclosure,
  addToast,
  Divider,
  Alert,
} from "@heroui/react";
import PaymentModal from "@/components/Modals/PaymentModal";
import CartItems from "./CartItems";
import ShareCartButton from "@/components/Cart/ShareCartButton";
import { useSettings } from "@/contexts/SettingsContext";
import { useDispatch, useSelector } from "react-redux";
import { RootState } from "@/lib/redux/store";
import { updateCartData } from "@/helpers/updators";
import {
  setPromoCode,
  setUseLoyaltyPoints,
  setUseWallet,
} from "@/lib/redux/slices/checkoutSlice";
import { clearCart } from "@/routes/api";
import ConfirmationModal from "@/components/Modals/ConfirmationModal";
import { Trash2, Tag, Wallet, Coins } from "lucide-react";
import { useRouter } from "next/router";
import { useTranslation } from "react-i18next";
import { formatAmount, handleCheckout, validateCheckoutAddress } from "@/helpers/functionalHelpers";

interface CheckoutSectionProps {
  cart: CartResponse;
  completed?: boolean;
}

const CheckoutSection: FC<CheckoutSectionProps> = ({ cart, completed = false }) => {
  const { isOpen, onOpen, onOpenChange } = useDisclosure();
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);
  const { paymentSettings } = useSettings();
  const user = useSelector((state: RootState) => state.auth.user);
  const isWalletEnabled = canUseCheckoutWallet(cart, getWalletContext(user), paymentSettings);
  const currencyValid = canCheckoutCurrency(cart);
  const totals = { ...cart, ...cart.payment_summary };

  const [showClearCartModal, setShowClearCartModal] = useState(false);
  const router = useRouter();
  const { currencySymbol, systemSettings, isSingleVendor } = useSettings();
  const { payment_summary, items, total_quantity } = cart;
  const selectedAddress = useSelector(
    (state: RootState) => state.checkout.selectedAddress,
  );
  const deliveryLocationNeedsSelection = useSelector(
    (state: RootState) => state.cart.deliveryLocationNeedsSelection,
  );

  const dispatch = useDispatch();

  const isLoading = useSelector((state: RootState) => state.cart.isLoading);
  const cartError = useSelector((state: RootState) => state.cart.error);
  const [isWalletUse, setIsWalletUse] = useState(payment_summary.use_wallet);

  const handleUseWalletSwitchToggle = async (checked: boolean) => {
    if (isLoading || cartError || (checked && !isWalletEnabled)) return;
    setIsWalletUse(checked);

    dispatch(setUseWallet(checked));

    setTimeout(() => {
      updateCartData(true, false);
    }, 500);
  };

  // Loyalty points redemption — all limits (eligibility, per-order cap from
  // max_redemption_percentage, min order value) come from the cart response's
  // loyalty_summary; the backend recomputes payable_amount on refetch.
  const { loyaltySettings } = useSettings();
  const loyalty = payment_summary.loyalty_summary;
  const isLoyaltyEnabled =
    loyaltySettings?.is_enabled !== false && !!loyalty?.is_loyalty_enabled;
  const canRedeemLoyalty =
    !!loyalty?.is_eligible_for_redemption &&
    (loyalty?.user_points_balance ?? 0) > 0 &&
    (loyalty?.max_points_redeemable ?? 0) > 0;
  const loyaltyRemainingToRedeem = loyalty
    ? Math.max(
        0,
        loyalty.min_order_value_to_redeem - payment_summary.items_total,
      )
    : 0;
  const useLoyaltyRedux = useSelector(
    (state: RootState) => state.checkout.useLoyaltyPoints,
  );
  const [isLoyaltyUse, setIsLoyaltyUse] = useState(useLoyaltyRedux);

  const handleUseLoyaltySwitchToggle = async (checked: boolean) => {
    setIsLoyaltyUse(checked);

    dispatch(setUseLoyaltyPoints(checked));

    setTimeout(() => {
      updateCartData(true, false);
    }, 500);
  };

  const handleCheckoutClick = async (walletOnly = false) => {
    if (completed || isLoading || cartError || !currencyValid || (walletOnly && !isWalletEnabled)) return;
    try {
      setLoading(true);
      if (!selectedAddress) {
        addToast({
          title: t("checkout.noAddressSelected.title"),
          description: t("checkout.noAddressSelected.description"),
          color: "warning",
        });
        return;
      }

      if (!(await validateCheckoutAddress())) return;

      // Check for required attachments
      const attachments = (window as any).__cartAttachments || {};
      const itemsRequiringAttachments =
        items?.filter(
          (item) =>
            item.product.is_attachment_required &&
            item.product.attachment_mode === "required",
        ) || [];

      if (itemsRequiringAttachments.length > 0) {
        const itemsMissingAttachments = itemsRequiringAttachments.filter(
          (item) =>
            !attachments[item.product.id] ||
            attachments[item.product.id].length === 0,
        );

        if (itemsMissingAttachments.length > 0) {
          const productNames = itemsMissingAttachments
            .map((item) => item.product.name)
            .filter(Boolean)
            .join(", ");

          addToast({
            title: t("checkout.attachmentsRequired.title", {
              defaultValue: "Attachments Required",
            }),
            description: t("checkout.attachmentsRequired.description", {
              products: productNames,
              defaultValue: `Please upload required attachments for: ${productNames}`,
            }),
            color: "warning",
          });
          return;
        }
      }

      // Check closed stores
      const closedStores =
        items?.length > 0
          ? items.filter((item) => !item.store?.status?.is_open)
          : [];

      if (closedStores.length > 0) {
        const closedStoreNames = closedStores
          .map((s) => s.store?.name)
          .filter(Boolean)
          .join(", ");

        addToast({
          title: t("checkout.closedStores.title"),
          description: t("checkout.closedStores.description", {
            stores: closedStoreNames,
          }),
          color: "danger",
        });

        return;
      }

      // Wallet-only checkout
      if (walletOnly) {
        setLoading(true);
        const res = await handleCheckout("wallet", {}, { paymentSettings });

        if (res?.success) {
          dispatch(setPromoCode(""));
          updateCartData(true, false);
        }

        return;
      } else {
        // Open payment selection modal
        onOpen();
      }
    } catch (error) {
      console.error("Error in handleCheckoutClick:", error);
      addToast({
        title: t("general.error.title"),
        description: t("general.error.somethingWentWrong"),
        color: "danger",
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!isLoading && !cartError && (
      (payment_summary.wallet_balance === 0 && payment_summary.use_wallet) ||
      (!isWalletEnabled && payment_summary.use_wallet)
    )) {
      setTimeout(() => {
        setIsWalletUse(false);
        dispatch(setUseWallet(false));
      }, 0);
      setTimeout(() => {
        updateCartData(true, false);
      }, 500);
    }
  }, [payment_summary, dispatch, isWalletEnabled, isLoading, cartError]);

  // Self-heal: drop the loyalty toggle when redemption stops being possible
  // (cart fell below the min order value, points spent elsewhere, disabled).
  useEffect(() => {
    if (isLoyaltyUse && loyalty && (!isLoyaltyEnabled || !canRedeemLoyalty)) {
      setTimeout(() => {
        setIsLoyaltyUse(false);
        dispatch(setUseLoyaltyPoints(false));
      }, 0);
      setTimeout(() => {
        updateCartData(true, false);
      }, 500);
    } // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [payment_summary, dispatch]);

  const promo = Array.isArray(payment_summary?.promo_applied)
    ? null
    : payment_summary?.promo_applied || null;

  const isFreeShipping = promo?.discount_type === "free_shipping";
  const promo_mode = promo?.promo_mode;
  const isInstantPromo = promo_mode === "instant";
  const isCashbackPromo = promo_mode === "cashback";

  // Validation checks based on system settings
  const validationErrors: string[] = [];
  if (cartError) validationErrors.push(t("payments.cartRefreshRequired"));
  if (!currencyValid) validationErrors.push(t("multiCurrency.mixedCart"));

  // Check checkout type (single_store vs multi_store)
  if (
    !isSingleVendor &&
    systemSettings?.checkoutType === "single_store" &&
    items?.length > 0
  ) {
    const uniqueStoreIds = new Set(
      items.map((item) => item.store?.id).filter(Boolean),
    );
    if (uniqueStoreIds.size > 1) {
      validationErrors.push(
        t("checkout.validation.multipleStores", {
          defaultValue:
            "Your cart contains products from multiple stores. Single store checkout only allows products from one store.",
        }),
      );
    }
  }

  // Check maximum items allowed in cart
  if (
    systemSettings?.maximumItemsAllowedInCart &&
    total_quantity > systemSettings.maximumItemsAllowedInCart
  ) {
    validationErrors.push(
      t("checkout.validation.maxItemsExceeded", {
        maxItems: systemSettings.maximumItemsAllowedInCart,
        currentItems:
          Number(total_quantity) -
          Number(systemSettings.maximumItemsAllowedInCart),
        defaultValue: `Maximum ${systemSettings.maximumItemsAllowedInCart} items allowed in cart. You have ${total_quantity} items.`,
      }),
    );
  }

  // Check minimum cart amount
  if (
    currencyValid && !getCurrencyCode(cart) && systemSettings?.minimumCartAmount &&
    payment_summary.items_total < systemSettings.minimumCartAmount
  ) {
    const remainingAmount =
      systemSettings.minimumCartAmount - payment_summary.items_total;
    validationErrors.push(
      t("checkout.validation.minCartAmount", {
        minAmount: formatAmount(systemSettings.minimumCartAmount),
        currentAmount: formatAmount(payment_summary.items_total),
        remainingAmount: formatAmount(remainingAmount),
        currencySymbol: formatMoney(0, cart, currencySymbol).replace(/[\d.,\s]+$/g, "") + " ",
        defaultValue: `Minimum cart amount is ${currencySymbol}${formatAmount(systemSettings.minimumCartAmount)}. Add ${currencySymbol}${formatAmount(remainingAmount)} more to proceed.`,
      }),
    );
  }

  if (deliveryLocationNeedsSelection) {
    validationErrors.push(t("address.deliveryNeedsSelection"));
  }
  const hasValidationErrors = validationErrors.length > 0;

  return (
    <div className="w-full flex justify-end">
      <Card radius="md" className="w-full p-2" shadow="sm">
        <CardHeader className="flex w-full flex-wrap justify-between items-center gap-2">
          <h2 className="shrink-0 font-semibold">{t("checkout.yourOrder")}</h2>
          <div className="flex flex-wrap items-center justify-end gap-1">
            {!completed && <ShareCartButton compact />}
            <Button
              size="sm"
              className="h-6 text-xs"
              color="danger"
              title={t("checkout.clearCart")}
              isDisabled={completed}
              onPress={() => setShowClearCartModal(true)}
            >
              {t("checkout.clearCart")}
            </Button>
          </div>
        </CardHeader>
        <CardBody>
          <CartItems items={cart.items} currencySymbol={currencySymbol} currencyContext={cart} />
        </CardBody>
        {currencyValid ? <CardFooter className="space-y-6 flex flex-col w-full border-t-1 border-gray-200 dark:border-default-100">
          <div className="space-y-3 pt-4 w-full text-sm">
            <div className="flex justify-between">
              <span>{t("checkout.itemsTotal")}</span>
              <span>
                {money(totals, "items_total", currencySymbol, totals)}
              </span>
            </div>
            <div className="w-full -mt-2 text-start text-xs text-gray-500">
              {`${t("checkout.allPricesIncludeTaxes")}`}
            </div>

            {/* The backend folds delivery into payable_amount whether or not
                an address is selected, so this row must always render — hiding
                it before address selection left the shown charges summing to
                less than the total, with the delivery amount unexplained. When
                no address is chosen yet it's an estimate on the cart's stored
                location; a note says so. */}
            <div className="flex justify-between">
              <span>{t("checkout.deliveryCharges")}</span>

              {isFreeShipping && isInstantPromo ? (
                <span className="flex items-center gap-2">
                  {/* Strikethrough original amount */}
                  <span className="line-through text-gray-500">
                    {money(totals, "total_delivery_charges", currencySymbol, totals)}
                  </span>

                  {/* Free Shipping text */}
                  <span className="text-green-600 font-semibold">
                    {t("checkout.freeShipping")}
                  </span>
                </span>
              ) : (
                <span>
                  {money(totals, "total_delivery_charges", currencySymbol, totals)}
                </span>
              )}
            </div>

            {(!selectedAddress?.id || deliveryLocationNeedsSelection) &&
              payment_summary.total_delivery_charges > 0 && (
                <div className="w-full -mt-2 text-start text-xs text-gray-500">
                  {t("checkout.deliveryEstimateNote")}
                </div>
              )}

            {Number(payment_summary.handling_charges || 0) > 0 && (
              <div className="flex justify-between">
                <span>{t("checkout.handlingCharges")}</span>
                <span>
                  {money(totals, "handling_charges", currencySymbol, totals)}
                </span>
              </div>
            )}

            {Number(payment_summary.cod_charges || 0) > 0 && (
              <div className="flex justify-between text-gray-500 text-xs italic">
                <span>{t("checkout.codChargeNote")}</span>
                <span>
                  +{money(totals, "cod_charges", currencySymbol, totals)}
                </span>
              </div>
            )}

            {payment_summary.per_store_drop_off_fee > 0 && (
              <div className="flex justify-between">
                <div>
                  {t("checkout.dropOffFee")}{" "}
                  {!isSingleVendor && (
                    <>
                      ({payment_summary.total_stores}
                      <span className="ml-1">{t("checkout.stores")})</span>
                    </>
                  )}
                </div>
                <span>
                  {money(totals, "per_store_drop_off_fee", currencySymbol, totals)}
                </span>
              </div>
            )}

            {Number(payment_summary.rider_tip_amount || 0) > 0 && (
              <div className="flex justify-between">
                <span>{t("tip.riderTip")}</span>
                <span>
                  {money(totals, "rider_tip_amount", currencySymbol, totals)}
                </span>
              </div>
            )}

            {/* {payment_summary.delivery_distance_charges > 0 && (
              <div className="flex justify-between">
                <span>
                  {t("checkout.distanceCharges")} (
                  {payment_summary.delivery_distance_km} km)
                </span>
                <span>
                  {currencySymbol}
                  {formatAmount(payment_summary.delivery_distance_charges)}
                </span>
              </div>
            )} */}

            {payment_summary.is_rush_delivery && (
              <div className="flex justify-between text-orange-600">
                <span className="flex items-center gap-1">
                  <span className="w-2 h-2 bg-orange-500 rounded-full"></span>
                  {t("checkout.rushDelivery")}
                </span>
                <span className="text-xs">
                  {t("checkout.rushDeliveryDescription")}
                </span>
              </div>
            )}

            {payment_summary.estimated_delivery_time > 0 && (
              <div className="flex justify-between text-gray-600">
                <span>{t("checkout.estimatedDelivery")}</span>
                <span className="text-xs">
                  {payment_summary.estimated_delivery_time} {t("mins")}
                </span>
              </div>
            )}

            {selectedAddress?.id ? (
              <>
                {/* INSTANT PROMO - Shown as discount with deduction */}
                {payment_summary.promo_applied &&
                  payment_summary.promo_discount > 0 &&
                  isInstantPromo && (
                    <div className="bg-green-50 p-3 rounded-lg border border-green-200">
                      <div className="flex items-center justify-between text-green-700">
                        <div className="w-full flex flex-col gap-1">
                          <div className="flex items-center gap-2">
                            <Tag className="w-4 h-4" />
                            <span className="font-medium">
                              {t("checkout.promo")}:{" "}
                              {payment_summary?.promo_code}
                            </span>
                          </div>
                        </div>

                        {/* Right side value - deduction */}
                        <span className="font-semibold inline-block whitespace-nowrap">
                          {isFreeShipping ? (
                            <span className="text-green-600 whitespace-nowrap">
                              {t("checkout.freeShipping")}
                            </span>
                          ) : (
                            `- ${money(totals, "promo_discount", currencySymbol)}`
                          )}
                        </span>
                      </div>
                    </div>
                  )}

                {/* CASHBACK PROMO - Shown as reward */}
                {payment_summary.promo_applied &&
                  payment_summary.promo_discount > 0 &&
                  isCashbackPromo && (
                    <div className="bg-blue-50 p-3 rounded-lg border border-blue-200">
                      <div className="flex justify-between items-start text-blue-700">
                        {/* Left Section */}
                        <div className="flex flex-col gap-2">
                          <div className="flex items-center gap-2">
                            <Wallet className="w-4 h-4" />
                            <span className="font-medium">
                              {t("checkout.promo")}:{" "}
                              {payment_summary?.promo_code}
                            </span>
                          </div>

                          <p className="text-xs text-blue-600 dark:text-blue-400 italic">
                            {t("cashbackPendingNote")}
                          </p>
                        </div>

                        {/* Right Section */}
                        <div className="flex flex-col items-end gap-1">
                          {isFreeShipping && (
                            <span className="text-green-600 font-semibold whitespace-nowrap">
                              {t("checkout.freeShipping")}
                            </span>
                          )}

                          <span className="font-semibold whitespace-nowrap text-blue-600">
                            + {money(totals, "promo_discount", currencySymbol, totals)}
                          </span>
                        </div>
                      </div>
                    </div>
                  )}
              </>
            ) : null}

            {/* Wallet Section */}
            {isWalletEnabled ? (
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Switch
                    isSelected={isWalletUse}
                    isDisabled={
                      completed || isLoading || !!cartError || payment_summary.wallet_balance == 0
                    }
                    onValueChange={handleUseWalletSwitchToggle}
                    classNames={{ label: "text-xs", thumbIcon: "w-2" }}
                    color="success"
                  >
                    {t("checkout.useWalletBalance")}
                  </Switch>
                  <span className="text-xxs text-foreground/80">
                    ({money(totals, "wallet_balance", currencySymbol, totals)})
                  </span>
                </div>
              </div>
            ) : null}

            {isWalletEnabled && isWalletUse && payment_summary.wallet_amount_used > 0 ? (
              <>
                <div className="flex justify-between text-green-600">
                  <span>{t("checkout.walletAmountUsed")}</span>
                  <span>
                    -{money(totals, "wallet_amount_used", currencySymbol, totals)}
                  </span>
                </div>

                {/* Remaining Wallet Balance */}
                <div className="flex justify-between text-blue-600">
                  <span>{t("checkout.remainingWalletBalance")}</span>
                  <span>
                    {formatMoney(Number(payment_summary?.wallet_balance ?? 0) -
                      Number(payment_summary?.wallet_amount_used ?? 0), totals, currencySymbol)}
                  </span>
                </div>
              </>
            ) : null}

            {/* Loyalty Points Section */}
            {isLoyaltyEnabled && loyalty ? (
              <div className="flex flex-col gap-1">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Switch
                      isSelected={isLoyaltyUse}
                      isDisabled={completed || isLoading || !canRedeemLoyalty}
                      onValueChange={handleUseLoyaltySwitchToggle}
                      classNames={{ label: "text-xs", thumbIcon: "w-2" }}
                      color="warning"
                    >
                      {t("checkout.useLoyaltyPoints")}
                    </Switch>
                    <span className="text-xxs text-foreground/80 flex items-center gap-1">
                      <Coins size={12} className="text-amber-500" />
                      {loyalty.user_points_balance || 0}
                    </span>
                  </div>
                </div>

                {/* Upsell nudge: order too small to redeem */}
                {loyalty.user_points_balance > 0 &&
                !loyalty.is_eligible_for_redemption &&
                loyaltyRemainingToRedeem > 0 ? (
                  <span className="text-xxs text-warning-600">
                    {t("checkout.loyaltyMinOrderNudge", {
                      amount: formatMoney(loyaltyRemainingToRedeem, cart, currencySymbol),
                      points: loyalty.user_points_balance,
                    })}
                  </span>
                ) : null}

                {/* Redeemable cap hint (limits apply, not all points usable) */}
                {canRedeemLoyalty && !isLoyaltyUse ? (
                  <span className="text-xxs text-foreground/60">
                    {t("checkout.loyaltyRedeemHint", {
                      points: loyalty.max_points_redeemable,
                    })}
                  </span>
                ) : null}
              </div>
            ) : null}

            {isLoyaltyUse &&
            (payment_summary.loyalty_points_redeemed ?? 0) > 0 ? (
              <div className="flex justify-between text-green-600">
                <span>
                  {t("checkout.loyaltyPointsRedeemed", {
                    points: payment_summary.loyalty_points_redeemed,
                  })}
                </span>
                <span>
                  -{money(totals, "loyalty_points_discount_amount", currencySymbol, totals)}
                </span>
              </div>
            ) : null}

            {/* Points earned on this order */}
            {isLoyaltyEnabled &&
            (loyalty?.potential_points_to_earn ?? 0) > 0 ? (
              <div className="flex items-center gap-1 text-xxs text-amber-600">
                <Coins size={12} />
                <span>
                  {t("checkout.loyaltyPotentialEarn", {
                    points: loyalty?.potential_points_to_earn,
                  })}
                </span>
              </div>
            ) : null}

            {/* Total Section */}
            <Divider orientation="horizontal" />
            <div className="pt-1">
              <div className="flex justify-between text-lg font-semibold">
                <span>{t("checkout.totalAmount")}</span>
                <span>
                  {money(totals, "payable_amount", currencySymbol, totals)}
                </span>
              </div>
            </div>
          </div>

          {hasValidationErrors ? (
            <Alert
              color="warning"
              variant="faded"
              title={t("checkout.validation.title", {
                defaultValue: "Cannot proceed to checkout",
              })}
              description={
                <ul className="list-disc pl-5">
                  {validationErrors.map((error, index) => (
                    <li key={index}>{error}</li>
                  ))}
                </ul>
              }
              classNames={{
                description: "text-xs",
              }}
            />
          ) : payment_summary.payable_amount == 0 && isWalletUse ? (
            <Button
              className="w-full font-medium py-3 rounded-lg"
              color="primary"
              isLoading={loading}
              onPress={() => handleCheckoutClick(true)}
              isDisabled={completed || isLoading || loading || hasValidationErrors}
            >
              {t("checkout.payWithWallet")} (
              {money(totals, "payable_amount", currencySymbol, totals)})
            </Button>
          ) : (
            <Button
              className="w-full font-medium py-3 rounded-lg"
              color="primary"
              isLoading={loading}
              onPress={() => handleCheckoutClick(false)}
              isDisabled={completed || isLoading || loading || hasValidationErrors}
            >
              {t("checkout.proceedToCheckout")} (
              {money(totals, "payable_amount", currencySymbol, totals)})
            </Button>
          )}
        </CardFooter> : <CardFooter className="flex-col gap-3">
          <Alert color="warning" description={t("multiCurrency.mixedCart")} />
          <Button isDisabled color="primary">{t("checkout.proceedToCheckout")}</Button>
        </CardFooter>}
      </Card>
      <PaymentModal open={isOpen && !completed} onOpenChange={onOpenChange} />
      <ConfirmationModal
        isOpen={showClearCartModal}
        onClose={() => setShowClearCartModal(false)}
        onConfirm={async () => {
          await clearCart();
          setShowClearCartModal(false);
          addToast({
            title: t("checkout.cartCleared.title"),
            description: t("checkout.cartCleared.description"),
            color: "success",
          });
          await router.push("/");
          updateCartData(true, false);
        }}
        title="Clear Cart"
        icon={<Trash2 className="w-4 h-4" />}
        description={t("checkout.clearCartModal.description")}
        confirmText={t("checkout.clearCartModal.confirmText")}
        cancelText={t("checkout.clearCartModal.cancelText")}
        variant="danger"
        alertTitle={t("checkout.clearCartModal.alertTitle")}
        alertDescription={t("checkout.clearCartModal.alertDescription")}
      />
    </div>
  );
};

export default CheckoutSection;
