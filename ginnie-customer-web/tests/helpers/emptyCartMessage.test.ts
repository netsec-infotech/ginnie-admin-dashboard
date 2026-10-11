import { describe, expect, it } from "vitest";
import { isEmptyCartMessage } from "@/helpers/updators";

/**
 * Decides whether a failed cart fetch means "empty" or "broken".
 *
 * This used to be `message == "Your cart is empty"`. Any other wording fell
 * through to the generic-error branch, which left the previous cart sitting in
 * redux — so after a zone change emptied the cart server-side, the navbar badge
 * kept showing the old item count.
 */
describe("isEmptyCartMessage", () => {
  it.each([
    "Your cart is empty",
    "your cart is empty",
    "Cart is empty",
    "The cart is empty.",
    "Your cart is empty after removing undeliverable items",
  ])("recognises %j as an empty cart", (message) => {
    expect(isEmptyCartMessage(message)).toBe(true);
  });

  it.each([
    "Failed to fetch cart",
    "Unauthenticated.",
    "Store is closed",
    "Something went wrong",
  ])("treats %j as a real error", (message) => {
    expect(isEmptyCartMessage(message)).toBe(false);
  });

  it.each([undefined, null, ""])("handles %j safely", (message) => {
    expect(isEmptyCartMessage(message)).toBe(false);
  });

  it("does not match either keyword on its own", () => {
    // "cart" alone is in plenty of unrelated errors; both must be present.
    expect(isEmptyCartMessage("Failed to update cart")).toBe(false);
    expect(isEmptyCartMessage("The list is empty")).toBe(false);
  });
});
