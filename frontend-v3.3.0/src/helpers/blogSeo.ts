import type { BlogPost } from "@/types/blog";
import { ensureAbsoluteUrl, getCanonicalUrl } from "@/helpers/seo";

const validDate = (value?: string | null) =>
  value &&
  /^\d{4}-\d{2}-\d{2}(?:T|$)/.test(value) &&
  Number.isFinite(Date.parse(value))
    ? value
    : undefined;

const imageUrl = (value?: string | null) => {
  if (!value || /^(?!https?:)[a-z][a-z\d+.-]*:/i.test(value)) return undefined;
  const absolute = ensureAbsoluteUrl(value);
  try {
    return /^https?:$/.test(new URL(absolute).protocol) ? absolute : undefined;
  } catch {
    return undefined;
  }
};

export const generateBlogPostingSchema = (
  post: BlogPost,
  publisher: { siteName: string; siteHeaderLogo: string },
  language: string,
) => {
  const url = getCanonicalUrl(`/blogs/${encodeURIComponent(post.slug)}/`);
  const image = imageUrl(post.image);
  const logo = imageUrl(publisher.siteHeaderLogo);
  const datePublished = validDate(post.publishedAt);
  const dateModified = validDate(post.updatedAt);
  return {
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    "@id": `${url}#article`,
    url,
    mainEntityOfPage: { "@type": "WebPage", "@id": url },
    headline: post.title,
    description: post.excerpt,
    inLanguage: language,
    ...(image ? { image: [image] } : {}),
    ...(datePublished ? { datePublished } : {}),
    ...(dateModified ? { dateModified } : {}),
    ...(post.category.name ? { articleSection: post.category.name } : {}),
    ...(post.tags.length ? { keywords: post.tags.join(", ") } : {}),
    ...(post.author
      ? { author: { "@type": "Person", name: post.author } }
      : {}),
    publisher: {
      "@type": "Organization",
      name: publisher.siteName,
      url: getCanonicalUrl("/"),
      ...(logo ? { logo: { "@type": "ImageObject", url: logo } } : {}),
    },
  };
};
