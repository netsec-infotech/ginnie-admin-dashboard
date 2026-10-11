import { describe, expect, it } from "vitest";
import {
  applyPrimaryColor,
  buildPrimaryScale,
  hexToHsl,
  hslToChannels,
} from "@/helpers/theme";

/** The live value on /api/settings today. */
const BRAND = "#397dea";

describe("hexToHsl", () => {
  it("converts the configured brand colour", () => {
    // Cross-checked against Python's colorsys: hsl(216.9492, 80.8219%, 57.0588%).
    const hsl = hexToHsl(BRAND)!;
    expect(hsl.h).toBeCloseTo(216.95, 1);
    expect(hsl.s).toBeCloseTo(80.82, 1);
    expect(hsl.l).toBeCloseTo(57.06, 1);
  });

  it.each([
    ["white", "#ffffff", { h: 0, s: 0, l: 100 }],
    ["black", "#000000", { h: 0, s: 0, l: 0 }],
    ["pure red", "#ff0000", { h: 0, s: 100, l: 50 }],
    ["pure green", "#00ff00", { h: 120, s: 100, l: 50 }],
    ["pure blue", "#0000ff", { h: 240, s: 100, l: 50 }],
  ])("converts %s", (_label, hex, expected) => {
    const hsl = hexToHsl(hex)!;
    expect(Math.round(hsl.h)).toBe(expected.h);
    expect(Math.round(hsl.s)).toBe(expected.s);
    expect(Math.round(hsl.l)).toBe(expected.l);
  });

  it("accepts shorthand and a missing hash", () => {
    expect(hexToHsl("#f00")).toEqual(hexToHsl("#ff0000"));
    expect(hexToHsl("397dea")).toEqual(hexToHsl("#397dea"));
  });

  it("is case-insensitive and tolerates whitespace", () => {
    expect(hexToHsl("  #397DEA  ")).toEqual(hexToHsl("#397dea"));
  });

  /**
   * A malformed admin value must not produce NaN channels — that would write
   * "NaN NaN% NaN%" into the CSS var and blank every primary-coloured element.
   */
  it.each([
    ["empty", ""],
    ["not a colour", "blue"],
    ["wrong length", "#12345"],
    ["non-hex chars", "#gggggg"],
    ["a css function", "rgb(57,125,234)"],
    ["null", null],
    ["a number", 123],
  ])("returns null for %s", (_label, input) => {
    expect(hexToHsl(input as string)).toBeNull();
  });
});

describe("hslToChannels", () => {
  it("emits HeroUI's space-separated channel format", () => {
    // Must match `--heroui-primary-500: 217.22 91.22% 59.8%` exactly in shape;
    // an hsl(...) wrapper or commas would break every bg-primary utility.
    expect(hslToChannels({ h: 217.22, s: 91.22, l: 59.8 })).toBe(
      "217.22 91.22% 59.8%",
    );
  });

  it("rounds to two decimals", () => {
    expect(hslToChannels({ h: 216.123456, s: 80.987654, l: 57.1 })).toBe(
      "216.12 80.99% 57.1%",
    );
  });
});

describe("buildPrimaryScale", () => {
  it("produces every step the theme defines", () => {
    const scale = buildPrimaryScale(BRAND)!;
    for (const step of [50, 100, 200, 300, 400, 500, 600, 700, 800, 900]) {
      expect(scale[String(step)]).toBeTruthy();
    }
    expect(scale.DEFAULT).toBe(scale["500"]);
  });

  it("keeps hue and saturation constant across the ramp", () => {
    // Only lightness should vary — otherwise the scale drifts to a different
    // colour at its extremes.
    const scale = buildPrimaryScale(BRAND)!;
    const hues = Object.values(scale).map((v) => v.split(" ")[0]);
    const sats = Object.values(scale).map((v) => v.split(" ")[1]);

    expect(new Set(hues).size).toBe(1);
    expect(new Set(sats).size).toBe(1);
  });

  it("gets lighter as the step number falls", () => {
    const scale = buildPrimaryScale(BRAND)!;
    const lightness = (step: string) =>
      parseFloat(scale[step].split(" ")[2].replace("%", ""));

    expect(lightness("50")).toBeGreaterThan(lightness("500"));
    expect(lightness("500")).toBeGreaterThan(lightness("900"));
  });

  it("returns null for an invalid colour", () => {
    expect(buildPrimaryScale("nonsense")).toBeNull();
  });

  it("never emits NaN", () => {
    const scale = buildPrimaryScale(BRAND)!;
    for (const value of Object.values(scale)) {
      expect(value).not.toMatch(/NaN/);
    }
  });
});

describe("applyPrimaryColor", () => {
  it("writes the scale onto the target element", () => {
    const root = document.createElement("div");
    expect(applyPrimaryColor(BRAND, root)).toBe(true);

    expect(root.style.getPropertyValue("--heroui-primary-500")).toBeTruthy();
    expect(root.style.getPropertyValue("--heroui-primary")).toBe(
      root.style.getPropertyValue("--heroui-primary-500"),
    );
  });

  it("repoints the focus ring so it isn't left on the old brand", () => {
    const root = document.createElement("div");
    applyPrimaryColor(BRAND, root);
    expect(root.style.getPropertyValue("--heroui-focus")).toBe(
      root.style.getPropertyValue("--heroui-primary-500"),
    );
  });

  /**
   * Leaving the build-time theme intact is the correct failure mode — writing
   * junk would blank every primary-coloured element on the site.
   */
  it.each([
    ["null", null],
    ["undefined", undefined],
    ["empty", ""],
    ["a malformed value", "#zzz"],
  ])("does nothing for %s", (_label, input) => {
    const root = document.createElement("div");
    expect(applyPrimaryColor(input as string | null, root)).toBe(false);
    expect(root.style.getPropertyValue("--heroui-primary-500")).toBe("");
  });
});
