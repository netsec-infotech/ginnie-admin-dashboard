import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import type { Product } from "@/types/ApiResponse";
import ProductDetailSection from "@/components/Products/ProductDetailPage/ProductDetailSection";

const mocks = vi.hoisted(() => ({
  loggedIn: false,
  push: vi.fn(),
  offlineAdd: vi.fn(),
  onlineAdd: vi.fn(),
  login: vi.fn(),
}));
vi.mock("next/router", () => ({ useRouter: () => ({ push: mocks.push }) }));
vi.mock("react-redux", () => ({
  useSelector: (select: (state: unknown) => unknown) =>
    select({ auth: { isLoggedIn: mocks.loggedIn } }),
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ currencySymbol: "$" }),
}));
vi.mock("@/helpers/functionalHelpers", () => ({
  handleOfflineAddToCart: mocks.offlineAdd,
  handleAddToCart: mocks.onlineAdd,
  makeTabClick: vi.fn(),
  handleNotifyProduct: vi.fn(),
}));
vi.mock("@/components/Products/ProductDetailPage/QtyInput", () => ({
  default: () => null,
}));
vi.mock("@/components/Products/ProductDetailPage/AdditionalSection", () => ({
  default: () => null,
}));
vi.mock("@/components/Functional/ProductIndicator", () => ({
  default: () => null,
}));
vi.mock("@/components/Functional/AttributeSelector", () => ({
  default: () => null,
}));

const product = {
  title: "Test product",
  slug: "test-product",
  store_status: { is_open: true },
  minimum_order_quantity: 1,
  quantity_step_size: 1,
  total_allowed_quantity: 5,
  variants: [
    {
      id: 1,
      store_id: 2,
      is_default: true,
      availability: true,
      stock: 10,
      price: 20,
      attributes: {},
    },
  ],
} as unknown as Product;
const originalShare = Object.getOwnPropertyDescriptor(navigator, "share");
const nativeShare = vi.fn().mockResolvedValue(undefined);
beforeEach(() => {
  vi.clearAllMocks();
  mocks.loggedIn = false;
  mocks.offlineAdd.mockReturnValue({ success: true });
  mocks.onlineAdd.mockResolvedValue({ success: true });
  document.cookie = "userLocation=; Max-Age=0; path=/";
  Object.defineProperty(navigator, "share", {
    configurable: true,
    value: nativeShare,
  });
});
afterEach(() => {
  document.cookie = "userLocation=; Max-Age=0; path=/";
  if (originalShare) Object.defineProperty(navigator, "share", originalShare);
  else Reflect.deleteProperty(navigator, "share");
});

it("keeps guests on the product and opens login after saving Buy Now locally", async () => {
  render(
    <>
      <button id="login-btn" onClick={mocks.login}>
        Login
      </button>
      <ProductDetailSection initialProduct={product} />
    </>,
  );
  fireEvent.click(screen.getByRole("button", { name: "buyNow" }));
  await waitFor(() =>
    expect(mocks.offlineAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        product,
        quantity: 1,
        variant: product.variants[0],
      }),
    ),
  );
  expect(mocks.login).toHaveBeenCalledOnce();
  expect(mocks.push).not.toHaveBeenCalled();
  expect(mocks.onlineAdd).not.toHaveBeenCalled();
});

it("still sends signed-in Buy Now to the cart after a successful add", async () => {
  mocks.loggedIn = true;
  render(<ProductDetailSection initialProduct={product} />);
  fireEvent.click(screen.getByRole("button", { name: "buyNow" }));
  await waitFor(() => expect(mocks.push).toHaveBeenCalledWith("/cart"));
  expect(mocks.offlineAdd).not.toHaveBeenCalled();
});

it("keeps guest Add to Cart independent of login navigation", () => {
  render(
    <>
      <button id="login-btn" onClick={mocks.login}>
        Login
      </button>
      <ProductDetailSection initialProduct={product} />
    </>,
  );
  fireEvent.click(screen.getByRole("button", { name: "addToBucket" }));
  expect(mocks.offlineAdd).toHaveBeenCalledOnce();
  expect(mocks.login).not.toHaveBeenCalled();
  expect(mocks.push).not.toHaveBeenCalled();
});

it("uses the common share button while preserving product location and preview version", async () => {
  document.cookie = `userLocation=${encodeURIComponent(JSON.stringify({ lat: 0, lng: 72.5 }))}; path=/`;
  render(
    <ProductDetailSection
      initialProduct={{
        ...product,
        short_description: "<strong>Fresh everyday essentials.</strong>",
        updated_at: "2026-10-06T12:00:00Z",
      }}
    />,
  );
  const shareButton = screen.getByRole("button", {
    name: "sharing.productLabel",
  });
  fireEvent.click(shareButton);

  await waitFor(() => expect(nativeShare).toHaveBeenCalledOnce());
  expect(nativeShare).toHaveBeenCalledWith({
    title: product.title,
    text: `${product.title} - Fresh everyday essentials.`,
    url: `${window.location.origin}/share/products/test-product/?lat=0&lng=72.5&v=2026-10-06T12%3A00%3A00Z`,
  });
  await waitFor(() => expect(shareButton).toBeEnabled());
});

it("still shares a clean product URL when the stored location is malformed", async () => {
  document.cookie = "userLocation=%bad-location; path=/";
  render(<ProductDetailSection initialProduct={product} />);
  fireEvent.click(screen.getByRole("button", { name: "sharing.productLabel" }));

  await waitFor(() =>
    expect(nativeShare).toHaveBeenCalledWith({
      title: product.title,
      text: "sharing.productFallback",
      url: `${window.location.origin}/share/products/test-product/`,
    }),
  );
});
