import type { HomeGeneralSettings } from "@/types/ApiResponse";

export interface HomeHeaderAppearance {
  type: "color" | "image" | "animation";
  backgroundColor?: string;
  backgroundImage?: string;
  mobileBackgroundImage?: string;
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
  backgroundColorOverride?: string | null,
  mobileBackgroundImageOverride?: string | null,
): HomeHeaderAppearance => {
  const configuredBackgroundColor =
    normalizeCssColor(settings?.backgroundColorWeb) ??
    normalizeCssColor(settings?.backgroundColor);
  const configuredBackgroundImage = normalizeAssetUrl(
    settings?.backgroundImageWeb ?? settings?.backgroundImage,
  );
  const configuredMobileBackgroundImage = normalizeAssetUrl(
    settings?.backgroundImageWebMobile,
  );
  const overriddenBackgroundImage = normalizeAssetUrl(backgroundImageOverride);
  const overriddenMobileBackgroundImage = normalizeAssetUrl(
    mobileBackgroundImageOverride,
  );
  const overriddenBackgroundColor = normalizeCssColor(backgroundColorOverride);
  const hasCategoryImageOverride = Boolean(
    overriddenBackgroundImage || overriddenMobileBackgroundImage,
  );
  const backgroundImage = hasCategoryImageOverride
    ? overriddenBackgroundImage
    : configuredBackgroundImage;
  const mobileBackgroundImage = hasCategoryImageOverride
    ? overriddenMobileBackgroundImage
    : configuredMobileBackgroundImage;
  // A category image must not inherit the unrelated global header colour as
  // its underlay. Transparent edge pixels would otherwise reveal that colour
  // as a one-pixel divider. Prefer the category colour; a neutral canvas is the
  // safe fallback for image overrides without one.
  const backgroundColor =
    overriddenBackgroundColor ??
    (hasCategoryImageOverride ? "#ffffff" : configuredBackgroundColor);
  const backgroundAnimation = normalizeAssetUrl(
    settings?.backgroundAnimationWeb ?? settings?.backgroundAnimation,
  );
  const fontColor = normalizeCssColor(settings?.fontColor);

  const requestedType = normalizeBackgroundType(settings?.backgroundType);
  // `backgroundType` is the authoritative mode. Admin responses can retain
  // stale image/animation URLs after switching to a colour; those assets must
  // not silently override the selected mode. Category artwork is an explicit
  // per-category override and therefore remains higher priority.
  const type = hasCategoryImageOverride
    ? "image"
    : requestedType === "animation" && backgroundAnimation
      ? "animation"
      : requestedType === "image" &&
          (backgroundImage || mobileBackgroundImage)
        ? "image"
        : "color";

  return {
    type,
    backgroundColor,
    backgroundImage: type === "image" ? backgroundImage : undefined,
    mobileBackgroundImage:
      type === "image" ? mobileBackgroundImage : undefined,
    backgroundAnimation:
      type === "animation" ? backgroundAnimation : undefined,
    fontColor,
  };
};
