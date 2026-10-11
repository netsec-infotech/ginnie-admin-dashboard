import { describe, it, expect, beforeEach, vi } from "vitest";

// Mock the axios singleton the api layer builds. vi.hoisted lets the mock
// factory (hoisted above imports) share the spy we assert against.
const { mockGet } = vi.hoisted(() => ({ mockGet: vi.fn() }));

vi.mock("axios", () => ({
  default: {
    create: () => ({
      get: mockGet,
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
      interceptors: {
        request: { use: vi.fn() },
        response: { use: vi.fn() },
      },
    }),
    isAxiosError: vi.fn(),
  },
}));

import { getHomeLayout, getHomeLayoutSection } from "@/routes/api";
import { fallbackApiRes, fallbackPaginateRes } from "@/config/constants";

beforeEach(() => {
  mockGet.mockReset();
});

describe("getHomeLayout", () => {
  it("returns the layout data on success and defaults platform to 'web'", async () => {
    const data = {
      scope_type: "global",
      scope_id: null,
      current_page: 1,
      last_page: 1,
      per_page: 5,
      total: 1,
      sections: [{ id: 35, type: "products", title: "New product" }],
    };
    mockGet.mockResolvedValue({ data: { success: true, message: "ok", data } });

    const res = await getHomeLayout({ latitude: 23.2, longitude: 69.6 });

    expect(res.success).toBe(true);
    expect(res.data?.sections).toHaveLength(1);
    const [url, config] = mockGet.mock.calls[0];
    expect(url).toBe("/home-layout");
    expect(config.params).toMatchObject({
      platform: "web",
      latitude: 23.2,
      longitude: 69.6,
    });
  });

  it("lets an explicit platform override the default", async () => {
    mockGet.mockResolvedValue({ data: { success: true, data: null } });
    await getHomeLayout({ platform: "app" });
    expect(mockGet.mock.calls[0][1].params.platform).toBe("app");
  });

  it("returns the typed fallback (never throws) on network error", async () => {
    mockGet.mockRejectedValue(new Error("network down"));
    const res = await getHomeLayout();
    expect(res).toEqual(fallbackApiRes);
    expect(res.success).toBe(false);
  });
});

describe("getHomeLayoutSection", () => {
  it("hits the id-based endpoint and returns the paginated feed", async () => {
    const data = {
      current_page: 1,
      last_page: 2,
      per_page: 24,
      total: 3,
      data: [{ id: 820, title: "bottle of lime" }],
    };
    mockGet.mockResolvedValue({ data: { success: true, message: "ok", data } });

    const res = await getHomeLayoutSection(35, { page: 1, per_page: 24 });

    expect(res.success).toBe(true);
    expect(res.data?.data).toHaveLength(1);
    expect(res.data?.total).toBe(3);
    expect(mockGet.mock.calls[0][0]).toBe("/home-layout/sections/35");
    expect(mockGet.mock.calls[0][1].params).toMatchObject({ page: 1, per_page: 24 });
  });

  it("returns the paginated fallback on error", async () => {
    mockGet.mockRejectedValue(new Error("boom"));
    const res = await getHomeLayoutSection(35);
    expect(res).toEqual(fallbackPaginateRes);
    expect(res.success).toBe(false);
  });
});
