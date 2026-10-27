import { afterEach, beforeEach, describe, expect, it } from "vitest";
import {
  buildProductShareUrl,
  buildSeoTitle,
  ensureAbsoluteUrl,
  generateBreadcrumbSchema,
  generateCollectionSchema,
  generateProductSchema,
  getCanonicalUrl,
  serializeJsonLd,
  extractSeoMetadata,
  generateProductMeta,
  generateCollectionMeta,
} from "@/helpers/seo";

const KEY = "NEXT_PUBLIC_SITE_URL";
const original = process.env[KEY];

beforeEach(() => {
  process.env[KEY] = "https://hyperlocal.eshopweb.store";
});

afterEach(() => {
  if (original === undefined) delete process.env[KEY];
  else process.env[KEY] = original;
});

/**
 * `next.config.ts` sets `trailingSlash: true`, so `/about-us` 308-redirects to
 * `/about-us/`. A canonical pointing at the redirect wastes crawl budget and
 * disagrees with `generate-sitemap.mjs`, which writes the slashed form.
 */
describe("getCanonicalUrl", () => {
  it("emits a trailing slash to match trailingSlash: true", () => {
    expect(getCanonicalUrl("/about-us")).toBe(
      "https://hyperlocal.eshopweb.store/about-us/",
    );
  });

  it("agrees with the URLs generate-sitemap.mjs writes", () => {
    // Verbatim from public/sitemap.xml.
    expect(getCanonicalUrl("/stores")).toBe(
      "https://hyperlocal.eshopweb.store/stores/",
    );
    expect(getCanonicalUrl("/seller-register")).toBe(
      "https://hyperlocal.eshopweb.store/seller-register/",
    );
  });

  it("does not double the slash when the path already has one", () => {
    expect(getCanonicalUrl("/categories/")).toBe(
      "https://hyperlocal.eshopweb.store/categories/",
    );
  });

  it("handles the site root", () => {
    expect(getCanonicalUrl("/")).toBe("https://hyperlocal.eshopweb.store/");
  });

  it("adds a leading slash to a bare path", () => {
    expect(getCanonicalUrl("brands")).toBe(
      "https://hyperlocal.eshopweb.store/brands/",
    );
  });

  it("tolerates a base URL with a trailing slash", () => {
    expect(getCanonicalUrl("/faqs", "https://example.com/")).toBe(
      "https://example.com/faqs/",
    );
  });

  it("handles nested dynamic routes", () => {
    expect(getCanonicalUrl("/categories/tasty-pizza")).toBe(
      "https://hyperlocal.eshopweb.store/categories/tasty-pizza/",
    );
  });

  /**
   * The slash belongs on the PATH — `/search?q=x/` would be a different query,
   * not a different path.
   */
  it("puts the slash before a query string, not after", () => {
    expect(getCanonicalUrl("/products/search?q=pizza")).toBe(
      "https://hyperlocal.eshopweb.store/products/search/?q=pizza",
    );
  });

  it("puts the slash before a hash", () => {
    expect(getCanonicalUrl("/about-us#team")).toBe(
      "https://hyperlocal.eshopweb.store/about-us/#team",
    );
  });

  it("still produces a usable relative URL when the site URL is unset", () => {
    delete process.env[KEY];
    expect(getCanonicalUrl("/about-us")).toBe("/about-us/");
  });
});

describe("buildSeoTitle", () => {
  it("keeps a concise page and site title", () => {
    expect(buildSeoTitle("Stores", "Hyper Local")).toBe(
      "Stores | Hyper Local",
    );
  });

  it("drops an overlong site-name suffix before truncating the page title", () => {
    expect(
      buildSeoTitle(
        "Margherita Pizza",
        "HyperLocal Multivendor eCommerce, Groceries, Food, Pharmacies Stores",
      ),
    ).toBe("Margherita Pizza");
  });

  it("caps an overlong standalone title", () => {
    expect(buildSeoTitle(undefined, "x".repeat(80))).toHaveLength(60);
  });
});

describe("buildProductShareUrl", () => {
  it("builds a trailing-slash share route with location and a cache version", () => {
    expect(
      buildProductShareUrl({
        origin: "https://shop.example.com",
        slug: "frozen-green-peas",
        latitude: 23.2347,
        longitude: 69.6471,
        updatedAt: "2026-08-29 10:30:00",
      }),
    ).toBe(
      "https://shop.example.com/share/products/frozen-green-peas/?lat=23.2347&lng=69.6471&v=2026-08-29+10%3A30%3A00",
    );
  });

  it("does not publish a partial location", () => {
    expect(
      buildProductShareUrl({
        origin: "https://shop.example.com",
        slug: "tea",
        latitude: "23.2",
      }),
    ).toBe("https://shop.example.com/share/products/tea/");
  });
});

describe("ensureAbsoluteUrl", () => {
  it("leaves absolute URLs alone", () => {
    expect(ensureAbsoluteUrl("https://cdn.example.com/a.png")).toBe(
      "https://cdn.example.com/a.png",
    );
  });

  it("leaves data URIs alone", () => {
    expect(ensureAbsoluteUrl("data:image/png;base64,AAA")).toBe(
      "data:image/png;base64,AAA",
    );
  });

  it("normalizes protocol-relative CDN URLs", () => {
    expect(ensureAbsoluteUrl("//cdn.example.com/a.png")).toBe(
      "https://cdn.example.com/a.png",
    );
  });

  it("absolutizes a site-relative path", () => {
    expect(ensureAbsoluteUrl("/images/logo.png")).toBe(
      "https://hyperlocal.eshopweb.store/images/logo.png",
    );
  });

  it("returns empty for empty input rather than a bare base URL", () => {
    // A bare base URL as og:image would be a broken image, not a missing one.
    expect(ensureAbsoluteUrl("")).toBe("");
  });

  /** Unlike canonicals, asset URLs must NOT gain a trailing slash. */
  it("does not add a trailing slash to asset paths", () => {
    expect(ensureAbsoluteUrl("/images/logo.png")).not.toMatch(/\/$/);
  });
});

/**
 * The Product schema hardcoded "USD" while the backend reports INR — a wrong
 * currency in a rich result is a merchant-listing problem, not a cosmetic one.
 */
describe("generateProductSchema currency", () => {
  const product = {
    slug: "margherita-pizza",
    title: "Margherita Pizza",
    short_description: "Italian style pizza",
    description: "Italian style pizza",
    main_image: "https://cdn.example.com/pizza.png",
    uuid: "abc-123",
    rating_count: 0,
    variants: [
      { price: 300, special_price: 250, stock: 5 },
      { price: 500, special_price: null, stock: 0 },
      { price: 200, special_price: 150, stock: 2 },
    ],
  } as unknown as Parameters<typeof generateProductSchema>[0];

  const offersOf = (schema: object) =>
    (schema as { offers: { priceCurrency: string } }).offers;

  it("uses the currency it is given", () => {
    expect(offersOf(generateProductSchema(product, undefined, "INR"))
      .priceCurrency).toBe("INR");
    expect(offersOf(generateProductSchema(product, undefined, "GBP"))
      .priceCurrency).toBe("GBP");
  });

  it("defaults to INR, not USD, when none is supplied", () => {
    expect(offersOf(generateProductSchema(product)).priceCurrency).toBe("INR");
  });

  it("falls back when handed an empty currency", () => {
    // SettingsContext.currency is "" until settings load — an empty
    // priceCurrency would be invalid schema.
    expect(offersOf(generateProductSchema(product, undefined, ""))
      .priceCurrency).toBe("INR");
  });

  it("still builds a slashed canonical URL for the offer", () => {
    const schema = generateProductSchema(product) as { url: string };
    expect(schema.url).toBe(
      "https://hyperlocal.eshopweb.store/products/margherita-pizza/",
    );
  });

  it("derives the price range from every current variant offer", () => {
    const offers = generateProductSchema(product) as {
      offers: { highPrice: number; lowPrice: number; offerCount: number };
    };

    expect(offers.offers).toMatchObject({
      lowPrice: 150,
      highPrice: 500,
      offerCount: 3,
    });
  });
});

describe("schema generators use canonical URLs", () => {
  it("builds absolute, slashed URLs in breadcrumbs", () => {
    const schema = generateBreadcrumbSchema([
      { name: "Home", url: "/" },
      { name: "Stores", url: "/stores" },
    ]) as { itemListElement: Array<{ item: string; position: number }> };

    expect(schema.itemListElement[0].item).toBe(
      "https://hyperlocal.eshopweb.store/",
    );
    expect(schema.itemListElement[1].item).toBe(
      "https://hyperlocal.eshopweb.store/stores/",
    );
    expect(schema.itemListElement[1].position).toBe(2);
  });

  it("builds an absolute, slashed URL in a collection schema", () => {
    const schema = generateCollectionSchema(
      "Stores",
      "All stores",
      "/stores",
    ) as { url: string; "@type": string };

    expect(schema["@type"]).toBe("CollectionPage");
    expect(schema.url).toBe("https://hyperlocal.eshopweb.store/stores/");
  });
});

describe("serializeJsonLd", () => {
  it("preserves valid JSON", () => {
    expect(JSON.parse(serializeJsonLd({ name: "Local shop" }))).toEqual({
      name: "Local shop",
    });
  });

  it("cannot close the JSON-LD script with API-authored text", () => {
    const serialized = serializeJsonLd({
      description: "</script><script>alert(1)</script>",
    });

    expect(serialized).not.toContain("<");
    expect(JSON.parse(serialized)).toEqual({
      description: "</script><script>alert(1)</script>",
    });
  });
});

describe("extractSeoMetadata", () => {
  it("extracts seo fields from object metadata", () => {
    const meta = extractSeoMetadata({
      metadata: {
        seo_title: "Custom SEO Title",
        seo_description: "Custom SEO Description",
        seo_keywords: ["keyword1", "keyword2"],
      },
    });

    expect(meta.seoTitle).toBe("Custom SEO Title");
    expect(meta.seoDescription).toBe("Custom SEO Description");
    expect(meta.seoKeywords).toBe("keyword1, keyword2");
  });

  it("extracts seo fields from json string metadata", () => {
    const meta = extractSeoMetadata({
      metadata: JSON.stringify({
        meta_title: "String Meta Title",
        meta_description: "String Meta Description",
        meta_keywords: "single, keyword",
      }),
    });

    expect(meta.seoTitle).toBe("String Meta Title");
    expect(meta.seoDescription).toBe("String Meta Description");
    expect(meta.seoKeywords).toBe("single, keyword");
  });

  it("returns empty object when metadata is missing or null", () => {
    expect(extractSeoMetadata(null)).toEqual({});
    expect(extractSeoMetadata(undefined)).toEqual({});
    expect(extractSeoMetadata({ metadata: null })).toEqual({});
  });
});

describe("generateProductMeta", () => {
  const baseProduct: any = {
    id: 1,
    uuid: "prod-1",
    title: "Fresh Farm Milk",
    slug: "fresh-farm-milk",
    description: "<p>Rich and creamy whole cow milk from local farms.</p>",
    short_description: "Pure whole milk 1L pouch.",
    main_image: "https://example.com/milk.jpg",
    category_name: "Dairy",
    brand_name: "FarmFresh",
    tags: ["milk", "dairy"],
    metadata: null,
  };

  it("uses main title and description when SEO details are not provided", () => {
    const meta = generateProductMeta(baseProduct);

    expect(meta.title).toBe("Fresh Farm Milk");
    expect(meta.description).toBe("Rich and creamy whole cow milk from local farms.");
    expect(meta.shortDescription).toBe("Pure whole milk 1L pouch.");
    expect(meta.ogDescription).toBe("Pure whole milk 1L pouch.");
    expect(meta.keywords).toContain("Fresh Farm Milk");
    expect(meta.keywords).toContain("Dairy");
  });

  it("uses SEO details when provided in metadata", () => {
    const productWithSeo = {
      ...baseProduct,
      metadata: {
        seo_title: "Buy Best Fresh Cow Milk Online",
        seo_description: "Order pure cow milk with doorstep delivery in 15 minutes.",
        seo_keywords: ["fresh cow milk", "milk delivery"],
      },
    };

    const meta = generateProductMeta(productWithSeo);

    expect(meta.title).toBe("Buy Best Fresh Cow Milk Online");
    expect(meta.description).toBe("Order pure cow milk with doorstep delivery in 15 minutes.");
    expect(meta.keywords).toBe("fresh cow milk, milk delivery");
    expect(meta.shortDescription).toBe("Pure whole milk 1L pouch.");
    expect(meta.ogDescription).toBe("Pure whole milk 1L pouch.");
  });

  it("falls back to description when short_description is missing", () => {
    const productWithoutShortDesc = {
      ...baseProduct,
      short_description: "",
    };

    const meta = generateProductMeta(productWithoutShortDesc);

    expect(meta.title).toBe("Fresh Farm Milk");
    expect(meta.description).toBe("Rich and creamy whole cow milk from local farms.");
    expect(meta.shortDescription).toBe("");
    expect(meta.ogDescription).toBe("Rich and creamy whole cow milk from local farms.");
  });
});

describe("generateCollectionMeta", () => {
  it("uses provided metadata when available", () => {
    const meta = generateCollectionMeta("Dairy", "All dairy products", undefined, {
      seo_title: "Fresh Dairy Online",
      seo_description: "Order fresh dairy products delivered to your door.",
      seo_keywords: ["dairy", "fresh"],
    });

    expect(meta.title).toBe("Fresh Dairy Online");
    expect(meta.description).toBe("Order fresh dairy products delivered to your door.");
    expect(meta.keywords).toBe("dairy, fresh");
  });

  it("falls back to category name and description when metadata is not provided", () => {
    const meta = generateCollectionMeta("Dairy", "All dairy products");

    expect(meta.title).toBe("Dairy");
    expect(meta.description).toBe("All dairy products");
  });
});
