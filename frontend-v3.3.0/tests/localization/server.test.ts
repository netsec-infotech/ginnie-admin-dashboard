import { describe, expect, it, vi } from "vitest";
import type { IncomingMessage } from "node:http";
import { getLocalSnapshot } from "@/lib/localization/data";
import { createServerI18n } from "@/lib/localization/serverI18n";

vi.mock("@/routes/api", () => ({
  getWebLanguages: vi.fn(async () => ({
    success: true,
    message: "",
    data: {
      default: "en",
      languages: [
        { code: "en", name: "English", native_name: "English", direction: "ltr" },
        { code: "ar", name: "Arabic", native_name: "العربية", direction: "rtl" },
      ],
    },
  })),
  getWebLanguageLabels: vi.fn(async (locale: string) => ({
    success: true,
    message: "",
    data: {
      locale,
      direction: locale === "ar" ? "rtl" : "ltr",
      labels: { home: locale === "ar" ? "الرئيسية" : "Home from API" },
    },
  })),
}));

import { getRequestLocalization, loadServerLocalization } from "@/lib/localization/server";

const request = (locale: string) => ({
  headers: { cookie: `i18nextLng=${encodeURIComponent(JSON.stringify(locale))}` },
}) as IncomingMessage;

describe("SSR localization", () => {
  it("isolates concurrent request dictionaries and records document direction", async () => {
    const arabicRequest = request("ar");
    const englishRequest = request("en");
    const [arabic, english] = await Promise.all([
      loadServerLocalization(arabicRequest),
      loadServerLocalization(englishRequest),
    ]);
    expect(arabic.snapshot.direction).toBe("rtl");
    expect(english.snapshot.direction).toBe("ltr");
    expect(getRequestLocalization(arabicRequest)).toBe(arabic);
    expect(createServerI18n(arabic.snapshot).t("home")).toBe("الرئيسية");
    expect(createServerI18n(english.snapshot).t("home")).toBe("Home from API");
    expect(getLocalSnapshot("en")?.labels.home).not.toBe("Home from API");
  });
});
