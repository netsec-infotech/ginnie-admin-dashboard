import { describe, it, expect } from "vitest";
import reducer, {
  addOfflineCartItem,
  updateOfflineCartItemQuantity,
  removeOfflineCartItem,
  clearOfflineCart,
  type OfflineCartItem,
  type OfflineCartState,
} from "@/lib/redux/slices/offlineCartSlice";

const makeItem = (overrides: Partial<OfflineCartItem> = {}): OfflineCartItem => ({
  id: "v1",
  name: "Test product",
  price: 100,
  quantity: 1,
  minQuantity: 1,
  maxQuantity: 100,
  stepSize: 1,
  stock: 100,
  product_variant_id: 1,
  store_id: 1,
  ...overrides,
});

const initial: OfflineCartState = { items: [], subtotal: 0, totalQuantity: 0 };

describe("offlineCartSlice", () => {
  it("adds an item and computes subtotal + totalQuantity", () => {
    const state = reducer(initial, addOfflineCartItem(makeItem({ quantity: 3 })));
    expect(state.items).toHaveLength(1);
    expect(state.totalQuantity).toBe(3);
    expect(state.subtotal).toBe(300);
  });

  it("includes addon prices in the subtotal", () => {
    const item = makeItem({
      quantity: 2,
      addons: [{ addon_group_id: 1, addon_item_id: 1, title: "Extra", price: 20 }],
    });
    const state = reducer(initial, addOfflineCartItem(item));
    // (100 + 20) * 2
    expect(state.subtotal).toBe(240);
  });

  it("clamps a below-minimum quantity up to minQuantity", () => {
    const state = reducer(
      initial,
      addOfflineCartItem(makeItem({ quantity: 0, minQuantity: 2 })),
    );
    expect(state.items[0].quantity).toBe(2);
  });

  it("caps quantity at available stock", () => {
    const state = reducer(
      initial,
      addOfflineCartItem(makeItem({ quantity: 50, stock: 3 })),
    );
    expect(state.items[0].quantity).toBe(3);
  });

  it("rounds a quantity down to the nearest valid step", () => {
    // min 1, step 2 -> valid quantities 1,3,5,... ; desired 6 -> 5
    const state = reducer(
      initial,
      addOfflineCartItem(makeItem({ quantity: 6, minQuantity: 1, stepSize: 2 })),
    );
    expect(state.items[0].quantity).toBe(5);
  });

  it("merges quantity when the same id is added again", () => {
    let state = reducer(initial, addOfflineCartItem(makeItem({ quantity: 2 })));
    state = reducer(state, addOfflineCartItem(makeItem({ quantity: 3 })));
    expect(state.items).toHaveLength(1);
    expect(state.items[0].quantity).toBe(5);
    expect(state.totalQuantity).toBe(5);
  });

  it("updates an existing item's quantity (clamped)", () => {
    let state = reducer(initial, addOfflineCartItem(makeItem({ quantity: 1 })));
    state = reducer(
      state,
      updateOfflineCartItemQuantity({ id: "v1", quantity: 4 }),
    );
    expect(state.items[0].quantity).toBe(4);
    expect(state.subtotal).toBe(400);
  });

  it("removes an item by id and recomputes the summary", () => {
    let state = reducer(initial, addOfflineCartItem(makeItem({ quantity: 2 })));
    state = reducer(state, removeOfflineCartItem("v1"));
    expect(state.items).toHaveLength(0);
    expect(state.subtotal).toBe(0);
    expect(state.totalQuantity).toBe(0);
  });

  it("clears the whole cart", () => {
    let state = reducer(initial, addOfflineCartItem(makeItem()));
    state = reducer(state, clearOfflineCart());
    expect(state).toEqual(initial);
  });
});
