import { afterEach, beforeEach, describe, expect, it } from "vitest";
import {
  ensureAbsoluteUrl,
  generateBreadcrumbSchema,
  generateCollectionSchema,
  generateProductSchema,
  getCanonicalUrl,
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
    variants: [{ price: 300, special_price: 250, stock: 5 }],
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
