import { describe, it, expect } from "vitest";
import reducer, {
  addRecentlyViewed,
  clearRecentlyViewed,
} from "@/lib/redux/slices/recentlyViewedSlice";
import type { Product } from "@/types/ApiResponse";

// The slice only ever reads `id`, so a minimal cast keeps the tests focused.
const product = (id: number) => ({ id }) as Product;

describe("recentlyViewedSlice", () => {
  it("adds a product to the front", () => {
    const state = reducer({ products: [] }, addRecentlyViewed(product(1)));
    expect(state.products.map((p) => p.id)).toEqual([1]);
  });

  it("moves an already-seen product to the front without duplicating", () => {
    let state = reducer({ products: [] }, addRecentlyViewed(product(1)));
    state = reducer(state, addRecentlyViewed(product(2)));
    state = reducer(state, addRecentlyViewed(product(1)));
    expect(state.products.map((p) => p.id)).toEqual([1, 2]);
  });

  it("caps the list at 20 most-recent products", () => {
    let state = { products: [] as Product[] };
    for (let id = 1; id <= 25; id++) {
      state = reducer(state, addRecentlyViewed(product(id)));
    }
    expect(state.products).toHaveLength(20);
    // Most recent first; the 5 oldest (ids 1-5) are dropped.
    expect(state.products[0].id).toBe(25);
    expect(state.products.at(-1)?.id).toBe(6);
    expect(state.products.some((p) => p.id === 5)).toBe(false);
  });

  it("clears the list", () => {
    let state = reducer({ products: [] }, addRecentlyViewed(product(1)));
    state = reducer(state, clearRecentlyViewed());
    expect(state.products).toEqual([]);
  });
});
