import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { notifyStoreOpening } from "@/routes/api";

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock("axios", async (importOriginal) => {
  const actual = await importOriginal<typeof import("axios")>();
  return {
    ...actual,
    default: {
      ...actual.default,
      create: () => ({
        post,
        interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
      }),
    },
  };
});
beforeEach(() => {
  post.mockReset();
  vi.spyOn(console, "warn").mockImplementation(() => {});
});
afterEach(() => vi.restoreAllMocks());

it.each([true, false])(
  "posts is_notify=%s to the selected store",
  async (isNotify) => {
    const response = { success: true, data: { is_notify: isNotify } };
    post.mockResolvedValue({ data: response });
    expect(await notifyStoreOpening(42, isNotify)).toBe(response);
    expect(post).toHaveBeenCalledWith("/stores/42/notify-me", {
      is_notify: isNotify,
    });
  },
);

it("returns a failed response when the network request fails", async () => {
  post.mockRejectedValue(new Error("Network unavailable"));
  expect(await notifyStoreOpening(42, true)).toMatchObject({ success: false });
});
