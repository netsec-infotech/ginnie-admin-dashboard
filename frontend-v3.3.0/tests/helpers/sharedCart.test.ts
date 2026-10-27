import { expect, it } from "vitest";
import { getSharedCartCode } from "@/helpers/sharedCart";
import cartReducer, { clearCart, setCartData, setImportedCart } from "@/lib/redux/slices/cartSlice";
import { getApiFailureDetails } from "@/helpers/apiError";
import { AxiosError, AxiosHeaders } from "axios";
import type { CartResponse } from "@/types/ApiResponse";
import type { ImportedCart } from "@/types/sharedCart";

it.each(["/cart/shared/abc123", "/cart/shared/abc123/", "/cart/shared/abc123/?source=friend#cart"])("extracts the code from the visible shared URL: %s", (path) => {
  expect(getSharedCartCode(path)).toBe("abc123");
});

it.each(["/cart/shared/", "/cart/shared/[shareCode]", "/cart/shared/__shared-cart__/", "/cart/shared/a%2Fb", "/cart/shared/%E0%A4%A", "/cart/shared/abc/extra", "/cart/shared/../", "/products/abc", "/cart/shared/a%3Fb"])("rejects invalid shared URLs: %s", (path) => {
  expect(getSharedCartCode(path)).toBeNull();
});

it("preserves imported contents without exposing old checkout totals, then settles or clears them", () => {
  const oldCart = { id: 1, items: [], payment_summary: { payable_amount: 99 } } as unknown as CartResponse;
  const importedCart = { id: 1, items: [{ id: 2 }], delivery_zone: null } as unknown as ImportedCart;
  const oldState = cartReducer(undefined, setCartData(oldCart));
  const pending = cartReducer(oldState, setImportedCart(importedCart));
  expect(pending.cartData).toBeNull();
  expect(pending.pendingImportedCart).toEqual(importedCart);
  expect(pending.deliveryLocationNeedsSelection).toBe(true);
  const calculatedCart = { ...oldCart, items: importedCart.items };
  const settled = cartReducer(pending, setCartData(calculatedCart));
  expect(settled.pendingImportedCart).toBeNull();
  expect(settled.cartData).toEqual(calculatedCart);
  expect(cartReducer(pending, clearCart()).pendingImportedCart).toBeNull();
});

it("redacts share codes from API diagnostics", () => {
  const error = new AxiosError("Failed", "ERR_BAD_RESPONSE", { url: "/user/cart/shared/private-code/import", method: "post", headers: new AxiosHeaders() });
  expect(getApiFailureDetails(error)?.path).not.toContain("private-code");
});
