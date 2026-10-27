import React, { useEffect } from "react";
import { act } from "@testing-library/react";
import { renderToString } from "react-dom/server";
import { hydrateRoot } from "react-dom/client";
import { useTranslation } from "react-i18next";
import { afterEach, describe, expect, it, vi } from "vitest";
import type { GetServerSidePropsContext } from "next";

// A saved Arabic preference must not affect module-time initialization.
vi.hoisted(() => {
  document.cookie = 'i18nextLng=%22ar%22; path=/';
});

import i18n, {
  changeLanguage,
  loadTranslations,
  restoreLanguagePreference,
} from "../../i18n";

function TranslatedHeader() {
  const { t } = useTranslation();
  useEffect(restoreLanguagePreference, []);
  return <span>{t("home")}</span>;
}

afterEach(() => {
  changeLanguage("en");
  document.cookie = "i18nextLng=; Max-Age=0; path=/";
});

describe("language hydration", () => {
  it("hydrates English HTML before restoring saved Arabic without replacing the tree", async () => {
    expect(i18n.language).toBe("en");
    const container = document.createElement("div");
    container.innerHTML = renderToString(<TranslatedHeader />);
    const originalSpan = container.firstChild;
    expect(container.textContent).toBe(i18n.getFixedT("en")("home"));
    const onRecoverableError = vi.fn();
    let root: ReturnType<typeof hydrateRoot>;
    await act(async () => {
      root = hydrateRoot(container, <TranslatedHeader />, { onRecoverableError });
    });
    expect(onRecoverableError).not.toHaveBeenCalled();
    expect(container.firstChild).toBe(originalSpan);
    expect(i18n.language).toBe("ar");
    expect(container.textContent).toBe(i18n.getFixedT("ar")("home"));
    expect(document.documentElement).toHaveAttribute("dir", "rtl");
    expect(document.documentElement).toHaveAttribute("lang", "ar");
    await act(async () => root.unmount());
  });

  it("does not let SSR request cookies mutate the shared language", async () => {
    await Promise.all(["ar", "hi"].map((language) => loadTranslations({
      req: { cookies: { i18nextLng: JSON.stringify(language) } },
    } as unknown as GetServerSidePropsContext)));
    expect(i18n.language).toBe("en");
  });

  it("updates both document language and direction when switching back to English", () => {
    changeLanguage("ar");
    changeLanguage("en");
    expect(document.documentElement).toHaveAttribute("dir", "ltr");
    expect(document.documentElement).toHaveAttribute("lang", "en");
  });
});
