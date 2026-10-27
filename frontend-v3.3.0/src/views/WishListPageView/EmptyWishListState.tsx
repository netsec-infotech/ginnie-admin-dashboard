import React, { ReactNode } from "react";

const EmptyWishListState = ({
  icon: Icon,
  title,
  description,
  action,
  compact = false,
}: {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  description: string;
  action?: ReactNode;
  compact?: boolean;
}) => {
  return (
    <div
      className={`flex flex-col items-center justify-center px-5 text-center ${
        compact ? "py-10" : "min-h-80 py-14"
      }`}
    >
      <div className="mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
        <Icon className="size-7" />
      </div>
      <h3 className="mb-1.5 text-base font-semibold text-foreground">{title}</h3>
      <p className="max-w-sm text-sm leading-6 text-default-500">
        {description}
      </p>
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
};

export default EmptyWishListState;
