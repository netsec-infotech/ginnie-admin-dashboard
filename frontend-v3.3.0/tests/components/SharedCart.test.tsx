import type { ReactNode } from "react";
import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { configureStore } from "@reduxjs/toolkit";
import { Provider } from "react-redux";
import { beforeEach, expect, it, vi } from "vitest";
import authReducer, { login, logout } from "@/lib/redux/slices/authSlice";
import cartReducer, { setCartData } from "@/lib/redux/slices/cartSlice";
import checkoutReducer from "@/lib/redux/slices/checkoutSlice";
import offlineCartReducer, { addOfflineCartItem } from "@/lib/redux/slices/offlineCartSlice";
import SharedCartPageView from "@/views/SharedCartPageView";
import ShareCartButton from "@/components/Cart/ShareCartButton";
import type { CartResponse, userData } from "@/types/ApiResponse";
import { acquirePaymentAttempt, completePaymentAttempt, markPaymentAttemptPaidPending, paymentAttemptScope, hasActivePaymentAttempt, releasePaymentAttempt } from "@/helpers/paymentReturn";

const mocks = vi.hoisted(() => ({
  getSharedCart: vi.fn(), getProduct: vi.fn(),
  importCart: vi.fn(), shareCart: vi.fn(), refresh: vi.fn(), getCookie: vi.fn(), invalidate: vi.fn(), toast: vi.fn(),
  router: { isReady: true, asPath: "/cart/shared/abc123/", replace: vi.fn() },
}));
vi.mock("next/router", () => ({ useRouter: () => mocks.router }));
vi.mock("@/routes/api", () => ({ getSharedCart: mocks.getSharedCart, getProductBySlug: mocks.getProduct, importSharedCart: mocks.importCart, shareCart: mocks.shareCart }));
vi.mock("next/dynamic", () => ({ default: () => () => null }));
vi.mock("@/contexts/SettingsContext", () => ({ useSettings: () => ({ webSettings: { defaultLatitude: "23", defaultLongitude: "69" } }) }));
vi.mock("@/SEO/DynamicSEO", () => ({ default: () => null }));
vi.mock("@/helpers/updators", () => ({ updateCartData: mocks.refresh }));
vi.mock("@/helpers/cartLocationConfirmation", () => ({ invalidateCartLocationConsent: mocks.invalidate }));
vi.mock("@/lib/cookies", () => ({ getCookie: mocks.getCookie }));
vi.mock("@/SEO/PageHead", () => ({ default: () => null }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key: string) => key, i18n: { language: "en" } }) }));
vi.mock("@heroui/react", () => {
  const Box = ({ children }: { children: ReactNode }) => <div>{children}</div>;
  return {
    Card: Box, CardBody: Box, ModalContent: Box, ModalHeader: Box, ModalBody: Box, ModalFooter: Box, Image: () => null,
    Modal: ({ isOpen, children }: { isOpen: boolean; children: ReactNode }) => isOpen ? <div role="dialog">{children}</div> : null,
    Spinner: () => <div>Loading</div>,
    Button: ({ children, onPress, isDisabled, isLoading }: { children: ReactNode; onPress?: () => void; isDisabled?: boolean; isLoading?: boolean }) => <button disabled={isDisabled || isLoading} onClick={onPress}>{children}</button>,
    Input: ({ label, value, onValueChange, isReadOnly }: { label: string; value: string; onValueChange?: (value: string) => void; isReadOnly?: boolean }) => <input aria-label={label} value={value} readOnly={isReadOnly} onChange={(event) => onValueChange?.(event.target.value)} />,
    addToast: mocks.toast,
  };
});

const cart = { id: 1, items: [{ id: 2 }], items_count: 1, delivery_zone: null } as unknown as CartResponse;
const previewItem = { store_id: 1, store_name: "Store", is_store_online: true, store_is_accepting_orders: true, is_deliverable: true,
  product_id: 1, product_name: "Milk", product_slug: "milk", product_image: "https://shop.test/milk.png", product_variant_id: 10,
  variant_title: "1L", shared_quantity: 2, effective_unit_price: 3, available_stock: 10, addons: [], is_available: true, unavailable_reason: null };
const preview = { share_code: "abc123", share_url: "https://shop.test/cart/shared/abc123/", title: "Weekend groceries", is_expired: false,
  expires_at: "2030-10-15T11:47:16+05:30", items: [previewItem], summary: { total_items: 1, available_items: 1, unavailable_items: 0, estimated_items_total: 6 } };
const product = { id: 1, title: "Milk", slug: "milk", minimum_order_quantity: 1, quantity_step_size: 1, total_allowed_quantity: 5,
  variants: [{ id: 10, store_id: 1, availability: true, stock: 10, price: 3, special_price: 0, addon_groups: [] }] };
const response = {
  success: true,
  data: {
    cart,
    import_summary: { imported_count: 1, updated_count: 2, unchanged_count: 3, skipped_count: 1, skipped_items: [{ store_id: 1, product_variant_id: 10, product_name: "Unavailable milk" }] },
  },
};
function mount(element: ReactNode, signedIn = true) {
  const store = configureStore({ reducer: { auth: authReducer, cart: cartReducer, checkout: checkoutReducer, offlineCart: offlineCartReducer } });
  if (signedIn) store.dispatch(login({ access_token: "token", user: { id: 1 } as userData }));
  store.dispatch(setCartData(cart));
  render(<Provider store={store}>{element}</Provider>);
  mocks.refresh.mockClear(); // Opening the cart also performs its normal background refresh.
  return store;
}
beforeEach(() => {
  localStorage.clear();
  sessionStorage.clear();
  vi.clearAllMocks();
  mocks.router.asPath = "/cart/shared/abc123/";
  mocks.getCookie.mockImplementation((key: string) => key === "access_token" ? "token" : null);
  mocks.getSharedCart.mockResolvedValue({ success: true, data: preview });
  mocks.getProduct.mockResolvedValue({ success: true, data: product });
  mocks.importCart.mockResolvedValue(response);
  mocks.refresh.mockResolvedValue({ success: true });
  mocks.shareCart.mockResolvedValue({ success: true, data: { share_url: "https://shop.test/cart/shared/abc123/", title: "Groceries", expires_at: "2026-10-15T11:47:16+05:30", items_count: 1 } });
});

it("retires a completed purchase lock after importing into the same cart ID", async () => {
  const scope = paymentAttemptScope({ usageType: "order", userId: 1, cartId: cart.id });
  const previous = await acquirePaymentAttempt(scope);
  completePaymentAttempt(previous!);
  mount(<SharedCartPageView />);
  await screen.findByText("Weekend groceries");
  fireEvent.click(screen.getByRole("button", { name: "cartSharing.importCart" }));
  await screen.findByText("cartSharing.importSuccess");
  expect(hasActivePaymentAttempt(scope)).toBe(false);
  const next = await acquirePaymentAttempt(scope);
  expect(next).not.toBeNull();
  releasePaymentAttempt(next!);
});

it.each(["collecting", "paid_pending"])("preserves a %s payment lock during cart import", async (status) => {
  const scope = paymentAttemptScope({ usageType: "order", userId: 1, cartId: cart.id });
  const previous = await acquirePaymentAttempt(scope);
  if (status === "paid_pending") markPaymentAttemptPaidPending(previous!);
  mount(<SharedCartPageView />);
  await screen.findByText("Weekend groceries");
  fireEvent.click(screen.getByRole("button", { name: "cartSharing.importCart" }));
  await screen.findByText("cartSharing.importSuccess");
  expect(hasActivePaymentAttempt(scope)).toBe(true);
  expect(await acquirePaymentAttempt(scope)).toBeNull();
  releasePaymentAttempt(previous!);
});

it("shows the actual title in a modal and only imports for a signed-in recipient after confirmation", async () => {
  const loginClick = vi.fn();
  const trigger = document.createElement("button");
  trigger.id = "login-btn";
  trigger.onclick = loginClick;
  document.body.appendChild(trigger);
  try {
    const store = mount(<SharedCartPageView />);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    expect(await screen.findByText("Weekend groceries")).toBeInTheDocument();
    expect(mocks.importCart).not.toHaveBeenCalled();
    expect(loginClick).not.toHaveBeenCalled();
    expect(screen.getByText("cartSharing.importCart")).toBeInTheDocument();
    expect(mocks.importCart).not.toHaveBeenCalled();
    fireEvent.click(screen.getByText("cartSharing.importCart"));
    await screen.findByText("cartSharing.importSuccess");
    expect(mocks.importCart).toHaveBeenCalledExactlyOnceWith("abc123", "merge");
    expect(store.getState().cart.pendingImportedCart?.items).toEqual(cart.items);
    expect(store.getState().cart.cartData).toBeNull();
    expect(mocks.refresh).toHaveBeenCalledOnce();
    expect(screen.getByText("Unavailable milk")).toBeInTheDocument();
  } finally {
    trigger.remove();
  }
});

it("explains both choices and only sends replace after the recipient selects it", async () => {
  const store = mount(<SharedCartPageView />);
  await screen.findByText("Weekend groceries");
  expect(screen.getByRole("radio", { name: "cartSharing.mergeTitle" })).toBeChecked();
  fireEvent.click(screen.getByRole("radio", { name: "cartSharing.replaceTitle" }));
  expect(screen.getByRole("status")).toHaveTextContent("cartSharing.replaceWarning");
  expect(mocks.importCart).not.toHaveBeenCalled();
  fireEvent.click(screen.getByRole("button", { name: "cartSharing.replaceCart" }));
  await screen.findByText("cartSharing.importSuccess");
  expect(mocks.importCart).toHaveBeenCalledExactlyOnceWith("abc123", "replace");
  expect(store.getState().cart.pendingImportedCart?.items).toEqual(cart.items);
  expect(mocks.invalidate).toHaveBeenCalledOnce();
});

it("replaces the guest cart after validating shared products instead of adding to old quantities", async () => {
  mocks.getCookie.mockReturnValue(null);
  const store = mount(<SharedCartPageView />, false);
  act(() => {
    store.dispatch(addOfflineCartItem({ id: "10", name: "Milk", price: 3, quantity: 4, minQuantity: 1, maxQuantity: 5, stepSize: 1, stock: 10, product_variant_id: 10, store_id: 1 }));
    store.dispatch(addOfflineCartItem({ id: "20", name: "Bread", price: 2, quantity: 1, minQuantity: 1, maxQuantity: 5, stepSize: 1, stock: 10, product_variant_id: 20, store_id: 1 }));
  });
  await screen.findByText("Weekend groceries");
  fireEvent.click(screen.getByRole("radio", { name: "cartSharing.replaceTitle" }));
  fireEvent.click(screen.getByRole("button", { name: "cartSharing.replaceCart" }));
  await screen.findByText("cartSharing.importSuccess");
  expect(store.getState().offlineCart.items.map(({ id, quantity }) => ({ id, quantity }))).toEqual([{ id: "10", quantity: 2 }]);
  expect(store.getState().offlineCart.subtotal).toBe(6);
  expect(mocks.importCart).not.toHaveBeenCalled();
});

it("keeps a guest cart intact when product validation fails during replace", async () => {
  mocks.getCookie.mockReturnValue(null);
  mocks.getProduct.mockResolvedValue({ success: false });
  const store = mount(<SharedCartPageView />, false);
  act(() => store.dispatch(addOfflineCartItem({ id: "20", name: "Bread", price: 2, quantity: 1, minQuantity: 1, maxQuantity: 5, stepSize: 1, stock: 10, product_variant_id: 20, store_id: 1 })));
  const original = store.getState().offlineCart;
  await screen.findByText("Weekend groceries");
  fireEvent.click(screen.getByRole("radio", { name: "cartSharing.replaceTitle" }));
  fireEvent.click(screen.getByRole("button", { name: "cartSharing.replaceCart" }));
  expect(await screen.findByRole("alert")).toHaveTextContent("cartSharing.importFailed");
  expect(store.getState().offlineCart).toEqual(original);
});

it("prevents repeated imports while a request is pending and after it succeeds", async () => {
  let resolve!: (value: typeof response) => void;
  mocks.importCart.mockReturnValue(new Promise((done) => { resolve = done; }));
  mount(<SharedCartPageView />);
  const button = await screen.findByText("cartSharing.importCart");
  fireEvent.click(button);
  fireEvent.click(button);
  expect(mocks.importCart).toHaveBeenCalledOnce();
  await act(async () => resolve(response));
  expect(screen.queryByText("cartSharing.importCart")).not.toBeInTheDocument();
});

it("keeps imported items available when totals refresh fails without re-importing", async () => {
  mocks.refresh.mockResolvedValue({ success: false });
  const store = mount(<SharedCartPageView />);
  fireEvent.click(await screen.findByText("cartSharing.importCart"));
  await screen.findByText("cartSharing.refreshNeeded");
  expect(store.getState().cart.pendingImportedCart?.items).toEqual(cart.items);
  expect(store.getState().cart.cartData).toBeNull();
  expect(mocks.importCart).toHaveBeenCalledOnce();
});

it.each([404, 410, 500])("shows an import failure for HTTP %s without replacing the cart", async (status) => {
  mocks.importCart.mockResolvedValue({ success: false, status });
  const store = mount(<SharedCartPageView />);
  fireEvent.click(await screen.findByText("cartSharing.importCart"));
  expect(await screen.findByRole("alert")).toHaveTextContent(status === 500 ? "cartSharing.importFailed" : "cartSharing.invalidLink");
  expect(store.getState().cart.cartData).toEqual(cart);
  expect(mocks.refresh).not.toHaveBeenCalled();
});

it("discards import results when the account changes during the request", async () => {
  let resolve!: (value: typeof response) => void;
  mocks.importCart.mockReturnValue(new Promise((done) => { resolve = done; }));
  const store = mount(<SharedCartPageView />);
  fireEvent.click(await screen.findByText("cartSharing.importCart"));
  mocks.getCookie.mockReturnValue("new-token");
  act(() => store.dispatch(logout()));
  await act(async () => resolve(response));
  expect(store.getState().cart.pendingImportedCart).toBeNull();
  expect(mocks.refresh).not.toHaveBeenCalled();
  expect(screen.queryByText("cartSharing.importSuccess")).not.toBeInTheDocument();
});

it("rejects malformed shared routes without calling the backend", () => {
  mocks.router.asPath = "/cart/shared/a%2Fb/";
  mount(<SharedCartPageView />);
  expect(screen.getByRole("alert")).toHaveTextContent("cartSharing.invalidLink");
  expect(mocks.importCart).not.toHaveBeenCalled();
});

it("requires login before creating a shared cart", () => {
  const onLogin = vi.fn();
  mount(<ShareCartButton onLogin={onLogin} />, false);
  fireEvent.click(screen.getByText("cartSharing.shareCart"));
  expect(onLogin).toHaveBeenCalledOnce();
  expect(mocks.shareCart).not.toHaveBeenCalled();
});

it("imports into the existing guest cart without login or an authenticated import request", async () => {
  mocks.getCookie.mockReturnValue(null);
  const store = mount(<SharedCartPageView />, false);
  act(() => store.dispatch(addOfflineCartItem({ id: "10", name: "Milk", price: 3, quantity: 1, minQuantity: 1, maxQuantity: 5, stepSize: 1, stock: 10, product_variant_id: 10, store_id: 1 })));
  expect(await screen.findByText("Weekend groceries")).toBeInTheDocument();
  expect(store.getState().offlineCart.totalQuantity).toBe(1);
  fireEvent.click(screen.getByText("cartSharing.importCart"));
  await screen.findByText("cartSharing.importSuccess");
  expect(store.getState().offlineCart.items).toHaveLength(1);
  expect(store.getState().offlineCart.totalQuantity).toBe(3);
  expect(mocks.importCart).not.toHaveBeenCalled();
  expect(mocks.getProduct).toHaveBeenCalledWith({ slug: "milk", latitude: 23, longitude: 69 });
  fireEvent.click(screen.getByText("cartSharing.viewCart"));
  expect(mocks.router.replace).toHaveBeenCalledWith("/cart/");
});

it("cancels without changing a guest cart", async () => {
  mocks.getCookie.mockReturnValue(null);
  const store = mount(<SharedCartPageView />, false);
  await screen.findByText("Weekend groceries");
  fireEvent.click(screen.getByText("cartSharing.notNow"));
  expect(mocks.router.replace).toHaveBeenCalledWith("/cart/");
  expect(store.getState().offlineCart.items).toEqual([]);
  expect(mocks.getProduct).not.toHaveBeenCalled();
});

it("does not commit a guest import if the user signs in during product validation", async () => {
  mocks.getCookie.mockReturnValue(null);
  let resolve!: (value: unknown) => void;
  mocks.getProduct.mockReturnValue(new Promise((done) => { resolve = done; }));
  const store = mount(<SharedCartPageView />, false);
  fireEvent.click(await screen.findByText("cartSharing.importCart"));
  await waitFor(() => expect(mocks.getProduct).toHaveBeenCalledOnce());
  mocks.getCookie.mockImplementation((key: string) => key === "access_token" ? "token" : null);
  act(() => store.dispatch(login({ access_token: "token", user: { id: 1 } as userData })));
  await act(async () => resolve({ success: true, data: product }));
  expect(store.getState().offlineCart.items).toEqual([]);
  expect(mocks.importCart).not.toHaveBeenCalled();
});

it("rejects an expired public snapshot without offering import", async () => {
  mocks.getSharedCart.mockResolvedValue({ success: true, data: { ...preview, is_expired: true } });
  mount(<SharedCartPageView />, false);
  expect(await screen.findByRole("alert")).toHaveTextContent("cartSharing.invalidLink");
  expect(screen.queryByText("cartSharing.importCart")).not.toBeInTheDocument();
});

it("creates a link with a trimmed title and displays the server URL", async () => {
  mount(<ShareCartButton />);
  fireEvent.click(screen.getByText("cartSharing.shareCart"));
  fireEvent.change(screen.getByLabelText("cartSharing.titleLabel"), { target: { value: "  Groceries  " } });
  fireEvent.click(screen.getByText("cartSharing.createLink"));
  await waitFor(() => expect(screen.getByLabelText("cartSharing.linkLabel")).toHaveValue("https://shop.test/cart/shared/abc123/"));
  expect(mocks.shareCart).toHaveBeenCalledExactlyOnceWith({ title: "Groceries" });
});

it("copies only the shared-cart URL", async () => {
  const previousClipboard = Object.getOwnPropertyDescriptor(navigator, "clipboard");
  const writeText = vi.fn().mockResolvedValue(undefined);
  Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText } });
  try {
    mount(<ShareCartButton />);
    fireEvent.click(screen.getByText("cartSharing.shareCart"));
    fireEvent.change(screen.getByLabelText("cartSharing.titleLabel"), { target: { value: "  Groceries  " } });
    fireEvent.click(screen.getByText("cartSharing.createLink"));
    await screen.findByText("cartSharing.copyLink");
    fireEvent.click(screen.getByText("cartSharing.copyLink"));
    await waitFor(() => expect(writeText).toHaveBeenCalledExactlyOnceWith("https://shop.test/cart/shared/abc123/"));
  } finally {
    if (previousClipboard) Object.defineProperty(navigator, "clipboard", previousClipboard);
    else Reflect.deleteProperty(navigator, "clipboard");
  }
});
