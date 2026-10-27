import type { FC, ReactNode } from "react";
import { Chip } from "@heroui/react";
import Link from "next/link";

export interface MediaSectionHeaderProps {
  titleId: string;
  icon: ReactNode;
  title: ReactNode;
  description: ReactNode;
  badge?: ReactNode | null;
  badgeColor?: "primary" | "secondary";
  seeAllHref?: string;
  seeAllLabel?: ReactNode;
  showSeeAll?: boolean;
}

/** Shared section chrome for media rails. Feed-specific icons stay with callers. */
const MediaSectionHeader: FC<MediaSectionHeaderProps> = ({
  titleId,
  icon,
  title,
  description,
  badge = null,
  badgeColor = "primary",
  seeAllHref = "/play/",
  seeAllLabel,
  showSeeAll = false,
}) => (
  <div className="mb-6 flex items-center justify-between gap-4">
    <div className="flex min-w-0 items-center gap-3">
      {icon}
      <div className="min-w-0">
        <div className="flex items-center gap-2">
          <h2
            className="text-sm font-bold tracking-wide text-foreground sm:text-lg sm:tracking-wider"
            id={titleId}
          >
            {title}
          </h2>
          {badge !== null && (
            <Chip
              color={badgeColor}
              size="sm"
              variant="flat"
              classNames={{ content: "text-xxs font-bold sm:text-xs" }}
            >
              {badge}
            </Chip>
          )}
        </div>
        <p className="text-xxs text-foreground/50 sm:text-xs">
          {description}
        </p>
      </div>
    </div>

    {showSeeAll && (
      <Link href={seeAllHref} className="text-xs sm:text-sm">
        {seeAllLabel}
      </Link>
    )}
  </div>
);

export default MediaSectionHeader;
