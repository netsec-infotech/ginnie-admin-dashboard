import { siteConfig } from "@/config/site";
import type { WebSettings } from "@/types/ApiResponse";

type SiteSeo = Pick<WebSettings,
  "siteName" | "metaDescription" | "metaKeywords" | "siteHeaderLogo" | "siteFavicon"
>;

// Next embeds only these public branding fields during the build. Keeping them
// separate from SettingsContext avoids seeding payment/auth/layout state with
// incomplete settings, and gives the server and first browser render a match.
export const getSiteSeo = (settings?: Partial<WebSettings> | null): SiteSeo => {
  const built: Partial<SiteSeo> = JSON.parse(process.env.NEXT_PUBLIC_SITE_SEO || "{}");
  const defaults: SiteSeo = {
    siteName: siteConfig.name,
    metaDescription: siteConfig.metaDescription,
    metaKeywords: siteConfig.metaKeywords,
    siteHeaderLogo: "/default-logo.png",
    siteFavicon: "/default-favicon.ico",
  };
  for (const key of Object.keys(defaults) as Array<keyof SiteSeo>) {
    defaults[key] = settings?.[key]?.trim() || built[key]?.trim() || defaults[key];
  }
  return defaults;
};
