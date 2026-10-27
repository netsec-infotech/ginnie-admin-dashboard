import React, { ReactNode } from "react";
import { LucideIcon } from "lucide-react";

interface NoProductsFoundProps {
  title: string;
  description: string;
  icon: LucideIcon;
  customActions?: ReactNode;
  compact?: boolean;
}

const NoProductsFound: React.FC<NoProductsFoundProps> = ({
  title,
  description,
  icon: IconComponent,
  customActions,
  compact = false,
}) => {
  return (
    <div className={compact
      ? "mx-auto my-6 flex w-full max-w-xl flex-col items-center justify-center rounded-2xl border border-divider bg-content1/50 px-6 py-10 text-center shadow-sm"
      : "flex min-h-[70vh] flex-col items-center justify-center"}>
      <div className="max-w-md mx-auto text-center">
        {/* Icon */}
        <div className={`mx-auto flex items-center justify-center rounded-full bg-foreground/10 ${compact ? "mb-4 h-16 w-16" : "mb-6 h-24 w-24"}`}>
          <IconComponent
            className={compact ? "h-8 w-8 text-foreground/60" : "h-10 w-10 text-foreground/50 sm:h-12 sm:w-12"}
            strokeWidth={1.5}
          />
        </div>

        {/* Title */}
        <h2 className="text-lg sm:text-xl font-semibold mb-3">{title}</h2>

        {/* Description */}
        <p className={`text-xs leading-relaxed text-foreground/60 ${compact ? "mb-5" : "mb-8"}`}>
          {description}
        </p>

        {/* Custom Actions */}
        {customActions && (
          <div className="flex gap-3 mt-2">{customActions}</div>
        )}
      </div>
    </div>
  );
};

export default NoProductsFound;
