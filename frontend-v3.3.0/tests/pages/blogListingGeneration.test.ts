import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
const mocks = vi.hoisted(() => ({
  ssr: false,
  listing: vi.fn(),
  categories: vi.fn(),
  settings: vi.fn(),
}));
vi.mock("@/helpers/getters", () => ({ isSSR: () => mocks.ssr }));
vi.mock("@/routes/api", () => ({
  getBlogCategories: mocks.categories,
  getSettings: mocks.settings,
}));
vi.mock("@/services/blogListingService", () => ({
  getBlogListingPage: mocks.listing,
}));
vi.mock("@/views/Blog/BlogListingView", () => ({
  default: () => null,
  POSTS_PER_PAGE: 6,
}));
vi.mock("@/SEO/DynamicSEO", () => ({ default: () => null }));
vi.mock("../../i18n", () => ({ loadTranslations: vi.fn() }));
beforeEach(() => {
  vi.spyOn(console, "warn").mockImplementation(() => {});
  vi.resetModules();
  mocks.ssr = false;
  mocks.listing
    .mockReset()
    .mockResolvedValue({
      success: true,
      data: { data: [{ id: 1, slug: "food", title: "Food" }] },
    });
  mocks.categories.mockReset().mockResolvedValue({ success: true, data: [] });
  mocks.settings.mockReset().mockResolvedValue({ success: true, data: [] });
});
afterEach(() => vi.restoreAllMocks());

describe("blog listing prerendering", () => {
  it("includes public article data in the static export", async () => {
    const { default: Page, getServerSideProps } = await import("@/pages/blogs");
    expect(getServerSideProps).toBeUndefined();
    const props = await Page.getInitialProps!();
    expect(props.initialBlogs?.data?.data[0].slug).toBe("food");
    expect(mocks.listing).toHaveBeenCalledWith({ page: 1, per_page: 6 });
    expect(JSON.parse(JSON.stringify(props))).toEqual(props);
  });

  it("skips initial blog data with a reason when the API fails", async () => {
    mocks.listing.mockResolvedValue({ success: false, message: "Blogs are disabled" });
    const { default: Page } = await import("@/pages/blogs");
    expect(await Page.getInitialProps!()).toMatchObject({ initialBlogs: null });
    expect(console.warn).toHaveBeenCalledWith("Skipping initial blog data: Blogs are disabled");
  });

  it("continues when blog requests reject and keeps available settings", async () => {
    mocks.listing.mockRejectedValue(new Error("Blog connection timed out"));
    mocks.categories.mockRejectedValue(new Error("Categories unavailable"));
    const { default: Page } = await import("@/pages/blogs");
    expect(await Page.getInitialProps!()).toMatchObject({
      initialBlogs: null, initialCategories: null, initialSettings: [],
    });
    expect(console.warn).toHaveBeenCalledWith("Skipping initial blog data: Blog connection timed out");
  });

  it("retains query-aware SSR without the export-only hook", async () => {
    mocks.ssr = true;
    const { default: Page, getServerSideProps } = await import("@/pages/blogs");
    expect(Page.getInitialProps).toBeUndefined();
    expect(getServerSideProps).toBeTypeOf("function");
  });
});
