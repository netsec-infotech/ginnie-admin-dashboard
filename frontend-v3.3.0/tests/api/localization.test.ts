import { beforeEach, expect, it, vi } from "vitest";
import { getWebLanguageLabels } from "@/routes/api";

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
});

it("requests the new dictionary's locale before the language preference changes", async () => {
  const response = { success: true, data: { locale: "ar" } };
  get.mockResolvedValue({ data: response });
  const controller = new AbortController();

  expect(await getWebLanguageLabels("ar", controller.signal)).toBe(response);
  expect(get).toHaveBeenCalledWith("/language-labels", {
    params: { surface: "web", locale: "ar" },
    headers: { Accept: "application/json", "X-Locale": "ar" },
    signal: controller.signal,
    timeout: 5000,
  });
});
