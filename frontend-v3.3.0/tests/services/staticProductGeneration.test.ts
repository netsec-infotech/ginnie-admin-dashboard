// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  mkdtempSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const mocks = vi.hoisted(() => ({
  getProducts: vi.fn(),
  getSettings: vi.fn(),
  getCategories: vi.fn(),
  getSubCategories: vi.fn(),
}));

vi.mock("@/routes/api", () => ({
  getBrands: vi.fn(),
  getCategories: mocks.getCategories,
  getDeliveryZones: vi.fn(),
  getHomeLayout: vi.fn(),
  getProductBySlug: vi.fn(),
  getProducts: mocks.getProducts,
  getSections: vi.fn(),
  getSettings: mocks.getSettings,
  getStores: vi.fn(),
  getSubCategories: mocks.getSubCategories,
}));

let temporaryDirectory: string;

beforeEach(() => {
  temporaryDirectory = mkdtempSync(join(tmpdir(), "hyperlocal-products-"));
  vi.resetModules();
  vi.clearAllMocks();
});

afterEach(() => {
  vi.unstubAllEnvs();
  rmSync(temporaryDirectory, { recursive: true, force: true });
});

describe("static product snapshot", () => {
  it("shares one catalog snapshot across product route generators", async () => {
    const filename = join(temporaryDirectory, "products.json");
    const product = {
      id: 725,
      slug: "sports-shorts",
      title: "Sports Shorts",
      main_image: "https://cdn.example.com/current.jpg",
    };
    writeFileSync(
      filename,
      JSON.stringify({ generatedAt: new Date().toISOString(), products: [product] }),
    );
    vi.stubEnv("HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT", filename);

    const { getStaticProduct, getStaticProductSlugs } = await import(
      "@/services/staticGenerationService"
    );

    expect(await getStaticProductSlugs()).toEqual(["sports-shorts"]);
    expect(await getStaticProduct("sports-shorts")).toMatchObject(product);
    expect(mocks.getProducts).not.toHaveBeenCalled();
    expect(mocks.getSettings).not.toHaveBeenCalled();
  });

  it("builds nested category pages from the snapshot without per-category product requests", async () => {
    const filename = join(temporaryDirectory, "products.json");
    writeFileSync(filename, JSON.stringify({ products: [
      { id: 1, slug: "first", category_id: 1 },
      { id: 2, slug: "second", category_id: 2 },
      { id: 3, slug: "third", category_id: 3 },
      { id: 4, slug: "unrelated", category_id: 4 },
    ] }));
    vi.stubEnv("HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT", filename);
    mocks.getSettings.mockResolvedValue({ success: true, data: [
      { variable: "web", value: { defaultLatitude: "23", defaultLongitude: "69" } },
    ] });
    mocks.getCategories.mockResolvedValue({ success: true, data: {
      data: [{ id: 1, slug: "parent", parent_id: null }, { id: 4, slug: "other", parent_id: null }],
      total: 2, per_page: 100,
    } });
    mocks.getSubCategories.mockResolvedValue({ success: true, data: {
      data: [{ id: 2, slug: "child", parent_id: 1 }, { id: 3, slug: "grandchild", parent_id: 2 }],
      total: 2, per_page: 100,
    } });

    const { getStaticCategoryProducts } = await import("@/services/staticGenerationService");
    const page = await getStaticCategoryProducts("parent", 2);

    expect(page).toMatchObject({
      success: true,
      data: { current_page: 1, per_page: 2, last_page: 2, total: 3 },
    });
    expect(page.data.data.map((product) => product.slug)).toEqual(["first", "second"]);
    expect(mocks.getProducts).not.toHaveBeenCalled();
  });
});
