// @vitest-environment node
import { afterEach, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ isSSR: vi.fn(), getSharedCart: vi.fn() }));
vi.mock("@/routes/api", () => ({ getSharedCart: mocks.getSharedCart }));
vi.mock("@/helpers/getters", () => ({ isSSR: mocks.isSSR }));
vi.mock("@/services/staticGenerationService", () => ({ getStaticGenerationContext: async () => ({ settings: [] }) }));
vi.mock("@/views/SharedCartPageView", () => ({ default: () => null }));
import { getStaticPaths, getStaticProps } from "@/pages/cart/shared/[shareCode]";

afterEach(() => vi.unstubAllEnvs());

it("exports a generic shell without embedding any customer share codes", async () => {
  vi.stubEnv("NODE_ENV", "production");
  mocks.isSSR.mockReturnValue(false);
  expect(await getStaticPaths({})).toEqual({ paths: [{ params: { shareCode: "__shared-cart__" } }], fallback: false });
  expect(await getStaticProps({})).toEqual({ props: { initialSettings: [], initialShareCode: null, initialSharedCart: null } });
  expect(mocks.getSharedCart).not.toHaveBeenCalled();
});

it("seeds server-rendered previews with the runtime shared title", async () => {
  mocks.isSSR.mockReturnValue(true);
  const data = { share_code: "abc", title: "Weekly groceries", is_expired: false, expires_at: "2030-10-15" };
  mocks.getSharedCart.mockResolvedValue({ success: true, data });
  expect(await getStaticProps({ params: { shareCode: "abc" } })).toEqual({ props: { initialSettings: [], initialShareCode: "abc", initialSharedCart: data }, revalidate: 30 });
});

it("does not publish expired snapshot metadata even if the API expiry flag is stale", async () => {
  mocks.isSSR.mockReturnValue(true);
  mocks.getSharedCart.mockResolvedValue({ success: true, data: { share_code: "abc", title: "Old cart", is_expired: false, expires_at: "2020-01-01" } });
  expect(await getStaticProps({ params: { shareCode: "abc" } })).toMatchObject({ props: { initialShareCode: "abc", initialSharedCart: null } });
});

it.each([true, false])("supports runtime share codes in development when SSR=%s", async (ssr) => {
  vi.stubEnv("NODE_ENV", "development");
  mocks.isSSR.mockReturnValue(ssr);
  expect(await getStaticPaths({})).toMatchObject({ fallback: "blocking" });
});

it("supports runtime share codes on an SSR deployment", async () => {
  vi.stubEnv("NODE_ENV", "production");
  mocks.isSSR.mockReturnValue(true);
  expect(await getStaticPaths({})).toEqual({ paths: [], fallback: "blocking" });
});
