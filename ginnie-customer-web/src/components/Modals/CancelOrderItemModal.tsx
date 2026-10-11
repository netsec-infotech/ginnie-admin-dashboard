import { FC, useState } from "react";
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Divider,
  Avatar,
  addToast,
  Chip,
  ScrollShadow,
} from "@heroui/react";
import { Order, OrderItem } from "@/types/ApiResponse";
import { isStatusBeforeOrAt } from "@/helpers/getters";
import { cancelOrderItem } from "@/routes/api";
import ConfirmationModal from "./ConfirmationModal";
import { Trash } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useRouter } from "next/router";
import { orderStatusColorMap } from "@/config/constants";
import { formatString } from "@/helpers/validator";
import Link from "next/link";
import { useSettings } from "@/contexts/SettingsContext";

interface CancelOrderItemModalProps {
  isOpen: boolean;
  onClose: () => void;
  order: Order;
  onItemCancelled?: (itemId: string | number) => void;
}

const CancelOrderItemModal: FC<CancelOrderItemModalProps> = ({
  isOpen,
  onClose,
  order,
  onItemCancelled,
}) => {
  const { t } = useTranslation();
  const router = useRouter();
  const { currencySymbol } = useSettings();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState<OrderItem | null>(null);
  const [loadingItemId, setLoadingItemId] = useState<string | number | null>(
    null
  );

  const openConfirm = (item: OrderItem) => {
    setSelectedItem(item);
    setConfirmOpen(true);
  };

  const closeConfirm = () => {
    setSelectedItem(null);
    setConfirmOpen(false);
  };

  const handleCancelItem = async () => {
    if (!selectedItem) return;
    setLoadingItemId(selectedItem.id);
    try {
      const res = await cancelOrderItem({
        orderItemId: String(selectedItem.id),
      });
      if (res.success) {
        addToast({
          title: t("cancel_item_success_title") || t("success"),
          description: res.message || t("cancel_item_success_msg"),
          color: "success",
        });
        router.push(router.asPath);
        if (onItemCancelled) {
          onItemCancelled(selectedItem.id);
        }
        closeConfirm();
      } else {
        addToast({
          title: t("cancel_item_failed_title") || t("error"),
          description: res.message || t("cancel_item_failed_msg"),
          color: "danger",
        });
      }
    } catch (err) {
      console.error("Cancel item error:", err);
      addToast({
        title: t("cancel_item_failed_title") || t("error"),
        description: t("something_went_wrong"),
        color: "danger",
      });
    } finally {
      setLoadingItemId(null);
    }
  };

  // Safely determine if a product allows cancellation. Checks multiple possible key names used across APIs.
  const productCancellable = (product?: unknown) => {
    if (!product || typeof product !== "object") return true;
    const p = product as Record<string, unknown>;
    if ("is_cancelleable" in p) return Boolean(p["is_cancelleable"]);
    if ("is_cancellable" in p) return Boolean(p["is_cancellable"]);
    if ("is_cancelable" in p) return Boolean(p["is_cancelable"]);
    return true;
  };
  return (
    <>
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        size="lg"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex flex-col gap-2 p-4">
            <h3 className="text-medium font-semibold text-foreground">
              {t("cancel_items")}
            </h3>
            <p className="text-xs text-foreground/50">
              {t("cancel_items_desc")}
            </p>
          </ModalHeader>

          <Divider />

          <ModalBody className="px-2 py-4">
            <ScrollShadow className="space-y-2 w-full h-full max-h-[50vh]">
              {order.items && order.items.length ? (
                order.items.map((item) => {
                  const isCancellable =
                    productCancellable(item.product) &&
                    (!item.product?.cancelable_till ||
                      isStatusBeforeOrAt(
                        order.status,
                        item.product.cancelable_till,
                      ));

                  return (
                    <div
                      key={item.id}
                      className="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-md p-2"
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <Avatar
                          size="sm"
                          src={item.product?.image || undefined}
                          alt={item.product?.name || "item"}
                        />
                        <div className="min-w-0">
                          <Link
                            href={`/products/${item?.product?.slug}`}
                            title={
                              item.product?.name || item?.variant_title || ""
                            }
                            className="text-xs font-medium text-foreground truncate max-w-full pr-1 block"
                          >
                            {item.product?.name || item.variant_title}
                          </Link>

                          <div className="text-xxs text-foreground/50 flex gap-1">
                            <span>
                              {t("qty")}: {item.quantity}
                            </span>
                            <span>
                              {t("price")}: {currencySymbol}
                              {item.price}
                            </span>
                          </div>

                          {/* Addons */}
                          {item.addons && item.addons.length > 0 && (
                            <div className="flex flex-wrap gap-1 mt-1">
                              {item.addons.map(
                                (addon: any, idx: number) => {
                                  const addonName =
                                    addon.item?.title || addon.title || addon.name;
                                  const addonPrice =
                                    addon.item?.price ?? addon.price ?? null;
                                  if (!addonName) return null;
                                  return (
                                    <span
                                      key={idx}
                                      className="inline-flex items-center gap-0.5 text-xxs bg-default-100 dark:bg-default-200/20 text-foreground/60 rounded px-1.5 py-0.5 border border-divider"
                                    >
                                      {addonName}
                                      {addonPrice != null &&
                                        Number(addonPrice) > 0 && (
                                          <span className="text-primary font-medium ml-0.5">
                                            +{currencySymbol}
                                            {Number(addonPrice).toFixed(2)}
                                          </span>
                                        )}
                                    </span>
                                  );
                                }
                              )}
                            </div>
                          )}
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        {item.status !== "cancelled" ? (
                          // If the product object explicitly disallows cancellation, show a disabled indicator
                          !isCancellable ? (
                            <Chip
                              size="sm"
                              radius="sm"
                              variant="flat"
                              color="default"
                              classNames={{ content: "text-xxs", base: "p-0" }}
                            >
                              {t("na")}
                            </Chip>
                          ) : (
                            <Button
                              size="sm"
                              variant="bordered"
                              color="danger"
                              startContent={<Trash className="w-3 h-3" />}
                              className="text-xs"
                              onPress={() => openConfirm(item)}
                              isLoading={loadingItemId === item.id}
                            >
                              {t("cancel")}
                            </Button>
                          )
                        ) : (
                          <Chip
                            size="sm"
                            radius="sm"
                            variant="flat"
                            color={orderStatusColorMap(item?.status)}
                            classNames={{ content: "text-xxs", base: "p-0" }}
                          >
                            {formatString(item?.status)}
                          </Chip>
                        )}
                      </div>
                    </div>
                  );
                })
              ) : (
                <div className="text-xs text-foreground/50">
                  {t("no_items")}
                </div>
              )}
            </ScrollShadow>
          </ModalBody>

          <Divider />

          <ModalFooter>
            <Button
              size="sm"
              color="default"
              variant="bordered"
              className="text-xs"
              onPress={onClose}
            >
              {t("close")}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <ConfirmationModal
        isOpen={confirmOpen}
        onClose={closeConfirm}
        title={t("confirm_cancel_item")}
        confirmText={t("yes_cancel")}
        cancelText={t("no")}
        onConfirm={handleCancelItem}
        variant="danger"
        alertTitle={t("confirm_cancel_item_desc")}
      />
    </>
  );
};

export default CancelOrderItemModal;
