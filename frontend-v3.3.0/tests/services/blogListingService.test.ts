import { beforeEach, expect, it, vi } from "vitest";
import type { ApiBlogPost } from "@/types/blog";
const api = vi.hoisted(() => ({ getBlogs: vi.fn() }));
vi.mock("@/routes/api", () => api);
import { getBlogListingPage } from "@/services/blogListingService";

const articles: ApiBlogPost[] = Array.from({ length: 18 }, (_, index) => ({
  id: index + 1,
  slug: `article-${index + 1}`,
  title: `Article ${index + 1}`,
  published_at: new Date(Date.UTC(2026, 9, 18 - index)).toISOString(),
}));
const picks = [articles[1], articles[12], articles[17]];
const ordinary = articles.filter((post) => !picks.includes(post));

beforeEach(() => {
  api.getBlogs.mockReset().mockImplementation(async ({ page = 1 }) => ({
    success: true,
    message: "",
    data: {
      current_page: page, per_page: 6, total: ordinary.length, last_page: 3,
      data: ordinary.slice((page - 1) * 6, page * 6),
      editors_pick: page === 1 ? picks : [],
    },
  }));
});

it("places featured articles in publication order before paginating six cards per page", async () => {
  const seen: string[] = [];
  for (const page of [1, 2, 3]) {
    const result = await getBlogListingPage({ page });
    expect(result.data?.data.map((post) => post.slug)).toEqual(articles.slice((page - 1) * 6, page * 6).map((post) => post.slug));
    expect(result.data).toMatchObject({ current_page: page, total: 18, last_page: 3, per_page: 6, from: (page - 1) * 6 + 1, to: page * 6 });
    seen.push(...result.data!.data.map((post) => post.slug));
  }
  expect(new Set(seen).size).toBe(18);
  expect(seen).toHaveLength(18);
});

it("does not inject unrelated editor picks into filtered results", async () => {
  const result = await getBlogListingPage({ page: 2, search: "rice" });
  expect(api.getBlogs).toHaveBeenCalledExactlyOnceWith({ page: 2, per_page: 6, search: "rice" });
  expect(result.data?.data).toEqual(ordinary.slice(6, 12));
});

it("supports a combined last page beyond the backend's ordinary-page count", async () => {
  api.getBlogs.mockImplementation(async ({ page = 1 }) => ({
    success: true, message: "", data: {
      current_page: page, per_page: 6, total: 12, last_page: 2,
      data: ordinary.slice(0, 12).slice((page - 1) * 6, page * 6),
      editors_pick: page === 1 ? picks : [],
    },
  }));
  const result = await getBlogListingPage({ page: 3 });
  expect(result.data).toMatchObject({ current_page: 3, total: 15, last_page: 3, from: 13, to: 15 });
  expect(result.data?.data).toHaveLength(3);
  expect(api.getBlogs).not.toHaveBeenCalledWith(expect.objectContaining({ page: 3 }));
});

it("keeps a failed prefix request from producing a partial or misleading page", async () => {
  const original = api.getBlogs.getMockImplementation()!;
  api.getBlogs.mockImplementation(async (params) => params.page === 2 ? { success: false, data: null } : original(params));
  expect((await getBlogListingPage({ page: 3 })).success).toBe(false);
});
