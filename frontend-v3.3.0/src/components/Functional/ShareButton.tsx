import { useRef, useState } from "react";
import { Button, addToast } from "@heroui/react";
import { Share2 } from "lucide-react";

interface ShareButtonProps {
  title: string;
  description?: string;
  url: string | (() => string);
  fallbackText: string;
  ariaLabel: string;
  successMessage: string;
  errorMessage: string;
  className?: string;
}

async function copyShareText(text: string): Promise<void> {
  if (typeof navigator.clipboard?.writeText === "function") {
    try {
      await navigator.clipboard.writeText(text);
      return;
    } catch {
      // Keep copying available in browsers without clipboard permission.
    }
  }

  const previousFocus = document.activeElement;
  const input = document.createElement("textarea");
  input.value = text;
  input.readOnly = true;
  input.style.position = "fixed";
  input.style.opacity = "0";
  input.style.top = "0";
  document.body.appendChild(input);
  try {
    input.select();
    if (
      typeof document.execCommand !== "function" ||
      !document.execCommand("copy")
    ) {
      throw new Error("Copy unavailable");
    }
  } finally {
    input.remove();
    if (previousFocus instanceof HTMLElement) {
      previousFocus.focus({ preventScroll: true });
    }
  }
}

export default function ShareButton({
  title,
  description = "",
  url,
  fallbackText,
  ariaLabel,
  successMessage,
  errorMessage,
  className = "",
}: ShareButtonProps) {
  const sharing = useRef(false);
  const [isSharing, setIsSharing] = useState(false);

  const handleShare = async () => {
    if (sharing.current) return;
    sharing.current = true;
    setIsSharing(true);
    try {
      const shareUrl = typeof url === "function" ? url() : url;
      const cleanDescription = description.trim();
      const text = cleanDescription
        ? cleanDescription
            .toLocaleLowerCase()
            .includes(title.toLocaleLowerCase())
          ? cleanDescription
          : `${title} - ${cleanDescription}`
        : fallbackText;
      if (typeof navigator.share === "function") {
        await navigator.share({ title, text, url: shareUrl });
      } else {
        await copyShareText(
          cleanDescription
            ? `${title}\n${cleanDescription}\n${shareUrl}`
            : shareUrl,
        );
        addToast({ title: successMessage, color: "success" });
      }
    } catch (error) {
      const isCancelled =
        error !== null &&
        typeof error === "object" &&
        "name" in error &&
        error.name === "AbortError";
      if (!isCancelled) {
        addToast({ title: errorMessage, color: "danger" });
      }
    } finally {
      sharing.current = false;
      setIsSharing(false);
    }
  };

  return (
    <Button
      isIconOnly
      variant="light"
      radius="full"
      aria-label={ariaLabel}
      title={ariaLabel}
      onPress={() => void handleShare()}
      isDisabled={isSharing}
      className={`shrink-0 text-foreground/50 hover:text-primary ${className}`}
    >
      <Share2 size={20} aria-hidden="true" />
    </Button>
  );
}
