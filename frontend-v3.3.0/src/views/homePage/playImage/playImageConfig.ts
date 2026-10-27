import type { CSSProperties } from "react";

import type {
  HomeLayoutPlayImageItemConfig,
  HomeLayoutPlayImageRow,
  HomeLayoutPlayImageRowConfig,
  HomeLayoutSection,
} from "@/types/ApiResponse";

export type PlayImageRowStyle = "grid" | "scroll";

export interface ResolvedPlayImageRow extends Omit<
  HomeLayoutPlayImageRow,
  "style" | "config"
> {
  style: PlayImageRowStyle;
  config: HomeLayoutPlayImageRowConfig;
}

export const asPlayImageRecord = (value: unknown): Record<string, unknown> =>
  value && typeof value === "object" && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : {};

/** Detect upgraded legacy banners across both supported API payload shapes. */
export const isLegacyBannerSection = (section: HomeLayoutSection): boolean =>
  section.type === "play_image" &&
  [section.legacy_type, section.config?.legacy_type].some(
    (legacyType) =>
      typeof legacyType === "string" &&
      legacyType.trim().toLowerCase() === "banners",
  );

const mergeDefined = (
  ...records: Record<string, unknown>[]
): HomeLayoutPlayImageRowConfig => {
  const merged: HomeLayoutPlayImageRowConfig = {};

  records.forEach((record) => {
    Object.entries(record).forEach(([key, value]) => {
      if (
        value !== null &&
        value !== undefined &&
        !(typeof value === "string" && value.trim() === "")
      ) {
        merged[key] = value;
      }
    });
  });

  return merged;
};

const firstStyle = (...values: unknown[]): PlayImageRowStyle => {
  const configured = values.find(
    (value) => typeof value === "string" && value.trim(),
  );
  const style = String(configured || "grid")
    .trim()
    .toLowerCase();

  return ["scroll", "slider", "carousel"].includes(style) ? "scroll" : "grid";
};

/**
 * Resolve the redundant builder payload defensively:
 * section defaults -> matching section.config.rows entry -> content row config.
 * Rows match by row_index first and array position as a compatibility fallback.
 * The content row's top-level style remains authoritative, matching the app.
 */
export const resolvePlayImageRow = (
  section: HomeLayoutSection,
  row: HomeLayoutPlayImageRow,
): ResolvedPlayImageRow => {
  const sectionConfig = asPlayImageRecord(section.config);
  const configuredRows = Array.isArray(section.config?.rows)
    ? section.config.rows
    : [];
  const rowIndex = String(row.row_index);
  const configuredRow = asPlayImageRecord(
    configuredRows.find(
      (candidate) =>
        String(asPlayImageRecord(candidate).row_index) === rowIndex,
    ) ?? configuredRows[row.sourceIndex],
  );
  const configuredRowConfig = mergeDefined(
    configuredRow,
    asPlayImageRecord(configuredRow.config),
  );
  const contentRowConfig = asPlayImageRecord(row.config);
  const config = mergeDefined(
    sectionConfig,
    configuredRowConfig,
    contentRowConfig,
  );
  const style = firstStyle(
    row.style,
    contentRowConfig.style,
    configuredRowConfig.style,
    section.style,
    sectionConfig.style,
  );

  return {
    ...row,
    title:
      row.title ||
      (typeof configuredRowConfig.title === "string"
        ? configuredRowConfig.title
        : null),
    style,
    config: { ...config, style },
  };
};

const normalizeCssLength = (value: unknown): string | null => {
  if (typeof value === "number" && Number.isFinite(value)) return `${value}px`;
  if (typeof value !== "string") return null;

  const candidate = value.trim();
  if (!candidate) return null;
  if (/^-?\d+(\.\d+)?$/.test(candidate)) return `${candidate}px`;
  return /^-?\d+(\.\d+)?(px|rem|em|vw|vh|dvh|svh|vmin|vmax|%)$/i.test(candidate)
    ? candidate
    : null;
};

const normalizeNonNegativeLength = (value: unknown): string | null => {
  const length = normalizeCssLength(value);
  return length && !length.startsWith("-") ? length : null;
};

const normalizeCssColor = (value: unknown): string | null => {
  if (typeof value !== "string") return null;
  const candidate = value.trim();
  if (!candidate || /[;"{}]/.test(candidate)) return null;
  return /^[#(),.%\w\s-]+$/.test(candidate) ? candidate : null;
};

const encodeUrl = (url: string) =>
  url.replace(/\(/g, "%28").replace(/\)/g, "%29").replace(/\s/g, "%20");

export const normalizePlayImageGap = (value: unknown): number => {
  const parsed =
    typeof value === "number"
      ? value
      : typeof value === "string" && value.trim()
        ? Number(value)
        : Number.NaN;

  return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
};

export const normalizeBuilderBoolean = (value: unknown): boolean =>
  value === true ||
  value === 1 ||
  (typeof value === "string" &&
    ["1", "true", "yes"].includes(value.trim().toLowerCase()));

export const getPlayImageBorderRadiusStyle = (
  value: unknown,
): CSSProperties => {
  const radius = normalizeNonNegativeLength(value);
  return radius ? { borderRadius: radius } : {};
};

const getPlayImageRowPadding = (
  config: HomeLayoutPlayImageRowConfig,
): CSSProperties => {
  const custom = config.outer_padding_custom;
  if (typeof custom === "string" && custom.trim()) {
    const values = custom.split("/").map(normalizeNonNegativeLength);
    if (values.length === 4 && values.every((value) => value !== null)) {
      return { padding: values.join(" ") };
    }
  }

  const padding = normalizeNonNegativeLength(config.outer_padding);
  return padding ? { paddingInline: padding } : {};
};

export const getPlayImageRowSurfaceStyle = (
  row: ResolvedPlayImageRow,
): CSSProperties => {
  const backgroundType = row.config.background_type;
  const backgroundColor = normalizeCssColor(row.config.background_color);
  const backgroundImage = row.config.background_image;
  const style: CSSProperties = {
    ...getPlayImageRowPadding(row.config),
    ...getPlayImageBorderRadiusStyle(row.config.border_radius),
  };

  if (
    backgroundType === "image" &&
    typeof backgroundImage === "string" &&
    backgroundImage.trim()
  ) {
    style.backgroundImage = `url("${encodeUrl(backgroundImage)}")`;
    style.backgroundPosition = "center";
    style.backgroundRepeat = "no-repeat";
    style.backgroundSize = "cover";
  } else if (backgroundType === "color" && backgroundColor) {
    style.backgroundColor = backgroundColor;
  }

  return style;
};

/** Flutter-compatible `auto` allocation on a 12-column row. */
export const getAutoPlayImageSpan = (itemCount: number): number => {
  if (itemCount <= 1) return 12;
  if (itemCount === 2) return 6;
  if (itemCount === 3) return 4;
  if (itemCount === 4) return 3;
  if (itemCount <= 6) return 2;
  return Math.max(1, Math.floor(12 / itemCount));
};

export const getPlayImageGridSpan = (
  value: unknown,
  itemCount: number,
): number => {
  // The app makes a lone image a complete row even if stale item metadata
  // still carries a smaller span from a previously multi-item row.
  if (itemCount <= 1) return 12;

  const parsed =
    typeof value === "number"
      ? value
      : typeof value === "string" && value.trim().toLowerCase() !== "auto"
        ? Number(value)
        : Number.NaN;

  return Number.isFinite(parsed) && parsed > 0
    ? Math.min(12, Math.max(1, Math.floor(parsed)))
    : getAutoPlayImageSpan(itemCount);
};

export const getPlayImageSlidesPerView = (
  value: unknown,
  fallback: number,
): number => {
  const parsed =
    typeof value === "number"
      ? value
      : typeof value === "string" && value.trim().toLowerCase() !== "auto"
        ? Number(value)
        : Number.NaN;

  return Number.isFinite(parsed) && parsed > 0
    ? Math.min(12, Math.max(0.5, parsed))
    : fallback;
};

export const normalizePlayImageAspectRatio = (
  value: unknown,
): string | null => {
  if (typeof value === "number" && Number.isFinite(value) && value > 0) {
    return String(value);
  }
  if (typeof value !== "string") return null;

  const candidate = value.trim().replace(/[:x]/i, " / ");
  if (/^\d+(\.\d+)?(?:\s*\/\s*\d+(\.\d+)?)?$/.test(candidate)) {
    return candidate.replace(/\s*\/\s*/, " / ");
  }
  return null;
};

export const normalizePlayImageFit = (
  value: unknown,
): CSSProperties["objectFit"] => {
  if (typeof value !== "string") return "cover";
  const candidate = value.trim().toLowerCase();
  return ["fill", "cover", "contain", "none", "scale-down"].includes(candidate)
    ? (candidate as CSSProperties["objectFit"])
    : "cover";
};

export const normalizePlayImagePosition = (value: unknown): string =>
  typeof value === "string" && /^[\w.%\-\s]+$/.test(value.trim())
    ? value.trim()
    : "center";

export const getPlayImageItemConfig = (
  value: unknown,
): HomeLayoutPlayImageItemConfig =>
  asPlayImageRecord(value) as HomeLayoutPlayImageItemConfig;
