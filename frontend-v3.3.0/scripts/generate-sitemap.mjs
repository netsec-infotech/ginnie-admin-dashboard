import { mkdirSync, renameSync, writeFileSync } from "fs";
import { dirname, join, resolve } from "path";
import { fileURLToPath } from "node:url";
import { config } from "dotenv";
import axios from "axios";
import { blogSitemapRoutes, sitemapLastmod } from "./blog-seo.mjs";
import { externalizeBlogMedia } from "./blog-media.mjs";

config();

const PAGE_SIZE = 100;
const MAX_PAGES = 500;
const PRODUCT_SNAPSHOT_PATH = process.env.HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT
  ? resolve(process.env.HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT)
  : join(
      process.cwd(),
      "node_modules",
      ".cache",
      "hyperlocal-static-products.json",
    );
const BLOG_SNAPSHOT_PATH = process.env.HYPERLOCAL_STATIC_BLOG_SNAPSHOT
  ? resolve(process.env.HYPERLOCAL_STATIC_BLOG_SNAPSHOT)
  : join(process.cwd(), "node_modules", ".cache", "hyperlocal-static-blogs.json");
const STORE_SNAPSHOT_PATH = process.env.HYPERLOCAL_STATIC_STORE_SNAPSHOT
  ? resolve(process.env.HYPERLOCAL_STATIC_STORE_SNAPSHOT)
  : join(process.cwd(), "node_modules", ".cache", "hyperlocal-static-stores.json");

const writeStoreSnapshot = (stores) => {
  mkdirSync(dirname(STORE_SNAPSHOT_PATH), { recursive: true });
  const temporaryPath = `${STORE_SNAPSHOT_PATH}.${process.pid}.tmp`;
  writeFileSync(temporaryPath, JSON.stringify({ generatedAt: new Date().toISOString(), stores }), "utf8");
  renameSync(temporaryPath, STORE_SNAPSHOT_PATH);
};

const writeBlogSnapshot = async (blogs, skippedReason = null) => {
  const posts = [];
  for (const blog of blogs) {
    const slug = typeof blog?.slug === "string" ? blog.slug.trim() : "";
    if (!slug) continue;
    const post = await requestWithRetry(`blog detail for ${slug}`, async () => {
      const { data } = await api.get(`/blogs/${encodeURIComponent(slug)}`);
      if (!data?.success || !data.data || data.data.slug !== slug) throw new Error("Invalid blog detail response");
      return externalizeBlogMedia(data.data, join(process.cwd(), "public"));
    });
    posts.push(post);
  }
  mkdirSync(dirname(BLOG_SNAPSHOT_PATH), { recursive: true });
  const temporaryPath = `${BLOG_SNAPSHOT_PATH}.${process.pid}.tmp`;
  writeFileSync(temporaryPath, JSON.stringify({
    generatedAt: new Date().toISOString(),
    posts,
    ...(skippedReason ? { skippedReason } : {}),
  }), "utf8");
  renameSync(temporaryPath, BLOG_SNAPSHOT_PATH);
  console.log(`   Saved one build-wide snapshot for ${posts.length} published blogs`);
  return posts;
};

const writeProductSnapshot = (products) => {
  mkdirSync(dirname(PRODUCT_SNAPSHOT_PATH), { recursive: true });
  const temporaryPath = `${PRODUCT_SNAPSHOT_PATH}.${process.pid}.tmp`;
  writeFileSync(
    temporaryPath,
    JSON.stringify({ generatedAt: new Date().toISOString(), products }),
    "utf8",
  );
  renameSync(temporaryPath, PRODUCT_SNAPSHOT_PATH);
  console.log(`   Saved one build-wide snapshot for ${products.length} products`);
};

const normalizeBaseUrl = (value, label) => {
  if (!value?.trim()) throw new Error(`${label} is required.`);
  const url = new URL(value.trim());
  if (!/^https?:$/.test(url.protocol)) {
    throw new Error(`${label} must use http or https.`);
  }
  return url.origin + url.pathname.replace(/\/+$/, "");
};

const SITE_URL = normalizeBaseUrl(
  process.env.NEXT_PUBLIC_SITE_URL,
  "NEXT_PUBLIC_SITE_URL",
);
const API_URL = normalizeBaseUrl(
  process.env.NEXT_PUBLIC_ADMIN_PANEL_URL,
  "NEXT_PUBLIC_ADMIN_PANEL_URL",
);

const api = axios.create({ baseURL: `${API_URL}/api`, timeout: 60_000 });

const requestWithRetry = async (label, request, attempts = 5) => {
  let lastError;
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      return await request();
    } catch (error) {
      lastError = error;
      if (attempt < attempts) {
        console.warn(`   ⚠ ${label} failed; retrying (${attempt}/${attempts})`);
        await new Promise((resolve) => setTimeout(resolve, attempt * 1000));
      }
    }
  }
  throw new Error(`${label}: ${lastError?.message || "unknown request error"}`);
};

const staticRoutes = [
  { path: "/", priority: "1.0" },
  { path: "/about-us/", priority: "0.7" },
  { path: "/blogs/", priority: "0.8" },
  { path: "/brands/", priority: "0.8" },
  { path: "/categories/", priority: "0.9" },
  { path: "/delivery-zones/", priority: "0.6" },
  { path: "/faqs/", priority: "0.6" },
  { path: "/feature-sections/", priority: "0.5" },
  { path: "/play/", priority: "0.8" },
  { path: "/privacy-policy/", priority: "0.4" },
  { path: "/return-refund-policy/", priority: "0.4" },
  { path: "/seller-register/", priority: "0.6" },
  { path: "/shipping-policy/", priority: "0.4" },
  { path: "/stores/", priority: "0.8" },
  { path: "/terms-and-conditions/", priority: "0.4" },
];

const route = (path, priority) => ({ path, priority });

const getDefaultLocation = async () => {
  const response = await requestWithRetry("settings request failed", () =>
    api.get("/settings"),
  );
  const settings = response.data?.data;
  const web = Array.isArray(settings)
    ? settings.find((setting) => setting?.variable === "web")?.value
    : null;
  const latitude = Number(web?.defaultLatitude);
  const longitude = Number(web?.defaultLongitude);

  if (
    !response.data?.success ||
    !Number.isFinite(latitude) ||
    latitude < -90 ||
    latitude > 90 ||
    !Number.isFinite(longitude) ||
    longitude < -180 ||
    longitude > 180
  ) {
    throw new Error(
      "Sitemap generation requires valid defaultLatitude/defaultLongitude web settings.",
    );
  }

  return { latitude: String(latitude), longitude: String(longitude) };
};

const fetchPaginated = async (label, endpoint, params = {}) => {
  const items = [];

  for (let page = 1; page <= MAX_PAGES; page += 1) {
    let response;
    try {
      response = await requestWithRetry(
        `${label} page ${page} request failed`,
        () =>
          api.get(endpoint, {
            params: { ...params, page, per_page: PAGE_SIZE },
          }),
      );
    } catch (error) {
      throw new Error(
        `${label} request failed on page ${page}: ${error.message}`,
      );
    }
    const envelope = response.data;
    if (!envelope?.success || !Array.isArray(envelope.data?.data)) {
      throw new Error(
        `${label} request failed: ${envelope?.message || "invalid API response"}`,
      );
    }

    const batch = envelope.data.data;
    items.push(...batch);

    const total = Number(envelope.data.total ?? items.length);
    const perPage = Number(envelope.data.per_page || batch.length || PAGE_SIZE);
    const lastPage = Math.max(Number(envelope.data.last_page) || 1, Math.ceil(total / perPage));
    if (page >= lastPage) {
      if (items.length < total) throw new Error(`${label} pagination returned fewer items than its total.`);
      console.log(`   ✓ Found ${items.length} ${label}`);
      return items;
    }
    if (batch.length === 0) throw new Error(`${label} pagination ended before its last page.`);
  }

  throw new Error(`${label} pagination exceeded ${MAX_PAGES} pages.`);
};

const fetchHomeLayoutSections = async ({ latitude, longitude }) => {
  const sections = [];

  for (let page = 1; page <= MAX_PAGES; page += 1) {
    let response;
    try {
      response = await requestWithRetry(
        `home-layout sections page ${page} request failed`,
        () =>
          api.get("/home-layout", {
            params: {
              platform: "web",
              page,
              per_page: PAGE_SIZE,
              latitude,
              longitude,
            },
          }),
      );
    } catch (error) {
      throw new Error(
        `home-layout sections request failed on page ${page}: ${error.message}`,
      );
    }
    const envelope = response.data;
    if (!envelope?.success || !envelope.data) {
      throw new Error(
        `home-layout sections request failed: ${envelope?.message || "invalid API response"}`,
      );
    }

    sections.push(...(envelope.data.sections ?? []));
    if (page >= (envelope.data.last_page || 1)) {
      console.log(`   ✓ Found ${sections.length} home-layout sections`);
      return sections;
    }
  }

  throw new Error(`home-layout pagination exceeded ${MAX_PAGES} pages.`);
};

const encodeSegment = (value) => encodeURIComponent(String(value).trim());

const fetchDynamicRoutes = async () => {
  console.log("📡 Fetching dynamic routes from API...");
  const location = await getDefaultLocation();
  console.log(
    `   ✓ Default location: ${location.latitude}, ${location.longitude}`,
  );

  // This backend is hosted on a small shared instance and can reset sockets
  // under a seven-request burst. Sequential scans make deploys slower by a few
  // seconds but deterministic, which matters more than publishing a partial map.
  const categories = await fetchPaginated("categories", "/categories", location);
  const brands = await fetchPaginated("brands", "/brands", location);
  const stores = await fetchPaginated(
    "stores",
    "/delivery-zone/stores",
    location,
  );
  writeStoreSnapshot(stores);
  const products = await fetchPaginated(
    "products",
    "/delivery-zone/products",
    { ...location, include_child_categories: 0 },
  );
  // Next prerenders /products/:slug and /share/products/:slug in separate
  // worker processes. Persist the catalog once so both routes use identical
  // metadata even if an administrator edits a product during the build.
  writeProductSnapshot(products);
  const zones = await fetchPaginated("delivery zones", "/delivery-zone");
  const featureSections = await fetchPaginated(
    "feature sections",
    "/featured-sections",
    location,
  );
  const homeSections = await fetchHomeLayoutSections(location);
  let blogs;
  try {
    blogs = await writeBlogSnapshot(await fetchPaginated("published blogs", "/blogs"));
  } catch (error) {
    const reason = error?.message || String(error);
    console.warn(`   ⚠ Skipped blogs for this build: ${reason}`);
    // An empty snapshot prevents Next from refetching the failed API or using
    // articles cached by a previous build, including a partially fetched list.
    blogs = await writeBlogSnapshot([], reason);
  }

  return [
    ...blogSitemapRoutes(blogs),
    ...categories
      .filter((item) => item?.slug)
      .map((item) => route(`/categories/${encodeSegment(item.slug)}/`, "0.7")),
    ...brands
      .filter((item) => item?.slug)
      .map((item) => route(`/brands/${encodeSegment(item.slug)}/`, "0.6")),
    ...stores
      .filter((item) => item?.slug)
      .map((item) => route(`/stores/${encodeSegment(item.slug)}/`, "0.7")),
    ...products
      .filter((item) => item?.slug)
      .map((item) => route(`/products/${encodeSegment(item.slug)}/`, "0.8")),
    ...zones
      .filter((item) => item?.id != null)
      .map((item) => route(`/delivery-zones/${encodeSegment(item.id)}/`, "0.5")),
    ...featureSections
      .filter((item) => item?.slug)
      .map((item) =>
        route(`/feature-sections/${encodeSegment(item.slug)}/`, "0.6"),
      ),
    ...homeSections
      .filter((item) => item?.id != null)
      .map((item) => route(`/section/${encodeSegment(item.id)}/`, "0.6")),
  ];
};

const escapeXml = (value) =>
  value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&apos;");

const generateSitemap = (routes) => {
  const currentDate = new Date().toISOString().split("T")[0];
  const entries = routes
    .map(
      (item) => `  <url>
    <loc>${escapeXml(`${SITE_URL}${item.path}`)}</loc>${sitemapLastmod(item, currentDate)}
    <priority>${item.priority}</priority>
  </url>`,
    )
    .join("\n\n");

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${entries}
</urlset>`;
};

export const main = async () => {
  console.log("🚀 Generating sitemap...\n");
  const dynamicRoutes = await fetchDynamicRoutes();
  const deduplicated = new Map();
  for (const item of [...staticRoutes, ...dynamicRoutes]) {
    deduplicated.set(item.path, item);
  }
  const routes = [...deduplicated.values()];

  writeFileSync(
    join(process.cwd(), "public", "sitemap.xml"),
    generateSitemap(routes),
    "utf8",
  );

  console.log("\n✅ Sitemap generated successfully!");
  console.log(`   🌐 Base URL: ${SITE_URL}`);
  console.log(`   📄 Static routes: ${staticRoutes.length}`);
  console.log(`   🔄 Dynamic routes: ${routes.length - staticRoutes.length}`);
  console.log(`   📊 Total URLs: ${routes.length}`);
};

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main().catch((error) => {
    console.error("❌ Sitemap generation failed:", error.message);
    process.exit(1);
  });
}
