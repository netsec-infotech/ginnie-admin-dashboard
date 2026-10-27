// @vitest-environment node
import { afterEach, beforeEach, expect, it } from "vitest";
import { spawnSync } from "node:child_process";
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";

const auditScript = resolve("scripts/audit-seo.mjs");
let root: string;

beforeEach(() => {
  root = mkdtempSync(join(tmpdir(), "hyperlocal-blog-audit-"));
  const write = (filename: string, content: string) => {
    const path = join(root, filename);
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, content);
  };
  write("public/sitemap.xml", '<urlset><url><loc>https://shop.example.com/blogs/</loc></url></urlset>');
  for (const path of [
    "404", "blog-fallback", "cart", "cart/shared/__shared-cart__", "forgot-password",
    "my-account", "my-account/addresses", "my-account/loyalty-points", "my-account/notifications",
    "my-account/orders", "my-account/orders/[slug]", "my-account/refer-and-earn",
    "my-account/transactions", "my-account/wallet", "my-account/wishlists", "offline",
    "products/search", "shopping-list",
  ]) write(`out/${path}/index.html`, '<meta name="robots" content="noindex, follow">');
  mkdirSync(join(root, "out", "blogs"));
});

afterEach(() => rmSync(root, { recursive: true, force: true }));

function audit(pageProps: object) {
  const url = "https://shop.example.com/blogs/";
  writeFileSync(join(root, "out", "blogs", "index.html"), `
    <html><head><title>Blog</title>
    <meta name="description" content="Published articles">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="${url}">
    <meta property="og:title" content="Blog">
    <meta property="og:description" content="Published articles">
    <meta property="og:image" content="https://shop.example.com/blog.jpg">
    <meta property="og:url" content="${url}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Blog">
    <meta name="twitter:description" content="Published articles">
    <meta name="twitter:image" content="https://shop.example.com/blog.jpg">
    <script type="application/ld+json">{"@type":"BreadcrumbList"}</script>
    </head><body><h1>Blog</h1>
    <script id="__NEXT_DATA__" type="application/json">${JSON.stringify({ props: { pageProps } })}</script>
    </body></html>`);
  return spawnSync(process.execPath, [auditScript], {
    cwd: root, env: { ...process.env, NEXT_PUBLIC_SSR: "false" }, encoding: "utf8",
  });
}

it("passes the final build audit with a warning when blog prerendering was explicitly skipped", () => {
  const result = audit({ initialBlogs: null });
  expect(result.status, result.stderr).toBe(0);
  expect(result.stderr).toContain("blog listing prerendering was skipped");
  expect(result.stdout).toContain("SEO audit passed");
});

it("still rejects unexpectedly missing blog props", () => {
  const result = audit({});
  expect(result.status).toBe(1);
  expect(result.stderr).toContain("blog listing is missing prerendered article data");
});
