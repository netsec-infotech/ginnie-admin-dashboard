import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { adaptBlogPost } from "@/helpers/blog";
import { generateBlogPostingSchema } from "@/helpers/blogSeo";
import { serializeJsonLd } from "@/helpers/seo";

beforeEach(() => vi.stubEnv("NEXT_PUBLIC_SITE_URL", "https://example.com/"));
afterEach(() => vi.unstubAllEnvs());
const publisher = { siteName: "Shop", siteHeaderLogo: "/logo.png" };

describe("BlogPosting metadata", () => {
  it("describes the actual article, author, image, dates and canonical URL", () => {
    const post = adaptBlogPost({
      id: 1,
      title: "Food",
      slug: "food & drink",
      short_description: "An intro",
      image: "/food.jpg",
      published_at: "2026-10-01T10:00:00+05:30",
      updated_at: "2026-10-06T10:00:00+05:30",
      author: { username: "Writer" },
      tags: ["food"],
    })!;
    expect(generateBlogPostingSchema(post, publisher, "en")).toMatchObject({
      "@type": "BlogPosting",
      headline: "Food",
      description: "An intro",
      mainEntityOfPage: {
        "@id": "https://example.com/blogs/food%20%26%20drink/",
      },
      image: ["https://example.com/food.jpg"],
      datePublished: "2026-10-01T10:00:00+05:30",
      dateModified: "2026-10-06T10:00:00+05:30",
      author: { "@type": "Person", name: "Writer" },
      publisher: {
        name: "Shop",
        logo: { url: "https://example.com/logo.png" },
      },
    });
  });

  it("omits unknown dates, bylines and unsafe image protocols", () => {
    const post = adaptBlogPost({
      id: 1,
      title: "Food",
      slug: "food",
      image: "data:image/png;base64,abc",
      published_at: "bad",
      updated_at: "bad",
    })!;
    const schema = generateBlogPostingSchema(post, publisher, "hi");
    expect(schema).not.toHaveProperty("datePublished");
    expect(schema).not.toHaveProperty("dateModified");
    expect(schema).not.toHaveProperty("author");
    expect(schema).not.toHaveProperty("image");
    expect(schema.inLanguage).toBe("hi");
    const unsafe = generateBlogPostingSchema(
      { ...post, image: "javascript:alert(1)" },
      publisher,
      "en",
    );
    expect(unsafe).not.toHaveProperty("image");
  });

  it("keeps API-authored script endings escaped in JSON-LD", () => {
    const post = adaptBlogPost({
      id: 1,
      slug: "food",
      title: "</script><script>alert(1)</script>",
    })!;
    const serialized = serializeJsonLd(
      generateBlogPostingSchema(post, publisher, "en"),
    );
    expect(serialized).not.toContain("</script>");
    expect(JSON.parse(serialized).headline).toBe(post.title);
  });
});
