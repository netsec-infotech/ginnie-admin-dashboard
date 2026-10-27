import { CSSProperties, FC } from "react";
import { Image } from "@heroui/react";

import type { StoryVisual } from "./types";

interface StoryArtworkProps {
  visual: StoryVisual;
  compact?: boolean;
  className?: string;
  imageSrc?: string;
  imagePosition?: string;
}

const StoryArtwork: FC<StoryArtworkProps> = ({
  visual,
  compact = false,
  className = "",
  imageSrc,
  imagePosition = "center",
}) => {
  const style: CSSProperties = {
    background: imageSrc ? "#09090b" : visual.background,
  };

  return (
    <div
      aria-hidden={compact ? "true" : undefined}
      aria-label={compact ? undefined : visual.headline}
      className={`relative isolate h-full w-full overflow-hidden ${className}`}
      role={compact ? undefined : "img"}
      style={style}
    >
      {imageSrc ? (
        <>
          <Image
            removeWrapper
            alt=""
            className={`absolute inset-0 h-full w-full object-cover ${
              compact
                ? "transform-gpu [backface-visibility:hidden]"
                : "scale-[1.01]"
            }`}
            disableAnimation={compact}
            draggable={false}
            loading="eager"
            src={imageSrc}
            style={{ objectPosition: imagePosition }}
          />
          <div className="pointer-events-none absolute inset-0 bg-linear-to-b from-black/35 via-transparent to-black/55" />
        </>
      ) : (
        <>
          <div
            className="absolute -right-[18%] -top-[8%] h-[48%] w-[64%] rounded-full opacity-40 blur-2xl"
            style={{ backgroundColor: visual.accent }}
          />
          <div className="absolute -bottom-[18%] -left-[20%] h-[48%] w-[72%] rounded-full bg-black/15 blur-3xl" />
          <div className="absolute inset-0 bg-linear-to-b from-white/5 via-transparent to-black/15" />
        </>
      )}

      {compact
        ? !imageSrc && (
            <div className="relative flex h-full items-center justify-center">
              <span className="text-[2rem] drop-shadow-md md:text-[2.25rem]">
                {visual.icon}
              </span>
            </div>
          )
        : !imageSrc && (
            <div className="relative flex h-full flex-col px-6 pb-28 pt-28 text-white sm:px-8 sm:pb-32 sm:pt-32">
              <div>
                <span className="inline-flex rounded-full border border-white/25 bg-black/20 px-3 py-1 text-[0.65rem] font-bold tracking-[0.2em] backdrop-blur-md sm:text-xs">
                  {visual.eyebrow}
                </span>
              </div>

              <div className="flex flex-1 flex-col items-center justify-center pb-5 text-center sm:pb-7">
                <div className="story-artwork-float relative mb-6 flex h-36 w-36 items-center justify-center sm:h-40 sm:w-40">
                  <span
                    className="absolute inset-4 rounded-full opacity-45 blur-2xl"
                    style={{ backgroundColor: visual.accent }}
                  />
                  <span className="story-artwork-orbit absolute inset-1 rounded-full border border-white/15 bg-white/5 shadow-[inset_0_1px_0_rgba(255,255,255,0.18),0_20px_60px_rgba(0,0,0,0.15)] backdrop-blur-sm" />
                  <span className="relative text-[5.5rem] drop-shadow-[0_18px_22px_rgba(0,0,0,0.24)] sm:text-[6.5rem]">
                    {visual.icon}
                  </span>
                </div>
                <h3 className="max-w-sm text-balance text-[2rem] font-black leading-[1.04] tracking-[-0.035em] drop-shadow-lg sm:text-[2.45rem]">
                  {visual.headline}
                </h3>
                <p className="mt-4 max-w-[20rem] text-pretty text-sm font-medium leading-relaxed text-white/85 drop-shadow-md sm:text-base">
                  {visual.description}
                </p>
              </div>
            </div>
          )}
    </div>
  );
};

export default StoryArtwork;
