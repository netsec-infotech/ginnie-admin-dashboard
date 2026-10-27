import type { NextConfig } from "next";
import { readFileSync } from "fs";
import { join } from "path";
import withPWAInit from "@ducanh2912/next-pwa";
import { PHASE_PRODUCTION_BUILD } from "next/constants";

// Read package.json to get version
const packageJson = JSON.parse(
  readFileSync(join(process.cwd(), "package.json"), "utf8"),
);
const { version = "0" } = packageJson;
const isExport = process.env.NEXT_PUBLIC_SSR !== "true";

// `trailingSlash: true` below means the offline page is served at /offline/ —
// the fallback and the precache entry must use that exact URL, because the
// service worker resolves the fallback with a plain caches.match() on it.
const OFFLINE_ROUTE = "/offline/";

const securityHeaders = [
  { key: "X-DNS-Prefetch-Control", value: "on" },
  {
    key: "Strict-Transport-Security",
    value: "max-age=63072000; includeSubDomains; preload",
  },
  { key: "X-Frame-Options", value: "SAMEORIGIN" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-XSS-Protection", value: "1; mode=block" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=(self), interest-cohort=()",
  },
  { key: "Cross-Origin-Resource-Policy", value: "cross-origin" },
];

const getRuntimeHeaders: NonNullable<NextConfig["headers"]> = async () => [
  { source: "/:path*", headers: securityHeaders },
  {
    source: "/sitemap.xml",
    headers: [
      { key: "Content-Type", value: "application/xml; charset=utf-8" },
      {
        key: "Cache-Control",
        value: "public, max-age=3600, s-maxage=3600",
      },
    ],
  },
  {
    source: "/robots.txt",
    headers: [
      { key: "Content-Type", value: "text/plain; charset=utf-8" },
      {
        key: "Cache-Control",
        value: "public, max-age=3600, s-maxage=3600",
      },
    ],
  },
];

const withPWA = withPWAInit({
  dest: "public",
  disable: process.env.NODE_ENV === "development",
  register: true,
  // The optional navigation worker writes directly to Cache Storage, bypassing
  // runtime rules. Use normal Workbox caching so private SSR pages stay excluded.
  cacheOnFrontEndNav: false,
  aggressiveFrontEndNavCaching: false,
  reloadOnOnline: true,
  // Customer responses must never be reused by another login or served stale.
  // Keep the default offline policies for public resources.
  extendDefaultRuntimeCaching: true,
  workboxOptions: {
    exclude: [
      /\/_next\/static\/.*(?<!\.p)\.woff2/,
      /\.map$/,
      /^manifest.*\.js$/,
      /dynamic-css-manifest\.json$/,
    ],
    runtimeCaching: [
      ...(isExport ? [] : [{
        // SSR HTML and page-data responses vary by locale cookie. Workbox must
        // never replay another visitor's language from a navigation cache.
        urlPattern: ({ url, request }: { url: URL; request: Request }) =>
          request.mode === "navigate" || url.pathname.startsWith("/_next/data/"),
        handler: "NetworkOnly" as const,
        method: "GET" as const,
      }]),
      {
        urlPattern: ({ url, request }) =>
          request.headers.has("authorization") ||
          /\/api\/user(?:\/|$)/.test(url.pathname) ||
          /^\/(?:my-account|cart)(?:\/|$)/.test(url.pathname) ||
          /^\/_next\/data\/[^/]+\/(?:my-account|cart)(?:\/|\.|$)/.test(url.pathname),
        handler: "NetworkOnly",
        method: "GET",
      },
    ],
  },
  // Served for a navigation that misses both the cache and the network — a cold
  // load while offline, where the app shell never boots and DefaultLayout's
  // inline <OfflinePage /> branch can never run. Without this the browser shows
  // its own "no internet" error page instead.
  //
  // next-pwa adds this URL to the precache manifest itself, so the worker
  // fetches and caches the page at install time. Do NOT also pass
  // `workboxOptions.additionalManifestEntries` to force it: supplying that
  // option suppresses the automatic glob of public/**, which would silently
  // stop every static asset in there from being precached.
  fallbacks: { document: OFFLINE_ROUTE },
});

const nextConfig: NextConfig = {
  transpilePackages: ["@heroui/system", "@heroui/react"],
  turbopack: {},
  reactStrictMode: true,
  // Runtime share codes need a fallback while developing. Production exports
  // serve the same generic page through the generated Apache rewrite rules.
  output: isExport && process.env.NODE_ENV !== "development" ? "export" : undefined,
  trailingSlash: true,
  images: {
    unoptimized: isExport,
    formats: ["image/avif", "image/webp"],
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
    minimumCacheTTL: 60,
    dangerouslyAllowSVG: true,
    contentDispositionType: "attachment",
    remotePatterns: [
      {
        protocol: "https",
        hostname: "**",
      },
    ],
  },
  experimental: {
    scrollRestoration: true,
    optimizePackageImports: ["@heroui/react", "lucide-react", "react-icons"],
  },
  allowedDevOrigins: ["localhost", "127.0.0.1", "*.localhost"],
  env: {
    NEXT_PUBLIC_APP_VERSION: version,
  },
  compress: true,
  poweredByHeader: false,
  compiler: {
    removeConsole:
      process.env.NODE_ENV === "production"
        ? {
            exclude: ["error", "warn"],
          }
        : false,
  },
  // Next cannot emit response headers into a static export. Apache receives
  // equivalent rules from the generated out/.htaccess instead. Omitting the
  // config key entirely also prevents Next from reporting a false deployment
  // warning for export builds.
  ...(isExport ? {} : { headers: getRuntimeHeaders }),
};

export default async function config(phase: string) {
  if (phase === PHASE_PRODUCTION_BUILD) {
    // Static pages without getStaticProps still need branding in their HTML.
    // Fetch once per build; never expose the rest of the backend settings.
    const adminUrl = process.env.NEXT_PUBLIC_ADMIN_PANEL_URL?.trim();
    if (!adminUrl) throw new Error("NEXT_PUBLIC_ADMIN_PANEL_URL is required for SEO settings.");
    let lastError: unknown;
    for (let attempt = 1; attempt <= 3; attempt += 1) {
      try {
        const response = await fetch(`${adminUrl.replace(/\/+$/, "")}/api/settings/web`, {
          headers: { Accept: "application/json" },
          signal: AbortSignal.timeout(30_000),
        });
        const envelope = await response.json();
        if (!response.ok || !envelope.success || !envelope.data?.value) {
          throw new Error("Web settings API returned an invalid response.");
        }
        const fields = ["siteName", "metaDescription", "metaKeywords", "siteHeaderLogo", "siteFavicon"];
        const seo = Object.fromEntries(fields.map((key) => {
          const value = envelope.data.value[key];
          return [key, typeof value === "string" ? value.trim() : ""];
        }));
        // Resolve backend storage paths against the backend, not the storefront.
        for (const key of ["siteHeaderLogo", "siteFavicon"]) {
          if (seo[key]) {
            const url = new URL(seo[key], `${adminUrl}/`);
            if (!/^https?:$/.test(url.protocol)) throw new Error(`Invalid ${key} URL.`);
            seo[key] = url.href;
          }
        }
        nextConfig.env = { ...nextConfig.env, NEXT_PUBLIC_SITE_SEO: JSON.stringify(seo) };
        lastError = undefined;
        break;
      } catch (error) {
        lastError = error;
      }
    }
    if (lastError) throw new Error("Unable to load build-time SEO settings.", { cause: lastError });
  }
  return withPWA(nextConfig);
}
