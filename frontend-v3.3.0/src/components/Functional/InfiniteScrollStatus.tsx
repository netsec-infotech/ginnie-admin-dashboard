// components/Functional/InfiniteScrollStatus.tsx
import React from "react";
import { ArrowDown } from "lucide-react";
import { useTranslation } from "react-i18next";
import InlineDataRetry from "@/components/Functional/InlineDataRetry";

interface InfiniteScrollStatusProps {
  entityType: string;
  total: number;
  hasMore: boolean;
  error?: Error | null;
  isRetrying?: boolean;
  onRetry?: () => void;
}

const InfiniteScrollStatus: React.FC<InfiniteScrollStatusProps> = ({
  entityType,
  total,
  hasMore,
  error,
  isRetrying = false,
  onRetry,
}) => {
  const { t } = useTranslation();

  if (error && onRetry) {
    return (
      <div className="flex flex-col items-center py-8 text-center">
        <InlineDataRetry
          message={t("load_more_unavailable", {
            defaultValue: "More items are taking a moment to load.",
          })}
          retryLabel={t("try_again", { defaultValue: "Try again" })}
          isRetrying={isRetrying}
          onRetry={onRetry}
        />
      </div>
    );
  }

  if (total === 0) return null;

  return (
    <div className="text-center py-8 flex flex-col items-center">
      {hasMore ? (
        <>
          <p className="text-foreground/50 mb-2 text-xs sm:text-sm">
            {t("scroll_more", { entityType })}
          </p>
          <ArrowDown className="text-foreground/50 animate-bounce" size={20} />
        </>
      ) : (
        <p className="text-foreground/50 text-xs sm:text-sm">
          {t(total === 1 ? "end_reached_single" : "end_reached_multiple", { total })}
        </p>
      )}
    </div>
  );
};

export default InfiniteScrollStatus;
