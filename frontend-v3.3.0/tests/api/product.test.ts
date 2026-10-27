import axios from "axios";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { getProductBySlug } from "@/routes/api";

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock("axios", async (importOriginal) => {
  const actual = await importOriginal<typeof import("axios")>();
  return { ...actual, default: { ...actual.default, create: () => ({
    get,
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  }) } };
});
beforeEach(() => {
  get.mockReset();
  vi.spyOn(console, "warn").mockImplementation(() => {});
});
afterEach(() => vi.restoreAllMocks());

it("looks up a slug directly with the supplied catalog location", async () => {
  const response = { success: true, data: { slug: "new-product" } };
  get.mockResolvedValue({ data: response });
  expect(await getProductBySlug({ slug: "new-product", latitude: "1", longitude: "2" })).toBe(response);
  expect(get).toHaveBeenCalledWith("/products/new-product", { params: { latitude: "1", longitude: "2" } });
});

it.each([404, 429, 500, undefined])("preserves retry policy for HTTP %s", async (status) => {
  const error = new axios.AxiosError("Request failed");
  if (status) error.response = { status, statusText: "Error", data: null, headers: {}, config: { headers: new axios.AxiosHeaders() } };
  get.mockRejectedValue(error);
  expect(await getProductBySlug({ slug: "new-product" })).toMatchObject({
    success: false, retryable: status !== 404,
  });
});
