import React from "react";
import NextHead from "next/head";
import Script from "next/script";
import { useRouter } from "next/router";
import { isNoIndexRoute } from "@/guards/authGuard";
import { Settings } from "@/types/ApiResponse";
import { getWebSettings } from "@/helpers/getters";
import { siteConfig } from "@/config/site";
import { ensureAbsoluteUrl } from "@/helpers/seo";

interface HeadProps {
  settings: Settings;
}

export const SEOHead = ({ settings }: HeadProps) => {
  // Before the early return below — hooks must not sit behind a conditional.
  const router = useRouter();
  const webSettings = getWebSettings(settings as Settings);
  const siteName =
    webSettings?.siteName || siteConfig.name || "Default Site Name";
  const fullTitle = siteName;
  const baseUrl = (process.env.NEXT_PUBLIC_SITE_URL || "").trim();
  const siteLogo = ensureAbsoluteUrl(webSettings?.siteHeaderLogo || "/logo.png", baseUrl);

  const robots = isNoIndexRoute(router.pathname)
    ? "noindex, follow"
    : "index, follow";

  if (!webSettings) {
    return (
      <NextHead>
        <title>{fullTitle}</title>
        <meta name="description" content={siteConfig.description} />
        <meta name="keywords" content={siteConfig.description} />
        {/* Also emitted here: settings failing to load must not turn a private
            page into an indexable one. */}
        <meta name="robots" content={robots} key="robots" />
      </NextHead>
    );
  }
  return (
    <NextHead>
      <title>{fullTitle}</title>
      <link
        rel="icon"
        href={webSettings?.siteFavicon || "/default-favicon.ico"}
        type="image/x-icon"
        key="favicon"
      />
      <meta
        name="description"
        content={webSettings.metaDescription || "Default meta description"}
        key="description"
      />

      <meta
        name="keywords"
        content={webSettings.metaKeywords || "default, keywords"}
        key="keywords"
      />
      <meta name="copyright" content={webSettings.siteCopyright} />
      <meta name="author" content={webSettings.supportEmail} />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" key="viewport" />
      {/* Route-aware rather than a blanket `index, follow`.
          Pages set their own value via PageHead/DynamicSEO (same `key`, so the
          later render wins), but that only works for pages rendered on the
          server — `/shopping-list` is `dynamic(..., { ssr: false })`, so its
          PageHead never reaches the crawler's HTML and this was the only robots
          tag it ever saw. Deciding here covers those too. */}
      <meta name="robots" content={robots} key="robots" />
      <meta property="og:title" content={webSettings.siteName} key="og:title" />
      <meta
        property="og:description"
        content={webSettings.metaDescription || "Default meta description"}
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
        content={webSettings.siteName || "Default Site Name"}
        key="twitter:title"
      />
      <meta
        name="twitter:description"
        content={webSettings.metaDescription || "Default meta description"}
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
