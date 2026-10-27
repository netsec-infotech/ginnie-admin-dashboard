import React from "react";
import { useTranslation } from "react-i18next";
import InlineDataRetry from "@/components/Functional/InlineDataRetry";

interface InitialDataRetryProps {
  onRetry: () => void;
  isRetrying?: boolean;
  className?: string;
}

const InitialDataRetry: React.FC<InitialDataRetryProps> = ({
  onRetry,
  isRetrying = false,
  className = "",
}) => {
  const { t } = useTranslation();

  return (
    <div
      className={`flex min-h-36 w-full items-center justify-center py-8 ${className}`}
    >
      <InlineDataRetry
        message={t("content_temporarily_unavailable", {
          defaultValue: "This content is taking a moment to load.",
        })}
        retryLabel={t("try_again", { defaultValue: "Try again" })}
        onRetry={onRetry}
        isRetrying={isRetrying}
      />
    </div>
  );
};

export default InitialDataRetry;
