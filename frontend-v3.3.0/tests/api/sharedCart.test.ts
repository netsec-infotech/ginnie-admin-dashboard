import axios from "axios";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { getSharedCart, importSharedCart, shareCart } from "@/routes/api";

const { post, get } = vi.hoisted(() => ({ post: vi.fn(), get: vi.fn() }));
vi.mock("axios", async (importOriginal) => {
  const actual = await importOriginal<typeof import("axios")>();
  return { ...actual, default: { ...actual.default, create: () => ({
    post, get,
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  }) } };
});
beforeEach(() => {
  post.mockReset();
  get.mockReset();
  vi.spyOn(console, "warn").mockImplementation(() => {});
});

it("reads a public snapshot with location and an encoded share code", async () => {
  const response = { success: true, data: { title: "Groceries", items: [] } };
  get.mockResolvedValue({ data: response });
  expect(await getSharedCart("a/b", { latitude: 23, longitude: 69 })).toBe(response);
  expect(get).toHaveBeenCalledWith("/user/cart/shared/a%2Fb", { params: { latitude: 23, longitude: 69 } });
});
afterEach(() => vi.restoreAllMocks());

it("creates a share with a title and returns the link metadata", async () => {
  const response = { success: true, data: { share_code: "abc", share_url: "https://shop.test/cart/shared/abc", title: "Groceries", items_count: 2, expires_at: "2026-10-15T11:47:16+05:30" } };
  post.mockResolvedValue({ data: response });
  expect(await shareCart({ title: "Groceries" })).toBe(response);
  expect(post).toHaveBeenCalledExactlyOnceWith("/user/cart/share", { title: "Groceries" });
});

it.each(["merge", "replace"] as const)("imports with %s and retains the complete import summary", async (action) => {
  const response = { success: true, data: { action, cart: { items: [] }, import_summary: { imported_count: 1, updated_count: 2, unchanged_count: 3, skipped_count: 4 } } };
  post.mockResolvedValue({ data: response });
  expect(await importSharedCart("a/b", action)).toBe(response);
  expect(post).toHaveBeenCalledExactlyOnceWith("/user/cart/shared/a%2Fb/import", { action });
});

it("keeps merge as the default for existing callers", async () => {
  post.mockResolvedValue({ data: { success: true } });
  await importSharedCart("abc");
  expect(post).toHaveBeenCalledExactlyOnceWith("/user/cart/shared/abc/import", { action: "merge" });
});

it.each([401, 404, 410, 422, 500])("returns a failure and status for HTTP %s without throwing", async (status) => {
  const error = new axios.AxiosError("Request failed");
  error.response = { status, statusText: "Error", data: null, headers: {}, config: { headers: new axios.AxiosHeaders() } };
  post.mockRejectedValue(error);
  expect(await importSharedCart("abc")).toMatchObject({ success: false, status });
  expect(await shareCart({ title: "Groceries" })).toMatchObject({ success: false });
});
