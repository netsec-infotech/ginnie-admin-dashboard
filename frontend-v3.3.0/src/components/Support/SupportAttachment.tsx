/* eslint-disable @next/next/no-img-element */
// Authenticated blob previews are displayed directly, without the image optimizer.
import { useEffect, useRef, useState } from "react";
import {
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalHeader,
} from "@heroui/react";
import { Download, FileText } from "lucide-react";
import { useTranslation } from "react-i18next";
import { getSupportAttachment } from "@/routes/api";
import type { SupportAttachment as Attachment } from "@/types/support";

const previewable = (type: string) =>
  /^image\/(png|jpeg|gif|webp|avif)$/.test(type);

export default function SupportAttachment({
  attachment,
}: {
  attachment: Attachment;
}) {
  const { t } = useTranslation();
  const [preview, setPreview] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(false);
  const previewFailed = () => {
    setPreview(null);
    setOpen(false);
    setError(true);
  };
  const downloadController = useRef<AbortController | null>(null);
  useEffect(
    () => () => {
      downloadController.current?.abort();
    },
    [],
  );
  useEffect(() => {
    if (!previewable(attachment.mime_type)) return;
    const controller = new AbortController();
    let url: string | null = null;
    void getSupportAttachment(attachment.id, controller.signal)
      .then((blob) => {
        if (controller.signal.aborted) return;
        if (!previewable(blob.type)) {
          setError(true);
          return;
        }
        url = URL.createObjectURL(blob);
        setPreview(url);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      });
    return () => {
      controller.abort();
      if (url) URL.revokeObjectURL(url);
    };
  }, [attachment.id, attachment.mime_type]);

  const download = async () => {
    if (downloadController.current) return;
    const controller = new AbortController();
    downloadController.current = controller;
    setBusy(true);
    try {
      const blob = await getSupportAttachment(attachment.id, controller.signal);
      if (controller.signal.aborted) return;
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = attachment.name;
      document.body.append(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      setError(false);
    } catch {
      if (!controller.signal.aborted) setError(true);
    } finally {
      downloadController.current = null;
      if (!controller.signal.aborted) setBusy(false);
    }
  };

  return (
    <div className="mt-2 max-w-full rounded-lg border border-divider bg-content1 p-2 text-foreground">
      {preview && (
        <button
          type="button"
          className="block max-w-full rounded-md focus-visible:outline-primary"
          onClick={() => setOpen(true)}
          aria-label={t("supportChat.previewFile", { name: attachment.name })}
        >
          {/* Authenticated blob URLs cannot use the Next image optimizer. */}
          <img
            src={preview}
            alt={attachment.name}
            onError={previewFailed}
            className="max-h-40 max-w-full rounded-md object-contain"
          />
        </button>
      )}
      <div className="flex min-w-0 items-center gap-2 text-xs">
        <FileText size={16} className="shrink-0" />
        <span className="min-w-0 flex-1 break-all">{attachment.name}</span>
        <Button
          className="text-xs"
          size="sm"
          variant="light"
          isIconOnly
          isLoading={busy}
          onPress={() => void download()}
          aria-label={t("supportChat.downloadFile", { name: attachment.name })}
        >
          <Download size={16} />
        </Button>
      </div>
      {error && (
        <p role="alert" className="text-xs text-danger">
          {t("supportChat.attachmentError")}
        </p>
      )}
      <Modal
        isOpen={open}
        onOpenChange={setOpen}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="break-all pe-10">
            {attachment.name}
          </ModalHeader>
          <ModalBody className="pb-6">
            {preview && (
              <img
                src={preview}
                alt={attachment.name}
                onError={previewFailed}
                className="max-h-[75dvh] w-full object-contain"
              />
            )}
          </ModalBody>
        </ModalContent>
      </Modal>
    </div>
  );
}
