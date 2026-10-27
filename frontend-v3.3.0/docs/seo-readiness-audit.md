# SEO readiness audit

Baseline audit date: 2026-08-29. Latest verification: 2026-10-08 (see blog update below).

## Baseline result

The application code is SEO-ready for its Pages Router static-export and SSR
deployment modes. The final export passed the automated raw-HTML audit for all
703 indexable URLs, 591 product share previews, and 16 excluded route templates.

The baseline local artifact was **not a production artifact** because
`.env` used `NEXT_PUBLIC_SITE_URL=http://localhost:3000`. Set the real HTTPS
origin before deployment and run `npm run build`; that command regenerates all
canonicals, Open Graph URLs, `robots.txt`, and `sitemap.xml`.

## Baseline findings and fixes

| Area | Baseline finding | Resolution |
| --- | --- | --- |
| Crawlable HTML | Static exports contained an empty React root, then a loading-only shell after the first correction. A later server/client persistence split emitted the page on the server but an empty first browser tree, causing React hydration failure. | Server/static renders bypass Redux persistence and the client-only settings loader. The browser persistence gate reuses the page tree while rehydrating, so its first render matches the exported HTML. Exported HTML contains the real head and page content. |
| Dynamic routes | Apache rewrote every product, category, brand, store, delivery-zone, feature-section, and home-section URL to one generic `[slug]` shell. | Every public dynamic URL is prerendered. Existing directories are served directly and unknown catalog URLs return a real 404. |
| Sitemap | The old map had 134 URLs, included the nonexistent `/products/` route, capped products at the first 100, omitted several dynamic route families, and could silently succeed after API failures. | Generation now paginates the complete default-location catalog, includes every public route family, removes invalid routes, deduplicates URLs, retries transient failures, and fails the build on incomplete data. Current coverage is 703 URLs. |
| Robots | `robots.txt` could retain an old hostname because only one placeholder replacement was attempted, and framework assets needed for rendering were blocked. | It is generated deterministically from a template and the configured site origin; public Next/static and image assets are crawlable. |
| Per-page metadata | Brand details lacked the shared SEO implementation. Delivery-zone details only set title and description. Several fallback pages lacked complete social tags. | Indexable routes now emit one title, description, robots directive, canonical, Open Graph set, Twitter set, and absolute preview image. Loading/error states use `noindex`. |
| Structured data | Product offers used only the first variant, images could be relative, and raw JSON-LD serialization did not escape `<`. | Product ranges use all variants with offer counts, schema URLs/images are absolute, and JSON-LD is safely serialized. Brand, delivery-zone, feature, section, store, product, organization, collection, and breadcrumb schemas are present where applicable. |
| Headings | Product exports had no H1 because they rendered a skeleton; responsive store markup emitted two H1 elements; several reusable sections used H1 for subsections. | Every sitemap page now has exactly one H1. Reusable and duplicated responsive headings use lower levels. |
| Index control | Search and offline pages were not in the shared noindex policy. | Private, transactional, search, offline, and error templates emit `noindex, follow` and remain outside the sitemap. |
| Soft 404s and share URLs | Unknown URLs fell back to the homepage shell. Product share URLs returned a metadata-empty generic shell, so Slack, WhatsApp, and Discord could not create an unfurl. | Unknown URLs return 404. Every product share URL is now fully prerendered with the product title, description, image, Open Graph/Twitter tags, JSON-LD, and a canonical pointing to the main product page. Generated share URLs include the product update timestamp to refresh social caches. |
| Static response headers | Next runtime headers do not apply to `output: export`. | SSR keeps Next headers; the generated Apache `.htaccess` supplies equivalent security and sitemap/robots cache headers for the FTP/static deployment. |
| Page payload | One feature-section page exceeded Next's 128 kB serialized-data threshold because listing cards carried full rich product descriptions. | Initial listing descriptions are compact excerpts. The formerly failing page now serializes about 81 kB and the build warning is gone. |
| API resilience | Transient catalog errors could prerender an indexable listing with an unsuccessful/empty response. | Static page fetches retry and then fail closed. The audit also rejects unsuccessful serialized prerender responses. |

## Baseline indexable inventory

| Route family | URLs |
| --- | ---: |
| Static public routes | 14 |
| Products | 591 |
| Brands | 31 |
| Stores | 18 |
| Categories | 10 |
| Delivery zones | 9 |
| Feature-section slugs | 14 |
| Home-layout sections | 16 |
| **Total** | **703** |

The feature-section API returned 28 records, but only 14 unique public slugs;
the sitemap and static paths intentionally deduplicate them.

## Baseline verification performed

- `npx tsc --noEmit`: passed.
- Focused Vitest suite: 52 tests passed across SEO helpers and noindex routes.
- Redux provider regression: server markup and the persistence gate's first
  browser markup are identical, and hydration reports no recoverable error.
- `npx next build`: passed; 1,311 total static/SSG pages generated with no static
  header or oversized page-data warnings.
- `npm run audit:seo`: passed 703 indexable URLs, 591 product share previews,
  and 16 noindex templates. It
  checks exported-file presence, unique required tags, canonical/OG agreement,
  absolute URLs, robots directives, one H1, valid JSON-LD, serialized page-data
  size, and successful prerender API responses.
- Live HTTP checks returned 200 with the expected metadata, one H1, and JSON-LD
  for home, product, store, and delivery-zone samples. Search returned
  `noindex, follow`; an unknown URL returned 404.

The in-app visual browser could not initialize in this session, so no visual or
interactive-browser result is claimed. That does not weaken the crawler-facing
raw-HTML checks above, which are the primary SEO signal.

## Production deployment checklist

1. Set `NEXT_PUBLIC_SITE_URL` to the final HTTPS origin with no path suffix.
2. Keep valid `defaultLatitude` and `defaultLongitude` values in web settings;
   catalog discovery is intentionally scoped to that default delivery area.
3. Run `npm run build`. Do not deploy if sitemap generation, prerendering, or
   `audit:seo` fails.
4. Deploy the complete `out/` directory, including `.htaccess`, `robots.txt`,
   and `sitemap.xml`. If the target is not Apache, port the 404, redirect,
   security-header, and cache-header rules to that platform.
5. Verify `robots.txt` and a few canonical tags on the public origin, then
   submit `/sitemap.xml` in the relevant search-engine webmaster tools.

For a static deployment, new catalog entities become crawlable after the next
successful build/deploy. In SSR mode, these public dynamic pages use a
five-minute revalidation interval and blocking fallback for newly discovered
routes.

## Blog SEO update — 2026-10-08

Sitemap generation now scans every page of the public `/api/blogs` endpoint,
includes `/blogs/` and each published article, and fails on incomplete API
pagination. Blog `lastmod` values use `updated_at` when supplied, otherwise
`published_at`; unknown dates are omitted. The public endpoint determines which
posts are published.

The generator fetches article details sequentially with retries, then saves one
build-wide snapshot under `node_modules/.cache/hyperlocal-static-blogs.json`.
Static article paths and props use that same snapshot. Embedded raster editor
images are extracted unchanged into hashed `public/blog-media/` assets before
Next builds, reducing duplicated HTML/page-data payloads. Related-post cards
receive summaries instead of full article bodies. These files are regenerated
during `npm run build` and must be included in the deployed `out/` directory.

Article pages retain their canonical, author, Open Graph, Twitter and breadcrumb
metadata and now include `BlogPosting` JSON-LD with the actual headline, byline,
publisher, image, language and available publication/modification dates. Missing
bylines and dates are not invented. The static blog index prerenders its first
page of article links; filtered requests fetch their own results without reusing
the unfiltered index.

The export audit checks blog inventory independently of sitemap contents,
verifies article structured data and prerendered content, and checks that the
generic blog fallback stays `noindex`. Existing Apache rules serve exported
articles directly and use the live fallback only for missing article paths,
including Next navigation JSON.

The shared-cart export shell uses the reserved `__shared-cart__` path rather
than the dynamic route's literal `[shareCode]` template, avoiding Next's static
path collision. HTML/data rewrites and the noindex audit use that same shell.
Tailwind automatic source discovery is scoped to `src`; the explicit application
and HeroUI paths in `tailwind.config.ts` remain enabled. This avoids scanning
unrelated build files during production compilation.

Store exports also reuse the public nearby-store listing saved during sitemap
generation (`node_modules/.cache/hyperlocal-static-stores.json`). This keeps
listed stores and exported store metadata consistent when the optional detail
endpoint fails. SSR and client requests continue using the live detail/listing
fallback.

For static hosting, publish a new build after adding, editing or removing blogs
to refresh their exported HTML and sitemap. SSR article pages revalidate every
five minutes, but the generated sitemap still refreshes during the build.

Final verification on 2026-10-08: 152 targeted regression tests passed, as did
lint, TypeScript, and the complete production build. The raw-HTML SEO audit
passed for 882 indexable URLs, 17 blog articles, 747 product share previews,
and 18 excluded route templates. The verified local export is in `out/`;
publishing remains a separate deployment step.
