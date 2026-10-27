import { describe, it, expect, vi, afterEach } from "vitest";
import { isHomeBuilderLayout } from "@/helpers/getters";
import { Settings } from "@/types/ApiResponse";

/**
 * Settings come back as a [{ variable, value }] list, not a keyed object —
 * `layoutType` lives on the "system" entry. Mirrors /api/settings verbatim.
 */
const settingsWith = (value: unknown): Settings =>
  [
    { variable: "web", value: { siteName: "Shop" } },
    { variable: "system", value },
  ] as unknown as Settings;

afterEach(() => vi.restoreAllMocks());

describe("isHomeBuilderLayout", () => {
  it("selects the home-builder homepage for layoutType 'home_builder'", () => {
    // The live value on /api/settings today.
    expect(
      isHomeBuilderLayout(
        settingsWith({ layoutType: "home_builder", systemVendorType: "multiple" }),
      ),
    ).toBe(true);
  });

  it("keeps the legacy homepage when layoutType is absent", () => {
    expect(isHomeBuilderLayout(settingsWith({ systemVendorType: "multiple" }))).toBe(
      false,
    );
  });

  /**
   * An unknown layout must degrade to the homepage that needs no backend
   * opt-in, rather than rendering nothing.
   */
  it.each([
    ["a layout we don't know", "some_future_layout"],
    ["an empty string", ""],
    ["null", null],
    ["a near-miss", "homebuilder"],
    ["different casing", "Home_Builder"],
  ])("keeps the legacy homepage for %s", (_label, layoutType) => {
    expect(isHomeBuilderLayout(settingsWith({ layoutType }))).toBe(false);
  });

  /**
   * Null/undefined settings mean "not loaded yet, or the request failed".
   * Legacy is the safe answer — it renders without any backend opt-in.
   */
  it.each([
    ["null settings", null],
    ["undefined settings", undefined],
  ])("keeps the legacy homepage for %s", (_label, settings) => {
    expect(isHomeBuilderLayout(settings as Settings | null)).toBe(false);
  });

  it("keeps the legacy homepage when the system entry is missing entirely", () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    const settings = [{ variable: "web", value: {} }] as unknown as Settings;
    expect(isHomeBuilderLayout(settings)).toBe(false);
  });

  it("survives a malformed payload rather than throwing", () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    // A non-array shape has been seen from the error path of getSettings.
    expect(isHomeBuilderLayout({} as unknown as Settings)).toBe(false);
    expect(isHomeBuilderLayout("nonsense" as unknown as Settings)).toBe(false);
    expect(
      isHomeBuilderLayout(settingsWith("not-an-object") as Settings),
    ).toBe(false);
  });
});
