import { describe, expect, it } from "vitest";
import { adaptBlogCategory, adaptBlogPost } from "@/helpers/blog";

const listingPost = {
  id: 2,
  title: "Food Blog",
  slug: "food-blog",
  short_description: "This is a food related blog",
  image: "https://example.com/cheese-nachos.jpg",
  published_at: "2026-10-05T12:49:00+05:30",
  views_count: 0,
  category: { id: 1, name: "Food", slug: "food" },
  tags: [],
  author: {
    id: 1,
    username: "hyperlocal",
    bio: "Official Hyperlocal profile",
    photo_url: "https://example.com/author.png",
  },
};

describe("admin blog normalization", () => {
  it.each([0, 1, 1452])("preserves the API view count (%s)", (views) => {
    expect(adaptBlogPost({ ...listingPost, views_count: views })?.viewsCount).toBe(views);
  });
  it.each([undefined, null, -1, 1.5, NaN, "unknown"])("does not invent a count for invalid views (%s)", (views) => {
    expect(adaptBlogPost({ ...listingPost, views_count: views })?.viewsCount).toBeNull();
  });
  it.each([true, false, undefined])("preserves the editor-pick flag without choosing articles by position (%s)", (flag) => {
    expect(adaptBlogPost({ ...listingPost, is_editors_pick: flag })?.isEditorsPick).toBe(flag === true);
  });
  it("maps listing fields without manufacturing content or reading time", () => {
    expect(adaptBlogPost(listingPost)).toMatchObject({
      id: "2",
      title: "Food Blog",
      slug: "food-blog",
      excerpt: "This is a food related blog",
      image: listingPost.image,
      imageAlt: "Food Blog",
      publishedAt: listingPost.published_at,
      author: "hyperlocal",
      authorBio: "Official Hyperlocal profile",
      authorPhoto: "https://example.com/author.png",
      category: { id: "1", name: "Food", slug: "food" },
      content: "",
      readTime: null,
      tags: [],
    });
  });

  it("normalizes complete categories and removes null SEO keywords", () => {
    expect(
      adaptBlogCategory({
        id: 1,
        name: "Food",
        slug: "food",
        description: "Food related blogs",
        image: "https://example.com/pasta.jpg",
        blogs_count: 3,
        seo: {
          title: "Food",
          description: "Food related blogs",
          keywords: [null, " recipes ", "", "recipes"],
        },
      }),
    ).toEqual({
      id: "1",
      name: "Food",
      slug: "food",
      description: "Food related blogs",
      image: "https://example.com/pasta.jpg",
      blogsCount: 3,
      seo: {
        title: "Food",
        description: "Food related blogs",
        keywords: ["recipes"],
      },
    });
  });

  it("only derives missing reading time from the full article body", () => {
    const post = adaptBlogPost({
      ...listingPost,
      short_description: "excerpt ".repeat(900),
      content: `<p>${"word ".repeat(181)}</p>`,
    });
    expect(post?.readTime).toBe(2);
    expect(adaptBlogPost({ ...listingPost, read_time: 7 })?.readTime).toBe(7);
    expect(
      adaptBlogPost({ ...listingPost, read_time: 0 })?.readTime,
    ).toBeNull();
    expect(
      adaptBlogPost({
        ...listingPost,
        content: "<p></p><img src='/photo.png'>",
      })?.readTime,
    ).toBeNull();
  });

  it("does not count scripts or HTML attributes as article words", () => {
    const post = adaptBlogPost({
      ...listingPost,
      content: `<script>${"unsafe ".repeat(2000)}</script><p>Fresh food.</p>`,
    });
    expect(post?.readTime).toBe(1);
  });

  it("supports string and object tags and optional author/category data", () => {
    const post = adaptBlogPost({
      id: "new",
      slug: "new-blog",
      title: "New blog",
      tags: ["vegan", { name: " Recipes " }, { slug: "food" }, "vegan", null],
      published_at: "invalid date",
      seo: { keywords: "vegan, recipes" },
    });
    expect(post).toMatchObject({
      tags: ["vegan", "Recipes", "food"],
      author: "",
      authorPhoto: null,
      category: { id: "", name: "", slug: "" },
      publishedAt: null,
      seo: { title: "", description: "", keywords: ["vegan", "recipes"] },
    });
  });

  it("keeps linked summaries as summaries with safe fields and no fake variants", () => {
    const post = adaptBlogPost({
      ...listingPost,
      linked_products: [
        {
          id: 11,
          title: "Almond Milk",
          slug: "almond-milk",
          short_description: "<p>Fresh &amp; creamy</p>",
          main_image: "javascript:alert(1)",
          category_name: "Food",
          brand_name: "Local",
          price: "40",
          special_price: "32",
          is_available: true,
          is_deliverable: null,
        },
        { id: 12, title: "Missing slug" },
        null,
      ],
    });
    expect(post?.linkedProducts).toEqual([
      {
        id: "11",
        title: "Almond Milk",
        slug: "almond-milk",
        short_description: "Fresh & creamy",
        main_image: null,
        category_name: "Food",
        brand_name: "Local",
        price: 40,
        special_price: 32,
        is_available: true,
        is_deliverable: null,
      },
    ]);
    expect(post?.products).toBeUndefined();
    expect(post?.linkedProducts?.[0]).not.toHaveProperty("variants");
  });

  it("preserves an explicitly empty linked-products array", () => {
    expect(
      adaptBlogPost({ ...listingPost, linked_products: [] })?.linkedProducts,
    ).toEqual([]);
    expect(adaptBlogPost(listingPost)?.linkedProducts).toBeUndefined();
  });

  it("keeps normalized optional metadata safe for Next.js page props", () => {
    const post = adaptBlogPost(listingPost);
    const category = adaptBlogCategory(listingPost.category);
    expect(JSON.parse(JSON.stringify(post))).toEqual(post);
    expect(JSON.parse(JSON.stringify(category))).toEqual(category);
  });

  it("does not turn malformed prices and delivery flags into real product data", () => {
    const post = adaptBlogPost({
      ...listingPost,
      linked_products: [
        {
          id: "bad-price",
          title: "Product",
          slug: "product",
          price: "",
          special_price: -2,
          is_available: "false",
          is_deliverable: "true",
        },
      ],
    });
    expect(post?.linkedProducts?.[0]).toMatchObject({
      price: null,
      special_price: null,
      is_available: false,
      is_deliverable: null,
    });
  });

  it.each([
    null,
    [],
    {},
    { id: 1, title: "Blog" },
    { id: 1, title: "", slug: "blog" },
    { id: NaN, title: "Blog", slug: "blog" },
  ])(
    "rejects a malformed post instead of creating an invalid card: %j",
    (raw) => {
      expect(adaptBlogPost(raw)).toBeNull();
    },
  );
});
