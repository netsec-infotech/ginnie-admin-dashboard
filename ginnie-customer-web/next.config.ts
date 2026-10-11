import type { NextConfig } from "next";
import { readFileSync } from "fs";
import { join } from "path";
import withPWAInit from "@ducanh2912/next-pwa";

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

const withPWA = withPWAInit({
  dest: "public",
  disable: process.env.NODE_ENV === "development",
  register: true,
  cacheOnFrontEndNav: true,
  aggressiveFrontEndNavCaching: true,
  reloadOnOnline: true,
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
  output: isExport ? "export" : undefined,
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
  async headers() {
    // Security headers for all routes
    const securityHeaders = [
      {
        key: "X-DNS-Prefetch-Control",
        value: "on",
      },
      {
        key: "Strict-Transport-Security",
        value: "max-age=63072000; includeSubDomains; preload",
      },
      {
        key: "X-Frame-Options",
        value: "SAMEORIGIN",
      },
      {
        key: "X-Content-Type-Options",
        value: "nosniff",
      },
      {
        key: "X-XSS-Protection",
        value: "1; mode=block",
      },
      {
        key: "Referrer-Policy",
        value: "strict-origin-when-cross-origin",
      },
      {
        key: "Permissions-Policy",
        value:
          "camera=(), microphone=(), geolocation=(self), interest-cohort=()",
      },
      {
        key: "Cross-Origin-Resource-Policy",
        value: "cross-origin",
      },
    ];

    return [
      // Apply security headers to all routes
      {
        source: "/:path*",
        headers: securityHeaders,
      },
      {
        source: "/sitemap.xml",
        headers: [
          {
            key: "Content-Type",
            value: "application/xml; charset=utf-8",
          },
          {
            key: "Cache-Control",
            value: "public, max-age=3600, s-maxage=3600",
          },
        ],
      },
      {
        source: "/sitemap.xsl",
        headers: [
          {
            key: "Content-Type",
            value: "application/xslt+xml; charset=utf-8",
          },
          {
            key: "Cache-Control",
            value: "public, max-age=86400, s-maxage=86400",
          },
        ],
      },
      {
        source: "/robots.txt",
        headers: [
          {
            key: "Content-Type",
            value: "text/plain; charset=utf-8",
          },
          {
            key: "Cache-Control",
            value: "public, max-age=3600, s-maxage=3600",
          },
        ],
      },
    ];
  },
};

export default withPWA(nextConfig);
