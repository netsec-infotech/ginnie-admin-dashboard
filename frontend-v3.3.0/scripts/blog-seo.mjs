/** The public /blogs API lists published posts; keep its dates, never build time. */
export const blogSitemapRoutes = (posts) =>
  posts
    .filter(
      (post) =>
        post?.id != null &&
        typeof post.title === "string" &&
        post.title.trim() &&
        typeof post.slug === "string" &&
        post.slug.trim(),
    )
    .map((post) => ({
      path: `/blogs/${encodeURIComponent(post.slug.trim())}/`,
      priority: "0.7",
      lastmod:
        validBlogDate(post.updated_at) || validBlogDate(post.published_at),
    }));

export const validBlogDate = (value) =>
  typeof value === "string" &&
  /^\d{4}-\d{2}-\d{2}(?:T|$)/.test(value) &&
  Number.isFinite(Date.parse(value))
    ? value
    : null;

export const sitemapLastmod = (route, buildDate) => {
  const date = route.path.startsWith("/blogs/")
    ? validBlogDate(route.lastmod)
    : buildDate;
  return date ? `\n    <lastmod>${date}</lastmod>` : "";
};

export const missingBlogPaths = (sitemapPaths, exportedBlogPaths) => {
  const paths = new Set(sitemapPaths);
  return ["/blogs/", ...exportedBlogPaths].filter((path) => !paths.has(path));
};

export const articleSchemas = (value) => {
  if (Array.isArray(value)) return value.flatMap(articleSchemas);
  if (!value || typeof value !== "object") return [];
  const types = Array.isArray(value["@type"])
    ? value["@type"]
    : [value["@type"]];
  return [
    ...(types.some((type) =>
      ["BlogPosting", "Article", "NewsArticle"].includes(type),
    )
      ? [value]
      : []),
    ...articleSchemas(value["@graph"]),
  ];
};
