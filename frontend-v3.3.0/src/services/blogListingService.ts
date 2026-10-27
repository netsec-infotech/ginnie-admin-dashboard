import { getBlogs } from "@/routes/api";
import type { BlogListResponse } from "@/types/blog";

export const POSTS_PER_PAGE = 6;

export async function getBlogListingPage(
  params: Parameters<typeof getBlogs>[0] = {},
): Promise<BlogListResponse> {
  const page = Number(params.page) || 1;
  const query = { ...params, per_page: POSTS_PER_PAGE };
  const filtered = Boolean(params.search?.trim() || params.category_slug || params.category_id || params.tag);
  if (filtered) return getBlogs({ ...query, page });

  // The API excludes editor picks from ordinary pages. Merge them into the
  // published-date order before slicing, rather than pinning them to page one.
  const first = await getBlogs({ ...query, page: 1 });
  if (!first.success || !first.data) return first;
  const picks = first.data.editors_pick ?? [];
  const separated = picks.length > 0 && first.data.per_page === POSTS_PER_PAGE &&
    !picks.every((pick) => first.data!.data.some((post) => post.slug === pick.slug));
  const backendPage = separated ? Math.min(page, first.data.last_page) : page;
  const response = backendPage === 1 ? first : await getBlogs({ ...query, page: backendPage });
  if (!response.success || !response.data) return response;
  if (!separated) {
    return response;
  }
  const prefix = [...first.data.data];
  for (let index = 2; index <= Math.min(page, first.data.last_page); index++) {
    const chunk = index === backendPage ? response : await getBlogs({ ...query, page: index });
    if (!chunk.success || !chunk.data) return chunk;
    prefix.push(...chunk.data.data);
  }
  const ordinarySlugs = new Set(prefix.map((post) => post.slug));
  const total = first.data.total + picks.filter((pick) => !ordinarySlugs.has(pick.slug)).length;
  const merged = Array.from(new Map([...prefix, ...picks].map((post) => [post.slug, post])).values());
  merged.sort((a, b) => (Date.parse(b.published_at || "") || 0) - (Date.parse(a.published_at || "") || 0));
  const offset = (page - 1) * POSTS_PER_PAGE;
  const data = merged.slice(offset, offset + POSTS_PER_PAGE);
  return { ...response, data: {
    ...response.data, current_page: page, data, total,
    last_page: Math.max(1, Math.ceil(total / POSTS_PER_PAGE)),
    from: data.length ? offset + 1 : null,
    to: data.length ? offset + data.length : null,
  } };
}
