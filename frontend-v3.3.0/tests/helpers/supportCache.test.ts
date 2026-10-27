import { describe, expect, it, vi } from "vitest";

const { options } = vi.hoisted(() => ({ options: vi.fn() }));
vi.mock("@ducanh2912/next-pwa", () => ({
  default: (value: unknown) => {
    options(value);
    return (config: unknown) => config;
  },
}));
import "../../next.config";

describe("private customer caching", () => {
  it("uses network-only for customer requests while retaining public caches", () => {
    const config = options.mock.calls[0][0];
    expect(config.extendDefaultRuntimeCaching).toBe(true);
    expect(config.cacheOnFrontEndNav).toBe(false);
    expect(config.aggressiveFrontEndNavCaching).toBe(false);
    const rule = config.workboxOptions.runtimeCaching[0];
    expect(rule.handler).toBe("NetworkOnly");
    expect(rule.method).toBe("GET");
    for (const path of [
      "/api/user/support/thread",
      "/api/user/support/thread/updates",
      "/api/user/support/attachments/7",
      "/backend/api/user/support/topics",
      "/api/user/orders",
      "/api/user/support-other",
      "/my-account",
      "/my-account/orders/123/",
      "/cart/",
      "/_next/data/build-id/my-account/orders/123.json",
      "/_next/data/build-id/my-account.json",
    ]) {
      expect(
        rule.urlPattern({ url: new URL(path, "https://backend.example"), request: new Request("https://backend.example") }),
      ).toBe(true);
    }
    for (const path of [
      "/api/settings",
      "/product.png",
      "/products/milk/",
      "/_next/data/build-id/products/milk.json",
    ]) {
      expect(
        rule.urlPattern({ url: new URL(path, "https://backend.example"), request: new Request("https://backend.example") }),
      ).toBe(false);
    }
    expect(rule.urlPattern({
      url: new URL("https://backend.example/custom-endpoint"),
      request: new Request("https://backend.example/custom-endpoint", { headers: { Authorization: "Bearer test" } }),
    })).toBe(true);
  });
});
