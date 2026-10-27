import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  getState: vi.fn(), dispatch: vi.fn(), getCart: vi.fn(),
  validate: vi.fn(), getCookie: vi.fn(), toast: vi.fn(),
}));
vi.mock("@/lib/redux/store", () => ({ store: mocks }));
vi.mock("@/lib/cookies", () => ({ getCookie: mocks.getCookie }));
vi.mock("@/routes/api", () => ({ getCart: mocks.getCart }));
vi.mock("@/services/addressDeliveryService", () => ({ validateAddressDelivery: mocks.validate }));
vi.mock("@/helpers/functionalHelpers", () => ({ resetCheckOutState: vi.fn() }));
vi.mock("@heroui/react", () => ({ addToast: mocks.toast }));
vi.mock("../../i18n", () => ({ default: { t: (key: string) => key } }));

const cart = () => ({
  id: 1, items: [{ id: 10, store_id: 2, product_variant_id: 3, quantity: 1 }],
  delivery_zone: { zone_id: 1 }, payment_summary: {}, removed_count: 0, removed_items: [],
});
const coordinates = { latitude: 23.23471626, longitude: 69.64707716 };

beforeEach(() => {
  vi.resetModules();
  vi.resetAllMocks();
  mocks.getState.mockReturnValue({
    auth: { isLoggedIn: true },
    cart: { cartData: cart(), deliveryLocationNeedsSelection: false },
    checkout: { selectedAddress: { id: 99, ...coordinates } },
  });
  mocks.dispatch.mockImplementation((action) => {
    const state = mocks.getState();
    if (action.type === "cart/setCartData") state.cart.cartData = action.payload;
    if (action.type === "cart/setDeliveryLocationNeedsSelection") state.cart.deliveryLocationNeedsSelection = action.payload;
    if (action.type === "cart/clearCart") state.cart.cartData = null;
  });
  mocks.getCookie.mockReturnValue({ lat: 23, lng: 69 });
  mocks.validate.mockImplementation(async ({ latitude }) => Number(latitude) === 23
    ? { success: true }
    : { success: false, messageKey: "address.zoneMismatch" });
  mocks.getCart.mockImplementation(async () => ({ success: true, data: cart() }));
});

async function setup() {
  const { updateCartData } = await import("@/helpers/updators");
  const { confirmCartLocation } = await import("@/helpers/cartLocationConfirmation");
  const { cartLocationConfirmationStore: confirmation } = await import("@/stores/cartLocationConfirmationStore");
  const chooseAddress = async (confirmed: boolean) => {
    const decision = confirmCartLocation("address:99", coordinates);
    await vi.waitFor(() => expect(confirmation.getSnapshot()).not.toBeNull());
    confirmation.respond(confirmed);
    return decision;
  };
  return { updateCartData, confirmCartLocation, confirmation, chooseAddress };
}

describe("cart delivery UX", () => {
  it("ignores a cart refresh started before new shared contents were imported", async () => {
    let resolve!: (value: unknown) => void;
    mocks.getCart.mockReturnValue(new Promise((done) => { resolve = done; }));
    mocks.validate.mockResolvedValue({ success: true });
    const { updateCartData } = await setup();
    const request = updateCartData(false, false);
    await vi.waitFor(() => expect(mocks.getCart).toHaveBeenCalledOnce());
    const importedCart = { ...cart(), items: [{ id: 50, store_id: 2, product_variant_id: 3, quantity: 2 }] };
    mocks.getState().cart.pendingImportedCart = importedCart;
    mocks.getState().cart.cartData = null;
    resolve({ success: true, data: cart() });
    await request;
    expect(mocks.getState().cart.pendingImportedCart).toBe(importedCart);
    expect(mocks.getState().cart.cartData).toBeNull();
  });
  it("validates imported cart contents and zone before refreshing checkout totals", async () => {
    const state = mocks.getState();
    state.cart.pendingImportedCart = { ...cart(), delivery_zone: { zone_id: 7 } };
    state.cart.cartData = null;
    mocks.validate.mockResolvedValue({ success: true });
    const { updateCartData } = await setup();
    await updateCartData(false, false);
    expect(mocks.validate).toHaveBeenCalledWith({ latitude: "23", longitude: "69" }, 7);
    expect(mocks.getCart).toHaveBeenCalledOnce();
  });
  it.each([true, false])("applies a new address that differs from the old Redux selection: same zone=%s", async (sameZone) => {
    mocks.getState().checkout.selectedAddress = { id: 98, latitude: 38.5, longitude: -106.9 };
    mocks.validate.mockResolvedValue(sameZone
      ? { success: true }
      : { success: false, messageKey: "address.zoneMismatch" });
    const { updateCartData, confirmCartLocation, confirmation } = await setup();
    const candidate = { id: 175, ...coordinates };
    const decision = confirmCartLocation("address:175", coordinates);
    if (!sameZone) {
      await vi.waitFor(() => expect(confirmation.getSnapshot()).not.toBeNull());
      confirmation.respond(true);
    }
    expect(await decision).toBe(true);
    const response = await updateCartData(false, false, candidate);
    expect(response?.success).toBe(true);
    expect(mocks.getCart).toHaveBeenCalledExactlyOnceWith(expect.objectContaining({
      address_id: 175, latitude: 23, longitude: 69,
    }));
    expect(mocks.validate).toHaveBeenCalledExactlyOnceWith(coordinates, 1);
    expect(confirmation.getSnapshot()).toBeNull();
    expect(mocks.getState().checkout.selectedAddress.id).toBe(98);
  });

  it.each([undefined, { lat: "", lng: "" }, { lat: "invalid", lng: 69 }])(
    "never sends a coordinate-free request when the current location is missing or invalid: %j",
    async (location) => {
      mocks.getCookie.mockReturnValue(location);
      const { updateCartData, confirmation } = await setup();
      await updateCartData(false, false);
      expect(mocks.getCart).not.toHaveBeenCalled();
      expect(mocks.getState().cart.cartData.items).toHaveLength(1);
      expect(mocks.getState().cart.deliveryLocationNeedsSelection).toBe(true);
      expect(confirmation.getSnapshot()).toBeNull();
    },
  );
  it("refreshes after adding and on cart navigation without prompting or applying an unsafe location", async () => {
    const { updateCartData, confirmation } = await setup();
    await updateCartData(false, true, 0, false);
    await updateCartData(true, false, 0, false);
    expect(mocks.getCart).toHaveBeenCalledTimes(2);
    for (const [params] of mocks.getCart.mock.calls) expect(params).toMatchObject({ latitude: 23, longitude: 69, address_id: "" });
    expect(confirmation.getSnapshot()).toBeNull();
    expect(mocks.getState().cart.cartData.items).toHaveLength(1);
    expect(mocks.getState().cart.deliveryLocationNeedsSelection).toBe(true);
    expect(mocks.toast).not.toHaveBeenCalled();
  });

  it("silently bootstraps a server cart in a fresh session", async () => {
    mocks.getState().cart.cartData = null;
    const { updateCartData, confirmation } = await setup();
    await updateCartData(true, false);
    expect(confirmation.getSnapshot()).toBeNull();
    expect(mocks.getState().cart.cartData.items).toHaveLength(1);
    for (const [params] of mocks.getCart.mock.calls) expect(params).toMatchObject({ latitude: 23, longitude: 69, address_id: "" });
  });

  it("applies a matching address without prompting", async () => {
    mocks.validate.mockResolvedValue({ success: true });
    const { updateCartData, confirmation } = await setup();
    await updateCartData(true, false);
    expect(mocks.getCart).toHaveBeenCalledWith(expect.objectContaining({ address_id: "99" }));
    expect(confirmation.getSnapshot()).toBeNull();
    expect(mocks.getState().cart.deliveryLocationNeedsSelection).toBe(false);
  });

  it("asks once for an explicit mismatched address, then refreshes and revisits silently", async () => {
    const { updateCartData, chooseAddress, confirmation } = await setup();
    expect(await chooseAddress(true)).toBe(true);
    await updateCartData(false, false, "99");
    await updateCartData(true, false);
    expect(mocks.getCart).toHaveBeenCalledTimes(2);
    for (const [params] of mocks.getCart.mock.calls) expect(params.address_id).toBe("99");
    expect(confirmation.getSnapshot()).toBeNull();
  });

  it("treats an address and map coordinates for the same destination as one choice", async () => {
    const { chooseAddress, confirmCartLocation, confirmation } = await setup();
    await chooseAddress(true);
    expect(await confirmCartLocation("location:23.23471626,69.64707716")).toBe(true);
    expect(mocks.validate).toHaveBeenCalledTimes(1);
    expect(confirmation.getSnapshot()).toBeNull();
  });

  it("keeps cancellation final through refreshes and revisiting the cart", async () => {
    const { updateCartData, chooseAddress, confirmation } = await setup();
    expect(await chooseAddress(false)).toBe(false);
    await updateCartData(true, false);
    await updateCartData(false, false);
    expect(confirmation.getSnapshot()).toBeNull();
    expect(mocks.getState().checkout.selectedAddress.id).toBe(99);
    for (const [params] of mocks.getCart.mock.calls) expect(params).toMatchObject({ latitude: 23, longitude: 69, address_id: "" });
  });

  it("preserves the cart without requesting when neither destination is safe", async () => {
    mocks.validate.mockResolvedValue({ success: false, messageKey: "address.zoneMismatch" });
    const { updateCartData } = await setup();
    await updateCartData(true, false);
    await updateCartData(true, false);
    expect(mocks.getState().cart.cartData.delivery_zone.zone_id).toBe(1);
    for (const [, zone] of mocks.validate.mock.calls) expect(zone).toBe(1);
    expect(mocks.getCart).not.toHaveBeenCalled();
  });

  it.each([false, true])("accepts an identical refresh but rejects changed contents while awaiting consent: changed=%s", async (changed) => {
    const { confirmCartLocation, confirmation } = await setup();
    const decision = confirmCartLocation("address:99", coordinates);
    await vi.waitFor(() => expect(confirmation.getSnapshot()).not.toBeNull());
    mocks.getState().cart.cartData = cart();
    if (changed) mocks.getState().cart.cartData.items[0].quantity = 2;
    confirmation.respond(true);
    expect(await decision).toBe(!changed);
    expect(mocks.getCart).not.toHaveBeenCalled();
  });

  it("coalesces simultaneous identical refreshes", async () => {
    const { updateCartData } = await setup();
    await Promise.all([updateCartData(true, false), updateCartData(true, false)]);
    expect(mocks.getCart).toHaveBeenCalledTimes(1);
    expect(mocks.getCart).toHaveBeenCalledWith(expect.objectContaining({ latitude: 23, longitude: 69 }));
  });

  it("preserves existing items when delivery validation fails", async () => {
    mocks.validate.mockResolvedValue({ success: false, messageKey: "address.zoneCheckFailed" });
    const { updateCartData, confirmation } = await setup();
    await updateCartData(true, false);
    expect(mocks.getCart).not.toHaveBeenCalled();
    expect(mocks.getState().cart.cartData.items).toHaveLength(1);
    expect(confirmation.getSnapshot()).toBeNull();
    expect(mocks.getState().cart.deliveryLocationNeedsSelection).toBe(true);
  });

  it("returns settled contents after consented removals", async () => {
    mocks.getCart.mockResolvedValueOnce({ success: true, data: {
      ...cart(), removed_count: 1, removed_items: [{ product_name: "Item" }],
    } }).mockResolvedValueOnce({ success: false, message: "Your cart is empty" });
    const { updateCartData, chooseAddress } = await setup();
    await chooseAddress(true);
    expect(await updateCartData(true, false)).toEqual({ success: false, message: "Your cart is empty" });
    expect(mocks.getState().cart.cartData).toBeNull();
  });

  it("invalidates previous removal consent after adding items and refreshes at the current location", async () => {
    const { updateCartData, chooseAddress, confirmation } = await setup();
    await chooseAddress(true);
    const changedCart = cart();
    changedCart.items[0].quantity = 2;
    mocks.getCart.mockResolvedValue({ success: true, data: changedCart });
    await updateCartData(true, false, 0, false, true, true);
    expect(mocks.getCart).toHaveBeenCalledTimes(1);
    expect(mocks.getCart).toHaveBeenCalledWith(expect.objectContaining({ latitude: 23, longitude: 69, address_id: "" }));
    expect(confirmation.getSnapshot()).toBeNull();
    expect(mocks.getState().cart.cartData.items[0].quantity).toBe(2);
    expect(mocks.getState().cart.deliveryLocationNeedsSelection).toBe(true);
  });
});
