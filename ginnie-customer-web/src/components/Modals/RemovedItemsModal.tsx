import React, { useMemo, useSyncExternalStore } from "react";
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Divider,
  ScrollShadow,
} from "@heroui/react";
import { useTranslation } from "react-i18next";
import { removedItemsStore } from "@/stores/removedItemsStore";

export interface RemovedItem {
  product_name: string;
  variant_name: string;
  store_name: string;
  quantity: number;
  reason: string;
}

/**
 * "These items were removed" notice, shown after a cart refresh drops items
 * that can't be delivered to the selected address.
 *
 * Driven by `removedItemsStore` rather than `cartData`. The payload has to
 * outlive the cart state that produced it: the post-removal refetch replaces
 * `cartData`, and a removal that empties the cart nulls it entirely — both of
 * which used to blank this dialog to "No removed items" a frame after it
 * opened. Open/closed is derived from the store too, so there is no separate
 * `useDisclosure` state to fall out of sync.
 */
const RemovedItemsModal: React.FC = () => {
  const { t } = useTranslation();

  const shownItems = useSyncExternalStore(
    removedItemsStore.subscribe,
    removedItemsStore.getSnapshot,
    removedItemsStore.getServerSnapshot,
  );

  const isOpen = shownItems.length > 0;
  const onClose = () => removedItemsStore.clear();

  const title = useMemo(() => {
    return shownItems.length > 1
      ? t("removedItems.multipleTitle")
      : t("removedItems.singleTitle");
  }, [shownItems.length, t]);

  return (
    <>
      {/*
        Deliberately not dismissable by backdrop click or Escape: this is the
        only place the customer is told what was dropped from their cart, so it
        should take a explicit Close.
      */}
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        size="lg"
        scrollBehavior="inside"
        isDismissable={false}
        isKeyboardDismissDisabled
      >
        <ModalContent>
          <ModalHeader className="flex flex-col gap-1 p-4">
            <h3 className="text-medium font-semibold">{title}</h3>
            <p className="text-xs text-foreground/60">
              {t("removedItems.description")}
            </p>
          </ModalHeader>

          <Divider />

          <ModalBody className="px-2 py-4">
            <ScrollShadow className="space-y-2 w-full h-full max-h-[50vh]">
              {shownItems.map((item, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-md p-2"
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <div className="min-w-0">
                      <div
                        title={item.variant_name}
                        className="text-xs font-medium text-foreground truncate"
                      >
                        {item.product_name}
                      </div>
                      <span className="text-xxs text-foreground/50">
                        {item.variant_name}
                      </span>

                      <div className="text-xxs text-foreground/50 flex flex-col">
                        <span>
                          {t("removedItems.qty")}: {item.quantity}
                        </span>
                        <span>
                          {t("removedItems.store")}: {item.store_name}
                        </span>
                        <span>
                          {t("reason")}: {item.reason}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </ScrollShadow>
          </ModalBody>

          <Divider />

          <ModalFooter>
            {/*
              Just dismisses. This used to bounce the customer to the home page
              when the removal emptied the cart, which hid the outcome — they
              were told items were removed and then found themselves somewhere
              else entirely. Closing onto the empty cart shows the actual result
              and keeps the address controls in reach.
            */}
            <Button variant="bordered" className="text-xs" onPress={onClose}>
              {t("removedItems.close")}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
};

export default RemovedItemsModal;
