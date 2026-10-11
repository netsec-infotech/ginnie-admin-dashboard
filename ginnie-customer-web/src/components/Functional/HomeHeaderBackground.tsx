import { FC, useEffect, useRef } from "react";
import type { AnimationItem } from "lottie-web";

import type { HomeHeaderAppearance } from "@/helpers/homeHeaderAppearance";

interface HomeHeaderBackgroundProps {
  appearance: HomeHeaderAppearance;
}

const encodeCssUrl = (value: string) =>
  value
    .replace(/\\/g, "\\\\")
    .replace(/"/g, '\\"')
    .replace(/\n|\r/g, "");

const LottieBackground: FC<{ src: string }> = ({ src }) => {
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    let animation: AnimationItem | null = null;
    let disposed = false;
    let syncMotionPreference: (() => void) | null = null;
    const reducedMotion =
      typeof window.matchMedia === "function"
        ? window.matchMedia("(prefers-reduced-motion: reduce)")
        : null;

    const handleAnimationFailure = () => {
      // A failed JSON request or malformed animation can leave an empty SVG
      // container behind. Destroy it so the colour layer underneath is the
      // visible, intentional fallback.
      animation?.destroy();
      animation = null;
    };

    void import("lottie-web")
      .then(({ default: lottie }) => {
        if (disposed) return;

        animation = lottie.loadAnimation({
          container,
          renderer: "svg",
          loop: true,
          autoplay: !reducedMotion?.matches,
          path: src,
          rendererSettings: {
            preserveAspectRatio: "xMidYMid slice",
          },
        });

        syncMotionPreference = () => {
          if (!animation) return;
          if (reducedMotion?.matches) {
            animation.pause();
            animation.goToAndStop(0, true);
          } else {
            animation.play();
          }
        };

        animation.addEventListener("DOMLoaded", syncMotionPreference);
        animation.addEventListener("data_failed", handleAnimationFailure);
        reducedMotion?.addEventListener("change", syncMotionPreference);
      })
      .catch(() => {
        // The configured colour remains visible when the remote animation is
        // unavailable or blocked by CORS.
      });

    return () => {
      disposed = true;
      if (syncMotionPreference) {
        reducedMotion?.removeEventListener("change", syncMotionPreference);
      }
      animation?.destroy();
    };
  }, [src]);

  return (
    <div
      ref={containerRef}
      className="absolute inset-0 h-full w-full [&_svg]:h-full! [&_svg]:w-full!"
    />
  );
};

const HomeHeaderBackground: FC<HomeHeaderBackgroundProps> = ({ appearance }) => {
  return (
    <div
      aria-hidden="true"
      className="pointer-events-none absolute inset-0 z-0 overflow-hidden"
    >
      <div
        className="absolute inset-0"
        style={
          appearance.backgroundColor
            ? { backgroundColor: appearance.backgroundColor }
            : undefined
        }
      />

      {appearance.type === "image" && appearance.backgroundImage && (
        <div
          className="absolute inset-0 bg-center bg-no-repeat"
          style={{
            backgroundImage: `url("${encodeCssUrl(appearance.backgroundImage)}")`,
            // Header artwork is supplied as a crop of the hero's top edge. Show
            // the complete crop so its final scanline can join the hero below.
            backgroundSize: "100% 100%",
          }}
        />
      )}

      {appearance.type === "animation" && appearance.backgroundAnimation && (
        <LottieBackground src={appearance.backgroundAnimation} />
      )}
    </div>
  );
};

export default HomeHeaderBackground;
