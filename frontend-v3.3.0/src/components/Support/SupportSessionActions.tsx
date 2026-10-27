import { useState } from "react";
import {
  Button,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Textarea,
} from "@heroui/react";
import { Star } from "lucide-react";
import { useTranslation } from "react-i18next";

export function SupportCallbackDialog({
  phone: initialPhone,
  busy,
  onClose,
  onSubmit,
}: {
  phone: string;
  busy: boolean;
  onClose: () => void;
  onSubmit: (phone: string, scheduledAt?: string) => Promise<boolean>;
}) {
  const { t } = useTranslation();
  const [phone, setPhone] = useState(initialPhone);
  const [scheduled, setScheduled] = useState("");
  const [validationError, setValidationError] = useState<
    "phone" | "schedule" | null
  >(null);
  const [failed, setFailed] = useState(false);
  const submit = async () => {
    const date = scheduled ? new Date(scheduled) : null;
    setFailed(false);

    if (!phone.trim()) {
      setValidationError("phone");
      return;
    }

    if (date && (Number.isNaN(date.getTime()) || date.getTime() <= Date.now())) {
      setValidationError("schedule");
      return;
    }

    setValidationError(null);
    if (await onSubmit(phone, date?.toISOString())) onClose();
    else setFailed(true);
  };
  return (
    <Modal
      isOpen
      onClose={onClose}
      isDismissable={!busy}
      isKeyboardDismissDisabled={busy}
      hideCloseButton={busy}
    >
      <ModalContent>
        <ModalHeader>{t("supportChat.callback")}</ModalHeader>
        <ModalBody>
          <p className="text-sm text-default-500">
            {t("supportChat.callbackHint")}
          </p>
          <Input
            label={t("supportChat.phone")}
            type="tel"
            autoComplete="tel"
            value={phone}
            onValueChange={setPhone}
            maxLength={32}
            isRequired
            isDisabled={busy}
          />
          <Input
            label={t("supportChat.schedule")}
            labelPlacement="outside-top"
            classNames={{
              base: "mt-2",
              label: "text-sm font-medium",
              inputWrapper: "min-h-12",
              input: "text-sm",
              description: "text-xs leading-5 text-default-500",
            }}
            type="datetime-local"
            value={scheduled}
            onValueChange={setScheduled}
            isDisabled={busy}
            description={t("supportChat.scheduleHint")}
          />
          {validationError && (
            <p role="alert" className="text-sm text-danger">
              {t(
                validationError === "phone"
                  ? "supportChat.callbackPhoneValidation"
                  : "supportChat.callbackScheduleValidation",
              )}
            </p>
          )}
          {failed && (
            <p role="alert" className="text-sm text-danger">
              {t("supportChat.actionFailed")}
            </p>
          )}
        </ModalBody>
        <ModalFooter>
          <Button variant="light" isDisabled={busy} onPress={onClose}>
            {t("supportChat.cancel")}
          </Button>
          <Button
            color="primary"
            isLoading={busy}
            isDisabled={busy || !phone.trim()}
            onPress={() => void submit()}
          >
            {t("supportChat.requestCallback")}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export function SupportResolveDialog({
  busy,
  onClose,
  onSubmit,
}: {
  busy: boolean;
  onClose: () => void;
  onSubmit: () => Promise<boolean>;
}) {
  const { t } = useTranslation();
  const [failed, setFailed] = useState(false);
  return (
    <Modal
      isOpen
      placement="center"
      size="md"
      onClose={onClose}
      isDismissable={!busy}
      isKeyboardDismissDisabled={busy}
      hideCloseButton={busy}
    >
      <ModalContent className="rounded-[20px]">
        <ModalHeader className="px-6 pb-3 pt-5 text-lg font-semibold">
          {t("supportChat.resolveTitle")}
        </ModalHeader>
        <ModalBody className="px-6 py-3">
          <p className="text-sm leading-6 text-default-600">
            {t("supportChat.resolveHint")}
          </p>
          {failed && (
            <p role="alert" className="text-sm text-danger">
              {t("supportChat.actionFailed")}
            </p>
          )}
        </ModalBody>
        <ModalFooter className="grid grid-cols-2 gap-3 px-6 pb-4 pt-3">
          <Button
            className="h-10 min-w-0 rounded-xl px-2 text-sm font-medium"
            variant="light"
            isDisabled={busy}
            onPress={onClose}
          >
            {t("supportChat.keepChatting")}
          </Button>
          <Button
            className="h-10 min-w-0 rounded-xl bg-success-600 px-2 text-sm font-semibold text-white dark:bg-success-400 dark:text-black"
            color="success"
            isLoading={busy}
            isDisabled={busy}
            onPress={() => {
              void onSubmit().then((success) => {
                if (success) onClose();
                else setFailed(true);
              });
            }}
          >
            {t("supportChat.confirmResolve")}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export function SupportRating({
  busy,
  onSubmit,
}: {
  busy: boolean;
  onSubmit: (score: number, feedback: string) => Promise<boolean>;
}) {
  const { t } = useTranslation();
  const [score, setScore] = useState(0);
  const [feedback, setFeedback] = useState("");
  return (
    <section className="mx-auto my-5 w-full max-w-xl rounded-2xl border border-divider bg-content1 p-5 text-center">
      <h2 className="font-semibold">{t("supportChat.rateAssistance")}</h2>
      <p className="mt-3 text-xs text-default-500">
        {t("supportChat.ratingHint")}
      </p>
      <div
        className="my-4 flex justify-center gap-1"
        role="group"
        aria-label={t("supportChat.rateAssistance")}
      >
        {[1, 2, 3, 4, 5].map((value) => (
          <Button
            key={value}
            isIconOnly
            variant="light"
            isDisabled={busy}
            aria-label={t("supportChat.ratingStars", { count: value })}
            aria-pressed={score === value}
            onPress={() => setScore(value)}
          >
            <Star
              size={25}
              className={`text-warning ${value <= score ? "fill-warning" : ""}`}
            />
          </Button>
        ))}
      </div>
      <Textarea
        aria-label={t("supportChat.feedback")}
        placeholder={t("supportChat.feedback")}
        value={feedback}
        onValueChange={setFeedback}
        maxLength={2000}
        minRows={2}
        isDisabled={busy}
      />
      <Button
        className="mt-3 w-full"
        color="primary"
        isDisabled={!score || busy}
        isLoading={busy}
        onPress={() => void onSubmit(score, feedback)}
      >
        {t("supportChat.submitRating")}
      </Button>
    </section>
  );
}
