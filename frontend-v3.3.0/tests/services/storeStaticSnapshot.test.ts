// @vitest-environment node
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
const mocks = vi.hoisted(() => ({ getStores: vi.fn(), getSettings: vi.fn() }));
vi.mock("@/routes/api", () => mocks);
vi.mock("@/helpers/getters", () => ({
  getConfiguredDefaultLocation: () => ({ lat: 1, lng: 2 }),
  getWebSettings: () => ({}),
}));
let root: string;
beforeEach(() => {
  vi.resetModules();
  root = mkdtempSync(join(tmpdir(), "hyperlocal-store-snapshot-"));
  mocks.getStores.mockReset();
  mocks.getSettings.mockReset().mockResolvedValue({ success: true, data: [] });
});
afterEach(() => {
  vi.unstubAllEnvs();
  rmSync(root, { recursive: true, force: true });
});

it("uses catalog-listed stores for both exported paths and page metadata", async () => {
  const filename = join(root, "snapshot.json");
  const store = { id: 1, slug: "listed-store", name: "Listed Store" };
  writeFileSync(filename, JSON.stringify({ stores: [store] }));
  vi.stubEnv("NODE_ENV", "production");
  vi.stubEnv("NEXT_PUBLIC_SSR", "false");
  vi.stubEnv("HYPERLOCAL_STATIC_STORE_SNAPSHOT", filename);
  const service = await import("@/services/staticGenerationService");
  expect(await service.getStaticStoreSlugs()).toEqual([store.slug]);
  expect(await service.getStaticStore(store.slug)).toEqual(store);
  expect(await service.getStaticStore("missing")).toBeNull();
  expect(mocks.getStores).not.toHaveBeenCalled();
});

it("preserves live catalog fetching in SSR mode", async () => {
  vi.stubEnv("NODE_ENV", "production");
  vi.stubEnv("NEXT_PUBLIC_SSR", "true");
  mocks.getStores.mockResolvedValue({
    success: true,
    data: {
      data: [{ slug: "live", name: "Live Store" }],
      total: 1,
      per_page: 100,
    },
  });
  const service = await import("@/services/staticGenerationService");
  expect(await service.getStaticStoreSlugs()).toEqual(["live"]);
  expect(mocks.getStores).toHaveBeenCalledWith({
    page: 1,
    per_page: 100,
    latitude: "1",
    longitude: "2",
  });
});

it("fails closed on corrupt snapshots and recognizes a missing file", async () => {
  const service = await import("@/services/staticGenerationService");
  const filename = join(root, "snapshot.json");
  expect(await service.readStaticStoreSnapshot(filename)).toBeNull();
  writeFileSync(filename, JSON.stringify({ stores: {} }));
  await expect(service.readStaticStoreSnapshot(filename)).rejects.toThrow(
    "Invalid store build snapshot",
  );
});
