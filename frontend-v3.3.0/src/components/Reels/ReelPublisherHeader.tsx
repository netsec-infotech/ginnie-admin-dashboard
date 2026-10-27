import type { FC } from "react";
import { Button } from "@heroui/react";
import { Store, X } from "lucide-react";
import Image from "next/image";
import { useTranslation } from "react-i18next";

import type { Reel } from "@/components/Reels/types";

export interface ReelPublisherHeaderProps {
  reel: Reel;
  isHidden?: boolean;
  onClose: () => void;
}

const ReelPublisherHeader: FC<ReelPublisherHeaderProps> = ({
  reel,
  isHidden = false,
  onClose,
}) => {
  const { t } = useTranslation();
  const publisherLabel =
    reel.publisherType === "admin"
      ? t("reels.viewer.official", { defaultValue: "Official" })
      : t("reels.viewer.seller", { defaultValue: "Seller" });

  return (
    <div
      inert={isHidden ? true : undefined}
      aria-hidden={isHidden || undefined}
      data-reel-chrome="header"
      className={`pointer-events-none absolute inset-x-0 top-0 z-30 px-[max(0.75rem,env(safe-area-inset-left))] pt-[max(0.75rem,env(safe-area-inset-top))] transition-opacity duration-150 motion-reduce:transition-none ${
        isHidden ? "opacity-0" : "opacity-100"
      }`}
    >
      <div className="pointer-events-auto flex items-center gap-3">
        <div className="relative h-10 w-10 shrink-0 overflow-hidden rounded-full border-2 border-white/80">
          <Image
            fill
            alt=""
            aria-hidden="true"
            className="object-cover"
            sizes="40px"
            src={reel.posterSrc}
          />
        </div>
        <div className="min-w-0 flex-1 text-white">
          <p className="flex items-center gap-1.5 text-sm font-bold">
            <span className="truncate">{reel.publisher}</span>
            <span className="sr-only">{publisherLabel}</span>
            {reel.publisherType === "seller" && (
              <Store
                aria-hidden="true"
                className="shrink-0 text-white/70"
                size={13}
              />
            )}
          </p>
          <p className="truncate text-xs text-white/85">
            {`${reel.distance} · ${reel.deliveryEstimate}`}
          </p>
        </div>

        <Button
          isIconOnly
          data-reel-control
          aria-label={t("reels.viewer.close", {
            defaultValue: "Close reels",
          })}
          className="border border-white/10 bg-black/30 text-white backdrop-blur-md"
          radius="full"
          variant="flat"
          onPress={onClose}
        >
          <X aria-hidden="true" size={21} />
        </Button>
      </div>
    </div>
  );
};

export default ReelPublisherHeader;
