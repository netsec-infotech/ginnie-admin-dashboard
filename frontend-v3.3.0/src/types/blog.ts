import type { ApiResponse, Product } from "@/types/ApiResponse";

export interface BlogSeo {
  title: string;
  description: string;
  keywords: string[];
}

type ApiBlogSeo = Partial<Omit<BlogSeo, "keywords">> & {
  keywords?: (string | null)[] | string | null;
};

export interface ApiBlogCategory {
  id: string | number;
  name: string;
  slug: string;
  description?: string | null;
  image?: string | null;
  blogs_count?: number;
  seo?: ApiBlogSeo | null;
}

export interface ApiBlogAuthor {
  id: string | number;
  username: string;
  bio?: string | null;
  photo_url?: string | null;
}

/** Linked products are summaries, not catalog products with purchasable variants. */
export interface BlogLinkedProduct {
  id: string | number;
  title: string;
  slug: string;
  short_description: string;
  main_image: string | null;
  category_name: string;
  brand_name: string;
  price: number | null;
  special_price: number | null;
  is_available: boolean;
  is_deliverable: boolean | null;
}

export interface ApiBlogPost {
  id: string | number;
  title: string;
  slug: string;
  short_description?: string | null;
  content?: string | null;
  image?: string | null;
  image_alt?: string | null;
  published_at?: string | null;
  updated_at?: string | null;
  read_time?: number | null;
  views_count?: number;
  is_editors_pick?: boolean;
  category?: ApiBlogCategory | null;
  tags?:
    | (string | { id?: string | number; name?: string; slug?: string })[]
    | null;
  author?: ApiBlogAuthor | null;
  seo?: ApiBlogSeo | null;
  linked_products?: BlogLinkedProduct[] | null;
  related_blogs?: ApiBlogPost[] | null;
}

export interface BlogPagination {
  current_page: number;
  editors_pick?: ApiBlogPost[];
  data: ApiBlogPost[];
  total: number;
  last_page: number;
  per_page: number;
  from?: number | null;
  to?: number | null;
}

export type BlogListResponse = ApiResponse<BlogPagination>;
export type BlogDetailResponse = ApiResponse<ApiBlogPost> & {
  notFound?: boolean;
};

export interface BlogProductPreview {
  id: string;
  title: string;
  unit: string;
  image: string;
  href: string;
}

export interface BlogCategory {
  id: string;
  slug: string;
  name: string;
  description?: string;
  image?: string | null;
  blogsCount?: number;
  seo?: BlogSeo;
}

export interface BlogPost {
  id: string;
  slug: string;
  title: string;
  isEditorsPick?: boolean;
  excerpt: string;
  content: string;
  image: string | null;
  imageAlt: string;
  category: BlogCategory;
  publishedAt: string | null;
  updatedAt?: string | null;
  author: string;
  readTime?: number | null;
  viewsCount?: number | null;
  tags: string[];
  authorPhoto?: string | null;
  authorBio?: string;
  seo?: BlogSeo;
  linkedProducts?: BlogLinkedProduct[];
  products?: Product[];
  productPreviews?: BlogProductPreview[];
}
