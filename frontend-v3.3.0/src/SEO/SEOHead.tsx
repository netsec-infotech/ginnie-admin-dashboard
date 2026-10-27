import React from "react";
import NextHead from "next/head";
import Script from "next/script";
import { useRouter } from "next/router";
import { isNoIndexRoute } from "@/guards/authGuard";
import { Settings } from "@/types/ApiResponse";
import { getWebSettings } from "@/helpers/getters";
import { getSiteSeo } from "@/config/siteSeo";
import { buildSeoTitle, ensureAbsoluteUrl } from "@/helpers/seo";

interface HeadProps {
  settings?: Settings | null;
}

export const SEOHead = ({ settings }: HeadProps) => {
  // Before the early return below — hooks must not sit behind a conditional.
  const router = useRouter();
  const webSettings = getWebSettings(settings);
  const seo = getSiteSeo(webSettings);
  const siteName = seo.siteName;
  const fullTitle = buildSeoTitle(undefined, siteName);
  const baseUrl = (process.env.NEXT_PUBLIC_SITE_URL || "").trim();
  const siteLogo = ensureAbsoluteUrl(
    seo.siteHeaderLogo,
    baseUrl,
  );

  const robots = isNoIndexRoute(router.pathname)
    ? "noindex, follow"
    : "index, follow";

  return (
    <NextHead>
      <title>{fullTitle}</title>
      <link
        rel="icon"
        href={ensureAbsoluteUrl(seo.siteFavicon, baseUrl)}
        key="favicon"
      />
      <meta
        name="description"
        content={seo.metaDescription}
        key="description"
      />

      <meta
        name="keywords"
        content={seo.metaKeywords}
        key="keywords"
      />
      <meta name="copyright" content={webSettings?.siteCopyright} />
      <meta name="author" content={webSettings?.supportEmail} />
      <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" key="viewport" />
      {/* Route-aware rather than a blanket `index, follow`.
          Pages set their own value via PageHead/DynamicSEO (same `key`, so the
          later render wins), but that only works for pages rendered on the
          server — `/shopping-list` is `dynamic(..., { ssr: false })`, so its
          PageHead never reaches the crawler's HTML and this was the only robots
          tag it ever saw. Deciding here covers those too. */}
      <meta name="robots" content={robots} key="robots" />
      <meta property="og:type" content="website" key="og:type" />
      <meta property="og:site_name" content={siteName} key="og:site_name" />
      <meta property="og:title" content={siteName} key="og:title" />
      <meta
        property="og:description"
        content={seo.metaDescription}
        key="og:description"
      />
      <meta property="og:image" content={siteLogo} key="og:image" />
      {/* Site URL, not window.location.href: this renders on the server, where
          `window` is undefined — so the old expression emitted an EMPTY og:url
          in exactly the HTML that crawlers and link unfurlers read. Pages using
          DynamicSEO override this with their own per-page og:url via the same
          key; this is the fallback for those that don't. */}
      {baseUrl && (
        <meta property="og:url" content={baseUrl} key="og:url" />
      )}
      <meta name="twitter:card" content="summary_large_image" key="twitter:card" />
      <meta
        name="twitter:title"
        content={siteName}
        key="twitter:title"
      />
      <meta
        name="twitter:description"
        content={seo.metaDescription}
        key="twitter:description"
      />
      <meta
        name="twitter:image"
        content={siteLogo}
        key="twitter:image"
      />

      {webSettings?.headerScript && (
        <Script
          id="global-header-script"
          strategy="afterInteractive"
          dangerouslySetInnerHTML={{ __html: webSettings.headerScript }}
          key="global-header-script"
        />
      )}
      {webSettings?.footerScript && (
        <Script
          id="global-footer-script"
          strategy="lazyOnload"
          dangerouslySetInnerHTML={{ __html: webSettings.footerScript }}
          key="global-footer-script"
        />
      )}
    </NextHead>
  );
};
