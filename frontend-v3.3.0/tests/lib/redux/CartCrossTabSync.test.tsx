import type { ReactNode } from "react";
import { act, render, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ updateCartData: vi.fn() }));

vi.mock("@/helpers/updators", () => ({
  updateCartData: mocks.updateCartData,
}));
vi.mock("redux-persist/integration/react", () => ({
  PersistGate: ({ children }: { children?: ReactNode }) => <>{children}</>,
}));
vi.mock("next/router", () => ({ default: { replace: vi.fn() } }));

import ReduxProvider from "@/lib/redux/ReduxProvider";
import { CART_SYNC_STORAGE_KEY, deleteCookie, setCookie } from "@/lib/cookies";
import { store } from "@/lib/redux/store";
import { login, logout } from "@/lib/redux/slices/authSlice";
import {
  clearCart,
  setCartData,
} from "@/lib/redux/slices/cartSlice";
import {
  clearOfflineCart,
  setOfflineCart,
  type OfflineCartItem,
} from "@/lib/redux/slices/offlineCartSlice";
import type { CartResponse, userData } from "@/types/ApiResponse";

const item = (quantity: number): OfflineCartItem => ({
  id: "variant-1",
  name: "Milk",
  price: 20,
  quantity,
  minQuantity: 1,
  maxQuantity: 10,
  stepSize: 1,
  stock: 10,
  product_variant_id: 1,
  store_id: 1,
});

const signature = (value: unknown): string => {
  const serialized = JSON.stringify(value) ?? "undefined";
  let hash = 2166136261;
  for (let index = 0; index < serialized.length; index += 1) {
    hash ^= serialized.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return (hash >>> 0).toString(36);
};

const serverCart = (payableAmount: number): CartResponse =>
  ({
    id: 10,
    items: [
      {
        id: 20,
        product_variant_id: 1,
        quantity: 2,
        save_for_later: false,
        addons: [],
      },
    ],
    payment_summary: { payable_amount: payableAmount },
  }) as unknown as CartResponse;

beforeEach(() => {
  mocks.updateCartData.mockReset();
  mocks.updateCartData.mockResolvedValue(undefined);
  deleteCookie("access_token");
  store.dispatch(logout());
  store.dispatch(clearCart());
  store.dispatch(clearOfflineCart());
  localStorage.removeItem(CART_SYNC_STORAGE_KEY);
});

afterEach(() => {
  deleteCookie("access_token");
  store.dispatch(logout());
  store.dispatch(clearCart());
  store.dispatch(clearOfflineCart());
});

describe("cross-tab cart synchronization", () => {
  it("applies and rebroadcasts guest-cart changes without credentials", async () => {
    const setItem = vi.spyOn(Storage.prototype, "setItem");
    const view = render(
      <ReduxProvider>
        <div>page</div>
      </ReduxProvider>,
    );
    const remoteItems = [item(3)];

    act(() => {
      window.dispatchEvent(
        new StorageEvent("storage", {
          key: CART_SYNC_STORAGE_KEY,
          newValue: JSON.stringify({
            version: 1,
            origin: "another-tab",
            mode: "guest",
            signature: signature(remoteItems),
            items: remoteItems,
          }),
        }),
      );
    });

    expect(store.getState().offlineCart.items).toEqual(remoteItems);
    expect(mocks.updateCartData).not.toHaveBeenCalled();

    act(() => store.dispatch(setOfflineCart([item(4)])));
    await waitFor(() =>
      expect(setItem).toHaveBeenCalledWith(
        CART_SYNC_STORAGE_KEY,
        expect.any(String),
      ),
    );
    const payload = JSON.parse(
      setItem.mock.calls.find(([key]) => key === CART_SYNC_STORAGE_KEY)?.[1] ||
        "null",
    );
    expect(payload).toMatchObject({ mode: "guest", items: [item(4)] });
    expect(JSON.stringify(payload)).not.toContain("access_token");
    view.unmount();
  });

  it("refreshes an authenticated cart only when the cookie matches Redux", async () => {
    setCookie("access_token", "account-a");
    store.dispatch(
      login({
        access_token: "account-a",
        user: { id: 1, name: "A" } as userData,
      }),
    );
    const view = render(
      <ReduxProvider>
        <div>page</div>
      </ReduxProvider>,
    );

    act(() => {
      window.dispatchEvent(
        new StorageEvent("storage", {
          key: CART_SYNC_STORAGE_KEY,
          newValue: JSON.stringify({
            version: 1,
            origin: "another-tab",
            mode: "authenticated",
            signature: "new-server-cart",
          }),
        }),
      );
    });

    await waitFor(() =>
      expect(mocks.updateCartData).toHaveBeenCalledWith(true, false),
    );
    view.unmount();
  });

  it("does not rebroadcast address-specific payment-summary changes", async () => {
    setCookie("access_token", "account-a");
    store.dispatch(
      login({
        access_token: "account-a",
        user: { id: 1, name: "A" } as userData,
      }),
    );
    const setItem = vi.spyOn(Storage.prototype, "setItem");
    const view = render(
      <ReduxProvider>
        <div>page</div>
      </ReduxProvider>,
    );

    act(() => store.dispatch(setCartData(serverCart(100))));
    const initialBroadcasts = setItem.mock.calls.filter(
      ([key]) => key === CART_SYNC_STORAGE_KEY,
    ).length;
    act(() => store.dispatch(setCartData(serverCart(125))));

    expect(
      setItem.mock.calls.filter(([key]) => key === CART_SYNC_STORAGE_KEY),
    ).toHaveLength(initialBroadcasts);
    view.unmount();
  });
});
