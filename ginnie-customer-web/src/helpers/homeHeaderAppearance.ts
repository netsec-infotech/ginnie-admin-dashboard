import type { HomeGeneralSettings } from "@/types/ApiResponse";

export interface HomeHeaderAppearance {
  type: "color" | "image" | "animation";
  backgroundColor?: string;
  backgroundImage?: string;
  backgroundAnimation?: string;
  fontColor?: string;
}

const normalizeCssColor = (value: unknown): string | undefined => {
  if (typeof value !== "string") return undefined;
  const candidate = value.trim();
  if (!candidate || /[;"{}]/.test(candidate)) return undefined;
  return /^[#(),.%\w\s-]+$/.test(candidate) ? candidate : undefined;
};

const normalizeAssetUrl = (value: unknown): string | undefined => {
  if (typeof value !== "string") return undefined;
  const candidate = value.trim();
  if (!candidate || /[\\\r\n]/.test(candidate)) return undefined;

  if (candidate.startsWith("/") && !candidate.startsWith("//")) {
    return candidate;
  }

  try {
    const url = new URL(candidate);
    return url.protocol === "http:" || url.protocol === "https:"
      ? url.href
      : undefined;
  } catch {
    return undefined;
  }
};

const normalizeBackgroundType = (
  value: unknown,
): HomeHeaderAppearance["type"] => {
  if (typeof value !== "string") return "color";

  switch (value.trim().toLowerCase()) {
    case "image":
      return "image";
    case "animation":
    case "lottie":
      return "animation";
    default:
      return "color";
  }
};

export const resolveHomeHeaderAppearance = (
  settings?: HomeGeneralSettings | null,
  backgroundImageOverride?: string | null,
): HomeHeaderAppearance => {
  const backgroundColor =
    normalizeCssColor(settings?.backgroundColorWeb) ??
    normalizeCssColor(settings?.backgroundColor);
  const configuredBackgroundImage = normalizeAssetUrl(
    settings?.backgroundImageWeb ?? settings?.backgroundImage,
  );
  const overriddenBackgroundImage = normalizeAssetUrl(backgroundImageOverride);
  const backgroundImage =
    overriddenBackgroundImage || configuredBackgroundImage;
  const backgroundAnimation = normalizeAssetUrl(
    settings?.backgroundAnimationWeb ?? settings?.backgroundAnimation,
  );
  const fontColor = normalizeCssColor(settings?.fontColor);

  const requestedType = normalizeBackgroundType(settings?.backgroundType);
  const hasWebMedia = Boolean(
    normalizeAssetUrl(settings?.backgroundImageWeb) ||
      normalizeAssetUrl(settings?.backgroundAnimationWeb),
  );

  // Web media wins over web colour regardless of the legacy background type.
  // If both web assets are present, the explicit legacy type remains a useful
  // tie-breaker; otherwise animation wins, then image.
  const webMediaType =
    requestedType === "animation" && backgroundAnimation
      ? "animation"
      : requestedType === "image" && backgroundImage
        ? "image"
        : backgroundAnimation
          ? "animation"
          : backgroundImage
            ? "image"
            : "color";

  // Legacy-only settings keep their existing selected-mode behaviour. This
  // avoids reviving an old asset after an older admin configuration explicitly
  // selected a colour, while the new *Web fields follow the media-first rule.
  const type = overriddenBackgroundImage
    ? "image"
    : hasWebMedia
      ? webMediaType
      : requestedType === "animation" && backgroundAnimation
        ? "animation"
        : requestedType === "image" && backgroundImage
          ? "image"
          : "color";

  return {
    type,
    backgroundColor,
    backgroundImage,
    backgroundAnimation,
    fontColor,
  };
};
