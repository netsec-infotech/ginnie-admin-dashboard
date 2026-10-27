import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Store } from "@/types/ApiResponse";

const mocks = vi.hoisted(() => ({
  getSpecificStore: vi.fn(),
  getStores: vi.fn(),
}));

vi.mock("@/routes/api", () => mocks);

import { getStoreWithListingFallback } from "@/services/storeDetailService";

const store = { slug: "grover-departmental-store", name: "Grover Departmental Store" } as Store;

beforeEach(() => {
  mocks.getSpecificStore.mockReset();
  mocks.getStores.mockReset();
});

describe("store detail fallback", () => {
  it("uses a working detail response without scanning the listing", async () => {
    mocks.getSpecificStore.mockResolvedValue({ success: true, data: store });
    expect(await getStoreWithListingFallback(store.slug)).toBe(store);
    expect(mocks.getStores).not.toHaveBeenCalled();
  });

  it("finds a listed store when its detail endpoint fails", async () => {
    mocks.getSpecificStore.mockResolvedValue({ success: false, data: null });
    mocks.getStores.mockResolvedValue({
      success: true,
      data: { data: [store], total: 1, per_page: 100 },
    });
    expect(await getStoreWithListingFallback(store.slug, {
      latitude: 23.2,
      longitude: 69.6,
    })).toBe(store);
    expect(mocks.getStores).toHaveBeenCalledWith({
      latitude: 23.2,
      longitude: 69.6,
      page: 1,
      per_page: 100,
    });
  });

  it("does not treat a failed listing as a store", async () => {
    mocks.getSpecificStore.mockResolvedValue({ success: false, data: null });
    mocks.getStores.mockResolvedValue({ success: false, data: null });
    expect(await getStoreWithListingFallback(store.slug, {
      latitude: 23.2,
      longitude: 69.6,
    })).toBeNull();
  });
});
