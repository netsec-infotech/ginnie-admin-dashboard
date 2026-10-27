import axios from "axios";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { getBlogBySlug, getBlogCategories, getBlogs, getBlogEditorsPick } from "@/routes/api";

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock("axios", async (importOriginal) => {
  const actual = await importOriginal<typeof import("axios")>();
  return {
    ...actual,
    default: {
      ...actual.default,
      create: () => ({
        get,
        interceptors: {
          request: { use: vi.fn() },
          response: { use: vi.fn() },
        },
      }),
    },
  };
});

beforeEach(() => {
  get.mockReset();
  vi.spyOn(console, "warn").mockImplementation(() => {});
});
afterEach(() => vi.restoreAllMocks());

describe("blog API endpoints", () => {
  it("fetches the page-one editors_pick collection independently of article pagination", async () => {
    const response = { success: true, data: { editors_pick: [{ id: 7 }], data: [] } };
    get.mockResolvedValue({ data: response });
    expect(await getBlogEditorsPick()).toBe(response);
    expect(get).toHaveBeenCalledWith("/blogs", {
      params: { page: 1, per_page: 6 },
      headers: { Accept: "application/json" },
    });
  });
  it("fetches independent category data and passes authentication to the interceptor", async () => {
    const response = { success: true, data: [{ id: 1, slug: "food" }] };
    get.mockResolvedValue({ data: response });
    expect(await getBlogCategories({ access_token: "test-token" })).toBe(
      response,
    );
    expect(get).toHaveBeenCalledWith("/blog-categories", {
      params: { access_token: "test-token" },
      headers: { Accept: "application/json" },
    });
  });

  it("passes server pagination and supported filters without changing the response", async () => {
    const response = {
      success: true,
      data: {
        current_page: 2,
        data: [],
        total: 15,
        last_page: 2,
        per_page: 12,
      },
    };
    const params = {
      page: 2,
      per_page: 12,
      search: "recipes",
      category_id: 1,
      category_slug: "food",
      tag: "vegan",
    };
    get.mockResolvedValue({ data: response });
    expect(await getBlogs(params)).toBe(response);
    expect(get).toHaveBeenCalledWith("/blogs", {
      params,
      headers: { Accept: "application/json" },
    });
  });

  it("uses a single encoded slug and keeps detail data in the backend's direct shape", async () => {
    const response = {
      success: true,
      data: { id: 2, slug: "food-blog", content: "<p>Full blog.</p>" },
    };
    get.mockResolvedValue({ data: response });
    expect(
      await getBlogBySlug({
        slug: "food/blog?draft",
        latitude: "1",
        longitude: "2",
        access_token: "test-token",
      }),
    ).toBe(response);
    expect(get).toHaveBeenCalledWith("/blogs/food%2Fblog%3Fdraft", {
      params: { latitude: "1", longitude: "2", access_token: "test-token" },
      headers: { Accept: "application/json" },
    });
  });

  it.each([404, 401, 429, 500, undefined])(
    "classifies detail failures without throwing for HTTP %s",
    async (status) => {
      const error = new axios.AxiosError("Request failed");
      if (status) {
        error.response = {
          status,
          statusText: "Error",
          data: { content: "private response body" },
          headers: {},
          config: { headers: new axios.AxiosHeaders() },
        };
      }
      get.mockRejectedValue(error);
      expect(await getBlogBySlug({ slug: "food-blog" })).toMatchObject({
        success: false,
        data: undefined,
        notFound: status === 404,
        retryable: status === undefined || status === 429 || status === 500,
      });
      expect(JSON.stringify(vi.mocked(console.warn).mock.calls)).not.toContain(
        "private response body",
      );
    },
  );

  it("provides stable empty listing and category fallbacks on network failure", async () => {
    get.mockRejectedValue(new axios.AxiosError("Offline"));
    expect(await getBlogs({ page: 2 })).toMatchObject({
      success: false,
      retryable: true,
      data: { current_page: 0, data: [], total: 0, last_page: 1, per_page: 0 },
    });
    expect(await getBlogCategories()).toMatchObject({
      success: false,
      retryable: true,
      data: [],
    });
  });
});
