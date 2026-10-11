import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import RatingModal from "@/components/Modals/RatingModal";
import ReturnOrderItemModal from "@/components/Modals/ReturnOrderItemModal";
import { orderStatusColorMap } from "@/config/constants";
import { useSettings } from "@/contexts/SettingsContext";
import { formatString } from "@/helpers/validator";
import {
  getFormattedDate,
  getOrderStatusBtnConfig,
  getUserDataFromRedux,
} from "@/helpers/getters";
import UserLayout from "@/layouts/UserLayout";
import { Order, OrderItem } from "@/types/ApiResponse";
import { Button, Chip, useDisclosure } from "@heroui/react";
import { ArrowLeft, CalendarClock, Download, RotateCcw } from "lucide-react";
import { useRouter } from "next/router";
import React, { useEffect, useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import OrderSummary from "./OrderSummary";
import PaymentInfo from "./PaymentInfo";
import DeliveryInfo from "./DeliveryInfo";
import ShippingInfo from "./ShippingInfo";
import OrderItems from "./OrderItems";
import OrderNote from "./OrderNote";
import SellerFeedbacks from "./SellerFeedbacks";
import RescheduleNotice from "./RescheduleNotice";
import OrderRecoveryModal from "@/components/Modals/OrderRecoveryModal";
import { canRecoverOrder, getRecoveryStoreId } from "@/helpers/orderRecovery";
import PageHead from "@/SEO/PageHead";
import CancelOrderItemModal from "@/components/Modals/CancelOrderItemModal";
import { addOrderTip, reorderOrder, xenditCreateOrder } from "@/routes/api";
import { addToast } from "@heroui/react";
import { updateCartData } from "@/helpers/updators";
import TipSection from "@/components/Cart/TipSection";
import {
  getTipPaymentMethods,
  shouldShowOrderTipBox,
} from "@/helpers/riderTip";
import { collectRazorpayPayment } from "@/components/PaymentGateway/collectRazorpayPayment";
import { collectPaystackPayment } from "@/components/PaymentGateway/collectPaystackPayment";
import TipStripePaymentModal from "@/components/Modals/TipStripePaymentModal";
import { mutate } from "swr";
import dynamic from "next/dynamic";

const TrackOrderModal = dynamic(
  () => import("@/components/Modals/TrackOrderModal"),
  { ssr: false },
);

interface OrderDetailPageViewProps {
  order: Order;
}

// OrderDetailPageView component
const OrderDetailPageView: React.FC<OrderDetailPageViewProps> = ({ order }) => {
  const { currencySymbol, paymentSettings } = useSettings();
  const { t } = useTranslation();
  const router = useRouter();
  const { isOpen, onClose, onOpen } = useDisclosure();
  const {
    isOpen: isDeliveryRatingOpen,
    onClose: onDeliveryRatingClose,
    onOpen: onDeliveryRatingOpen,
  } = useDisclosure();

  const {
    isOpen: isOpenProductReview,
    onClose: onProductReviewClose,
    onOpen: onProductReviewOpen,
  } = useDisclosure();

  const {
    isOpen: isCancelOpen,
    onClose: onCancelClose,
    onOpen: onCancelOpen,
  } = useDisclosure();

  const buttonConfig = getOrderStatusBtnConfig(order.status);

  const [selectedItem, setSelectedItem] = useState<OrderItem | null>(null);
  const [selectedSeller, setSelectedSeller] = useState<{
    sellerId: number | string;
    sellerName?: string;
    itemsID?: string[];
    existingReview?: {
      id?: number | string;
      rating?: number;
      title?: string;
      comment?: string;
      review_images?: string[];
    } | null;
  } | null>(null);

  const {
    isOpen: isSellerReviewOpen,
    onOpen: onSellerReviewOpen,
    onClose: onSellerReviewClose,
  } = useDisclosure();

  const {
    isOpen: isReturnOpen,
    onOpen: onReturnOpen,
    onClose: onReturnClose,
  } = useDisclosure();

  const {
    isOpen: isRescheduleOpen,
    onOpen: onRescheduleOpen,
    onClose: onRescheduleClose,
  } = useDisclosure();

  /**
   * The Reschedule button is driven ENTIRELY by the backend's recovery fields
   * (`reschedule_status` + `reschedule_deadline`), not by order status: it
   * shows only while the order is flagged `needs_reschedule` and the deadline
   * hasn't passed. The clock has to advance, otherwise a page left open would
   * keep offering a reschedule the backend now refuses; a minute matches the
   * deadline's granularity.
   */
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 60_000);
    return () => clearInterval(id);
  }, []);

  const canReschedule = canRecoverOrder(order, now);

  const [isReordering, setIsReordering] = useState(false);

  const handleReorder = async () => {
    try {
      setIsReordering(true);
      const response = await reorderOrder(order.id);
      if (response.success) {
        addToast({
          title:
            t("pages.ordersPage.reorderSuccess") || "Reordered successfully",
          color: "success",
        });
        await updateCartData(true, true);
      } else {
        addToast({
          title:
            response.message ||
            t("pages.ordersPage.reorderFailed") ||
            "Reorder failed",
          color: "danger",
        });
      }
    } catch (error) {
      console.error("Reorder error:", error);
      addToast({
        title:
          t("pages.ordersPage.reorderFailed") ||
          "An error occurred during reorder",
        color: "danger",
      });
    } finally {
      setIsReordering(false);
    }
  };

  const handleProductReview = (item: OrderItem) => {
    setSelectedItem(item);
    onProductReviewOpen();
  };

  const tipPaymentMethods = getTipPaymentMethods(paymentSettings);
  const showTipBox = shouldShowOrderTipBox(order) && tipPaymentMethods.length > 0;

  // Stripe tips collect through a modal (PaymentElement needs a mount), so
  // the TipSection submit promise is parked here until the modal settles.
  const [stripeTipAmount, setStripeTipAmount] = useState<number | null>(null);
  const stripeTipResolver = useRef<((ok: boolean) => void) | null>(null);

  const recordTip = async (
    amount: number,
    paymentMethod: string,
    paymentRefs: {
      razorpay_order_id?: string;
      transaction_id?: string;
      razorpay_signature?: string;
      reference?: string;
    } = {},
  ): Promise<boolean> => {
    const res = await addOrderTip({
      orderSlug: order.slug,
      rider_tip_amount: amount,
      payment_method: paymentMethod,
      ...paymentRefs,
    });
    if (!res.success) {
      addToast({
        title: res.message || t("tip.updateFailed"),
        color: "danger",
      });
      return false;
    }
    addToast({ title: t("tip.thankYou"), color: "success" });
    // Revalidate the page's SWR entry so the new tip shows up right away.
    await mutate(`/api/orders/detail/${router.query.slug}`);
    return true;
  };

  // The tip endpoint only records — every gateway method must collect the
  // money up front, and a dismissed/failed popup records nothing.
  const handleAddTip = async (
    amount: number,
    paymentMethod?: string,
  ): Promise<boolean> => {
    switch (paymentMethod) {
      case "wallet":
        return recordTip(amount, paymentMethod);

      case "razorpay": {
        const user = getUserDataFromRedux();
        const payment = await collectRazorpayPayment({
          keyId: paymentSettings?.razorpayKeyId || "",
          amount,
          description: t("tip.riderTip"),
          prefill: {
            name: user?.name || "",
            email: user?.email || "",
            contact: user?.mobile || "",
          },
        });
        if (!payment) return false;
        return recordTip(amount, paymentMethod, {
          razorpay_order_id: payment.razorpay_order_id,
          transaction_id: payment.razorpay_payment_id,
          razorpay_signature: payment.razorpay_signature,
        });
      }

      case "paystack": {
        const payment = await collectPaystackPayment({ amount });
        if (!payment) return false;
        // Checkout sends the reference under both keys; keep parity.
        return recordTip(amount, paymentMethod, {
          reference: payment.reference,
          transaction_id: payment.reference,
        });
      }

      case "stripe":
        return new Promise<boolean>((resolve) => {
          stripeTipResolver.current = resolve;
          setStripeTipAmount(amount);
        });

      case "xenditPayment": {
        const res = await addOrderTip({
          orderSlug: order.slug,
          rider_tip_amount: amount,
          payment_method: paymentMethod,
        });

        if (!res.success) {
          addToast({
            title: res.message || t("tip.updateFailed"),
            color: "danger",
          });
          return false;
        }

        const user = getUserDataFromRedux();
        const [givenNames, ...surnameParts] = (user?.name || "")
          .trim()
          .split(/\s+/);
          
        const xenditRes = await xenditCreateOrder({
          amount: amount,
          order_id: order.id,
          redirect_url: `${window.location.origin}/my-account/orders/${order.slug}`,
          customer: {
            given_names: givenNames || undefined,
            surname: surnameParts.join(" ") || undefined,
            email: user?.email || undefined,
            mobile_number: user?.mobile || undefined,
          },
        });

        const xenditData = xenditRes.data;
        const checkoutLink =
          xenditData?.payment_response?.link ||
          xenditData?.payment_response?.invoice_url ||
          xenditData?.payment_response?.checkout_url ||
          xenditData?.payment_response?.url ||
          xenditData?.invoice_url ||
          xenditData?.checkout_url ||
          xenditData?.link ||
          xenditData?.url;

        if (checkoutLink) {
          window.location.href = checkoutLink;
          return new Promise(() => {}); // Wait forever for redirect
        } else {
          addToast({ title: t("tip.xendit_link_invalid", { defaultValue: "Failed to generate Xendit payment link" }), color: "danger" });
          return false;
        }
      }

      default:
        return false;
    }
  };

  const settleStripeTip = (ok: boolean) => {
    setStripeTipAmount(null);
    stripeTipResolver.current?.(ok);
    stripeTipResolver.current = null;
  };

  return (
    <>
      <MyBreadcrumbs
        breadcrumbs={[
          { href: "/my-account/orders", label: t("myOrders") },
          { href: "#", label: `${t("order")} #${order.id}` },
        ]}
      />
      <PageHead pageTitle={`${t("order")} #${order?.id || ""}`} noindex />

      <UserLayout activeTab="orders">
        <div className="w-full mx-auto">
          {/* Above the header: a flagged order needs acting on before anything
              else on this page matters. Renders nothing when unflagged. */}
          <RescheduleNotice
            order={order}
            onRecovered={() => router.replace(router.asPath)}
          />

          {/* Header Section */}
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4">
            <div className="flex items-center gap-3">
              <Button
                isIconOnly
                variant="flat"
                color="default"
                size="sm"
                onPress={() => router.push("/my-account/orders")}
              >
                <ArrowLeft className="w-4 h-4" />
              </Button>
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <h1 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {t("order")} #{order.id}
                  </h1>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {t("placedOn")} {getFormattedDate(order.created_at)}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Chip
                color={orderStatusColorMap(order?.status)}
                variant="flat"
                size="sm"
                radius="sm"
                className="text-xs h-8 cursor-pointer"
                title={formatString(order?.status)}
              >
                {formatString(order?.status)}
              </Chip>

              {order.status !== "cancelled" && (
                <Button
                  color="primary"
                  variant="flat"
                  size="sm"
                  startContent={<Download className="w-4 h-4" />}
                  onPress={() => {
                    if (order.invoice) {
                      window.open(order.invoice, "_blank");
                    }
                  }}
                  title={t("invoice")}
                  className="text-xs"
                >
                  {t("invoice")}
                </Button>
              )}

              {canReschedule && (
                <Button
                  color="primary"
                  variant="bordered"
                  size="sm"
                  startContent={<CalendarClock className="w-4 h-4" />}
                  onPress={onRescheduleOpen}
                  title={t("order_recovery.reschedule_cta")}
                  className="text-xs"
                >
                  {t("order_recovery.reschedule_cta")}
                </Button>
              )}

              {order.status === "delivered" && buttonConfig.reorder && (
                <Button
                  color="primary"
                  variant="bordered"
                  size="sm"
                  startContent={<RotateCcw className="w-4 h-4" />}
                  onPress={handleReorder}
                  isLoading={isReordering}
                  title={t("reorder")}
                  className="text-xs"
                >
                  {t("reorder")}
                </Button>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Left Column - Order Items & Details */}
            <div className="lg:col-span-2 space-y-4">
              {/* Order Items */}
              <OrderItems
                onOpen={onOpen}
                order={order}
                currencySymbol={currencySymbol}
                handleProductReview={handleProductReview}
                onReturnOpen={
                  buttonConfig.returnOrder ? onReturnOpen : undefined
                }
                onCancelOpen={
                  buttonConfig.cancelOrder ? onCancelOpen : undefined
                }
              />

              {/* Shipping Address */}
              <ShippingInfo order={order} />

              {/* Order Note */}
              <OrderNote order={order} />

              {/* Seller Feedbacks (grouped) */}
              {buttonConfig.review && (
                <SellerFeedbacks
                  seller_feedbacks={order.seller_feedbacks}
                  items={order.items}
                  onOpenReview={({
                    sellerId,
                    sellerName,
                    existingReview,
                    itemsID,
                  }) => {
                    setSelectedSeller({
                      sellerId,
                      sellerName,
                      existingReview,
                      itemsID,
                    });
                    onSellerReviewOpen();
                  }}
                />
              )}
            </div>

            {/* Right Column - Order Summary & Actions */}
            <div className="space-y-4">
              {/* Order Summary */}
              <OrderSummary currencySymbol={currencySymbol} order={order} />

              {/* Rider Tip — additive after checkout; hidden once the order
                  reaches a terminal status (the backend doesn't gate this,
                  so the frontend must). */}
              {showTipBox && order.tipping_config && (
                <TipSection
                  mode="order"
                  config={order.tipping_config}
                  currentTip={Number(order.rider_tip_amount || 0)}
                  onSubmit={handleAddTip}
                  paymentMethods={tipPaymentMethods}
                />
              )}

              {/* Payment Information */}
              <PaymentInfo order={order} />

              {/* Delivery Information */}
              <DeliveryInfo
                order={order}
                onDeliveryRatingOpen={onDeliveryRatingOpen}
              />
            </div>

            {buttonConfig.review && order.delivery_boy_id && (
              <RatingModal
                isOpen={isDeliveryRatingOpen}
                onClose={onDeliveryRatingClose}
                deliveryBoyId={order.delivery_boy_id}
                orderId={order.id}
                type="delivery"
                existingReview={
                  order.is_delivery_feedback_given && order.delivery_feedback
                    ? {
                        id: (order as any)?.delivery_feedback?.id,
                        rating: order.delivery_feedback.rating,
                        title: order.delivery_feedback.title,
                        comment: order.delivery_feedback.description,
                      }
                    : null
                }
              />
            )}
          </div>
        </div>
        {buttonConfig.trackOrder && (
          <TrackOrderModal isOpen={isOpen} onClose={onClose} order={order} />
        )}
        {/* Rating Modal */}
        {selectedItem && (
          <RatingModal
            isOpen={isOpenProductReview}
            onClose={() => {
              onProductReviewClose();
              setSelectedItem(null);
            }}
            productId={selectedItem.product_id}
            orderItemId={selectedItem.id}
            onSuccess={() => {}}
            type="product"
          />
        )}
        {/* Seller Rating Modal */}
        {selectedSeller && (
          <RatingModal
            isOpen={isSellerReviewOpen}
            onClose={() => {
              onSellerReviewClose();
              setSelectedSeller(null);
            }}
            type="seller"
            orderId={order.id}
            sellerId={selectedSeller.sellerId}
            sellerName={selectedSeller.sellerName}
            existingReview={selectedSeller.existingReview}
            onSuccess={() => {}}
            orderItemId={selectedSeller?.itemsID?.[0] || "0"}
          />
        )}
        {/* Return Order Items Modal */}
        {buttonConfig.returnOrder && (
          <ReturnOrderItemModal
            isOpen={isReturnOpen}
            onClose={onReturnClose}
            order={order}
          />
        )}
        <CancelOrderItemModal
          isOpen={isCancelOpen}
          onClose={onCancelClose}
          order={order}
          onItemCancelled={onCancelClose}
        />
        {/* Stripe-paid tips: the tip is recorded only after the
            PaymentIntent succeeds inside the modal. */}
        {stripeTipAmount !== null && (
          <TipStripePaymentModal
            isOpen
            amount={stripeTipAmount}
            onPaid={async (paymentIntentId) => {
              const ok = await recordTip(stripeTipAmount, "stripe", {
                transaction_id: paymentIntentId,
              });
              settleStripeTip(ok);
            }}
            onCancel={() => settleStripeTip(false)}
          />
        )}
        {/* The same recovery flow the banner offers — flagged orders only, so
            the cancel-order path stays available. Store rules come inline
            from the order when the backend sends them. */}
        {canReschedule && (
          <OrderRecoveryModal
            isOpen={isRescheduleOpen}
            onClose={onRescheduleClose}
            orderId={order.id}
            storeConfig={order.store}
            storeId={order.store?.id ?? getRecoveryStoreId(order)}
            onRecovered={() => router.replace(router.asPath)}
          />
        )}
      </UserLayout>
    </>
  );
};

export default OrderDetailPageView;
