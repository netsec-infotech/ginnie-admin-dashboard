/**
 * Shared colour tokens for the homepage hero and its category-aware content.
 *
 * Keeping this as a document-level custom property lets the sticky navigation
 * and the page content agree on the exact same colour even though they live in
 * different branches of the layout tree.
 */
export interface HomeCategoryTheme {
  color: string;
  foreground: "#111827" | "#ffffff";
  controlBackground: string;
  controlHoverBackground: string;
  controlBorder: string;
  controlSelectedBackground: string;
  controlIconBackground: string;
  controlIconSelectedBackground: string;
  focusRing: string;
}

const toSixDigitHex = (value?: string | null): string | null => {
  if (!value || typeof value !== "string") return null;

  const hex = value.trim();
  if (/^#[0-9a-f]{6}$/i.test(hex)) return hex;

  if (/^#[0-9a-f]{3}$/i.test(hex)) {
    return `#${hex
      .slice(1)
      .split("")
      .map((channel) => channel + channel)
      .join("")}`;
  }

  return null;
};

const relativeLuminance = (hex: string) => {
  const red = parseInt(hex.slice(1, 3), 16) / 255;
  const green = parseInt(hex.slice(3, 5), 16) / 255;
  const blue = parseInt(hex.slice(5, 7), 16) / 255;
  const linear = (channel: number) =>
    channel <= 0.03928
      ? channel / 12.92
      : Math.pow((channel + 0.055) / 1.055, 2.4);

  const luminance =
    0.2126 * linear(red) + 0.7152 * linear(green) + 0.0722 * linear(blue);

  return luminance;
};

const contrastRatio = (first: string, second: string) => {
  const [lighter, darker] = [relativeLuminance(first), relativeLuminance(second)].sort(
    (a, b) => b - a,
  );

  return (lighter + 0.05) / (darker + 0.05);
};

/** A restrained lift keeps the brand hue recognisable and text legible. */
const lightenHex = (hex: string, amount = 0.1) => {
  const lightenChannel = (offset: number) => {
    const channel = parseInt(hex.slice(offset, offset + 2), 16);
    return Math.round(channel + (255 - channel) * amount)
      .toString(16)
      .padStart(2, "0");
  };

  return `#${lightenChannel(1)}${lightenChannel(3)}${lightenChannel(5)}`;
};

const alpha = (hex: "#111827" | "#ffffff", opacity: number) => {
  const red = parseInt(hex.slice(1, 3), 16);
  const green = parseInt(hex.slice(3, 5), 16);
  const blue = parseInt(hex.slice(5, 7), 16);

  return `rgba(${red}, ${green}, ${blue}, ${opacity})`;
};

const resolveControlTokens = (foreground: "#111827" | "#ffffff") => {
  const isDarkInk = foreground === "#111827";

  return {
    controlBackground: alpha(foreground, isDarkInk ? 0.06 : 0.18),
    controlHoverBackground: alpha(foreground, isDarkInk ? 0.1 : 0.26),
    controlBorder: alpha(foreground, isDarkInk ? 0.14 : 0.32),
    controlSelectedBackground: alpha(foreground, isDarkInk ? 0.1 : 0.24),
    controlIconBackground: alpha(foreground, isDarkInk ? 0.07 : 0.16),
    controlIconSelectedBackground: alpha(foreground, isDarkInk ? 0.13 : 0.34),
    focusRing: alpha(foreground, isDarkInk ? 0.55 : 0.9),
  };
};

/**
 * Category colour wins. "All" deliberately passes no category colour, so the
 * API-driven brand primary is used as a consistent, lightly lifted surface.
 * Invalid API values fall back to the existing HeroUI primary without writing
 * arbitrary CSS into the document.
 */
export const resolveHomeCategoryTheme = (
  categoryColor?: string | null,
  primaryColor?: string | null,
): HomeCategoryTheme => {
  const sourceColor =
    toSixDigitHex(categoryColor) ?? toSixDigitHex(primaryColor);
  const color = sourceColor
    ? lightenHex(sourceColor)
    : "color-mix(in srgb, hsl(var(--heroui-primary)) 90%, white)";
  const darkForeground = "#111827" as const;
  const lightForeground = "#ffffff" as const;
  const foreground =
    color.startsWith("#") &&
    contrastRatio(color, darkForeground) >= contrastRatio(color, lightForeground)
      ? darkForeground
      : lightForeground;

  return {
    color,
    // Pick the most legible ink rather than treating every saturated colour as
    // a dark surface. A lifted blue, for example, has better contrast with
    // dark text and icons than with white ones.
    foreground,
    ...resolveControlTokens(foreground),
  };
};

/** Apply the resolved theme for the category-aware full-bleed hero. */
export const applyHomeCategoryTheme = (theme: HomeCategoryTheme) => {
  if (typeof document === "undefined") return;

  document.documentElement.style.setProperty("--home-category-color", theme.color);
  document.documentElement.style.setProperty(
    "--home-category-foreground",
    theme.foreground,
  );
  document.documentElement.style.setProperty(
    "--home-category-control-bg",
    theme.controlBackground,
  );
  document.documentElement.style.setProperty(
    "--home-category-control-hover-bg",
    theme.controlHoverBackground,
  );
  document.documentElement.style.setProperty(
    "--home-category-control-border",
    theme.controlBorder,
  );
  document.documentElement.style.setProperty(
    "--home-category-control-selected-bg",
    theme.controlSelectedBackground,
  );
  document.documentElement.style.setProperty(
    "--home-category-control-icon-bg",
    theme.controlIconBackground,
  );
  document.documentElement.style.setProperty(
    "--home-category-control-icon-selected-bg",
    theme.controlIconSelectedBackground,
  );
  document.documentElement.style.setProperty(
    "--home-category-focus-ring",
    theme.focusRing,
  );
};
