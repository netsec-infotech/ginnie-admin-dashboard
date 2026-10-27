/* eslint-disable @next/next/no-img-element */
// Blob URLs are local previews and cannot use the Next image optimizer.
import { useEffect, useState } from "react";
import {
  Button,
  Modal,
  ModalBody,
  ModalContent,
  ModalHeader,
} from "@heroui/react";
import { FileText, X } from "lucide-react";
import { useTranslation } from "react-i18next";

export default function SupportSelectedFile({
  file,
  onRemove,
  disabled,
}: {
  file: File;
  onRemove: () => void;
  disabled: boolean;
}) {
  const { t } = useTranslation();
  const [url, setUrl] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const isImage =
    file.type.startsWith("image/") || /\.(?:jpe?g|png|webp)$/i.test(file.name);

  useEffect(() => {
    const objectUrl = URL.createObjectURL(file);
    // A blob URL is a browser resource, so create it after mount and revoke it on cleanup.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setUrl(objectUrl);
    return () => URL.revokeObjectURL(objectUrl);
  }, [file]);

  return (
    <li className="relative w-32 overflow-hidden rounded-xl border border-divider bg-content1 sm:w-36">
      {isImage ? (
        <button
          type="button"
          className="flex h-24 w-full items-center justify-center bg-default-100 focus-visible:outline-2 focus-visible:outline-primary"
          aria-label={t("supportChat.previewFile", { name: file.name })}
          onClick={() => setOpen(true)}
          disabled={!url}
        >
          {url && (
            <img
              src={url}
              alt={file.name}
              className="h-full w-full object-cover"
            />
          )}
        </button>
      ) : url ? (
        <a
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          aria-label={t("supportChat.previewFile", { name: file.name })}
          className="flex h-24 flex-col items-center justify-center gap-1 bg-default-100 text-default-600 focus-visible:outline-2 focus-visible:outline-primary"
        >
          <FileText size={28} aria-hidden="true" />
          <span className="text-[10px] font-semibold">PDF</span>
        </a>
      ) : (
        <div className="flex h-24 items-center justify-center bg-default-100 text-default-600">
          <FileText size={28} aria-hidden="true" />
        </div>
      )}
      <span className="block truncate px-2 py-1.5 text-xs" title={file.name}>
        {file.name}
      </span>
      <Button
        isIconOnly
        type="button"
        size="sm"
        radius="full"
        variant="flat"
        className="absolute right-1 top-1 h-6 w-6 min-w-6 bg-content1/90"
        isDisabled={disabled}
        aria-label={t("supportChat.removeFile", { name: file.name })}
        onPress={onRemove}
      >
        <X size={13} />
      </Button>
      {isImage && (
        <Modal
          isOpen={open}
          onOpenChange={setOpen}
          size="3xl"
          scrollBehavior="inside"
        >
          <ModalContent>
            <ModalHeader className="break-all pe-10">{file.name}</ModalHeader>
            <ModalBody className="pb-6">
              {url && (
                <img
                  src={url}
                  alt={file.name}
                  className="max-h-[75dvh] w-full object-contain"
                />
              )}
            </ModalBody>
          </ModalContent>
        </Modal>
      )}
    </li>
  );
}
