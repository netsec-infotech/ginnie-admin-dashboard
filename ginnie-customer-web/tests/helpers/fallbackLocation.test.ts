import { describe, expect, it, vi } from "vitest";
import { getFallbackLocation } from "@/helpers/functionalHelpers";
import { staticLat, staticLng } from "@/config/constants";
import { Settings } from "@/types/ApiResponse";

/**
 * Settings arrive as a [{ variable, value }] list — `defaultLatitude` lives on
 * the "web" entry. Mirrors /api/settings verbatim.
 */
const settingsWith = (value: unknown): Settings =>
  [
    { variable: "system", value: { systemVendorType: "multiple" } },
    { variable: "web", value },
  ] as unknown as Settings;

/**
 * Crawlers send no cookies, and the catalog API rejects blank coordinates, so
 * without this fallback every bot-rendered product page came back empty.
 */
describe("getFallbackLocation", () => {
  it("prefers the backend's configured default location", () => {
    // The live values on /api/settings today. `generate-sitemap.mjs` resolves
    // its product URLs at exactly these, so anything in the sitemap resolves.
    const settings = settingsWith({
      defaultLatitude: "23.2347162612901",
      defaultLongitude: "69.6471415369673",
    });

    expect(getFallbackLocation(settings)).toEqual({
      lat: "23.2347162612901",
      lng: "69.6471415369673",
    });
  });

  it("falls back to the static constants when settings are absent", () => {
    expect(getFallbackLocation(null)).toEqual({
      lat: String(staticLat),
      lng: String(staticLng),
    });
    expect(getFallbackLocation(undefined)).toEqual({
      lat: String(staticLat),
      lng: String(staticLng),
    });
  });

  it("falls back when the web entry has no default location", () => {
    expect(getFallbackLocation(settingsWith({ siteName: "Shop" }))).toEqual({
      lat: String(staticLat),
      lng: String(staticLng),
    });
  });

  /**
   * The API answers a non-numeric coord with a 422 validation error rather than
   * an empty result, so a junk value is strictly worse than falling back.
   */
  it.each([
    ["empty strings", { defaultLatitude: "", defaultLongitude: "" }],
    ["non-numeric", { defaultLatitude: "abc", defaultLongitude: "def" }],
    ["only latitude", { defaultLatitude: "23.5" }],
    ["only longitude", { defaultLongitude: "69.6" }],
    ["null values", { defaultLatitude: null, defaultLongitude: null }],
  ])("ignores %s and uses the static fallback", (_label, value) => {
    expect(getFallbackLocation(settingsWith(value))).toEqual({
      lat: String(staticLat),
      lng: String(staticLng),
    });
  });

  it("accepts a legitimate zero coordinate", () => {
    // 0 is a real longitude (Greenwich) — it must not be mistaken for "unset".
    expect(
      getFallbackLocation(
        settingsWith({ defaultLatitude: "51.48", defaultLongitude: "0" }),
      ),
    ).toEqual({ lat: "51.48", lng: "0" });
  });

  it("survives a malformed settings payload rather than throwing", () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    expect(() =>
      getFallbackLocation("nonsense" as unknown as Settings),
    ).not.toThrow();
    expect(getFallbackLocation({} as unknown as Settings)).toEqual({
      lat: String(staticLat),
      lng: String(staticLng),
    });
    vi.restoreAllMocks();
  });

  it("always returns usable coordinates so SSR never sends blanks", () => {
    // The whole point: the catalog API fails on blank coords, so every branch
    // must yield something finite.
    for (const input of [null, undefined, settingsWith({}), settingsWith(null)]) {
      const result = getFallbackLocation(input as Settings | null);
      expect(result).not.toBeNull();
      expect(Number.isFinite(Number(result!.lat))).toBe(true);
      expect(Number.isFinite(Number(result!.lng))).toBe(true);
    }
  });
});
