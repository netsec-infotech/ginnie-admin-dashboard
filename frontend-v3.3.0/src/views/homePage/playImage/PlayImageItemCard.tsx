import type { CSSProperties, FC } from "react";
import Link from "next/link";

import { resolveSectionBannerHref } from "@/helpers/homeLayout";
import type { HomeLayoutBannerItem } from "@/types/ApiResponse";
import {
  getPlayImageItemConfig,
  normalizePlayImageAspectRatio,
  normalizePlayImageFit,
  normalizePlayImagePosition,
} from "@/views/homePage/playImage/playImageConfig";

interface PlayImageItemCardProps {
  item: HomeLayoutBannerItem;
  alt: string;
  borderRadiusStyle: CSSProperties;
  hoverEffect: string;
  priority?: boolean;
  compactOnDesktop?: boolean;
  legacyBannerOnDesktop?: boolean;
}

/** One responsive, linked image inside a Play Image row. */
const PlayImageItemCard: FC<PlayImageItemCardProps> = ({
  item,
  alt,
  borderRadiusStyle,
  hoverEffect,
  priority = false,
  compactOnDesktop = false,
  legacyBannerOnDesktop = false,
}) => {
  if (!item.image) return null;

  const config = getPlayImageItemConfig(item.config);
  const aspectRatio = normalizePlayImageAspectRatio(config.aspect_ratio);
  const useLegacyBannerDesktopFrame = legacyBannerOnDesktop && !aspectRatio;
  const useCompactDesktopFrame =
    compactOnDesktop && !aspectRatio && !useLegacyBannerDesktopFrame;
  const href = resolveSectionBannerHref(item);
  const image = (
    <picture className="block h-full w-full">
      {item.image_web_mobile && (
        <source
          data-play-image-mobile-source
          media="(max-width: 767px)"
          srcSet={item.image_web_mobile}
        />
      )}
      <img
        data-play-image
        src={item.image}
        alt={alt}
        loading={priority ? "eager" : "lazy"}
        fetchPriority={priority ? "high" : undefined}
        style={{
          objectFit: normalizePlayImageFit(config.object_fit),
          objectPosition: normalizePlayImagePosition(config.object_position),
        }}
        className={
          aspectRatio ? "block h-full w-full" : "block h-auto w-full"
        }
      />
    </picture>
  );
  const content = (
    <div
      className={`group w-full overflow-hidden ${
        useCompactDesktopFrame ? "home-play-image-frame--compact-desktop" : ""
      } ${
        useLegacyBannerDesktopFrame
          ? "home-play-image-frame--legacy-banner-desktop"
          : ""
      } ${
        hoverEffect === "lift"
          ? "transition-transform duration-300 hover:-translate-y-1 motion-reduce:transition-none"
          : ""
      }`}
      style={{
        ...(aspectRatio ? { aspectRatio } : {}),
        ...borderRadiusStyle,
      }}
    >
      {image}
    </div>
  );

  if (!href) return content;

  return (
    <Link
      href={href}
      title={alt}
      className="block w-full overflow-hidden focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      style={borderRadiusStyle}
    >
      {content}
    </Link>
  );
};

export default PlayImageItemCard;
