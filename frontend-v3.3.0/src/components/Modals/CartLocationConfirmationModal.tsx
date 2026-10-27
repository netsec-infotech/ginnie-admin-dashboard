import { useSyncExternalStore } from "react";
import { useTranslation } from "react-i18next";
import {
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
} from "@heroui/react";
import { MapPin } from "lucide-react";
import { cartLocationConfirmationStore } from "@/stores/cartLocationConfirmationStore";

export default function CartLocationConfirmationModal() {
  const { t } = useTranslation();
  const pending = useSyncExternalStore(
    cartLocationConfirmationStore.subscribe,
    cartLocationConfirmationStore.getSnapshot,
    cartLocationConfirmationStore.getServerSnapshot,
  );
  return (
    <Modal
      isOpen={!!pending}
      onClose={() => cartLocationConfirmationStore.respond(false, pending?.key)}
      size="md"
      placement="center"
      backdrop="blur"
      scrollBehavior="inside"
      isDismissable={false}
    >
      <ModalContent>
        <ModalHeader className="flex items-center gap-2 pb-2 pe-10">
          <MapPin className="h-5 w-5 shrink-0 text-primary" />
          <h2 className="text-sm font-semibold text-foreground">
            {t("cartLocationConfirmation.title")}
          </h2>
        </ModalHeader>
        <ModalBody className="py-2">
          <p className="text-xs font-normal leading-relaxed text-default-500">
            {t("cartLocationConfirmation.description")}
          </p>
        </ModalBody>
        <ModalFooter className="flex-wrap gap-2 pt-4">
          <Button
            color="default"
            variant="flat"
            size="sm"
            className="h-auto min-h-8 whitespace-normal py-2 text-xs"
            onPress={() => cartLocationConfirmationStore.respond(false, pending?.key)}
          >
            {t("cartLocationConfirmation.cancel")}
          </Button>
          <Button
            color="primary"
            size="sm"
            className="h-auto min-h-8 whitespace-normal py-2 text-xs"
            onPress={() => cartLocationConfirmationStore.respond(true, pending?.key)}
          >
            {t("cartLocationConfirmation.confirm")}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
