import { existsSync, readFileSync, readdirSync } from "fs";
import { join } from "path";
import { isStaticExport } from "./build-mode.mjs";
import { articleSchemas, missingBlogPaths, validBlogDate } from "./blog-seo.mjs";

const ROOT = process.cwd();
if (!isStaticExport(ROOT)) {
  console.log("SSR build: skipping the static-export SEO audit.");
  process.exit(0);
}
const OUT_DIR = join(ROOT, "out");
const SITEMAP_PATH = join(ROOT, "public", "sitemap.xml");

const failures = [];
const warnings = [];

const fail = (page, message) => failures.push(`${page}: ${message}`);
const warn = (page, message) => warnings.push(`${page}: ${message}`);

const decodeXml = (value) =>
  value
    .replaceAll("&amp;", "&")
    .replaceAll("&quot;", '"')
    .replaceAll("&apos;", "'")
    .replaceAll("&lt;", "<")
    .replaceAll("&gt;", ">");

const attributes = (tag) => {
  const result = new Map();
  for (const match of tag.matchAll(/([\w:-]+)="([^"]*)"/g)) {
    result.set(match[1].toLowerCase(), decodeXml(match[2]));
  }
  return result;
};

const matchingTags = (html, tagName, attribute, value) =>
  [...html.matchAll(new RegExp(`<${tagName}\\b[^>]*>`, "gi"))]
    .map((match) => ({ raw: match[0], attributes: attributes(match[0]) }))
    .filter((tag) => tag.attributes.get(attribute) === value);

const metaValues = (html, attribute, name) =>
  matchingTags(html, "meta", attribute, name)
    .map((tag) => tag.attributes.get("content"))
    .filter((value) => value !== undefined);

const linkValues = (html, rel) =>
  matchingTags(html, "link", "rel", rel)
    .map((tag) => tag.attributes.get("href"))
    .filter((value) => value !== undefined);

const titleValues = (html) =>
  [...html.matchAll(/<title\b[^>]*>(.*?)<\/title>/gis)].map((match) =>
    decodeXml(match[1].trim()),
  );

const outputPathForUrl = (url) => {
  const pathname = new URL(url).pathname.replace(/^\/+|\/+$/g, "");
  return pathname
    ? join(OUT_DIR, ...pathname.split("/"), "index.html")
    : join(OUT_DIR, "index.html");
};

const requireSingle = (page, label, values) => {
  if (values.length !== 1) {
    fail(page, `expected exactly one ${label}, found ${values.length}`);
    return "";
  }
  if (!values[0]?.trim()) fail(page, `${label} is empty`);
  return values[0] || "";
};

const requireAbsoluteHttpUrl = (page, label, value) => {
  try {
    const url = new URL(value);
    if (!/^https?:$/.test(url.protocol)) throw new Error("unsupported protocol");
  } catch {
    fail(page, `${label} must be an absolute http(s) URL, received "${value}"`);
  }
};

if (!existsSync(SITEMAP_PATH)) {
  fail("sitemap", "public/sitemap.xml is missing");
}
if (!existsSync(OUT_DIR)) {
  fail("export", "out/ is missing; run npm run build before the audit");
}

const sitemap = existsSync(SITEMAP_PATH)
  ? readFileSync(SITEMAP_PATH, "utf8")
  : "";
const sitemapUrls = [...sitemap.matchAll(/<loc>(.*?)<\/loc>/g)].map((match) =>
  decodeXml(match[1].trim()),
);

if (sitemapUrls.length === 0) fail("sitemap", "contains no URLs");

const sitemapOrigins = new Set(
  sitemapUrls.map((url) => {
    try {
      return new URL(url).origin;
    } catch {
      fail("sitemap", `contains an invalid URL: ${url}`);
      return "";
    }
  }),
);
if (sitemapOrigins.size > 1) {
  fail("sitemap", "contains URLs from more than one origin");
}
if ([...sitemapOrigins].some((origin) => /localhost|127\.0\.0\.1/i.test(origin))) {
  warn(
    "deployment",
    "NEXT_PUBLIC_SITE_URL is local; set the production origin before deploying",
  );
}

if (new Set(sitemapUrls).size !== sitemapUrls.length) {
  fail("sitemap", "contains duplicate URLs");
}
if (sitemapUrls.some((url) => new URL(url).pathname === "/products/")) {
  fail("sitemap", "contains /products/, but that route does not exist");
}

// Check exported inventory too: a sitemap-only audit cannot detect omitted posts.
const blogDir = join(OUT_DIR, "blogs");
const exportedBlogPaths = existsSync(blogDir)
  ? readdirSync(blogDir, { withFileTypes: true })
      .filter((entry) => entry.isDirectory() && existsSync(join(blogDir, entry.name, "index.html")))
      .map((entry) => `/blogs/${entry.name}/`)
  : [];
for (const path of missingBlogPaths(sitemapUrls.map((url) => new URL(url).pathname), exportedBlogPaths)) {
  fail("sitemap", `missing published blog route ${path}`);
}

for (const pageUrl of sitemapUrls) {
  const file = outputPathForUrl(pageUrl);
  if (!existsSync(file)) {
    fail(pageUrl, `missing exported page ${file.slice(ROOT.length + 1)}`);
    continue;
  }

  const html = readFileSync(file, "utf8");
  const title = requireSingle(pageUrl, "title", titleValues(html));
  const description = requireSingle(
    pageUrl,
    "meta description",
    metaValues(html, "name", "description"),
  );
  const robots = requireSingle(
    pageUrl,
    "robots directive",
    metaValues(html, "name", "robots"),
  );
  const canonical = requireSingle(pageUrl, "canonical", linkValues(html, "canonical"));
  const ogTitle = requireSingle(
    pageUrl,
    "og:title",
    metaValues(html, "property", "og:title"),
  );
  const ogDescription = requireSingle(
    pageUrl,
    "og:description",
    metaValues(html, "property", "og:description"),
  );
  const ogImage = requireSingle(
    pageUrl,
    "og:image",
    metaValues(html, "property", "og:image"),
  );
  const ogUrl = requireSingle(
    pageUrl,
    "og:url",
    metaValues(html, "property", "og:url"),
  );
  requireSingle(
    pageUrl,
    "twitter:card",
    metaValues(html, "name", "twitter:card"),
  );
  requireSingle(
    pageUrl,
    "twitter:title",
    metaValues(html, "name", "twitter:title"),
  );
  requireSingle(
    pageUrl,
    "twitter:description",
    metaValues(html, "name", "twitter:description"),
  );
  const twitterImage = requireSingle(
    pageUrl,
    "twitter:image",
    metaValues(html, "name", "twitter:image"),
  );

  if (/noindex/i.test(robots)) {
    fail(pageUrl, "is in the sitemap but declares noindex");
  }
  if (canonical && canonical !== pageUrl) {
    fail(pageUrl, `canonical mismatch: expected ${pageUrl}, received ${canonical}`);
  }
  if (ogUrl && ogUrl !== pageUrl) {
    fail(pageUrl, `og:url mismatch: expected ${pageUrl}, received ${ogUrl}`);
  }

  for (const [label, value] of [
    ["canonical", canonical],
    ["og:url", ogUrl],
    ["og:image", ogImage],
    ["twitter:image", twitterImage],
  ]) {
    if (value) requireAbsoluteHttpUrl(pageUrl, label, value);
  }

  if (title.length > 65) warn(pageUrl, `title is ${title.length} characters`);
  if (description.length > 170) {
    warn(pageUrl, `description is ${description.length} characters`);
  }

  const h1Count = (html.match(/<h1\b/gi) || []).length;
  if (h1Count !== 1) fail(pageUrl, `expected exactly one h1, found ${h1Count}`);

  const jsonLdBlocks = [
    ...html.matchAll(
      /<script\b[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/gis,
    ),
  ];
  if (jsonLdBlocks.length === 0) {
    fail(pageUrl, "has no JSON-LD structured data");
  }
  for (const block of jsonLdBlocks) {
    try {
      JSON.parse(block[1]);
    } catch (error) {
      fail(pageUrl, `contains invalid JSON-LD: ${error.message}`);
    }
  }

  if (/^\/blogs\/[^/]+\/$/.test(new URL(pageUrl).pathname)) {
    const articles = jsonLdBlocks.flatMap((block) => {
      try { return articleSchemas(JSON.parse(block[1])); } catch { return []; }
    });
    if (articles.length !== 1) fail(pageUrl, `expected exactly one Article or BlogPosting schema, found ${articles.length}`);
    for (const article of articles) {
      if (!article.headline?.trim()) fail(pageUrl, "article schema has no headline");
      if (article.url !== canonical || article.mainEntityOfPage?.["@id"] !== canonical) fail(pageUrl, "article schema does not identify the canonical page");
      if (!article.publisher?.name?.trim()) fail(pageUrl, "article schema has no publisher name");
      for (const date of ["datePublished", "dateModified"]) {
        if (article[date] !== undefined && !validBlogDate(article[date])) fail(pageUrl, `article ${date} is invalid`);
      }
      for (const image of article.image ?? []) requireAbsoluteHttpUrl(pageUrl, "article image", image);
    }
    if (metaValues(html, "property", "og:type").join() !== "article") fail(pageUrl, "blog og:type must be article");
    if (!/<article\b/i.test(html)) fail(pageUrl, "blog article content is not prerendered");
  }

  const nextDataMatch = html.match(
    /<script\b[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/is,
  );
  if (!nextDataMatch) {
    fail(pageUrl, "is missing serialized Next page data");
  } else {
    const nextDataBytes = Buffer.byteLength(nextDataMatch[1], "utf8");
    if (nextDataBytes > 256 * 1024) {
      fail(pageUrl, `serialized page data is ${nextDataBytes} bytes (limit 262144)`);
    } else if (nextDataBytes > 128 * 1024) {
      warn(pageUrl, `serialized page data is ${nextDataBytes} bytes (exceeds recommended 131072)`);
    }

    try {
      const pageProps = JSON.parse(nextDataMatch[1])?.props?.pageProps ?? {};
      const pathname = new URL(pageUrl).pathname;
      if (/^\/blogs\/[^/]+\/$/.test(pathname)) {
        const post = pageProps.initialData?.post;
        if (!post?.content?.trim() || pageProps.initialData?.notFound) fail(pageUrl, "published article data is missing from prerender");
        if (post && jsonLdBlocks.length) {
          const articles = jsonLdBlocks.flatMap((block) => {
            try { return articleSchemas(JSON.parse(block[1])); } catch { return []; }
          });
          if (articles[0]?.headline !== post.title) fail(pageUrl, "article headline does not match published content");
          if (validBlogDate(post.publishedAt) && articles[0]?.datePublished !== post.publishedAt) fail(pageUrl, "article publication date does not match published content");
        }
      }
      if (pathname === "/blogs/") {
        if (pageProps.initialBlogs === null) {
          warn(pageUrl, "blog listing prerendering was skipped; see the blog API warnings for the reason. Blog data will load in the browser.");
        } else if (!pageProps.initialBlogs?.success || !Array.isArray(pageProps.initialBlogs?.data?.data)) {
          fail(pageUrl, "blog listing is missing prerendered article data");
        }
      }
      for (const [name, value] of Object.entries(pageProps)) {
        if (
          value &&
          typeof value === "object" &&
          "success" in value &&
          value.success !== true
        ) {
          fail(pageUrl, `${name} contains an unsuccessful prerender response`);
        }
      }
    } catch (error) {
      fail(pageUrl, `contains invalid Next page data: ${error.message}`);
    }
  }

  void ogTitle;
  void ogDescription;
}

const productUrls = sitemapUrls.filter((pageUrl) => {
  const pathname = new URL(pageUrl).pathname;
  return /^\/products\/[^/]+\/$/.test(pathname);
});

for (const productUrl of productUrls) {
  const productHtml = readFileSync(outputPathForUrl(productUrl), "utf8");
  const slug = new URL(productUrl).pathname.split("/").filter(Boolean).at(-1);
  const shareUrl = new URL(`/share/products/${slug}/`, productUrl).toString();
  const shareFile = outputPathForUrl(shareUrl);

  if (!existsSync(shareFile)) {
    fail(shareUrl, `missing prerendered product share page ${shareFile}`);
    continue;
  }

  const shareHtml = readFileSync(shareFile, "utf8");
  const expectedTitle = requireSingle(
    productUrl,
    "product title used by share audit",
    titleValues(productHtml),
  );
  const expectedDescription = requireSingle(
    productUrl,
    "product description used by share audit",
    metaValues(productHtml, "name", "description"),
  );
  const expectedImage = requireSingle(
    productUrl,
    "product image used by share audit",
    metaValues(productHtml, "property", "og:image"),
  );
  const expectedOgTitle = requireSingle(
    productUrl,
    "product og:title used by share audit",
    metaValues(productHtml, "property", "og:title"),
  );
  const expectedOgDescription = requireSingle(
    productUrl,
    "product og:description used by share audit",
    metaValues(productHtml, "property", "og:description"),
  );
  const expectedTwitterTitle = requireSingle(
    productUrl,
    "product twitter:title used by share audit",
    metaValues(productHtml, "name", "twitter:title"),
  );
  const expectedTwitterImage = requireSingle(
    productUrl,
    "product twitter:image used by share audit",
    metaValues(productHtml, "name", "twitter:image"),
  );

  const shareTitle = requireSingle(shareUrl, "title", titleValues(shareHtml));
  const shareDescription = requireSingle(
    shareUrl,
    "meta description",
    metaValues(shareHtml, "name", "description"),
  );
  const shareCanonical = requireSingle(
    shareUrl,
    "canonical",
    linkValues(shareHtml, "canonical"),
  );
  const shareOgTitle = requireSingle(
    shareUrl,
    "og:title",
    metaValues(shareHtml, "property", "og:title"),
  );
  const shareOgDescription = requireSingle(
    shareUrl,
    "og:description",
    metaValues(shareHtml, "property", "og:description"),
  );
  const shareOgImage = requireSingle(
    shareUrl,
    "og:image",
    metaValues(shareHtml, "property", "og:image"),
  );
  const shareOgUrl = requireSingle(
    shareUrl,
    "og:url",
    metaValues(shareHtml, "property", "og:url"),
  );
  const shareTwitterCard = requireSingle(
    shareUrl,
    "twitter:card",
    metaValues(shareHtml, "name", "twitter:card"),
  );
  const shareTwitterTitle = requireSingle(
    shareUrl,
    "twitter:title",
    metaValues(shareHtml, "name", "twitter:title"),
  );
  const shareTwitterImage = requireSingle(
    shareUrl,
    "twitter:image",
    metaValues(shareHtml, "name", "twitter:image"),
  );

  for (const [label, actual, expected] of [
    ["title", shareTitle, expectedTitle],
    ["description", shareDescription, expectedDescription],
    ["og:title", shareOgTitle, expectedOgTitle],
    ["og:description", shareOgDescription, expectedOgDescription],
    ["canonical", shareCanonical, productUrl],
    ["og:url", shareOgUrl, productUrl],
    ["og:image", shareOgImage, expectedImage],
    ["twitter:title", shareTwitterTitle, expectedTwitterTitle],
    ["twitter:image", shareTwitterImage, expectedTwitterImage],
  ]) {
    if (actual && expected && actual !== expected) {
      fail(shareUrl, `${label} mismatch: expected ${expected}, received ${actual}`);
    }
  }

  if (shareTwitterCard !== "summary_large_image") {
    fail(shareUrl, `expected summary_large_image, received ${shareTwitterCard}`);
  }
  if ((shareHtml.match(/<h1\b/gi) || []).length !== 1) {
    fail(shareUrl, "expected exactly one product h1");
  }
  if (!/type="application\/ld\+json"/i.test(shareHtml)) {
    fail(shareUrl, "has no product JSON-LD structured data");
  }
}

const noindexPages = [
  "404/index.html",
  "blog-fallback/index.html",
  "cart/index.html",
  "cart/shared/__shared-cart__/index.html",
  "forgot-password/index.html",
  "my-account/index.html",
  "my-account/addresses/index.html",
  "my-account/loyalty-points/index.html",
  "my-account/notifications/index.html",
  "my-account/orders/index.html",
  "my-account/orders/[slug]/index.html",
  "my-account/refer-and-earn/index.html",
  "my-account/transactions/index.html",
  "my-account/wallet/index.html",
  "my-account/wishlists/index.html",
  "offline/index.html",
  "products/search/index.html",
  "shopping-list/index.html",
];

for (const relativePath of noindexPages) {
  const file = join(OUT_DIR, ...relativePath.split("/"));
  if (!existsSync(file)) {
    fail(relativePath, "expected noindex export is missing");
    continue;
  }
  const robots = metaValues(
    readFileSync(file, "utf8"),
    "name",
    "robots",
  );
  if (robots.length !== 1 || !/noindex/i.test(robots[0])) {
    fail(relativePath, `expected one noindex robots tag, found ${robots.join(", ") || "none"}`);
  }
}

for (const message of warnings) console.warn(`⚠ ${message}`);

if (failures.length > 0) {
  for (const message of failures) console.error(`✗ ${message}`);
  console.error(`\nSEO audit failed with ${failures.length} issue(s).`);
  process.exit(1);
}

console.log(
  `✓ SEO audit passed: ${sitemapUrls.length} indexable URLs, ${exportedBlogPaths.length} blog articles, ${productUrls.length} product share previews, and ${noindexPages.length} excluded route templates checked.`,
);
