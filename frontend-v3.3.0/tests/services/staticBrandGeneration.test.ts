// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from "vitest";
import { getStaticBrandSlugs } from "@/services/staticGenerationService";

const mocks = vi.hoisted(() => ({
  getBrands: vi.fn(),
  getSettings: vi.fn(async () => ({
    success: true,
    data: [
      {
        variable: "web",
        value: {
          defaultLatitude: "23.23",
          defaultLongitude: "69.64",
        },
      },
    ],
  })),
}));

vi.mock("@/routes/api", () => ({
  getBrands: mocks.getBrands,
  getSettings: mocks.getSettings,
  getCategories: vi.fn(),
  getDeliveryZones: vi.fn(),
  getHomeLayout: vi.fn(),
  getProductBySlug: vi.fn(),
  getProducts: vi.fn(),
  getSections: vi.fn(),
  getStores: vi.fn(),
}));

describe("getStaticBrandSlugs", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("collects both global and category-scoped brands into a deduplicated slug list", async () => {
    mocks.getBrands.mockImplementation(async (params) => {
      if (params?.scope_category_slug === "all") {
        return {
          success: true,
          data: {
            data: [{ slug: "amul" }, { slug: "nike" }],
            total: 2,
            per_page: 100,
          },
        };
      }
      return {
        success: true,
        data: {
          data: [{ slug: "bata-1" }, { slug: "campus-1" }, { slug: "amul" }],
          total: 3,
          per_page: 100,
        },
      };
    });

    const slugs = await getStaticBrandSlugs();
    expect(slugs).toEqual(["bata-1", "campus-1", "amul", "nike"]);
    expect(mocks.getBrands).toHaveBeenCalledWith(
      expect.objectContaining({ scope_category_slug: "all" })
    );
    expect(mocks.getBrands).toHaveBeenCalledWith(
      expect.not.objectContaining({ scope_category_slug: "all" })
    );
  });
});
