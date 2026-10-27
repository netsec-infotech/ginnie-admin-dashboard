// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const api = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock("axios", () => ({ default: { create: () => api } }));
vi.mock("dotenv", () => ({ config: vi.fn() }));

let root: string;
const post = { id: 1, slug: "fresh-post", title: "Fresh post", published_at: "2026-10-01" };
const paginated = (data: unknown[], page = 1, total = data.length, lastPage = 1) => ({
  data: { success: true, data: { data, current_page: page, total, per_page: 100, last_page: lastPage } },
});
const snapshot = () => JSON.parse(readFileSync(join(root, "blogs.json"), "utf8"));
const sitemap = () => readFileSync(join(root, "public", "sitemap.xml"), "utf8");

beforeEach(() => {
  vi.resetModules();
  vi.useFakeTimers();
  root = mkdtempSync(join(tmpdir(), "hyperlocal-sitemap-"));
  mkdirSync(join(root, "public"));
  vi.spyOn(process, "cwd").mockReturnValue(root);
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", "https://shop.example.com");
  vi.stubEnv("NEXT_PUBLIC_ADMIN_PANEL_URL", "https://api.example.com");
  vi.stubEnv("HYPERLOCAL_STATIC_BLOG_SNAPSHOT", join(root, "blogs.json"));
  vi.stubEnv("HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT", join(root, "products.json"));
  vi.stubEnv("HYPERLOCAL_STATIC_STORE_SNAPSHOT", join(root, "stores.json"));
  vi.spyOn(console, "log").mockImplementation(() => {});
  vi.spyOn(console, "warn").mockImplementation(() => {});
  // A failed run must overwrite articles from earlier builds.
  writeFileSync(join(root, "blogs.json"), JSON.stringify({ posts: [{ ...post, slug: "stale-post" }] }));
  api.get.mockReset().mockImplementation(async (endpoint: string) => {
    if (endpoint === "/settings") return {
      data: { success: true, data: [{ variable: "web", value: { defaultLatitude: 20, defaultLongitude: 70 } }] },
    };
    if (endpoint === "/home-layout") return { data: { success: true, data: { sections: [], last_page: 1 } } };
    if (endpoint === "/blogs") return paginated([post]);
    if (endpoint === "/blogs/fresh-post") return { data: { success: true, data: post } };
    if (endpoint === "/categories") return paginated([{ slug: "groceries" }]);
    return paginated([]);
  });
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllEnvs();
  vi.useRealTimers();
  rmSync(root, { recursive: true, force: true });
});

describe("optional blogs during sitemap generation", () => {
  it("includes published articles when their API is available", async () => {
    const { main } = await import("../../scripts/generate-sitemap.mjs");
    await main();
    expect(sitemap()).toContain("https://shop.example.com/blogs/fresh-post/");
    expect(snapshot().posts).toEqual([post]);
    expect(snapshot()).not.toHaveProperty("skippedReason");
    expect(console.warn).not.toHaveBeenCalled();
  });

  it.each([
    ["/blogs", "Request failed with status code 404"],
    ["/blogs/fresh-post", "timeout of 60000ms exceeded"],
  ])("continues after %s fails and records the reason", async (failedEndpoint, reason) => {
    const normal = api.get.getMockImplementation()!;
    api.get.mockImplementation((endpoint, ...args) => {
      if (endpoint === failedEndpoint) return Promise.reject(new Error(reason));
      return normal(endpoint, ...args);
    });
    const { main } = await import("../../scripts/generate-sitemap.mjs");
    const result = main();
    await vi.runAllTimersAsync();
    await result;
    expect(sitemap()).toContain("https://shop.example.com/categories/groceries/");
    expect(sitemap()).toContain("https://shop.example.com/blogs/</loc>");
    expect(sitemap()).not.toContain("/blogs/fresh-post/");
    expect(sitemap()).not.toContain("/blogs/stale-post/");
    expect(snapshot()).toMatchObject({ posts: [], skippedReason: expect.stringContaining(reason) });
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining(`Skipped blogs for this build:`));
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining(reason));
  });

  it("discards partial pagination when a later blog page returns an API error", async () => {
    const normal = api.get.getMockImplementation()!;
    api.get.mockImplementation((endpoint, options) => {
      if (endpoint === "/blogs") return Promise.resolve(options.params.page === 1
        ? paginated([post], 1, 2, 2)
        : { data: { success: false, message: "Blogs are disabled" } });
      return normal(endpoint, options);
    });
    const { main } = await import("../../scripts/generate-sitemap.mjs");
    await main();
    expect(snapshot()).toMatchObject({ posts: [], skippedReason: expect.stringContaining("Blogs are disabled") });
    expect(sitemap()).not.toContain("/blogs/fresh-post/");
  });

  it("keeps failures of required catalog endpoints fatal", async () => {
    const normal = api.get.getMockImplementation()!;
    api.get.mockImplementation((endpoint, ...args) => endpoint === "/categories"
      ? Promise.resolve({ data: { success: false, message: "Catalog unavailable" } })
      : normal(endpoint, ...args));
    const { main } = await import("../../scripts/generate-sitemap.mjs");
    await expect(main()).rejects.toThrow("Catalog unavailable");
  });
});
