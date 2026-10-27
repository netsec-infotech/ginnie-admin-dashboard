import { formatMoney, money } from "@/helpers/currency";
import OrderItemReviewCard from "@/components/Modals/OrderItemReviewCard";
import FilePreview from "@/components/FilePreview";
import OrderDeliveryOtp from "@/components/OrderDeliveryOtp";
import { getItemDisplayStatus } from "@/config/constants";
import { getOrderStatusBtnConfig } from "@/helpers/getters";
import { canCancelOrderItem } from "@/helpers/orderCancellation";
import { getItemOtps } from "@/helpers/orderOtp";
import { formatString } from "@/helpers/validator";
import { Order, OrderItem } from "@/types/ApiResponse";
import { Button, Card, CardBody, CardHeader, Chip, Image } from "@heroui/react";
import { Package, ShoppingBag, Star, Truck, Undo2 } from "lucide-react";
import Link from "next/link";
import React, { FC, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import Lightbox from "yet-another-react-lightbox";

interface GroupedStore {
  store: OrderItem["store"];
  items: OrderItem[];
}

interface OrderItemsProps {
  order: Order;
  currencySymbol: string;
  onOpen: () => void;
  handleProductReview: (item: OrderItem) => void;
  onReturnOpen?: () => void;
  onCancelOpen?: () => void;
}

/**
 * Delivery + return-pickup OTPs for a single order item.
 *
 * Delivery codes are item-level only in item-wise mode. Each return request
 * still carries its own pickup OTP, independently of the delivery OTP mode.
 */
const ItemOtpChips: FC<{ item: OrderItem; order: Order }> = ({ item, order }) => {
  const { t } = useTranslation();
  const { deliveryOtp, returnPickupOtp } = getItemOtps(item, order);

  if (!deliveryOtp && !returnPickupOtp) return null;

  return (
    <div className="flex flex-wrap items-center gap-2" data-testid="item-otps">
      {deliveryOtp && (
        <Chip
          size="sm"
          color="primary"
          variant="bordered"
          radius="sm"
          startContent={<Truck className="w-3 h-3" />}
          title={`${t("deliveryOtp")}: ${deliveryOtp}`}
          classNames={{ content: "text-xxs sm:text-xs font-medium" }}
        >
          {t("deliveryOtp")}: {deliveryOtp}
        </Chip>
      )}

      {returnPickupOtp && (
        <Chip
          size="sm"
          color="warning"
          variant="bordered"
          radius="sm"
          startContent={<Package className="w-3 h-3" />}
          title={`${t("returnPickupOtp")}: ${returnPickupOtp}`}
          classNames={{ content: "text-xxs sm:text-xs font-medium" }}
        >
          {t("returnPickupOtp")}: {returnPickupOtp}
        </Chip>
      )}
    </div>
  );
};

const OrderItems: FC<OrderItemsProps> = ({
  order,
  onOpen,
  currencySymbol,
  handleProductReview,
  onReturnOpen,
  onCancelOpen,
}) => {
  const buttonConfig = getOrderStatusBtnConfig(order.status);
  const { t } = useTranslation();

  const groupedItems = useMemo(() => {
    const grouped = order.items.reduce(
      (acc, item) => {
        const storeId = item.store.id;
        if (!acc[storeId]) {
          acc[storeId] = {
            store: item.store,
            items: [],
          };
        }
        acc[storeId].items.push(item);
        return acc;
      },
      {} as Record<number, GroupedStore>,
    );

    return Object.values(grouped);
  }, [order.items]);

  const ProductImageWithLightbox = ({
    src,
    alt,
  }: {
    src?: string | null;
    alt: string;
  }) => {
    const [isOpen, setIsOpen] = useState(false);

    if (!src) {
      return (
        <div className="w-16 h-16 sm:w-20 sm:h-20 rounded-md object-cover shrink-0 bg-gray-100 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-[10px] px-2 text-gray-500 dark:text-gray-400 text-center">
          {alt || "N/A"}
        </div>
      );
    }

    return (
      <>
        <Image
          src={src}
          alt={alt}
          className="w-16 h-16 sm:w-20 sm:h-20 rounded-md object-cover shrink-0 cursor-pointer"
          onClick={() => setIsOpen(true)}
        />

        {isOpen && (
          <Lightbox
            open={isOpen}
            close={() => setIsOpen(false)}
            slides={[{ src }]}
          />
        )}
      </>
    );
  };

  return (
    <Card shadow="sm" radius="sm">
      <CardHeader className="pb-2 flex flex-col sm:flex-row items-start sm:items-center justify-between w-full gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <ShoppingBag className="w-4 h-4 text-gray-600 dark:text-gray-300" />
          <h3 className="text-sm font-medium text-gray-900 dark:text-gray-100">
            {t("orderItems")} ({order.items.length})
          </h3>
          <OrderDeliveryOtp order={order} />
        </div>

        <div className="flex items-center flex-wrap gap-2">
          {onCancelOpen &&
            order.items.some((item) => canCancelOrderItem(item, order.status)) && (
              <Button
                size="sm"
                variant="light"
                startContent={<Package className="w-3 h-3" />}
                className="text-xs h-6 sm:h-5"
                onPress={onCancelOpen}
                title={t("cancel")}
              >
                {t("cancel")}
              </Button>
            )}
          {buttonConfig.trackOrder && (
            <Button
              size="sm"
              color="primary"
              variant="light"
              className="text-xs h-6 sm:h-5"
              onPress={onOpen}
              startContent={<Truck className="w-4 h-4" />}
              title={t("trackOrder")}
            >
              {t("trackOrder")}
            </Button>
          )}
          {buttonConfig.returnOrder &&
            order.items.some((item) => item.return_eligible) &&
            onReturnOpen && (
              <Button
                size="sm"
                color="primary"
                variant="light"
                className="text-xs h-6 sm:h-5"
                onPress={onReturnOpen}
                startContent={<Package className="w-4 h-4" />}
                title={t("return")}
              >
                {t("return")}
              </Button>
            )}
        </div>
      </CardHeader>
      <CardBody className="pt-0">
        <div className="space-y-4">
          {groupedItems.map((group) => (
            <div
              key={group.store.id}
              className="bg-gray-50 dark:bg-gray-800/30 rounded-lg p-3 border border-gray-200 dark:border-gray-700"
            >
              {/* Store Header */}
              <div className="mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                <div className="flex flex-wrap items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                  <span>{t("soldBySection.storeLabel")}</span>
                  <Link
                    title={group.store.name}
                    href={`/stores/${group.store.slug}`}
                    className="font-semibold text-gray-900 dark:text-gray-100 hover:text-primary"
                  >
                    {group.store.name}
                  </Link>
                </div>
              </div>

              {/* Items in this store */}
              <div className="space-y-3">
                {group.items.map((item, itemIndex) => (
                  <div key={item.id}>
                    <div className="flex flex-row justify-between w-full gap-3 sm:gap-4">
                      {/* Product Image */}
                      <div className="shrink-0 self-start">
                        <ProductImageWithLightbox
                          src={item.product?.image}
                          alt={item.variant_title}
                        />
                      </div>

                      {/* Content Container */}
                      <div className="flex-1 min-w-0 space-y-3">
                        {/* Product Name and Variant */}
                        <div className="space-y-1">
                          <h3 className="font-medium text-sm">
                            {item.product?.slug ? (
                              <Link
                                title={item.product?.name || ""}
                                href={`/products/${item.product?.slug}`}
                                className="hover:text-blue-600 dark:hover:text-blue-400 wrap-break-word"
                              >
                                {item.product?.name || item.title}
                              </Link>
                            ) : (
                              <span title={item.title}>{item.title}</span>
                            )}
                          </h3>

                          {!(item as any)?.product && (
                            <p className="text-xxs sm:text-xs text-red-600 dark:text-red-400">
                              {item.title} product is deleted.
                            </p>
                          )}

                          <div className="flex flex-wrap gap-2 items-center">
                            {item.variant?.title && (
                              <div className="text-xxs sm:text-xs text-foreground/50">
                                {item.variant.title}
                              </div>
                            )}
                            {/* ADDONS LIST */}
                            {item.addons && item.addons.length > 0 && (
                              <div className="text-[10px] text-foreground/40 leading-tight mt-0.5 break-words">
                                {item.addons
                                  .map(
                                    (addon: any) =>
                                      addon.item?.title || addon.title,
                                  )
                                  .filter(Boolean)
                                  .join(", ")}
                              </div>
                            )}
                          </div>
                        </div>

                        {/* Item-wise delivery codes and return pickup codes
                            stay with the corresponding order item. */}
                        <ItemOtpChips item={item} order={order} />

                        {/* Price, Status and Review Row */}
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          {/* Price Info */}
                          <div className="flex flex-wrap items-center gap-2 sm:gap-3 text-xs">
                            <span className="text-gray-600 dark:text-gray-300">
                              {formatMoney(Number(item.price) +
                                Number(item.tax_amount), order, currencySymbol)} × {item.quantity}
                            </span>
                            <p className="font-semibold text-gray-900 dark:text-gray-100">
                              {money(item, "subtotal", currencySymbol, order)}
                            </p>
                          </div>

                          {/*
                            Status Chip — a live return supersedes the item's
                            own status. An item stays "delivered" forever, so
                            showing that next to an in-progress return told the
                            customer nothing about what they're waiting on.
                          */}
                          {(() => {
                            const display = getItemDisplayStatus(item);
                            const label = formatString(display.status);

                            return !display.isReturn ? (
                              <Chip
                                color={display.color}
                                variant="flat"
                                radius="sm"
                                className="hover:cursor-pointer"
                                classNames={{ content: "text-xs" }}
                                title={label}
                              >
                                {label}
                              </Chip>
                            ) : null;
                          })()}
                        </div>

                        {/* Review Section */}
                        {item.status === "delivered" && (
                          <div className="pt-1">
                            {item.is_user_review_given ? (
                              <OrderItemReviewCard
                                userReview={item.user_review}
                              />
                            ) : (
                              <Button
                                onPress={() => handleProductReview(item)}
                                size="sm"
                                variant="flat"
                                color="warning"
                                className="text-xs h-7 px-3"
                                startContent={<Star size={12} />}
                                title={t("review")}
                              >
                                {t("review")}
                              </Button>
                            )}
                          </div>
                        )}

                        {/* Attachments Section */}
                        {item.attachments && item.attachments.length > 0 && (
                          <FilePreview attachments={item.attachments} />
                        )}

                        {/* Return Details */}
                        {item.returns && item.returns.length > 0 && (
                          <div className="mt-2 p-3 bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30 rounded-lg">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100 dark:border-blue-800/30 pb-2 mb-2">
                              <div className="flex items-center gap-2">
                                <Undo2 className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                <span className="font-semibold text-sm text-blue-900 dark:text-blue-300">
                                  {t("returnDetails", "Return Details")}
                                </span>
                              </div>
                              <Chip
                                size="md"
                                color={getItemDisplayStatus(item).color}
                                variant="flat"
                                className="ml-auto"
                              >
                                {formatString(item.returns[0].return_status)}
                              </Chip>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-4 text-xs text-gray-700 dark:text-gray-300">
                              {item.returns[0].reason && (
                                <div>
                                  <span className="text-gray-500 dark:text-gray-400">
                                    {t("reason", "Reason")}:
                                  </span>{" "}
                                  <span className="font-medium text-gray-800 dark:text-gray-200">
                                    {item.returns[0].reason}
                                  </span>
                                </div>
                              )}
                              {Number(item.returns[0].refund_amount) > 0 && (
                                <div>
                                  <span className="text-gray-500 dark:text-gray-400">
                                    {t("refundAmount", "Refund Amount")}:
                                  </span>{" "}
                                  <span className="font-semibold text-success-600 dark:text-success-500">
                                    {money(item.returns[0], "refund_amount", currencySymbol, order)}
                                  </span>
                                </div>
                              )}
                              {item.returns[0].store_rejection_reason && (
                                <div className="col-span-full mt-1 p-2 bg-danger-50 dark:bg-danger-900/20 rounded-md border border-danger-100 dark:border-danger-800/30">
                                  <span className="text-danger-700 dark:text-danger-400 font-medium">
                                    {t("rejectionNote", "Rejection Note")}:
                                  </span>{" "}
                                  <span className="text-danger-600 dark:text-danger-300">
                                    {item.returns[0].store_rejection_reason}
                                  </span>
                                </div>
                              )}
                            </div>
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Divider between items */}
                    {itemIndex < group.items.length - 1 && (
                      <div className="my-3 border-t border-gray-200 dark:border-gray-700" />
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
};

export default OrderItems;
