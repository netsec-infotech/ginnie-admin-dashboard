import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render } from "@testing-library/react";

/**
 * Covers the recovery behaviour of the /offline route.
 *
 * The route is the service worker's document fallback, so it is served under
 * whatever URL the user actually asked for — not under /offline. That makes
 * "what do we do when the connection comes back" two different answers, and
 * getting it wrong strands the user on the offline screen.
 */

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string) => key,
      i18n: { language: "en", resolvedLanguage: "en" },
    }),
  };
});

const { replace } = vi.hoisted(() => ({ replace: vi.fn() }));
vi.mock("next/router", () => ({ useRouter: () => ({ replace }) }));

// PageHead pulls in next/head + SettingsContext; neither is under test here.
vi.mock("@/SEO/PageHead", () => ({ default: () => null }));

import Offline from "@/pages/offline";

const reload = vi.fn();
const originalLocation = window.location;

/** Swap in a location whose pathname and reload() we control. */
const setPathname = (pathname: string) => {
  Object.defineProperty(window, "location", {
    configurable: true,
    writable: true,
    value: { ...originalLocation, pathname, reload },
  });
};

beforeEach(() => {
  replace.mockClear();
  reload.mockClear();
});

afterEach(() => {
  Object.defineProperty(window, "location", {
    configurable: true,
    writable: true,
    value: originalLocation,
  });
});

describe("/offline route — recovery when the connection returns", () => {
  it("reloads the requested URL when served as a fallback for another page", () => {
    setPathname("/products/mango-pickle/");
    render(<Offline />);

    window.dispatchEvent(new Event("online"));

    // The address bar still shows the product URL, so a reload retries it.
    expect(reload).toHaveBeenCalledTimes(1);
    expect(replace).not.toHaveBeenCalled();
  });

  it("sends a direct visit to /offline home instead of reloading it", () => {
    setPathname("/offline/");
    render(<Offline />);

    window.dispatchEvent(new Event("online"));

    // Reloading /offline would just render the offline screen again.
    expect(replace).toHaveBeenCalledWith("/");
    expect(reload).not.toHaveBeenCalled();
  });

  it("matches /offline with or without the trailing slash", () => {
    // `trailingSlash: true` means the canonical URL has one, but a direct hit
    // on /offline must not fall through to the reload branch either.
    setPathname("/offline");
    render(<Offline />);

    window.dispatchEvent(new Event("online"));

    expect(replace).toHaveBeenCalledWith("/");
    expect(reload).not.toHaveBeenCalled();
  });

  it("does nothing while still offline", () => {
    setPathname("/offline/");
    render(<Offline />);

    expect(replace).not.toHaveBeenCalled();
    expect(reload).not.toHaveBeenCalled();
  });

  it("stops listening once unmounted", () => {
    setPathname("/offline/");
    const { unmount } = render(<Offline />);
    unmount();

    window.dispatchEvent(new Event("online"));

    expect(replace).not.toHaveBeenCalled();
  });

  it("renders without the default layout", () => {
    // DefaultLayout's chrome is settings-driven over the network — exactly what
    // is unavailable here — so the page must opt out.
    expect(Offline.getLayout).toBeTypeOf("function");
    const page = <div data-testid="bare" />;
    expect(Offline.getLayout!(page)).toBe(page);
  });
});
