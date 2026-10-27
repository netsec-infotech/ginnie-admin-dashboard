import { afterEach, describe, expect, it, vi } from "vitest";
import { getPageSEOConfig, getSEOConfig } from "@/config/seo";
import { getSiteSeo } from "@/config/siteSeo";

afterEach(() => vi.unstubAllEnvs());

describe("site SEO defaults", () => {
  it("uses build branding before client settings are available", () => {
    vi.stubEnv("NEXT_PUBLIC_SITE_SEO", JSON.stringify({
      siteName: "Local Shop",
      metaDescription: "Groceries delivered from nearby stores.",
      siteHeaderLogo: "https://assets.example.com/logo.png",
      siteFavicon: "https://assets.example.com/favicon.png",
    }));
    expect(getSiteSeo(null)).toMatchObject({
      siteName: "Local Shop",
      metaDescription: "Groceries delivered from nearby stores.",
      siteHeaderLogo: "https://assets.example.com/logo.png",
      siteFavicon: "https://assets.example.com/favicon.png",
    });
    expect(getSiteSeo({ siteName: "Updated Shop", siteFavicon: " " })).toMatchObject({
      siteName: "Updated Shop",
      siteFavicon: "https://assets.example.com/favicon.png",
    });
  });

  it("uses shipped assets when no branding is configured", () => {
    vi.stubEnv("NEXT_PUBLIC_SITE_SEO", "{}");
    expect(getSiteSeo()).toMatchObject({
      siteHeaderLogo: "/default-logo.png",
      siteFavicon: "/default-favicon.ico",
    });
  });

  it("applies runtime branding to homepage and fallback SEO", () => {
    const settings = {
      siteName: "Live Local",
      metaDescription: "Live description",
      metaKeywords: "live, local",
    };

    expect(getPageSEOConfig("home", settings)).toMatchObject({
      title: "Live Local",
      description: "Live description",
      keywords: "live, local",
      canonical: "/",
    });
    expect(getPageSEOConfig("missing-page", settings)).toMatchObject({
      title: "Live Local",
      description: "Live description",
      keywords: "live, local",
    });
    expect(getSEOConfig(settings).openGraph.siteName).toBe("Live Local");
    expect(getSEOConfig(settings).additionalMetaTags).toContainEqual({
      name: "application-name",
      content: "Live Local",
    });
  });
});
