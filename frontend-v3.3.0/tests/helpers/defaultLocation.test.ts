import { describe, expect, it } from "vitest";
import {
  getConfiguredDefaultLocation,
  hasConfiguredDefaultLocation,
} from "@/helpers/getters";

describe("hasConfiguredDefaultLocation", () => {
  it("accepts valid coordinates supplied by web settings", () => {
    const settings = {
      defaultLatitude: "23.242",
      defaultLongitude: "69.6669",
    };

    expect(hasConfiguredDefaultLocation(settings)).toBe(true);
    expect(getConfiguredDefaultLocation(settings)).toEqual({
      lat: 23.242,
      lng: 69.6669,
    });
  });

  it.each([
    ["missing settings", null],
    ["empty coordinates", { defaultLatitude: "", defaultLongitude: "" }],
    [
      "non-numeric coordinates",
      { defaultLatitude: "north", defaultLongitude: "west" },
    ],
    [
      "out-of-range coordinates",
      { defaultLatitude: "91", defaultLongitude: "181" },
    ],
  ])("rejects %s", (_label, settings) => {
    expect(hasConfiguredDefaultLocation(settings)).toBe(false);
    expect(getConfiguredDefaultLocation(settings)).toBeNull();
  });
});
