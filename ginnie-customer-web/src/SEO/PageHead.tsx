import { siteConfig } from "@/config/site";
import { useSettings } from "@/contexts/SettingsContext";
import NextHead from "next/head";
import React, { ReactNode } from "react";
interface PageHeadProps {
  pageTitle: string;
  /**
   * Keep this page out of search results.
   *
   * Needed because the site-wide `SEOHead` in the default layout emits
   * `robots: index, follow` for EVERY page. robots.txt disallows crawling the
   * private ones, but that only blocks crawling — a URL linked from elsewhere
   * can still be indexed, and the layout is actively inviting it. Overriding
   * here works because `next/head` de-dupes on `key`, and the page renders
   * after the layout.
   */
  noindex?: boolean;
  children?: ReactNode;
}

const PageHead = ({
  pageTitle = "",
  noindex = false,
  children,
}: PageHeadProps) => {
  const { webSettings } = useSettings();

  const siteName =
    webSettings?.siteName || siteConfig?.name || "Default Site Name";

  const fullTitle = pageTitle ? `${pageTitle} | ${siteName}` : siteName;

  return (
    <NextHead>
      <title>{fullTitle}</title>
      {/* `follow` rather than `nofollow`: don't index this page, but do let
          crawlers walk its links out to pages that should rank. */}
      {noindex && (
        <meta name="robots" content="noindex, follow" key="robots" />
      )}
      {children}
    </NextHead>
  );
};

export default PageHead;
