/**
 * Runtime brand theming.
 *
 * `web.primaryColor` is set in the admin panel (currently "#397dea") while the
 * build-time theme in `tailwind.config.ts` hardcodes "#3b82f6". Rather than
 * requiring a redeploy to change the brand colour, the palette is regenerated
 * from the setting and written over HeroUI's CSS variables at runtime.
 *
 * HeroUI stores colours as space-separated HSL CHANNELS — `--heroui-primary-500:
 * 217.22 91.22% 59.8%` — not as hex or `hsl(...)`. Anything written here must
 * match that format exactly or every `bg-primary` utility silently breaks.
 */

export interface Hsl {
  h: number;
  s: number;
  l: number;
}

/** #rgb / #rrggbb → HSL. Returns null for anything unparseable. */
export const hexToHsl = (hex: string): Hsl | null => {
  if (typeof hex !== "string") return null;

  const clean = hex.trim().replace(/^#/, "");
  const full =
    clean.length === 3
      ? clean
          .split("")
          .map((c) => c + c)
          .join("")
      : clean;

  if (!/^[0-9a-f]{6}$/i.test(full)) return null;

  const r = parseInt(full.slice(0, 2), 16) / 255;
  const g = parseInt(full.slice(2, 4), 16) / 255;
  const b = parseInt(full.slice(4, 6), 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const delta = max - min;
  const l = (max + min) / 2;

  if (delta === 0) return { h: 0, s: 0, l: l * 100 };

  const s = delta / (1 - Math.abs(2 * l - 1));

  let h: number;
  if (max === r) h = ((g - b) / delta) % 6;
  else if (max === g) h = (b - r) / delta + 2;
  else h = (r - g) / delta + 4;

  h *= 60;
  if (h < 0) h += 360;

  return { h, s: s * 100, l: l * 100 };
};

/** The HeroUI variable format: "217.22 91.22% 59.8%". */
export const hslToChannels = ({ h, s, l }: Hsl): string => {
  const round = (n: number) => Math.round(n * 100) / 100;
  return `${round(h)} ${round(s)}% ${round(l)}%`;
};

/**
 * Lightness for each step of the scale.
 *
 * Taken from the existing tailwind theme's blue ramp so a swapped brand colour
 * keeps the same visual rhythm — the 500 step is the base colour's own
 * lightness, and the rest fan out around it proportionally.
 */
const SCALE_LIGHTNESS: Record<number, number> = {
  50: 96.86,
  100: 92.75,
  200: 87.25,
  300: 78.43,
  400: 67.84,
  500: 59.8,
  600: 53.33,
  700: 48.04,
  800: 42.35,
  900: 35.29,
};

/**
 * Build the full 50–900 scale from one brand colour.
 *
 * Hue and saturation come from the supplied colour; only lightness varies. That
 * keeps the ramp recognisably the same brand at every step, which a naive
 * lighten/darken does not.
 */
export const buildPrimaryScale = (
  hex: string,
): Record<string, string> | null => {
  const base = hexToHsl(hex);
  if (!base) return null;

  const scale: Record<string, string> = {};
  for (const [step, lightness] of Object.entries(SCALE_LIGHTNESS)) {
    scale[step] = hslToChannels({ h: base.h, s: base.s, l: lightness });
  }
  // DEFAULT tracks 500, matching how the tailwind theme is written.
  scale.DEFAULT = scale["500"];

  return scale;
};

/**
 * Write the palette onto the document root.
 *
 * No-ops (returning false) for a missing/invalid colour so a blank or malformed
 * admin value leaves the build-time theme untouched rather than blanking the UI.
 */
export const applyPrimaryColor = (
  hex: string | null | undefined,
  root?: HTMLElement,
): boolean => {
  if (!hex || typeof document === "undefined") return false;

  const scale = buildPrimaryScale(hex);
  if (!scale) return false;

  const target = root ?? document.documentElement;
  for (const [step, channels] of Object.entries(scale)) {
    const name =
      step === "DEFAULT" ? "--heroui-primary" : `--heroui-primary-${step}`;
    target.style.setProperty(name, channels);
  }

  // Focus rings read from their own variable, so they'd otherwise stay blue.
  target.style.setProperty("--heroui-focus", scale["500"]);

  return true;
};
