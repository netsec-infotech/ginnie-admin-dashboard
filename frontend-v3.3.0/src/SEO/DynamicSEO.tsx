import NextHead from "next/head";
import React from "react";
import { useSettings } from "@/contexts/SettingsContext";
import { getSiteSeo } from "@/config/siteSeo";
import {
  getCanonicalUrl,
  ensureAbsoluteUrl,
  serializeJsonLd,
  buildSeoTitle,
} from "@/helpers/seo";

export interface SEOProps {
  // Basic Meta Tags
  title?: string;
  description?: string;
  keywords?: string;
  author?: string;

  // Canonical & Alternate
  canonical?: string;

  // Open Graph
  ogType?: "website" | "article" | "product" | "profile";
  ogTitle?: string;
  ogDescription?: string;
  ogImage?: string;
  ogImageAlt?: string;
  ogImageWidth?: string | number;
  ogImageHeight?: string | number;
  ogImageType?: string;
  ogUrl?: string;
  ogSiteName?: string;

  // Twitter Card
  twitterCard?: "summary" | "summary_large_image" | "app" | "player";
  twitterTitle?: string;
  twitterDescription?: string;
  twitterImage?: string;
  twitterImageAlt?: string;
  twitterSite?: string;
  twitterCreator?: string;

  // Additional Meta
  robots?: string;
  googlebot?: string;

  // Product Specific (for ecommerce)
  productPrice?: string;
  productCurrency?: string;
  productAvailability?: "in stock" | "out of stock" | "preorder";
  productCondition?: "new" | "used" | "refurbished";

  // Structured Data (JSON-LD)
  jsonLd?: object | object[];

  // Additional Head Elements
  children?: React.ReactNode;
}

const DynamicSEO: React.FC<SEOProps> = ({
  title,
  description,
  keywords,
  author,
  canonical,
  ogType = "website",
  ogTitle,
  ogDescription,
  ogImage,
  ogImageAlt,
  ogImageWidth,
  ogImageHeight,
  ogImageType,
  ogUrl,
  ogSiteName,
  twitterCard = "summary_large_image",
  twitterTitle,
  twitterDescription,
  twitterImage,
  twitterImageAlt,
  twitterSite,
  twitterCreator,
  robots = "index, follow",
  googlebot,
  productPrice,
  productCurrency,
  productAvailability,
  productCondition,
  jsonLd,
  children,
}) => {
  const { webSettings } = useSettings();

  // Get defaults from settings or config
  const seo = getSiteSeo(webSettings);
  const siteName = seo.siteName;
  const siteDescription = seo.metaDescription;
  const siteKeywords = seo.metaKeywords;
  const siteLogo = ensureAbsoluteUrl(
    seo.siteHeaderLogo,
  );
  const baseUrl = (process.env.NEXT_PUBLIC_SITE_URL || "").trim();

  // Compute final values
  const finalTitle = buildSeoTitle(title, siteName);
  const finalDescription = description || siteDescription;
  const finalKeywords = keywords || siteKeywords;
  const finalAuthor = author || webSettings?.supportEmail || "";
  const finalCanonical = canonical ? getCanonicalUrl(canonical, baseUrl) : "";

  // Open Graph defaults
  const finalOgTitle = ogTitle || title || siteName;
  const finalOgDescription = ogDescription || finalDescription;
  const finalOgImage = ensureAbsoluteUrl(ogImage || siteLogo, baseUrl);
  const finalOgUrl = ogUrl || finalCanonical;
  const finalOgSiteName = ogSiteName || siteName;

  // Twitter defaults
  const finalTwitterTitle = twitterTitle || finalOgTitle;
  const finalTwitterDescription = twitterDescription || finalOgDescription;
  const finalTwitterImage = ensureAbsoluteUrl(twitterImage || finalOgImage, baseUrl);

  return (
    <NextHead>
      {/* Basic Meta Tags */}
      <title>{finalTitle}</title>
      <meta name="description" content={finalDescription} key="description" />
      {finalKeywords && <meta name="keywords" content={finalKeywords} key="keywords" />}
      {finalAuthor && <meta name="author" content={finalAuthor} key="author" />}

      {/* Viewport & Mobile */}
      <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover"
        key="viewport"
      />
      <meta name="mobile-web-app-capable" content="yes" />
      <meta name="apple-mobile-web-app-capable" content="yes" />
      <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />

      {/* Robots */}
      <meta name="robots" content={robots} key="robots" />
      {googlebot && <meta name="googlebot" content={googlebot} key="googlebot" />}

      {/* Canonical URL */}
      {finalCanonical && (
        <link rel="canonical" href={finalCanonical} key="canonical" />
      )}

      {/* Favicon */}
      <link rel="icon" href={ensureAbsoluteUrl(seo.siteFavicon, baseUrl)} key="favicon" />

      {/* Open Graph / Facebook */}
      <meta property="og:type" content={ogType} key="og:type" />
      <meta property="og:title" content={finalOgTitle} key="og:title" />
      <meta property="og:description" content={finalOgDescription} key="og:description" />
      {finalOgImage && (
        <>
          <meta property="og:image" content={finalOgImage} key="og:image" />
          {finalOgImage.startsWith("https") && (
            <meta
              property="og:image:secure_url"
              content={finalOgImage}
              key="og:image:secure_url"
            />
          )}
          {ogImageType ? (
            <meta property="og:image:type" content={ogImageType} key="og:image:type" />
          ) : (
             finalOgImage.endsWith(".png") ? <meta property="og:image:type" content="image/png" key="og:image:type" /> :
             finalOgImage.endsWith(".jpg") || finalOgImage.endsWith(".jpeg") ? <meta property="og:image:type" content="image/jpeg" key="og:image:type" /> :
             finalOgImage.endsWith(".webp") ? <meta property="og:image:type" content="image/webp" key="og:image:type" /> : null
          )}
          {ogImageWidth && <meta property="og:image:width" content={ogImageWidth.toString()} key="og:image:width" />}
          {ogImageHeight && <meta property="og:image:height" content={ogImageHeight.toString()} key="og:image:height" />}
        </>
      )}
      {finalOgImage && (
        <meta
          property="og:image:alt"
          content={ogImageAlt || finalOgTitle}
          key="og:image:alt"
        />
      )}
      {finalOgUrl && <meta property="og:url" content={finalOgUrl} key="og:url" />}
      <meta property="og:site_name" content={finalOgSiteName} key="og:site_name" />
      <meta property="og:locale" content="en_US" key="og:locale" />

      {/* Twitter Card */}
      <meta name="twitter:card" content={twitterCard} key="twitter:card" />
      <meta name="twitter:title" content={finalTwitterTitle} key="twitter:title" />
      <meta name="twitter:description" content={finalTwitterDescription} key="twitter:description" />
      {finalTwitterImage && (
        <meta name="twitter:image" content={finalTwitterImage} key="twitter:image" />
      )}
      {finalTwitterImage && (
        <meta
          name="twitter:image:alt"
          content={twitterImageAlt || finalTwitterTitle}
          key="twitter:image:alt"
        />
      )}
      {twitterSite && <meta name="twitter:site" content={twitterSite} key="twitter:site" />}
      {twitterCreator && (
        <meta name="twitter:creator" content={twitterCreator} key="twitter:creator" />
      )}

      {/* Product Meta Tags (for ecommerce) */}
      {productPrice && productCurrency && (
        <>
          <meta property="product:price:amount" content={productPrice} key="product:price:amount" />
          <meta property="product:price:currency" content={productCurrency} key="product:price:currency" />
        </>
      )}
      {productAvailability && (
        <meta property="product:availability" content={productAvailability} key="product:availability" />
      )}
      {productCondition && (
        <meta property="product:condition" content={productCondition} key="product:condition" />
      )}

      {/* Copyright */}
      {webSettings?.siteCopyright && (
        <meta name="copyright" content={webSettings.siteCopyright} />
      )}

      {/* Structured Data (JSON-LD) */}
      {jsonLd && (
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: serializeJsonLd(jsonLd),
          }}
          key="json-ld"
        />
      )}

      {/* Additional custom elements */}
      {children}
    </NextHead>
  );
};

export default DynamicSEO;
