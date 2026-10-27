import { describe, it, expect } from "vitest";
import { productAmount, productPrice } from "@/helpers/currency";
import {
  adaptHomeLayoutBannerItem,
  adaptHomeLayoutBrand,
  adaptHomeLayoutCategories,
  adaptHomeLayoutCategory,
  adaptHomeLayoutProduct,
  adaptHomeLayoutProducts,
  adaptHomeLayoutStore,
  getSectionBanners,
  getPlayImageRows,
  getSectionBrands,
  getSectionCategories,
  getSectionProducts,
  getSectionStores,
  resolveSectionBannerHref,
  toSectionListPage,
} from "@/helpers/homeLayout";
import homeLayoutResponse from "../fixtures/homeLayoutResponse.json";
import sectionFeed from "../fixtures/homeLayoutSectionFeed.json";
import categoriesResponse from "../fixtures/homeLayoutCategoriesResponse.json";
import bannersResponse from "../fixtures/homeLayoutBannersResponse.json";

// Real payloads captured from the live /home-layout API.
const rawSection = (homeLayoutResponse as any).data.sections[0];
const rawProduct = rawSection.content.products[0];
const rawFeedItems = (sectionFeed as any).data.data;

const makeRaw = (over: Record<string, any> = {}) => ({
  ...JSON.parse(JSON.stringify(rawProduct)),
  ...over,
});

describe("section product currencies", () => {
  // Price fields from the Silicon Valley Safal listing that previously showed ₹599.
  const usdVariant = {
    id: 1204, store_id: 4, is_default: true, stock: 50,
    price: 800, special_price: 599, display_price: 800, display_special_price: 599,
    currency_code: "USD",
    formatted_price: "USD $ 800.00", formatted_special_price: "USD $ 599.00",
    formatted_display_price: "USD $ 800.00", formatted_display_special_price: "USD $ 599.00",
  };

  it.each(["inline", "paginated"])("keeps USD prices through the %s listing adapter", (path) => {
    const raw = makeRaw({ title: "Safal Frozen Mixed Vegetables", variants: [usdVariant] });
    const products = path === "inline"
      ? getSectionProducts({ products: [raw] })
      : adaptHomeLayoutProducts([raw]);
    const product = products[0];
    const variant = product.variants[0];
    expect(variant).toMatchObject(usdVariant);
    expect(productPrice(variant, "special_price", "₹", product)).toBe("USD $ 599.00");
    expect(productPrice(variant, "price", "₹", product)).toBe("USD $ 800.00");
  });

  it("keeps snapshot formatting and tax-inclusive display amounts", () => {
    const product = adaptHomeLayoutProduct(makeRaw({ variants: [{
      ...usdVariant, display_special_price: "629.50", formatted_display_special_price: null,
      currency_snapshot: { formatted: { display_special_price: "USD $ 629.50" } },
    }] }))!;
    expect(productAmount(product.variants[0])).toBe(629.5);
    expect(productPrice(product.variants[0], "special_price", "₹", product)).toBe("USD $ 629.50");
  });

  it("preserves product currency as a fallback for legacy variants", () => {
    const product = adaptHomeLayoutProduct(makeRaw({ currency_code: "USD", variants: [{ id: 1, price: 800, stock: 1 }] }))!;
    expect(productPrice(product.variants[0], "price", "₹", product)).toBe("USD 800.00");
  });

  it("preserves zero display prices", () => {
    const product = adaptHomeLayoutProduct(makeRaw({ variants: [{ ...usdVariant, display_special_price: 0 }] }))!;
    expect(productAmount(product.variants[0])).toBe(0);
  });

  it("preserves store currency metadata", () => {
    expect(adaptHomeLayoutStore({ id: 4, slug: "silicon-valley-store", currency_code: "USD" })?.currency_code).toBe("USD");
  });
});

describe("adaptHomeLayoutProduct — real API fixture", () => {
  it("adapts a live product into a render-ready Product", () => {
    const product = adaptHomeLayoutProduct(rawProduct);

    expect(product).not.toBeNull();
    expect(product!.id).toBe(rawProduct.id);
    expect(product!.slug).toBe(rawProduct.slug);
    expect(product!.title).toBe(rawProduct.title);
    expect(product!.variants.length).toBeGreaterThan(0);
  });

  it("preserves the variant fields add-to-cart depends on", () => {
    const product = adaptHomeLayoutProduct(rawProduct)!;
    const variant = product.variants[0];
    const rawVariant = rawProduct.variants[0];

    // The load-bearing trio for addToCart({product_variant_id, store_id, quantity})
    expect(variant.id).toBe(rawVariant.id);
    expect(variant.store_id).toBe(rawVariant.store_id);
    expect(variant.stock).toBe(rawVariant.stock);
    // Store link fields read off variants[0]
    expect(variant.store_slug).toBe(rawVariant.store_slug);
    expect(variant.store_name).toBe(rawVariant.store_name);
  });

  it("coerces the metadata object to the string|null contract", () => {
    // The API sends metadata as an SEO object; Product declares string | null.
    expect(typeof rawProduct.metadata).toBe("object");
    expect(adaptHomeLayoutProduct(rawProduct)!.metadata).toBeNull();
  });

  it("fills fields the API omits so the card never reads undefined", () => {
    const product = adaptHomeLayoutProduct(rawProduct)!;
    expect(product.description).toBe("");
    expect(product.custom_fields).toEqual({});
    expect(product.custom_product_sections).toEqual([]);
    expect(product.returnable_days).toBeNull();
    expect(product.store_status).toBeDefined();
  });
});

describe("adaptHomeLayoutProduct — gating edge cases", () => {
  it("returns null when the product has no variants", () => {
    expect(adaptHomeLayoutProduct(makeRaw({ variants: [] }))).toBeNull();
    expect(adaptHomeLayoutProduct(makeRaw({ variants: undefined }))).toBeNull();
  });

  it("returns null for a nullish payload", () => {
    expect(adaptHomeLayoutProduct(null as any)).toBeNull();
  });

  it("defaults a missing `availability` from stock (never renders false OUT OF STOCK)", () => {
    const inStock = adaptHomeLayoutProduct(
      makeRaw({ variants: [{ id: 1, stock: 5, price: 10 }] }),
    )!;
    expect(inStock.variants[0].availability).toBe(true);

    const noStock = adaptHomeLayoutProduct(
      makeRaw({ variants: [{ id: 1, stock: 0, price: 10 }] }),
    )!;
    expect(noStock.variants[0].availability).toBe(false);
  });

  it("respects an explicit availability=false even with stock", () => {
    const product = adaptHomeLayoutProduct(
      makeRaw({ variants: [{ id: 1, stock: 5, availability: false }] }),
    )!;
    expect(product.variants[0].availability).toBe(false);
  });

  it("moves the default variant to index 0 so store link and cart agree", () => {
    const product = adaptHomeLayoutProduct(
      makeRaw({
        variants: [
          { id: 1, is_default: false, store_id: 10, store_name: "A", stock: 1 },
          { id: 2, is_default: true, store_id: 20, store_name: "B", stock: 1 },
        ],
      }),
    )!;

    expect(product.variants[0].id).toBe(2);
    expect(product.variants[0].store_name).toBe("B");
    // ProductCard's resolution must land on the same variant as variants[0]
    const defaultVariant =
      product.variants.find((v) => v.is_default) || product.variants[0];
    expect(defaultVariant.id).toBe(product.variants[0].id);
  });

  it("promotes the first variant to default when none is flagged", () => {
    const product = adaptHomeLayoutProduct(
      makeRaw({
        variants: [
          { id: 1, store_id: 10, stock: 1 },
          { id: 2, store_id: 20, stock: 1 },
        ],
      }),
    )!;
    expect(product.variants[0].id).toBe(1);
    expect(product.variants[0].is_default).toBe(true);
  });

  it("keeps the discount relationship intact", () => {
    const product = adaptHomeLayoutProduct(
      makeRaw({
        variants: [{ id: 1, price: 100, special_price: 75, stock: 2 }],
      }),
    )!;
    const v = product.variants[0];
    expect(v.price).toBe(100);
    expect(v.special_price).toBe(75);
    expect(v.special_price > 0 && v.special_price < v.price).toBe(true);
  });

  it("normalises variant attributes `[]` into a Record for the tooltip", () => {
    const product = adaptHomeLayoutProduct(
      makeRaw({ variants: [{ id: 1, stock: 1, attributes: [] }] }),
    )!;
    expect(product.variants[0].attributes).toEqual({});
    expect(() => Object.entries(product.variants[0].attributes)).not.toThrow();
  });

  it("infers product type from variant count when absent", () => {
    const simple = adaptHomeLayoutProduct(
      makeRaw({ type: undefined, variants: [{ id: 1, stock: 1 }] }),
    )!;
    expect(simple.type).toBe("simple");

    const variant = adaptHomeLayoutProduct(
      makeRaw({
        type: undefined,
        variants: [
          { id: 1, stock: 1 },
          { id: 2, stock: 1 },
        ],
      }),
    )!;
    expect(variant.type).toBe("variant");
  });

  it("defaults store_status so the card's is_open read is safe", () => {
    const product = adaptHomeLayoutProduct(
      makeRaw({ store_status: undefined }),
    )!;
    expect(product.store_status.is_open).toBe(true);
  });
});

describe("adaptHomeLayoutProducts / getSectionProducts", () => {
  it("adapts every product in the live section", () => {
    const products = adaptHomeLayoutProducts(rawSection.content.products);
    expect(products).toHaveLength(rawSection.content.products.length);
  });

  it("drops unrenderable (variant-less) products instead of failing", () => {
    const products = adaptHomeLayoutProducts([
      rawProduct,
      makeRaw({ variants: [] }),
    ]);
    expect(products).toHaveLength(1);
    expect(products[0].id).toBe(rawProduct.id);
  });

  it("handles an empty/absent list", () => {
    expect(adaptHomeLayoutProducts([])).toEqual([]);
    expect(adaptHomeLayoutProducts(undefined)).toEqual([]);
  });

  it("getSectionProducts reads content.products from a live section", () => {
    const products = getSectionProducts(rawSection.content);
    expect(products.length).toBe(rawSection.content.products.length);
    expect(products[0].variants[0].id).toBeDefined();
  });

  it("getSectionProducts returns [] for a section with no products key", () => {
    expect(getSectionProducts({} as any)).toEqual([]);
  });

  it("adapts the paginated 'view all' feed identically (same shape)", () => {
    const products = adaptHomeLayoutProducts(rawFeedItems);
    expect(products).toHaveLength(rawFeedItems.length);
    expect(products[0].variants[0].store_id).toBe(
      rawFeedItems[0].variants[0].store_id,
    );
  });
});

describe("adaptHomeLayoutCategory — real 'categories' section fixture", () => {
  const catSection = (categoriesResponse as any).data.sections[0];
  const rawCategories = catSection.content.categories as any[];
  const rawCategory = rawCategories[0];

  it("adapts a live category into a render-ready Category", () => {
    const category = adaptHomeLayoutCategory(rawCategory)!;
    expect(category).not.toBeNull();
    expect(category.id).toBe(rawCategory.id);
    expect(category.title).toBe(rawCategory.title);
    expect(category.slug).toBe(rawCategory.slug);
    expect(category.image).toBe(rawCategory.image);
    // CategoryCard builds its link from parent_slug + slug
    expect(category.parent_slug).toBe(rawCategory.parent_slug);
  });

  it("supplies the `banner` field the API never sends", () => {
    expect("banner" in rawCategory).toBe(false);
    expect(adaptHomeLayoutCategory(rawCategory)!.banner).toBe("");
  });

  it("coerces the metadata object to the string|null contract", () => {
    expect(typeof rawCategory.metadata).toBe("object");
    expect(adaptHomeLayoutCategory(rawCategory)!.metadata).toBeNull();
  });

  it("drops a category with no slug (its link would be broken)", () => {
    expect(adaptHomeLayoutCategory({ id: 1, title: "x" })).toBeNull();
    expect(adaptHomeLayoutCategory(null as any)).toBeNull();
  });

  it("adapts the whole live category list", () => {
    const categories = adaptHomeLayoutCategories(rawCategories);
    expect(categories).toHaveLength(rawCategories.length);
    expect(categories).toHaveLength(18);
  });

  it("getSectionCategories reads content.categories from a live section", () => {
    expect(getSectionCategories(catSection.content)).toHaveLength(
      rawCategories.length,
    );
  });

  it("returns [] for a section with no categories key", () => {
    expect(getSectionCategories({} as any)).toEqual([]);
    expect(adaptHomeLayoutCategories(undefined)).toEqual([]);
  });
});

describe("adaptHomeLayoutBannerItem — real 'banners' section fixture", () => {
  const rawBannerSection = (bannersResponse as any).data.sections[0];
  const rawItems = rawBannerSection.content.items as any[];

  it("adapts the live banner items", () => {
    const items = getSectionBanners(rawBannerSection.content);

    expect(items).toHaveLength(rawItems.length);
    expect(items[0].id).toBe(rawItems[0].id);
    expect(items[0].image).toBe(rawItems[0].image);
    expect(items[0].link_type).toBe("custom");
  });

  it("preserves the mobile-web image and uses it as a desktop fallback", () => {
    expect(
      adaptHomeLayoutBannerItem({
        id: 91,
        image: "desktop.jpg",
        image_web_mobile: "mobile.jpg",
      }),
    ).toMatchObject({
      image: "desktop.jpg",
      image_web_mobile: "mobile.jpg",
    });

    expect(
      adaptHomeLayoutBannerItem({
        id: 92,
        image: null,
        image_web_mobile: "mobile-only.jpg",
      }),
    ).toMatchObject({
      image: "mobile-only.jpg",
      image_web_mobile: "mobile-only.jpg",
    });
  });

  it("reads banners from `items` — the key the live API actually uses", () => {
    // The other section types key on their kind (`products`, `categories`);
    // banners are the odd one out, which is what made them render as nothing.
    expect(Object.keys(rawBannerSection.content)).toEqual(["items"]);
    expect(getSectionBanners(rawBannerSection.content).length).toBeGreaterThan(
      0,
    );
  });

  it("drops an item with no image rather than rendering an empty slide", () => {
    expect(adaptHomeLayoutBannerItem({ id: 1, image: "" })).toBeNull();
    expect(getSectionBanners({ items: [{ id: 1 }, rawItems[0]] })).toHaveLength(
      1,
    );
  });

  it("returns an empty list for a section with no banner content", () => {
    expect(getSectionBanners({})).toEqual([]);
    expect(getSectionBanners({ items: [] })).toEqual([]);
  });
});

describe("play_image row adaptation", () => {
  const playImageSection = {
    id: 657,
    type: "play_image",
    title: null,
    style: "grid",
    config: {
      spacing: {
        border_radius: {
          top_left: 12,
          top_right: 12,
          bottom_left: 12,
          bottom_right: 12,
        },
      },
    },
    background_image: null,
    hero_media: null,
    card_background_image: null,
    content: {
      rows: [
        {
          row_index: 0,
          style: "grid",
          config: {
            container_width: "full",
            border_radius: "0",
            grid_gap: "8",
          },
          items: [
            {
              id: 1064,
              image: "https://cdn.test/hero.png",
              link_type: null,
              config: {
                aspect_ratio: "original",
                object_fit: "cover",
                object_position: "center",
              },
            },
          ],
        },
        {
          row_index: 1,
          style: "grid",
          config: { container_width: "full", grid_gap: "12" },
          items: [
            {
              id: 1065,
              image: "https://cdn.test/category.png",
              link_type: "category",
              target_id: 28,
              slug: "electronics-1",
              config: { aspect_ratio: "original" },
            },
            {
              id: 1066,
              image: "https://cdn.test/custom.png",
              link_type: "custom",
              link_url: "https://example.test/deal",
              config: { aspect_ratio: "original" },
            },
          ],
        },
      ],
    },
  } as any;

  it("normalises nested rows and preserves image/link configuration", () => {
    const rows = getPlayImageRows(playImageSection.content);

    expect(rows).toHaveLength(2);
    expect(rows[0]).toMatchObject({
      row_index: 0,
      style: "grid",
      config: {
        container_width: "full",
        border_radius: "0",
        grid_gap: "8",
      },
    });
    expect(rows[0].items[0]).toMatchObject({
      id: 1064,
      image: "https://cdn.test/hero.png",
      config: { aspect_ratio: "original", object_fit: "cover" },
    });
    expect(resolveSectionBannerHref(rows[1].items[0])).toBe(
      "/categories/electronics-1",
    );
  });

  it("drops malformed rows without reordering the valid ones", () => {
    const rows = getPlayImageRows({
      rows: [null, playImageSection.content.rows[1], { items: [] }],
    } as any);

    expect(rows).toHaveLength(1);
    expect(rows[0].sourceIndex).toBe(1);
    expect(rows[0].items.map((item) => item.id)).toEqual([1065, 1066]);
  });
});

describe("resolveSectionBannerHref", () => {
  const item = (over: Record<string, any>) =>
    ({
      id: 1,
      image: "x.jpg",
      link_type: null,
      link_url: null,
      target_id: null,
      slug: null,
      ...over,
    }) as any;

  it("sends a live 'custom' banner to its URL", () => {
    const live = getSectionBanners(
      (bannersResponse as any).data.sections[0].content,
    )[0];
    expect(resolveSectionBannerHref(live)).toBe(live.link_url);
  });

  it.each([
    ["product", "/products/pickle"],
    ["category", "/categories/pickle"],
    ["brand", "/brands/pickle"],
    ["store", "/stores/pickle"],
  ])("routes link_type=%s by slug", (linkType, expected) => {
    expect(
      resolveSectionBannerHref(item({ link_type: linkType, slug: "pickle" })),
    ).toBe(expected);
  });

  it("returns null when a slug-based type has no slug", () => {
    expect(resolveSectionBannerHref(item({ link_type: "product" }))).toBeNull();
  });

  it("returns null for link_type 'none' even when a URL is present", () => {
    expect(
      resolveSectionBannerHref(
        item({ link_type: "none", link_url: "https://x.test" }),
      ),
    ).toBeNull();
  });

  it("returns null for an unlinked banner instead of '#'", () => {
    // An href="#" would render a focusable link that jumps to the top.
    expect(resolveSectionBannerHref(item({}))).toBeNull();
  });
});

describe("adaptHomeLayoutBrand / getSectionBrands", () => {
  const raw = { id: 4, title: "Everest", slug: "everest", logo: "logo.png" };

  it("adapts a brand into what BrandCard needs", () => {
    expect(adaptHomeLayoutBrand(raw)).toMatchObject({
      id: 4,
      title: "Everest",
      slug: "everest",
      logo: "logo.png",
    });
  });

  it("drops a brand with no slug — BrandCard's link would 404", () => {
    expect(adaptHomeLayoutBrand({ id: 4, title: "Everest" })).toBeNull();
    expect(getSectionBrands({ brands: [raw, { id: 5 }] })).toHaveLength(1);
  });

  it("falls back to `items` when the section keys brands that way", () => {
    expect(getSectionBrands({ items: [raw] })).toHaveLength(1);
  });
});

describe("adaptHomeLayoutStore / getSectionStores", () => {
  const raw = {
    id: 9,
    name: "Spice Depot",
    slug: "spice-depot",
    status: { is_open: false, status: "offline" },
    avg_store_rating: "4.5",
  };

  it("adapts a store into what StoreCard needs", () => {
    expect(adaptHomeLayoutStore(raw)).toMatchObject({
      id: 9,
      name: "Spice Depot",
      slug: "spice-depot",
      status: { is_open: false, status: "offline" },
      avg_store_rating: "4.5",
    });
  });

  it("preserves the saved opening notification on homepage store cards", () => {
    expect(adaptHomeLayoutStore({ ...raw, is_notify: true })?.is_notify).toBe(true);
    expect(adaptHomeLayoutStore({ ...raw, is_notify: false })?.is_notify).toBe(false);
    expect(adaptHomeLayoutStore({ ...raw, is_notify: "1" })?.is_notify).toBe(true);
  });

  it("defaults the fields StoreCard reads unguarded", () => {
    // StoreCard does `store.status.is_open` and `parseFloat(avg_store_rating)`
    // with no guard — absent values would crash it or print NaN.
    const store = adaptHomeLayoutStore({ id: 9, slug: "s" })!;
    expect(store.status.is_open).toBe(true);
    expect(Number.isNaN(parseFloat(store.avg_store_rating))).toBe(false);
    expect(store.total_store_feedback).toBe("0");
    expect(store.distance).toBe(0);
  });

  it("accepts a plain-string status as well as the object form", () => {
    expect(
      adaptHomeLayoutStore({ id: 9, slug: "s", status: "offline" })!.status,
    ).toEqual({ is_open: false, status: "offline" });
  });

  it("drops a store with no slug", () => {
    expect(adaptHomeLayoutStore({ id: 9, name: "x" })).toBeNull();
    expect(getSectionStores({ stores: [raw, { id: 10 }] })).toHaveLength(1);
  });
});

describe("toSectionListPage — bridges the non-standard envelope", () => {
  it("moves data.sections onto data.data for useInfiniteData", () => {
    const page = toSectionListPage(homeLayoutResponse as any);
    const raw = (homeLayoutResponse as any).data;

    expect(page.success).toBe(true);
    // The hook reads its array from data.data; the API puts it at data.sections.
    expect(page.data.data).toHaveLength(raw.sections.length);
    expect(page.data.data[0].id).toBe(raw.sections[0].id);
    expect(page.data.total).toBe(raw.total);
  });

  it("falls back to an empty page when data is null (api fallback)", () => {
    const page = toSectionListPage({
      success: false,
      message: "An error occurred.",
      data: null,
    } as any);
    expect(page.success).toBe(false);
    expect(page.data.data).toEqual([]);
    expect(page.data.total).toBe(0);
  });

  it("survives a malformed/absent response without throwing", () => {
    expect(toSectionListPage(undefined as any)).toEqual({
      success: false,
      message: "An error occurred.",
      data: { data: [], total: 0 },
    });
  });
});
