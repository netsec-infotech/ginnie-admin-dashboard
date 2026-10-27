import type { TFunction } from "i18next";
import type { BlogPost } from "@/types/blog";

// Local sample stories support the design preview; they are not admin content.
const productPreviewDefinitions = {
  market: "/images/new-at/vertical/farm-grocery-box.webp",
  pasta: "/images/new-at/vertical/artisan-pasta-meal.webp",
  bakery: "/images/new-at/vertical/bakery-breakfast-box.webp",
  flowers: "/images/new-at/vertical/fresh-flower-bouquet.webp",
  coffee: "/images/new-at/vertical/coffee-snack-pairing.webp",
  wellness: "/images/new-at/vertical/daily-wellness-set.webp",
  homecare: "/images/new-at/vertical/home-cleaning-set.webp",
} as const;

const storyDefinitions = [
  {
    id: "produce",
    slug: "fresh-produce-guide",
    category: "fresh-food",
    image: "/images/stories/seller-fresh-produce.webp",
    previews: ["market", "pasta", "bakery"],
  },
  {
    id: "meals",
    slug: "easy-weeknight-meals",
    category: "fresh-food",
    image: "/images/new-at/vertical/artisan-pasta-meal.webp",
    previews: ["pasta", "market"],
  },
  {
    id: "shopping",
    slug: "thoughtful-local-shopping",
    category: "local-stories",
    image: "/images/stories/seller-bakery.webp",
    previews: ["market", "bakery", "coffee"],
  },
  {
    id: "flowers",
    slug: "small-space-fresh-flowers",
    category: "everyday-living",
    image: "/images/stories/seller-florist.webp",
    previews: ["flowers", "homecare"],
  },
  {
    id: "coffee",
    slug: "morning-coffee-ritual",
    category: "local-stories",
    image: "/images/stories/seller-cafe.webp",
    previews: ["coffee", "bakery"],
  },
  {
    id: "wellness",
    slug: "everyday-wellness-routine",
    category: "everyday-living",
    image: "/images/stories/seller-wellness.webp",
    previews: ["wellness", "homecare"],
  },
] as const;

export const blogPostSlugs: string[] = storyDefinitions.map(({ slug }) => slug);

const escapeHtml = (value: string): string =>
  value.replace(/[&<>"']/g, (character) => {
    const entities: Record<string, string> = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    };
    return entities[character];
  });

export const getBlogPosts = (t: TFunction): BlogPost[] =>
  storyDefinitions.map((story) => {
    const prefix = `blog.posts.${story.id}`;
    const sections = [1, 2, 3].map((section) => ({
      title: t(`${prefix}.section${section}.title`),
      body: t(`${prefix}.section${section}.body`),
    }));
    const content = sections
      .map(
        (section, index) =>
          `<h2 id="section-${index + 1}">${escapeHtml(section.title)}</h2><p>${escapeHtml(section.body)}</p>`,
      )
      .join("");
    const wordCount = sections
      .map(({ body }) => body)
      .join(" ")
      .trim()
      .split(/\s+/).length;

    return {
      id: story.id,
      slug: story.slug,
      title: t(`${prefix}.title`),
      excerpt: t(`${prefix}.excerpt`),
      content,
      image: story.image,
      imageAlt: t(`${prefix}.imageAlt`),
      category: {
        id: story.category,
        slug: story.category,
        name: t(`blog.categories.${story.category}`),
      },
      publishedAt: null,
      author: t("blog.editorialTeam"),
      readTime: Math.max(1, Math.ceil(wordCount / 180)),
      tags: [t(`${prefix}.tag1`), t(`${prefix}.tag2`)],
      productPreviews: story.previews.map((id) => ({
        id,
        title: t(`blog.productPreviews.${id}.title`),
        unit: t(`blog.productPreviews.${id}.unit`),
        image: productPreviewDefinitions[id],
        href: `/products/search/?q=${encodeURIComponent(t(`blog.productPreviews.${id}.query`))}`,
      })),
    };
  });
