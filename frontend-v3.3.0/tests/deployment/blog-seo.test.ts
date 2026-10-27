// @vitest-environment node
import { describe, expect, it } from "vitest";
import {
  articleSchemas,
  blogSitemapRoutes,
  missingBlogPaths,
  sitemapLastmod,
} from "../../scripts/blog-seo.mjs";

describe("published blog sitemap coverage", () => {
  it("encodes public slugs and uses publication or modification dates", () => {
    const routes = blogSitemapRoutes([
      {
        id: 1,
        title: "Food",
        slug: "food & drink",
        published_at: "2026-10-05T12:00:00+05:30",
      },
      {
        id: 2,
        title: "New",
        slug: "new",
        published_at: "2026-10-01",
        updated_at: "2026-10-06",
      },
      { id: 3, title: "Unknown date", slug: "unknown", published_at: "bad" },
      { id: 4, title: "Invalid", slug: " " },
    ]);
    expect(routes).toHaveLength(3);
    expect(routes[0].path).toBe("/blogs/food%20%26%20drink/");
    expect(routes[1].lastmod).toBe("2026-10-06");
    expect(sitemapLastmod(routes[0], "2099-01-01")).toContain(
      "2026-10-05T12:00:00+05:30",
    );
    expect(sitemapLastmod(routes[2], "2099-01-01")).toBe("");
    expect(sitemapLastmod({ path: "/blogs/" }, "2099-01-01")).toBe("");
  });

  it("detects missing articles even when every existing sitemap URL passes", () => {
    expect(
      missingBlogPaths(
        ["/blogs/", "/blogs/food/"],
        ["/blogs/food/", "/blogs/new/"],
      ),
    ).toEqual(["/blogs/new/"]);
    expect(missingBlogPaths([], [])).toEqual(["/blogs/"]);
  });

  it("finds article schemas in arrays and graphs without counting breadcrumbs", () => {
    const article = { "@type": "BlogPosting", headline: "Food" };
    expect(
      articleSchemas([{ "@type": "BreadcrumbList" }, { "@graph": [article] }]),
    ).toEqual([article]);
  });
});
