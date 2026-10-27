import { useRef, useState } from "react";
import { Button, Textarea } from "@heroui/react";
import { Paperclip, Send } from "lucide-react";
import { useTranslation } from "react-i18next";
import {
  SUPPORT_FILE_ACCEPT,
  SUPPORT_MESSAGE_LIMIT,
  supportFileError,
} from "@/helpers/support";
import SupportSelectedFile from "./SupportSelectedFile";

export interface SupportComposerProps {
  message: string;
  files: File[];
  onMessage: (message: string) => void;
  onFiles: (files: File[]) => void;
  onSubmit: () => void;
  disabled?: boolean;
  busy?: boolean;
  start?: boolean;
  onBack?: () => void;
  onBlur?: () => void;
}

export default function SupportComposer({
  message,
  files,
  onMessage,
  onFiles,
  onSubmit,
  disabled,
  busy,
  start,
  onBack,
  onBlur,
}: SupportComposerProps) {
  const { t } = useTranslation();
  const input = useRef<HTMLInputElement>(null);
  const form = useRef<HTMLFormElement>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [dragging, setDragging] = useState(false);
  const addFiles = (incoming: File[]) => {
    if (disabled || busy) return;
    const next = [...files, ...incoming];
    const issue = supportFileError(next);
    setFileError(issue ? t(`supportChat.${issue}`) : null);
    if (!issue) onFiles(next);
  };

  return (
    <form
      ref={form}
      onSubmit={(event) => {
        event.preventDefault();
        if (!disabled && !busy && (message.trim() || files.length)) {
          onSubmit();
          requestAnimationFrame(() =>
            form.current?.querySelector("textarea")?.focus(),
          );
        }
      }}
      onDragOver={(event) => {
        event.preventDefault();
        if (!disabled && !busy) setDragging(true);
      }}
      onDragLeave={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null))
          setDragging(false);
      }}
      onDrop={(event) => {
        event.preventDefault();
        setDragging(false);
        addFiles(Array.from(event.dataTransfer.files));
      }}
      className={`flex flex-col gap-3 rounded-xl ${dragging ? "ring-2 ring-primary bg-primary/5" : ""}`}
      aria-label={t("supportChat.describeIssue")}
    >
      <div className="flex items-end gap-1 rounded-2xl border border-default-300 bg-content1 p-2 transition-colors focus-within:border-primary">
        <Textarea
          aria-label={t("supportChat.message")}
          placeholder={t(
            start
              ? "supportChat.firstMessagePlaceholder"
              : "supportChat.messagePlaceholder",
            start
              ? "Tell us what happened. Add any details that might help."
              : "Type your message…",
          )}
          value={message}
          onValueChange={onMessage}
          onBlur={onBlur}
          maxLength={SUPPORT_MESSAGE_LIMIT}
          minRows={start ? 3 : 1}
          maxRows={5}
          isDisabled={disabled || busy}
          classNames={{
            base: "min-w-0 flex-1",
            inputWrapper:
              "min-h-10 bg-transparent px-2 py-2 shadow-none data-[hover=true]:bg-transparent group-data-[focus=true]:bg-transparent",
            input: "text-base leading-6 md:text-sm",
          }}
          onKeyDown={(event) => {
            if (
              event.key === "Enter" &&
              !event.shiftKey &&
              !event.nativeEvent.isComposing
            ) {
              event.preventDefault();
              if (!disabled && !busy && (message.trim() || files.length))
                onSubmit();
            }
          }}
        />
        <div className="contents">
          <input
            ref={input}
            type="file"
            multiple
            accept={SUPPORT_FILE_ACCEPT}
            className="hidden"
            tabIndex={-1}
            aria-label={t("supportChat.attach")}
            onChange={(event) => {
              addFiles(Array.from(event.target.files ?? []));
              event.target.value = "";
            }}
          />
          <Button
            type="button"
            isIconOnly
            variant="light"
            size="sm"
            radius="full"
            className="order-first h-10 w-10 min-w-10 shrink-0 text-default-500"
            aria-label={t("supportChat.attach")}
            isDisabled={disabled || busy}
            onPress={() => input.current?.click()}
          >
            <Paperclip size={18} />
          </Button>
          {!start && (
            <Button
              type="submit"
              isIconOnly
              radius="full"
              color="primary"
              className="h-10 w-10 min-w-10 shrink-0"
              aria-label={t("supportChat.send")}
              isLoading={busy}
              isDisabled={
                disabled || busy || (!message.trim() && files.length === 0)
              }
            >
              <Send size={18} />
            </Button>
          )}
        </div>
      </div>
      <p className="text-xs text-default-500">
        {t("supportChat.allowedFiles", {
          defaultValue: "JPG, PNG, WebP or PDF · Up to 5 files, 10 MB each",
        })}
      </p>
      {message.length >= SUPPORT_MESSAGE_LIMIT * 0.9 && (
        <span className="text-end text-xs text-default-500">
          {message.length}/{SUPPORT_MESSAGE_LIMIT}
        </span>
      )}
      {files.length > 0 && (
        <ul
          className="flex flex-wrap gap-3"
          aria-label={t("supportChat.attachments")}
        >
          {files.map((file, index) => (
            <SupportSelectedFile
              key={`${file.name}-${file.size}-${index}`}
              file={file}
              disabled={Boolean(disabled || busy)}
              onRemove={() => {
                onFiles(files.filter((_, i) => i !== index));
                setFileError(null);
              }}
            />
          ))}
        </ul>
      )}
      {fileError && (
        <p role="alert" className="text-xs text-danger">
          {fileError}
        </p>
      )}
      {start && (
        <div className="flex items-center justify-between gap-3 pt-1">
          {onBack && (
            <Button
              type="button"
              variant="light"
              onPress={onBack}
              isDisabled={busy}
            >
              {t("supportChat.back")}
            </Button>
          )}
          <Button
            type="submit"
            color="primary"
            className="min-w-36"
            isLoading={busy}
            isDisabled={
              disabled || busy || (!message.trim() && files.length === 0)
            }
            endContent={!busy && !start ? <Send size={16} /> : undefined}
          >
            {t(start ? "supportChat.startChat" : "supportChat.send")}
          </Button>
        </div>
      )}
    </form>
  );
}
