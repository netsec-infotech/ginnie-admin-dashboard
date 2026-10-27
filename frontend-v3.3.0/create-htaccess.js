import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { isStaticExport } from "./scripts/build-mode.mjs";

// ESM does not provide __dirname by default. Create it from import.meta.url.
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export function generateHtaccess(root = __dirname) {
  if (!isStaticExport(root)) {
    console.log("SSR build: skipping static .htaccess generation.");
    return;
  }
  if (!fs.existsSync(path.join(root, "out"))) {
    throw new Error("Static export out/ is missing. Complete a successful static Next.js build before generating .htaccess.");
  }
  const outputPath = path.join(root, "out", ".htaccess");

  // Construct the .htaccess content
  const htaccessContent = `<IfModule mod_headers.c>
    Header always set X-DNS-Prefetch-Control "on"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(self), interest-cohort=()"
    Header always set Cross-Origin-Resource-Policy "cross-origin"

    <FilesMatch "^(sitemap\\.xml|robots\\.txt)$">
      Header set Cache-Control "public, max-age=3600"
    </FilesMatch>
  </IfModule>

  <IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    DirectoryIndex index.html
    ErrorDocument 404 /404/index.html

    # Block an old PHP preview file left by non-deleting FTP uploads.
    RewriteRule ^cart-shared\\.php$ - [F,L]
     
    # Dynamic routes (Index pages)
    RewriteRule ^brands/?$ brands/index.html [L]
    RewriteRule ^categories/?$ categories/index.html [L]
    RewriteRule ^delivery-zones/?$ delivery-zones/index.html [L]
    RewriteRule ^feature-sections/?$ feature-sections/index.html [L]
    RewriteRule ^my-account/orders/?$ my-account/orders/index.html [L]
    RewriteRule ^stores/?$ stores/index.html [L]

    # Cart share codes are created at runtime, after the static export.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^cart/shared/([A-Za-z0-9_-]+)/?$ cart/shared/__shared-cart__/index.html [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^_next/data/([^/]+)/cart/shared/([A-Za-z0-9_-]+)\\.json$ _next/data/$1/cart/shared/__shared-cart__.json [L]
     
    # Public dynamic routes are generated as real directories at build time.
    # Do not rewrite an existing directory back to the old generic [slug]
    # shell: that would discard its prerendered content and metadata.
    # Newly added catalog and blog URLs use their matching live fallback shells.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^brands/([^/]+)/?$ brand-fallback/index.html [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^_next/data/([^/]+)/brands/([^/]+)\\.json$ _next/data/$1/brand-fallback.json [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^categories/([^/]+)/?$ category-fallback/index.html [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^_next/data/([^/]+)/categories/([^/]+)\\.json$ _next/data/$1/category-fallback.json [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^blogs/([^/]+)/?$ blog-fallback/index.html [L]

    # Preserve existing article payloads; newly published articles use the shell.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^_next/data/([^/]+)/blogs/([^/]+)\\.json$ _next/data/$1/blog-fallback.json [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^delivery-zones/([^/]+)/?$ - [R=404,L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^feature-sections/([^/]+)/?$ - [R=404,L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^my-account/orders/([^/]+)/?$ my-account/orders/[slug]/index.html [L]

    # The order detail page is a client-populated dynamic shell. Next fetches
    # its JSON data route before rendering during Link/router navigation.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^_next/data/([^/]+)/my-account/orders/([^/]+)\\.json$ _next/data/$1/my-account/orders/[slug].json [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(?:share/)?products/([^/]+)/?$ product-fallback/index.html [L]

    # Next Link/router navigation fetches JSON before rendering the product.
    # Keep generated payloads; unknown slugs use the same live product shell.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^_next/data/([^/]+)/(?:share/)?products/([^/]+)\\.json$ _next/data/$1/product-fallback.json [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^section/([^/]+)/?$ - [R=404,L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^stores/([^/]+)/?$ store-fallback/index.html [L]

    # Client navigation also requests a JSON payload for the dynamic store URL.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^_next/data/([^/]+)/stores/([^/]+)\\.json$ _next/data/$1/store-fallback.json [L]

    # Existing share pages keep their prerendered Open Graph data. Products
    # added after deployment use the live shell above until the next build.
    
    # If the request is not for a valid directory
    RewriteCond %{REQUEST_FILENAME} !-d
    # If the request is not for a valid file
    RewriteCond %{REQUEST_FILENAME} !-f
    # If the request is not for a valid link
    RewriteCond %{REQUEST_FILENAME} !-l

    # Avoid search-engine-visible soft 404s for every other unknown URL.
    RewriteRule . - [R=404,L]
  </IfModule>`;

  // Write the content to .htaccess file
  fs.rmSync(path.join(root, "out", "cart-shared.php"), { force: true });
  fs.writeFileSync(outputPath, htaccessContent.trim());
  console.log(".htaccess file has been generated successfully.");
}

// Call the function to generate .htaccess
if (process.argv[1] && path.resolve(process.argv[1]) === __filename) {
  generateHtaccess();
}
