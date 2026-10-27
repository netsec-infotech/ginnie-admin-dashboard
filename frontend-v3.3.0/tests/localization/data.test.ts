import { describe, expect, it } from "vitest";
import {
  getApiSnapshot,
  getLocalSnapshot,
  parseLabels,
  parseLanguages,
  translate,
} from "@/lib/localization/data";

describe("admin localization contract", () => {
  it("rejects a language list whose default cannot be selected", () => {
    expect(parseLanguages({
      default: "fr",
      languages: [{ code: "en", name: "English", native_name: "English", direction: "ltr" }],
    })).toBeNull();
  });

  it("rejects a different locale, malformed direction, and unsafe nested keys", () => {
    expect(parseLabels({ locale: "ar", direction: "rtl", labels: { home: "بيت" } }, "en")).toBeNull();
    expect(parseLabels({ locale: "ar", direction: "sideways", labels: { home: "بيت" } }, "ar")).toBeNull();
    expect(parseLabels({
      locale: "en",
      direction: "ltr",
      labels: JSON.parse('{"a":{"__proto__":{"polluted":"yes"}}}'),
    }, "en")).toBeNull();
  });

  it("keeps local text for omitted API keys and supports nested interpolation", () => {
    const response = parseLabels({
      locale: "en",
      direction: "ltr",
      labels: { promoEligibility: { unavailable: "Need {{remaining}} {{currency}} more" } },
    }, "en");
    expect(response).not.toBeNull();
    const snapshot = getApiSnapshot(response!);
    expect(translate(snapshot.labels, "promoEligibility.unavailable", {
      remaining: 5,
      currency: "USD",
    })).toBe("Need 5 USD more");
    expect(translate(snapshot.labels, "home")).toBe(translate(getLocalSnapshot("en")!.labels, "home"));
  });
});
