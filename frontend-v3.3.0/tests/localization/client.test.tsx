import { afterEach, describe, expect, it, vi } from "vitest";
import { act, render, screen } from "@testing-library/react";
import { useTranslation } from "react-i18next";
import { getLocalSnapshot } from "@/lib/localization/data";
import { activateLanguage } from "@/lib/redux/slices/localizationSlice";
import { store } from "@/lib/redux/store";
import { applyLanguageSnapshot } from "../../i18n";

vi.mock("@/routes/api", () => ({
  getWebLanguages: vi.fn(),
  getWebLanguageLabels: vi.fn(),
}));

import { getWebLanguageLabels } from "@/routes/api";
import { switchLanguage } from "@/lib/localization/client";

const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
};

function Title() {
  const { t } = useTranslation();
  return <span>{t("home")}</span>;
}

afterEach(() => {
  const local = getLocalSnapshot("en")!;
  store.dispatch(activateLanguage(local));
  applyLanguageSnapshot(local);
  vi.resetAllMocks();
});

describe("language switching", () => {
  it("discards a late response from an earlier selection", async () => {
    const hi = deferred<Awaited<ReturnType<typeof getWebLanguageLabels>>>();
    const ar = deferred<Awaited<ReturnType<typeof getWebLanguageLabels>>>();
    vi.mocked(getWebLanguageLabels).mockImplementation((locale) =>
      locale === "hi" ? hi.promise : ar.promise,
    );

    const first = switchLanguage("hi");
    const second = switchLanguage("ar");
    ar.resolve({ success: true, message: "", data: {
      locale: "ar", direction: "rtl", labels: { home: "الرئيسية" },
    } });
    await expect(second).resolves.toBe(true);
    hi.resolve({ success: true, message: "", data: {
      locale: "hi", direction: "ltr", labels: { home: "मुखपृष्ठ" },
    } });
    await expect(first).resolves.toBe(false);
    expect(store.getState().localization.active.locale).toBe("ar");
    expect(document.documentElement).toHaveAttribute("dir", "rtl");
  });

  it("updates existing useTranslation callers when API labels change in the same locale", async () => {
    render(<Title />);
    await act(async () => {
      applyLanguageSnapshot({
        locale: "en", direction: "ltr", source: "api", labels: { home: "Admin home" },
      });
    });
    expect(screen.getByText("Admin home")).toBeInTheDocument();
  });

  it("switches to bundled labels when the labels API is unavailable", async () => {
    vi.mocked(getWebLanguageLabels).mockResolvedValue({ success: false, message: "", data: null });
    await expect(switchLanguage("hi")).resolves.toBe(true);
    expect(store.getState().localization.active).toMatchObject({ locale: "hi", source: "local" });
    expect(document.documentElement).toHaveAttribute("lang", "hi");
  });
});
