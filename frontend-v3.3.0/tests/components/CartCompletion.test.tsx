import { act, fireEvent, render, screen } from "@testing-library/react";
import { configureStore } from "@reduxjs/toolkit";
import { Provider } from "react-redux";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import cartReducer, { clearCart, setCartData } from "@/lib/redux/slices/cartSlice";
import { notifyCheckoutCompletion } from "@/helpers/checkoutCompletion";
import CartPageView from "@/views/CartPageView";
import type { CartResponse } from "@/types/ApiResponse";
import Link from "next/link";

const router = vi.hoisted(() => ({ replace: vi.fn() }));
const actions = vi.hoisted(() => ({ edit: vi.fn(), checkout: vi.fn() }));
vi.mock("next/router", () => ({ useRouter: () => router }));
beforeEach(() => { vi.useFakeTimers(); vi.clearAllMocks(); });
afterEach(() => { vi.useRealTimers(); });

vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key: string) => key }) }));
vi.mock("@/views/CartPageView/CartAdditionalInfo", () => ({ default: () => <><button onClick={actions.edit}>Edit address</button><input aria-label="Order note" /><Link href="/" onClick={actions.edit}>Change delivery</Link></> }));
vi.mock("@/views/CartPageView/CheckoutSection", () => ({ default: () => <button onClick={actions.checkout}>Place order</button> }));
vi.mock("@/views/CartPageView/SaveForLaterItems", () => ({ default: () => null }));
vi.mock("@/views/empty/CartPageEmpty", () => ({ default: () => <p>Empty cart</p> }));
vi.mock("react-confetti", () => ({ default: () => <div data-testid="confetti" /> }));

const cart = { items: [{ id: 1 }] } as unknown as CartResponse;

it("keeps the completed cart inert and redirects after five seconds of confetti without a dialog", () => {
  const store = configureStore({ reducer: { cart: cartReducer, auth: () => ({ isLoggedIn: true }) } });
  store.dispatch(setCartData(cart));
  const { container } = render(<Provider store={store}><CartPageView /></Provider>);
  expect(screen.queryByTestId("confetti")).not.toBeInTheDocument();
  expect(screen.getByText("Edit address")).not.toBeDisabled();
  expect(screen.getByText("Place order")).not.toBeDisabled();
  act(() => {
    notifyCheckoutCompletion(cart);
    store.dispatch(clearCart());
  });
  expect(store.getState().cart.cartData).toBeNull();
  expect(screen.queryByText("Empty cart")).not.toBeInTheDocument();
  expect(container.querySelector("[inert]")).toHaveTextContent("Edit address");
  expect(screen.getByText("Place order")).toBeDisabled();
  expect(screen.getByText("Edit address")).toBeDisabled();
  expect(screen.getByLabelText("Order note")).toBeDisabled();
  expect(container.querySelector("fieldset")).toHaveClass("opacity-60", "pointer-events-none");
  fireEvent.click(screen.getByText("Edit address"));
  fireEvent.click(screen.getByText("Place order"));
  fireEvent.click(screen.getByText("Change delivery"));
  expect(actions.edit).not.toHaveBeenCalled();
  expect(actions.checkout).not.toHaveBeenCalled();
  expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  expect(screen.getByTestId("confetti")).toBeInTheDocument();
  act(() => { vi.advanceTimersByTime(4999); });
  expect(router.replace).not.toHaveBeenCalled();
  act(() => { vi.advanceTimersByTime(1); });
  expect(router.replace).toHaveBeenCalledExactlyOnceWith("/my-account/orders");
  expect(screen.queryByTestId("confetti")).not.toBeInTheDocument();
  expect(screen.queryByText("Empty cart")).not.toBeInTheDocument();
});

it("ordinary cart clearing still shows the empty view without celebrating", () => {
  const store = configureStore({ reducer: { cart: cartReducer, auth: () => ({ isLoggedIn: true }) } });
  store.dispatch(setCartData(cart));
  render(<Provider store={store}><CartPageView /></Provider>);
  act(() => { store.dispatch(clearCart()); });
  expect(screen.getByText("Empty cart")).toBeInTheDocument();
  expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  expect(screen.queryByTestId("confetti")).not.toBeInTheDocument();
  act(() => { vi.advanceTimersByTime(5000); });
  expect(router.replace).not.toHaveBeenCalled();
});

it("cancels the delayed redirect when the customer leaves the cart", () => {
  const store = configureStore({ reducer: { cart: cartReducer, auth: () => ({ isLoggedIn: true }) } });
  store.dispatch(setCartData(cart));
  const { unmount } = render(<Provider store={store}><CartPageView /></Provider>);
  act(() => { notifyCheckoutCompletion(cart); store.dispatch(clearCart()); });
  unmount();
  act(() => { vi.advanceTimersByTime(5000); });
  expect(router.replace).not.toHaveBeenCalled();
});
