import React from "react";

interface InlineDataRetryProps {
  message: string;
  retryLabel: string;
  onRetry: () => void;
  isRetrying?: boolean;
  className?: string;
  variant?: "inline" | "panel";
}

const InlineDataRetry: React.FC<InlineDataRetryProps> = ({
  message,
  retryLabel,
  onRetry,
  isRetrying = false,
  className = "",
  variant = "inline",
}) => {
  const isPanel = variant === "panel";

  return (
    <div
      className={`flex max-w-full items-center text-default-600 ${
        isPanel
          ? "min-h-[76px] w-full flex-col justify-center gap-2 rounded-2xl bg-background/[0.55] px-4 py-2.5 text-center text-sm backdrop-blur-xl sm:min-h-[82px] sm:flex-row sm:gap-3 dark:bg-black/25"
          : "min-h-9 gap-2 rounded-full bg-default-100/80 px-3 text-xs"
      } ${className}`}
      role="group"
      aria-label={message}
    >
      <span
        className={isPanel ? "leading-5" : "min-w-0 truncate"}
        role="status"
        aria-live="polite"
      >
        {message}
      </span>
      <button
        type="button"
        className={`shrink-0 rounded-full font-semibold text-primary outline-none transition-colors motion-reduce:transition-none hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 ${
          isPanel ? "bg-primary/10 px-4 py-2" : "px-2 py-1"
        }`}
        onClick={onRetry}
        disabled={isRetrying}
        aria-label={`${retryLabel}: ${message}`}
      >
        {retryLabel}
      </button>
    </div>
  );
};

export default InlineDataRetry;
