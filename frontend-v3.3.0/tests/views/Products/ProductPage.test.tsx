import React from "react";
import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
import { SWRConfig } from "swr";
import ProductPage from "@/views/Products/ProductPage";

const mocks = vi.hoisted(() => ({
  router: { isReady: true, asPath: "/products/new-item/?ref=home", query: {}, push: vi.fn() },
  getProduct: vi.fn(),
}));
vi.mock("next/router", () => ({ useRouter: () => mocks.router }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key: string) => key }) }));
vi.mock("react-redux", () => ({ useDispatch: () => vi.fn() }));
vi.mock("@/helpers/getters", () => ({ isSSR: () => false }));
vi.mock("@/helpers/functionalHelpers", () => ({ getFallbackLocation: () => ({ lat: "1", lng: "2" }) }));
vi.mock("@/lib/cookies", () => ({ getCookie: () => undefined }));
vi.mock("@/contexts/SettingsContext", () => ({ useSettings: () => ({ settings: [], currency: "INR" }) }));
vi.mock("@/routes/api", () => ({
  getProductBySlug: mocks.getProduct,
  getProducts: async () => ({ success: true, data: { data: [] } }),
}));
vi.mock("@/components/custom/MyBreadcrumbs", () => ({ default: () => null }));
vi.mock("@/components/NoProductsFound", () => ({ default: () => <div>Product unavailable</div> }));
vi.mock("@heroui/react", () => ({ Button: () => null }));
vi.mock("@/SEO/DynamicSEO", () => ({ default: ({ robots }: { robots?: string }) => <span data-testid="seo">{robots || "index"}</span> }));
vi.mock("@/helpers/seo", () => ({
  generateProductMeta: () => ({ title: "New item" }),
  generateProductSchema: () => ({}),
  generateBreadcrumbSchema: () => ({}),
}));
vi.mock("@/views/Products/ProductDetailPageView", () => ({
  default: ({ isLoading, initialProduct }: { isLoading: boolean; initialProduct?: { title: string } }) => <div>{isLoading ? "Loading product" : initialProduct?.title}</div>,
}));
const mount = () => render(<SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}><ProductPage clientFallback /></SWRConfig>);
beforeEach(() => { mocks.getProduct.mockReset(); mocks.router.asPath = "/products/new-item/?ref=home"; });
it("loads an unbuilt product from the public URL and default location", async () => {
  mocks.getProduct.mockResolvedValue({ success: true, data: { slug: "new-item", title: "New item" } });
  mount();
  await screen.findByText("New item");
  expect(mocks.getProduct).toHaveBeenCalledWith({ slug: "new-item", latitude: "1", longitude: "2" });
  expect(screen.getByTestId("seo").textContent).toBe("index");
});
it.each([
  "/share/products/new-item/",
  "/share/products/new-item?lat=1&lng=2#details",
  "/share/products/new%2Ditem/",
])("loads an unbuilt shared product at %s", async (url) => {
  mocks.router.asPath = url;
  mocks.getProduct.mockResolvedValue({ success: true, data: { slug: "new-item", title: "New item" } });
  mount();
  await screen.findByText("New item");
  expect(mocks.getProduct).toHaveBeenCalledWith({ slug: "new-item", latitude: "1", longitude: "2" });
});
it.each(["/share/products/%E0%A4%A/", "/share/products/", "/share/products/item/extra/"])(
  "does not fetch an invalid shared product URL (%s)", async (url) => {
    mocks.router.asPath = url;
    mount();
    await screen.findByText("Product unavailable");
    expect(mocks.getProduct).not.toHaveBeenCalled();
  },
);
it("does not mark the loading shell noindex before the API responds", () => {
  mocks.getProduct.mockReturnValue(new Promise(() => {}));
  mount();
  expect(screen.getByText("Loading product")).toBeTruthy();
  expect(screen.queryByTestId("seo")).toBeNull();
});
it("renders unavailable and noindex for a missing product", async () => {
  mocks.getProduct.mockResolvedValue({ success: false, data: [] });
  mount();
  await screen.findByText("Product unavailable");
  await waitFor(() => expect(screen.getByTestId("seo").textContent).toBe("noindex, follow"));
});

it("loads the next product when client navigation changes the URL", async () => {
  mocks.getProduct.mockImplementation(async ({ slug }: { slug: string }) => ({ success: true, data: { slug, title: slug } }));
  const view = mount();
  await screen.findByText("new-item");
  mocks.router.asPath = "/products/another-new-item/";
  view.rerender(<SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}><ProductPage clientFallback /></SWRConfig>);
  await screen.findByText("another-new-item");
});
