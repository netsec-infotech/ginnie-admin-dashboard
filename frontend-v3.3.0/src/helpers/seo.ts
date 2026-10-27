import { getCurrencyCode, productAmount } from "@/helpers/currency";
import { Brand, Category, Product, Store } from "@/types/ApiResponse";
import { formatString } from "@/helpers/validator";

/** Keep API-authored text from being interpreted as a closing script tag. */
export const serializeJsonLd = (value: object | object[]): string =>
  JSON.stringify(value).replace(/</g, "\\u003c");

export const buildSeoTitle = (
  pageTitle: string | undefined,
  siteName: string,
  maxLength = 60,
): string => {
  const page = pageTitle?.replace(/\s+/g, " ").trim() || "";
  const site = siteName.replace(/\s+/g, " ").trim();
  const combined = page && page !== site ? `${page} | ${site}` : page || site;
  if (combined.length <= maxLength) return combined;
  if (page && page.length <= maxLength) return page;
  return `${(page || site).slice(0, Math.max(1, maxLength - 3)).trimEnd()}...`;
};

export const buildProductShareUrl = ({
  origin,
  slug,
  latitude,
  longitude,
  updatedAt,
}: {
  origin: string;
  slug: string;
  latitude?: string | number;
  longitude?: string | number;
  updatedAt?: string;
}): string => {
  const url = new URL(`/share/products/${encodeURIComponent(slug)}/`, origin);

  if (latitude !== undefined && latitude !== "" && longitude !== undefined && longitude !== "") {
    url.searchParams.set("lat", String(latitude));
    url.searchParams.set("lng", String(longitude));
  }
  if (updatedAt) url.searchParams.set("v", updatedAt);

  return url.toString();
};

/**
 * Generates canonical URL for a page.
 *
 * Emits a TRAILING SLASH because `next.config.ts` sets `trailingSlash: true`:
 * `/about-us` 308-redirects to `/about-us/`, so a slash-less canonical points at
 * a redirect rather than the real page — and that is also the form
 * `generate-sitemap.mjs` writes. Query strings are preserved and the slash goes
 * before them (`/products/search/?q=x`), since the redirect applies to the path.
 */
export const getCanonicalUrl = (path: string, baseUrl?: string): string => {
  const base = (baseUrl || process.env.NEXT_PUBLIC_SITE_URL || "")
    .trim()
    .replace(/\/$/, "");
  const cleanPath = path.startsWith("/") ? path : `/${path}`;

  // Split the query/hash off so the slash lands on the path, not after "?q=x".
  const [pathname, ...rest] = cleanPath.split(/(?=[?#])/);
  const suffix = rest.join("");
  const withSlash = pathname.endsWith("/") ? pathname : `${pathname}/`;

  return `${base}${withSlash}${suffix}`;
};

/**
 * Ensures a URL is absolute by prepending the base URL if needed.
 */
export const ensureAbsoluteUrl = (url: string, baseUrl?: string): string => {
  if (!url) return "";
  if (url.startsWith("http://") || url.startsWith("https://") || url.startsWith("data:")) {
    return url;
  }
  if (url.startsWith("//")) return `https:${url}`;
  const base = (baseUrl || process.env.NEXT_PUBLIC_SITE_URL || "").trim().replace(/\/$/, "");
  const cleanPath = url.startsWith("/") ? url : `/${url}`;
  return `${base}${cleanPath}`;
};

/**
 * Generates Product structured data (JSON-LD)
 */
export const generateProductSchema = (
  product: Product,
  baseUrl?: string,
  /**
   * ISO 4217 code from `system.currency`. Defaults to INR rather than USD:
   * the backend reports INR and the stores are India-based, so a USD default
   * would misprice every rich result. Pass the live value where available.
   */
  currency: string = "INR"
): object => {
  const url = getCanonicalUrl(`/products/${product.slug}`, baseUrl);
  const currencyFor = (variant: Product["variants"][number]) => getCurrencyCode(variant) || getCurrencyCode(product) || currency || "INR";
  const hasMultipleCurrencies = new Set((product.variants || []).map(currencyFor)).size > 1;
  const offerPrices = (product.variants ?? [])
    .map((variant) => productAmount(variant))
    .filter((price) => Number.isFinite(price) && price >= 0);
  const lowPrice = !hasMultipleCurrencies && offerPrices.length > 0 ? Math.min(...offerPrices) : 0;
  const highPrice = !hasMultipleCurrencies && offerPrices.length > 0 ? Math.max(...offerPrices) : 0;

  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.title,
    description: stripHtmlTags(product.short_description || product.description || "").trim(),
    image: ensureAbsoluteUrl(product.main_image, baseUrl),
    sku: product.uuid,
    brand: product.brand_name
      ? {
          "@type": "Brand",
          name: product.brand_name,
        }
      : undefined,
    offers: hasMultipleCurrencies ? product.variants.map((variant) => ({
      "@type": "Offer",
      priceCurrency: currencyFor(variant),
      price: productAmount(variant),
      availability: variant.stock > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
      url,
    })) : {
      "@type": "AggregateOffer",
      // `SettingsContext.currency` is "" until settings load; an empty
      // priceCurrency is invalid schema, so fall back rather than emit it.
      priceCurrency: getCurrencyCode(product.variants?.find((variant) => variant.is_default) || product.variants?.[0]) || getCurrencyCode(product) || currency || "INR",
      lowPrice,
      highPrice,
      offerCount: product.variants?.length || 0,
      availability: product.variants?.some((v) => v.stock > 0)
        ? "https://schema.org/InStock"
        : "https://schema.org/OutOfStock",
      url: url,
    },
    aggregateRating:
      product.rating_count > 0
        ? {
            "@type": "AggregateRating",
            ratingValue: product.ratings,
            reviewCount: product.rating_count,
            bestRating: 5,
            worstRating: 1,
          }
        : undefined,
    url: url,
    category: product.category_name,
  };
};

/**
 * Generates BreadcrumbList structured data
 */
export const generateBreadcrumbSchema = (
  items: Array<{ name: string; url: string }>,
  baseUrl?: string
): object => {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: getCanonicalUrl(item.url, baseUrl),
    })),
  };
};

/**
 * Generates LocalBusiness structured data for stores
 */
export const generateStoreSchema = (store: Store, baseUrl?: string): object => {
  return {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    name: store.name,
    description: store.description,
    image: ensureAbsoluteUrl(store.logo, baseUrl),
    url: getCanonicalUrl(`/stores/${store.slug}`, baseUrl),
    telephone: store.contact_number,
    email: store.contact_email,
    address: store.address
      ? {
          "@type": "PostalAddress",
          streetAddress: store.address,
        }
      : undefined,
  };
};

/**
 * Generates Organization structured data
 */
export const generateOrganizationSchema = (
  siteName: string,
  siteDescription: string,
  logo: string,
  baseUrl?: string
): object => {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: siteName,
    description: siteDescription,
    url: getCanonicalUrl("/", baseUrl),
    logo: ensureAbsoluteUrl(logo, baseUrl),
    sameAs: [
      // Add social media links if available
    ],
  };
};

/**
 * Generates WebSite structured data with search action
 */
export const generateWebsiteSchema = (
  siteName: string,
  baseUrl?: string
): object => {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: siteName,
    url: getCanonicalUrl("/", baseUrl),
    potentialAction: {
      "@type": "SearchAction",
      target: {
        "@type": "EntryPoint",
        urlTemplate: `${getCanonicalUrl("/products/search", baseUrl)}?search={search_term_string}`,
      },
      "query-input": "required name=search_term_string",
    },
  };
};

/**
 * Generates CollectionPage structured data for category/brand pages
 */
export const generateCollectionSchema = (
  name: string,
  description: string,
  url: string,
  baseUrl?: string
): object => {
  return {
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    name: name,
    description: description,
    url: getCanonicalUrl(url, baseUrl),
  };
};

/**
 * Generates FAQ structured data
 */
export const generateFAQSchema = (
  faqs: Array<{ question: string; answer: string }>
): object => {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faqs.map((faq) => ({
      "@type": "Question",
      name: faq.question,
      acceptedAnswer: {
        "@type": "Answer",
        text: faq.answer,
      },
    })),
  };
};

/**
 * Truncates text to specified length with ellipsis
 */
export const truncateText = (text: string, maxLength: number): string => {
  if (!text || text.length <= maxLength) return text;
  return text.substring(0, maxLength - 3) + "...";
};

/**
 * Strips HTML tags from text
 */
export const stripHtmlTags = (html: string): string => {
  if (!html) return "";
  return html.replace(/<[^>]*>/g, "");
};

/**
 * Generates meta description from content
 */
export const generateMetaDescription = (
  content: string,
  maxLength: number = 160
): string => {
  const cleanContent = stripHtmlTags(content);
  return truncateText(cleanContent, maxLength);
};

/**
 * Generate keywords from text
 */
export const generateKeywords = (text: string, limit: number = 10): string => {
  if (!text) return "";

  const words = text
    .toLowerCase()
    .replace(/[^\w\s]/g, "")
    .split(/\s+/)
    .filter((word) => word.length > 3);

  // Get unique words
  const uniqueWords = [...new Set(words)];

  return uniqueWords.slice(0, limit).join(", ");
};

/**
 * Safely extracts SEO metadata (meta title, description, keywords) from an entity's
 * metadata property (object or JSON string) or direct properties.
 */
export const extractSeoMetadata = (
  entity: { metadata?: string | object | null; [key: string]: any } | null | undefined,
): {
  seoTitle?: string;
  seoDescription?: string;
  seoKeywords?: string;
} => {
  if (!entity) return {};

  let metaObj: Record<string, any> = {};

  if (typeof entity.metadata === "string") {
    try {
      metaObj = JSON.parse(entity.metadata) || {};
    } catch {
      metaObj = {};
    }
  } else if (entity.metadata && typeof entity.metadata === "object") {
    metaObj = entity.metadata as Record<string, any>;
  }

  const raw = entity as Record<string, any>;

  const seoTitle = (
    metaObj.seo_title ||
    metaObj.meta_title ||
    raw.seo_title ||
    raw.meta_title ||
    ""
  )
    .toString()
    .trim();

  const seoDescription = (
    metaObj.seo_description ||
    metaObj.meta_description ||
    raw.seo_description ||
    raw.meta_description ||
    ""
  )
    .toString()
    .trim();

  const rawKeywords =
    metaObj.seo_keywords ||
    metaObj.meta_keywords ||
    raw.seo_keywords ||
    raw.meta_keywords;

  let seoKeywords = "";
  if (Array.isArray(rawKeywords)) {
    seoKeywords = rawKeywords.filter(Boolean).map(String).join(", ");
  } else if (typeof rawKeywords === "string") {
    seoKeywords = rawKeywords.trim();
  }

  return {
    seoTitle: seoTitle || undefined,
    seoDescription: seoDescription || undefined,
    seoKeywords: seoKeywords || undefined,
  };
};

/**
 * Generate product meta tags.
 * When SEO details (meta title, description) are not provided, falls back to the
 * product's main title and description. When sharing, ogDescription prefers the
 * product's short description so social link previews display it.
 */
export const generateProductMeta = (product: Product) => {
  const seo = extractSeoMetadata(product);

  // Title: Use SEO meta title if provided, otherwise fallback to main product title
  const title = seo.seoTitle || product.title;

  // Description: Use SEO meta description if provided, otherwise fallback to main description
  const rawDescription =
    seo.seoDescription ||
    product.description ||
    product.short_description ||
    "";
  const description = generateMetaDescription(rawDescription, 160);

  // Clean short description for sharing
  const cleanShortDescription = stripHtmlTags(product.short_description || "").trim();

  // Social / OpenGraph description: Prefer short description when sharing if available,
  // falling back to SEO description or cleaned main description
  const ogDescription = cleanShortDescription
    ? truncateText(cleanShortDescription, 200)
    : description;

  const keywords =
    seo.seoKeywords ||
    [
      product.title,
      product.category_name,
      product.brand_name,
      ...(Array.isArray(product.tags) ? product.tags : []),
    ]
      .filter(Boolean)
      .join(", ");

  return {
    title,
    description,
    ogDescription,
    shortDescription: cleanShortDescription,
    keywords,
    image: ensureAbsoluteUrl(product.main_image),
  };
};

/**
 * Generate collection meta tags
 */
export const generateCollectionMeta = (
  name: string,
  description: string,
  image?: string,
  metadata?: any,
) => {
  const seo = extractSeoMetadata(metadata ? { metadata } : undefined);

  const finalTitle = seo.seoTitle || name;
  const finalDescription = seo.seoDescription
    ? generateMetaDescription(seo.seoDescription, 160)
    : generateMetaDescription(description, 160);
  const keywords =
    seo.seoKeywords || generateKeywords(`${finalTitle} ${finalDescription}`);

  return {
    title: finalTitle,
    description: finalDescription,
    keywords,
    image: image ? ensureAbsoluteUrl(image) : undefined,
  };
};

export interface BrandMetaOptions {
  brand?: Brand | null;
  slug?: string;
  total?: number;
  fallbackTitle?: string;
  fallbackDescription?: string;
}

/**
 * Generate brand meta tags for SEO and social sharing previews.
 * Prefers backend SEO information (seo_title, seo_description, seo_keywords).
 * Falls back to brand title, brand description, and localized fallback copy.
 * Passes the brand's logo as ogImage/image so link previews display the brand logo.
 */
export const generateBrandMeta = (options: BrandMetaOptions) => {
  const { brand, slug, total = 0, fallbackTitle, fallbackDescription } = options;
  const brandName = brand?.title?.trim() || formatString(slug || "");
  const seo = extractSeoMetadata(brand);

  const title =
    seo.seoTitle ||
    fallbackTitle ||
    (brandName ? `${brandName} Products` : "Products");

  const cleanDescription = brand?.description
    ? stripHtmlTags(brand.description).trim()
    : "";

  const description =
    seo.seoDescription ||
    cleanDescription ||
    fallbackDescription ||
    (brandName
      ? `Browse ${total} products from ${brandName}.`
      : "Browse products from this brand.");

  const keywords =
    seo.seoKeywords ||
    (brandName
      ? `${brandName}, ${brandName} products, buy ${brandName} online`
      : "");

  const image = brand?.logo ? ensureAbsoluteUrl(brand.logo) : undefined;

  return {
    brandName,
    title,
    description,
    keywords,
    image,
    ogTitle: title,
    ogDescription: description,
    ogImage: image,
  };
};

export interface CategoryMetaOptions {
  category?: Category | null;
  slug?: string;
  total?: number;
  fallbackTitle?: string;
  fallbackDescription?: string;
}

/**
 * Generate category meta tags for SEO and social sharing previews.
 * Prefers backend SEO information (seo_title, seo_description, seo_keywords).
 * Falls back to category title, category description, and localized fallback copy.
 * Passes the category image/banner/icon as ogImage/image so link previews display the category visual.
 */
export const generateCategoryMeta = (options: CategoryMetaOptions) => {
  const { category, slug, total = 0, fallbackTitle, fallbackDescription } = options;
  const categoryName = category?.title?.trim() || formatString(slug || "");
  const seo = extractSeoMetadata(category);

  const title =
    seo.seoTitle ||
    fallbackTitle ||
    (categoryName ? `${categoryName} Products` : "Products");

  const cleanDescription = category?.description
    ? stripHtmlTags(category.description).trim()
    : "";

  const description =
    seo.seoDescription ||
    cleanDescription ||
    fallbackDescription ||
    (categoryName
      ? `Browse ${total} products from ${categoryName}.`
      : "Browse products from this category.");

  const keywords =
    seo.seoKeywords ||
    (categoryName
      ? `${categoryName}, ${categoryName} products, buy ${categoryName} online`
      : "");

  const image =
    category?.image || category?.banner || category?.icon
      ? ensureAbsoluteUrl(category.image || category.banner || category.icon)
      : undefined;

  return {
    categoryName,
    title,
    description,
    keywords,
    image,
    ogTitle: title,
    ogDescription: description,
    ogImage: image,
  };
};

