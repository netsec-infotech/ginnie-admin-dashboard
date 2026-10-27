import type { FC, ReactNode } from "react";
import type { LucideIcon } from "lucide-react";

export interface MediaEmptyStateProps {
  icon: LucideIcon;
  title: ReactNode;
  description: ReactNode;
  testId?: string;
}

const MediaEmptyState: FC<MediaEmptyStateProps> = ({
  icon: Icon,
  title,
  description,
  testId,
}) => (
  <div
    className="flex w-full flex-col items-center justify-center gap-4 px-4 py-20 text-center"
    data-testid={testId}
    role="status"
  >
    <span className="flex size-20 items-center justify-center rounded-full bg-default-100">
      <Icon
        aria-hidden="true"
        className="size-10 text-default-300"
        strokeWidth={1.5}
      />
    </span>
    <div className="max-w-sm">
      <h3 className="mb-1 font-semibold text-default-700">
        {title}
      </h3>
      <p className="text-sm text-default-400">{description}</p>
    </div>
  </div>
);

export default MediaEmptyState;
