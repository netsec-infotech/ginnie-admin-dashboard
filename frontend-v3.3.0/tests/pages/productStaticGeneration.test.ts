// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { getStaticPaths, getStaticProps } from "@/pages/products/[slug]/index";
import { getStaticProps as getShareProps } from "@/pages/share/products/[slug]";

const mocks = vi.hoisted(() => ({
  ssr: false,
  getProduct: vi.fn(),
  getProducts: vi.fn(async () => ({ success: true, data: {
    data: [{ slug: "existing", title: "Existing product" }], total: 1, per_page: 100,
  } })),
}));
vi.mock("@/routes/api", () => ({
  getProductBySlug: mocks.getProduct,
  getProducts: mocks.getProducts,
  getSettings: async () => ({ success: true, data: [] }),
}));
vi.mock("@/helpers/getters", () => ({
  isSSR: () => mocks.ssr,
  getConfiguredDefaultLocation: () => ({ lat: 1, lng: 2 }),
  getWebSettings: () => ({}),
}));
vi.mock("@/helpers/auth", () => ({}));
vi.mock("@/helpers/functionalHelpers", () => ({}));
vi.mock("../../i18n", () => ({}));
vi.mock("@/views/Products/ProductPage", () => ({ default: () => null }));

beforeEach(() => {
  // A local build may leave a real catalog snapshot in node_modules/.cache.
  // Keep this test bound to its mocked catalog regardless of build history.
  vi.stubEnv("HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT", "tests/fixtures/missing-product-snapshot.json");
  mocks.ssr = true;
  mocks.getProduct.mockReset();
});

afterEach(() => vi.unstubAllEnvs());

describe.each([
  ["product", getStaticProps],
  ["shared product", getShareProps],
] as const)("%s generation", (_label, load) => {
  it("uses the catalog snapshot at build time", async () => {
    const result = await load({ params: { slug: "existing" }, revalidateReason: "build" });
    expect(result).toMatchObject({ props: { initialProduct: { title: "Existing product" } } });
    expect(mocks.getProduct).not.toHaveBeenCalled();
  });

  it("loads a new slug even after the catalog snapshot has been cached", async () => {
    await getStaticPaths({});
    mocks.getProduct.mockResolvedValue({ success: true, data: { slug: "new", title: "New product" } });
    const result = await load({ params: { slug: "new" }, revalidateReason: "stale" });
    expect(result).toMatchObject({ props: { initialProduct: { title: "New product" }, slug: "new" }, revalidate: 300 });
    expect(mocks.getProduct).toHaveBeenCalledWith({ slug: "new", latitude: "1", longitude: "2" });
  });

  it("refreshes existing product details instead of reusing the catalog forever", async () => {
    mocks.getProduct.mockResolvedValue({ success: true, data: { slug: "existing", title: "Updated product" } });
    const result = await load({ params: { slug: "existing" }, revalidateReason: "on-demand" });
    expect(result).toMatchObject({ props: { initialProduct: { title: "Updated product" } } });
  });

  it.each([
    { success: false, data: [], retryable: false },
    { success: false, data: [] },
    { success: true, data: [] },
  ])("returns a revalidatable 404 for unavailable products (%j)", async (response) => {
    mocks.getProduct.mockResolvedValue(response);
    expect(await load({ params: { slug: "removed" }, revalidateReason: "stale" }))
      .toEqual({ notFound: true, revalidate: 300 });
  });

  it("does not cache a transient API failure as a missing product", async () => {
    mocks.getProduct.mockResolvedValue({ success: false, retryable: true });
    await expect(load({ params: { slug: "existing" }, revalidateReason: "stale" }))
      .rejects.toThrow("temporarily unavailable");
    mocks.getProduct.mockResolvedValue({ success: true, data: { slug: "existing", title: "Recovered" } });
    expect(await load({ params: { slug: "existing" }, revalidateReason: "stale" }))
      .toMatchObject({ props: { initialProduct: { title: "Recovered" } } });
  });

  it("keeps static export compatible with its shared client fallback", async () => {
    mocks.ssr = false;
    expect(await load({ params: { slug: "unbuilt" }, revalidateReason: "build" }))
      .toEqual({ notFound: true });
    expect(mocks.getProduct).not.toHaveBeenCalled();
  });

  it("rejects malformed slug parameters", async () => {
    expect(await load({ params: { slug: ["invalid", "slug"] } })).toEqual({ notFound: true });
    expect(mocks.getProduct).not.toHaveBeenCalled();
  });

  it("recovers a disabled product on the next development request in export mode", async () => {
    vi.stubEnv("NODE_ENV", "development");
    mocks.ssr = false;
    mocks.getProduct.mockResolvedValueOnce({ success: false, data: [], retryable: false });
    expect(await load({ params: { slug: "reenabled" } })).toEqual({ notFound: true });
    mocks.getProduct.mockResolvedValueOnce({ success: true, data: { slug: "reenabled", title: "Re-enabled product" } });
    expect(await load({ params: { slug: "reenabled" } }))
      .toMatchObject({ props: { initialProduct: { title: "Re-enabled product" } } });
  });
});

it("refreshes development paths when an admin enables a previously absent product", async () => {
  vi.stubEnv("NODE_ENV", "development");
  mocks.ssr = false;
  mocks.getProducts.mockResolvedValueOnce({ success: true, data: { data: [], total: 0, per_page: 100 } });
  expect(await getStaticPaths({})).toEqual({ paths: [], fallback: false });
  mocks.getProducts.mockResolvedValueOnce({ success: true, data: { data: [{ slug: "reenabled", title: "Re-enabled product" }], total: 1, per_page: 100 } });
  expect(await getStaticPaths({})).toEqual({ paths: [{ params: { slug: "reenabled" } }], fallback: false });
});

it("uses blocking generation only on a server that can generate new pages", async () => {
  expect(await getStaticPaths({})).toMatchObject({ fallback: "blocking" });
  mocks.ssr = false;
  expect(await getStaticPaths({})).toMatchObject({ fallback: false });
});
