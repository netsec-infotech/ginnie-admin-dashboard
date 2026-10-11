import { describe, expect, it } from "vitest";
import { isNoIndexRoute, PROTECTED_ROUTES } from "@/guards/authGuard";

/**
 * The site-wide SEOHead used to emit `index, follow` for every page, inviting
 * Google to index carts and account pages. robots.txt blocks crawling but not
 * indexing — only a noindex tag does that.
 */
describe("isNoIndexRoute", () => {
  it.each([
    "/cart",
    "/checkout",
    "/my-account",
    "/shopping-list",
    "/forgot-password",
    "/404",
  ])("excludes %s", (path) => {
    expect(isNoIndexRoute(path)).toBe(true);
  });

  it("matches nested routes by prefix", () => {
    expect(isNoIndexRoute("/my-account/orders")).toBe(true);
    expect(isNoIndexRoute("/my-account/orders/1234")).toBe(true);
    expect(isNoIndexRoute("/my-account/wallet")).toBe(true);
  });

  /** `trailingSlash: true` means pathnames reach us both ways. */
  it("ignores a trailing slash", () => {
    expect(isNoIndexRoute("/cart/")).toBe(true);
    expect(isNoIndexRoute("/my-account/orders/")).toBe(true);
  });

  it("handles Next dynamic-route pathnames", () => {
    // router.pathname keeps the bracket form, not the resolved URL.
    expect(isNoIndexRoute("/my-account/orders/[slug]")).toBe(true);
  });

  it.each([
    "/",
    "/about-us",
    "/stores",
    "/categories",
    "/categories/tasty-pizza",
    "/products/margherita-pizza",
    "/brands",
    "/faqs",
    "/seller-register",
    "/delivery-zones",
  ])("keeps %s indexable", (path) => {
    expect(isNoIndexRoute(path)).toBe(false);
  });

  /**
   * The guard is a prefix match, so a public route that merely STARTS with the
   * same letters must not be caught.
   */
  it("does not match on a partial segment", () => {
    expect(isNoIndexRoute("/cartography")).toBe(false);
    expect(isNoIndexRoute("/my-accounting")).toBe(false);
    expect(isNoIndexRoute("/shopping-list-guide")).toBe(false);
  });

  it("handles empty input without throwing", () => {
    expect(isNoIndexRoute("")).toBe(false);
  });

  /**
   * Every auth-gated route must be noindex. The reverse doesn't hold —
   * /shopping-list and /forgot-password are reachable logged-out but still
   * shouldn't rank — so this is a subset check, not equality.
   */
  it("covers every protected route", () => {
    for (const route of PROTECTED_ROUTES) {
      expect(isNoIndexRoute(route)).toBe(true);
    }
  });
});
