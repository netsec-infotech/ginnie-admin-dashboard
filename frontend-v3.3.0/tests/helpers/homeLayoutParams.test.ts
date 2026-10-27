import { describe, it, expect, beforeEach } from "vitest";
import { buildHomeLayoutParams } from "@/helpers/homeLayout";

/**
 * Regression cover for a real bug: the homepage captured the active category in
 * a useMemo at mount, so switching category kept requesting the ORIGINAL
 * category_slug forever ("stuck with the same"). These tests drive the real
 * getActiveCategory() via jsdom's URL — no mocks — to pin the per-call contract.
 */
const setUrl = (search: string) =>
  window.history.pushState({}, "", `/${search}`);

beforeEach(() => {
  setUrl("");
  // Clear any homeCategory cookie fallback getActiveCategory() would read.
  document.cookie = "homeCategory=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
});

describe("buildHomeLayoutParams — resolves scope per call", () => {
  it("picks up the CURRENT category on every call, not the first", () => {
    setUrl("?category=beauty-1");
    expect(buildHomeLayoutParams().category_slug).toBe("beauty-1");

    // The bug: this used to keep returning "beauty-1".
    setUrl("?category=grocery-1");
    expect(buildHomeLayoutParams().category_slug).toBe("grocery-1");

    setUrl("?category=electronics-1");
    expect(buildHomeLayoutParams().category_slug).toBe("electronics-1");
  });

  it("omits category_slug for the 'all' tab", () => {
    setUrl("?category=all");
    expect(buildHomeLayoutParams().category_slug).toBeUndefined();
  });

  it("omits category_slug when no category is selected", () => {
    setUrl("");
    expect(buildHomeLayoutParams().category_slug).toBeUndefined();
  });

  it("defaults platform to web", () => {
    expect(buildHomeLayoutParams().platform).toBe("web");
  });

  it("passes through pagination params from the hook", () => {
    const params = buildHomeLayoutParams({ page: 3, per_page: 5 });
    expect(params.page).toBe(3);
    expect(params.per_page).toBe(5);
  });

  it("omits latitude/longitude when no location cookie is set", () => {
    const params = buildHomeLayoutParams();
    expect(params.latitude).toBeUndefined();
    expect(params.longitude).toBeUndefined();
  });

  it("includes the location from the cookie when present", () => {
    document.cookie = `userLocation=${encodeURIComponent(
      JSON.stringify({ lat: "23.242", lng: "69.6669" }),
    )}; path=/`;

    const params = buildHomeLayoutParams();
    expect(params.latitude).toBe("23.242");
    expect(params.longitude).toBe("69.6669");

    document.cookie =
      "userLocation=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  });
});
