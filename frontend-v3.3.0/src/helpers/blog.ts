import { sanitizeRichText } from "@/helpers/sanitizeRichText";
import type {
  BlogCategory,
  BlogLinkedProduct,
  BlogPost,
  BlogSeo,
} from "@/types/blog";

export interface BlogHeading {
  id: string;
  title: string;
  level: number;
}

// The editor's HTML is sanitized before it becomes article markup. Generate
// unique anchors independently of any IDs supplied by the content source.
export function prepareBlogContent(content: string): {
  html: string;
  headings: BlogHeading[];
} {
  const headings: BlogHeading[] = [];
  const html = sanitizeRichText(content, {
    allowRasterDataImages: true,
  }).replace(
    /<h([23])([^>]*)>([\s\S]*?)<\/h\1>/gi,
    (match, level: string, attributes: string, inner: string) => {
      const title = blogPlainText(inner);
      if (!title) return match;
      const id = `article-section-${headings.length + 1}`;
      headings.push({ id, title, level: Number(level) });
      return `<h${level}${attributes} id="${id}">${inner}</h${level}>`;
    },
  );
  return { html, headings };
}

export function blogPlainText(html: string): string {
  const entities: Record<string, string> = {
    amp: "&",
    lt: "<",
    gt: ">",
    quot: '"',
    apos: "'",
    nbsp: " ",
    ndash: "–",
    mdash: "—",
    lsquo: "‘",
    rsquo: "’",
    ldquo: "“",
    rdquo: "”",
  };
  return html
    .replace(/<[^>]*>/g, " ")
    .replace(/&(#x[\da-f]+|#\d+|[a-z]+);/gi, (entity, value: string) => {
      if (!value.startsWith("#"))
        return entities[value.toLowerCase()] ?? entity;
      const codePoint =
        value.slice(0, 2).toLowerCase() === "#x"
          ? parseInt(value.slice(2), 16)
          : parseInt(value.slice(1), 10);
      return codePoint > 0 && codePoint <= 0x10ffff
        ? String.fromCodePoint(codePoint)
        : "";
    })
    .replace(/\s+/g, " ")
    .trim();
}

const asRecord = (value: unknown): Record<string, unknown> | null =>
  value !== null && typeof value === "object" && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null;

const asText = (value: unknown): string =>
  typeof value === "string" ? value.trim() : "";

const asId = (value: unknown): string =>
  typeof value === "number" && Number.isFinite(value) && value >= 0
    ? String(value)
    : asText(value);

const asImage = (value: unknown): string | null => {
  const image = asText(value);
  return /^(?:https?:\/\/|\/(?!\/))/i.test(image) ? image : null;
};

const asAmount = (value: unknown): number | null => {
  if (typeof value !== "number" && typeof value !== "string") return null;
  if (typeof value === "string" && !value.trim()) return null;
  const amount = Number(value);
  return Number.isFinite(amount) && amount >= 0 ? amount : null;
};

const adaptBlogSeo = (value: unknown): BlogSeo | undefined => {
  const seo = asRecord(value);
  if (!seo) return undefined;
  const keywords = Array.isArray(seo.keywords)
    ? seo.keywords
    : typeof seo.keywords === "string"
      ? seo.keywords.split(",")
      : [];
  return {
    title: blogPlainText(asText(seo.title)),
    description: blogPlainText(asText(seo.description)),
    keywords: Array.from(new Set(keywords.map(asText).filter(Boolean))),
  };
};

export function adaptBlogCategory(value: unknown): BlogCategory | null {
  const category = asRecord(value);
  if (!category) return null;
  const id = asId(category.id);
  const name = asText(category.name);
  const slug = asText(category.slug);
  if (!id || !name || !slug) return null;
  const seo = adaptBlogSeo(category.seo);
  const blogsCount =
    typeof category.blogs_count === "number" &&
    Number.isFinite(category.blogs_count) &&
    category.blogs_count >= 0
      ? Math.floor(category.blogs_count)
      : undefined;
  return {
    id,
    name,
    slug,
    description: asText(category.description),
    image: asImage(category.image),
    ...(blogsCount !== undefined ? { blogsCount } : {}),
    ...(seo ? { seo } : {}),
  };
}

function adaptBlogLinkedProduct(value: unknown): BlogLinkedProduct | null {
  const product = asRecord(value);
  if (!product) return null;
  const id = asId(product.id);
  const title = asText(product.title);
  const slug = asText(product.slug);
  if (!id || !title || !slug) return null;
  return {
    id,
    title,
    slug,
    short_description: blogPlainText(asText(product.short_description)),
    main_image: asImage(product.main_image),
    category_name: asText(product.category_name),
    brand_name: asText(product.brand_name),
    price: asAmount(product.price),
    special_price: asAmount(product.special_price),
    is_available: product.is_available === true,
    is_deliverable:
      typeof product.is_deliverable === "boolean"
        ? product.is_deliverable
        : null,
  };
}

/** Convert admin snake_case fields without inventing listing metadata. */
export function adaptBlogPost(value: unknown): BlogPost | null {
  const post = asRecord(value);
  if (!post) return null;
  const id = asId(post.id);
  const title = asText(post.title);
  const slug = asText(post.slug);
  if (!id || !title || !slug) return null;

  const content = asText(post.content);
  const author = asRecord(post.author);
  const plainContent = content ? blogPlainText(sanitizeRichText(content)) : "";
  const readTime =
    typeof post.read_time === "number" &&
    Number.isFinite(post.read_time) &&
    post.read_time > 0
      ? Math.ceil(post.read_time)
      : plainContent
        ? Math.max(1, Math.ceil(plainContent.split(/\s+/u).length / 180))
        : null;
  const publishedAt = asText(post.published_at);
  const updatedAt = asText(post.updated_at);
  const linkedProducts = Array.isArray(post.linked_products)
    ? post.linked_products
        .map(adaptBlogLinkedProduct)
        .filter((product): product is BlogLinkedProduct => product !== null)
    : undefined;
  const seo = adaptBlogSeo(post.seo);

  return {
    id,
    title,
    slug,
    excerpt: asText(post.short_description),
    content,
    image: asImage(post.image),
    imageAlt: asText(post.image_alt) || title,
    isEditorsPick: post.is_editors_pick === true,
    category: adaptBlogCategory(post.category) ?? {
      id: "",
      name: "",
      slug: "",
    },
    publishedAt:
      publishedAt && Number.isFinite(Date.parse(publishedAt))
        ? publishedAt
        : null,
    ...(updatedAt && Number.isFinite(Date.parse(updatedAt)) ? { updatedAt } : {}),
    author: asText(author?.username),
    authorPhoto: asImage(author?.photo_url),
    authorBio: asText(author?.bio),
    readTime,
    viewsCount: typeof post.views_count === "number" && Number.isSafeInteger(post.views_count) && post.views_count >= 0
      ? post.views_count : null,
    tags: Array.isArray(post.tags)
      ? Array.from(
          new Set(
            post.tags
              .map((tag) =>
                typeof tag === "string"
                  ? tag.trim()
                  : asText(asRecord(tag)?.name) || asText(asRecord(tag)?.slug),
              )
              .filter(Boolean),
          ),
        )
      : [],
    ...(seo ? { seo } : {}),
    ...(linkedProducts ? { linkedProducts } : {}),
  };
}
