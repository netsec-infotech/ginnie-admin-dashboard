import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  createOrder: vi.fn(),
  xenditCreateOrder: vi.fn(),
  dispatch: vi.fn(),
  getState: vi.fn(),
  toast: vi.fn(),
  trackPurchase: vi.fn(),
  checkDeliveryZone: vi.fn(),
}));
vi.mock("@/routes/api", () => ({
  createOrder: mocks.createOrder,
  xenditCreateOrder: mocks.xenditCreateOrder,
  checkDeliveryZone: mocks.checkDeliveryZone,
}));
vi.mock("@/lib/redux/store", () => ({
  store: { getState: mocks.getState, dispatch: mocks.dispatch },
}));
vi.mock("@heroui/react", () => ({ addToast: mocks.toast }));
vi.mock("@/lib/analytics", () => ({ trackPurchase: mocks.trackPurchase }));
vi.mock("@/helpers/updators", () => ({ updateCartData: vi.fn() }));
vi.mock("../../i18n", () => ({ default: { t: (key: string) => key } }));
import { handleCheckout } from "@/helpers/functionalHelpers";
import checkoutReducer from "@/lib/redux/slices/checkoutSlice";
import { clearCart } from "@/lib/redux/slices/cartSlice";
import { subscribeCheckoutCompletion } from "@/helpers/checkoutCompletion";
import {
  acquirePaymentAttempt,
  paymentAttemptScope,
  readPaymentReturn,
  hasActivePaymentAttempt,
  releasePaymentAttempt,
} from "@/helpers/paymentReturn";

const paymentLink =
  "https://sandbox.mercadopago.com.co/checkout/v1/redirect?pref_id=test-preference";
const order = (link: unknown = paymentLink) => ({
  success: true,
  message: "Order created successfully",
  data: {
    id: 10,
    final_total: "120.00",
    currency_code: "COP",
    items: [],
    payment_status: "pending",
    payment_response: { link },
  },
});

beforeEach(() => {
  vi.clearAllMocks();
  sessionStorage.clear();
  localStorage.clear();
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", "https://storefront.example/");
  vi.stubGlobal("window", {
    location: {
      origin: "https://shop.example",
      href: "https://shop.example/cart",
    },
    sessionStorage,
    __cartAttachments: {},
  });
  mocks.getState.mockReturnValue({
    checkout: {
      selectedAddress: { id: 7, latitude: 23.25, longitude: 69.66 },
      orderNote: "Leave at reception",
      promoCode: "TEST",
      useWallet: true,
      useLoyaltyPoints: true,
      rushDelivery: false,
      scheduledDelivery: { slotDbId: 12, date: "2026-10-01" },
    },
    auth: { user: { id: 7, name: "Test Customer" } },
    cart: {
      cartData: {
        uuid: "cart-42",
        available_payment_methods: ["cod", "wallet", "stripePayment", "mercadoPagoPayment", "flutterwavePayment", "xenditPayment"].map(key => ({key, is_enabled: true, is_available: true})),
        delivery_zone: { zone_id: 7 },
        payment_summary: { payable_amount: 120 },
      },
    },
  });
  mocks.checkDeliveryZone.mockResolvedValue({
    success: true,
    data: { is_deliverable: true, zone_id: 7 },
  });
  mocks.createOrder.mockResolvedValue(order());
});
afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("cart payment authorization", () => {
  it("places an order using settings when the cart omits the payment list", async () => {
    mocks.getState().cart.cartData.available_payment_methods = undefined;
    const result = await handleCheckout("cod", {}, {paymentSettings: {cod: true, wallet: true}});
    expect(result.success).toBe(true);
    expect(mocks.createOrder).toHaveBeenCalledOnce();
  });

  it.each([{methods: []}, {methods: [{key: "cod", is_enabled: true, is_available: false}]}])("keeps cart availability authoritative over enabled settings: %j", async ({methods}) => {
    mocks.getState().cart.cartData.available_payment_methods = methods;
    const result = await handleCheckout("cod", {}, {paymentSettings: {cod: true, wallet: true}});
    expect(result.success).toBe(false);
    expect(mocks.createOrder).not.toHaveBeenCalled();
  });

  it.each([{methods: undefined}, {methods: []}, {methods: [{key: "mercadoPagoPayment", is_enabled: true, is_available: false}]}])("blocks checkout when the cart list does not authorize payment: %j", async ({methods}) => {
    const state = mocks.getState();
    state.cart.cartData.available_payment_methods = methods;
    const result = await handleCheckout("mercadoPagoPayment", {});
    expect(result.success).toBe(false);
    expect(mocks.createOrder).not.toHaveBeenCalled();
  });

  it.each(["isLoading", "error"])("blocks order creation while the cart has %s", async (field) => {
    const state = mocks.getState();
    state.cart[field] = field === "error" ? "failed" : true;
    const result = await handleCheckout("cod", {});
    expect(result.success).toBe(false);
    expect(mocks.createOrder).not.toHaveBeenCalled();
  });

  it("uses the backend reason when a method is unavailable", async () => {
    mocks.getState().cart.cartData.available_payment_methods = [{key: "cod", is_enabled: true, is_available: false, message: "Cash payments are unavailable for this cart."}];
    const result = await handleCheckout("cod", {});
    expect(result.message).toBe("Cash payments are unavailable for this cart.");
    expect(mocks.createOrder).not.toHaveBeenCalled();
  });
});

describe("completed checkout cart cleanup", () => {
  it("allows the imported cart to pay after COD when the backend reuses its cart UUID", async () => {
    const scope = paymentAttemptScope({ usageType: "order", userId: 7, cartId: "cart-42" });
    const result = await handleCheckout("cod", {});
    expect(result.success).toBe(true);
    expect(hasActivePaymentAttempt(scope)).toBe(false);
    // The imported/refreshed cart has the same UUID as the completed COD cart.
    sessionStorage.clear();
    window.name = "imported-cart-tab";
    const nextPayment = await acquirePaymentAttempt(scope);
    expect(nextPayment).not.toBeNull();
    releasePaymentAttempt(nextPayment!);
  });
  it("blocks wallet checkout while another tab owns the cart payment", async () => {
    const scope = paymentAttemptScope({
      usageType: "order",
      userId: 7,
      cartId: "cart-42",
    });
    expect(await acquirePaymentAttempt(scope)).not.toBeNull();

    // Keep the shared localStorage record while switching to a different
    // tab-scoped identity for the wallet checkout.
    sessionStorage.clear();
    window.name = "wallet-tab";
    const result = await handleCheckout("wallet", {});

    expect(result.success).toBe(false);
    expect(mocks.createOrder).not.toHaveBeenCalled();
    expect(mocks.toast).toHaveBeenCalledWith(
      expect.objectContaining({
        title: "paymentAttempt.inProgressTitle",
        color: "warning",
      }),
    );
  });

  it.each(["stripePayment", "wallet", "cod"])(
    "clears the cart before %s returns success to its caller",
    async (method) => {
      mocks.createOrder.mockResolvedValue({
        ...order(),
        data: { ...order().data, payment_status: "completed" },
      });
      const completed = vi.fn(() =>
        expect(mocks.dispatch).not.toHaveBeenCalledWith(clearCart()),
      );
      const unsubscribe = subscribeCheckoutCompletion(completed);
      const result = await handleCheckout(method, {
        transaction_id: "pi_paid",
      });
      unsubscribe();
      expect(completed).toHaveBeenCalledWith(mocks.getState().cart.cartData);
      expect(result.success).toBe(true);
      expect(mocks.dispatch).toHaveBeenCalledWith(clearCart());
      expect(mocks.dispatch.mock.calls[0][0]).toEqual(clearCart());
    },
  );

  it("preserves cart items when order creation fails after payment", async () => {
    mocks.createOrder.mockResolvedValue({
      success: false,
      message: "Order could not be created",
    });
    const completed = vi.fn();
    const unsubscribe = subscribeCheckoutCompletion(completed);
    const result = await handleCheckout("stripePayment", {
      transaction_id: "pi_paid",
    });
    unsubscribe();
    expect(completed).not.toHaveBeenCalled();
    expect(result.success).toBe(false);
    expect(mocks.dispatch).not.toHaveBeenCalledWith(clearCart());
  });
});

describe("Mercado Pago order redirect", () => {
  it.each(["mercadoPagoPayment", "flutterwavePayment", "xenditPayment"])(
    "persists the %s transaction UUID before redirecting",
    async (method) => {
      mocks.createOrder.mockResolvedValue({
        success: true,
        data: {
          transaction_uuid: "transaction-42",
          order: null,
          payment_response: { link: "https://gateway.example/checkout" },
        },
      });

      const result = await handleCheckout(method, {});

      expect(result).toEqual({
        success: true,
        data: null,
        message: "Redirected",
      });
      expect(readPaymentReturn("7")?.transactionUuid).toBe("transaction-42");
      expect(window.location.href).toBe("https://gateway.example/checkout");
      expect(mocks.xenditCreateOrder).not.toHaveBeenCalled();
      expect(mocks.trackPurchase).not.toHaveBeenCalled();
      const body = mocks.createOrder.mock.calls[0][0];
      expect(body.get("amount")).toBe(
        method === "xenditPayment" ? "120" : null,
      );
    },
  );

  it.each(["mercadoPagoPayment", "flutterwavePayment", "xenditPayment"])(
    "opens the top-level link returned for %s payment intents",
    async (method) => {
      mocks.createOrder.mockResolvedValue({
        success: true,
        message: "Payment intent created successfully",
        data: {
          link: "https://gateway.example/hosted/pay/transaction-42",
          payment_url: "https://gateway.example/hosted/pay/transaction-42",
          checkout_url: "https://gateway.example/hosted/pay/transaction-42",
          transaction_uuid: "transaction-42",
          order: null,
        },
      });

      const result = await handleCheckout(method, {});

      expect(result.success).toBe(true);
      expect(window.location.href).toBe(
        "https://gateway.example/hosted/pay/transaction-42",
      );
      expect(mocks.toast).not.toHaveBeenCalled();
    },
  );

  it.each(["flutterwavePayment", "xenditPayment"])(
    "reports %s checkout failure when its payment link is missing",
    async (method) => {
      mocks.createOrder.mockResolvedValue(order(""));
      mocks.xenditCreateOrder.mockResolvedValue({ success: false, data: null });
      const result = await handleCheckout(method, {});
      expect(result.success).toBe(false);
      expect(window.location.href).toBe("https://shop.example/cart");
      expect(mocks.dispatch).not.toHaveBeenCalled();
    },
  );
  it("does not create a Xendit payment intent without a positive amount", async () => {
    const state = mocks.getState();
    mocks.getState.mockReturnValue({
      ...state,
      cart: {
        ...state.cart,
        cartData: {
          ...state.cart.cartData,
          payment_summary: { payable_amount: 0 },
        },
      },
    });

    const result = await handleCheckout("xenditPayment", {});

    expect(result.success).toBe(false);
    expect(result.message).toBe("Invalid Xendit payment amount");
    expect(mocks.createOrder).not.toHaveBeenCalled();
    expect(window.location.href).toBe("https://shop.example/cart");
  });
  it("blocks order creation and payment redirects for a saved address in another zone", async () => {
    mocks.checkDeliveryZone.mockResolvedValue({
      success: true,
      data: { is_deliverable: true, zone_id: 8 },
    });
    const result = await handleCheckout("mercadoPagoPayment", {});
    expect(result.success).toBe(false);
    expect(mocks.createOrder).not.toHaveBeenCalled();
    expect(window.location.href).toBe("https://shop.example/cart");
  });
  it("places the full multipart order once and opens the API link", async () => {
    const result = await handleCheckout("mercadoPagoPayment", {});
    expect(mocks.createOrder).toHaveBeenCalledOnce();
    const body = mocks.createOrder.mock.calls[0][0];
    expect(body).toBeInstanceOf(FormData);
    expect(body.get("payment_type")).toBe("mercadoPagoPayment");
    expect(body.get("address_id")).toBe("7");
    expect(body.get("order_note")).toBe("Leave at reception");
    expect(body.get("promo_code")).toBe("TEST");
    expect(body.get("use_wallet")).toBe("1");
    expect(body.get("use_loyalty_points")).toBe("1");
    expect(body.get("delivery_time_slot_id")).toBe("12");
    expect(body.get("scheduled_date")).toBe("2026-10-01");
    expect(body.get("redirect_url")).toBe(
      "https://storefront.example/my-account/orders",
    );
    expect(window.location.href).toBe(paymentLink);
    expect(result).toEqual({
      success: true,
      data: null,
      message: "Redirected",
    });
    expect(mocks.xenditCreateOrder).not.toHaveBeenCalled();
    expect(mocks.dispatch).toHaveBeenCalled();
    const checkoutAfter = mocks.dispatch.mock.calls.reduce(
      (state, [action]) => checkoutReducer(state, action),
      mocks.getState().checkout,
    );
    expect(checkoutAfter.orderNote).toBe("");
    expect(checkoutAfter.useLoyaltyPoints).toBe(false);
    expect(mocks.toast).not.toHaveBeenCalled();
  });

  it.each([
    "",
    null,
    "not a url",
    "javascript:alert(1)",
    "http://payment.example",
    "https://user:password@payment.example",
  ])(
    "does not redirect or clear checkout for an invalid link (%s)",
    async (link) => {
      mocks.createOrder.mockResolvedValue(order(link));
      const result = await handleCheckout("mercadoPagoPayment", {});
      expect(result.success).toBe(false);
      expect(window.location.href).toBe("https://shop.example/cart");
      expect(mocks.dispatch).not.toHaveBeenCalled();
      expect(mocks.toast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: "checkout.mercadoPago_link_invalid",
          color: "danger",
        }),
      );
      expect(mocks.createOrder).toHaveBeenCalledOnce();
    },
  );

  it("does not redirect after a rejected order", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    mocks.createOrder.mockResolvedValue({
      success: false,
      message: "Out of stock",
      data: null,
    });
    const result = await handleCheckout("mercadoPagoPayment", {});
    expect(result.success).toBe(false);
    expect(window.location.href).toBe("https://shop.example/cart");
    expect(mocks.dispatch).not.toHaveBeenCalled();
    vi.restoreAllMocks();
  });

  it.each(["flutterwavePayment", "xenditPayment"])(
    "preserves the existing %s redirect",
    async (method) => {
      mocks.createOrder.mockResolvedValue(
        order("https://gateway.example/checkout"),
      );
      await handleCheckout(method, {});
      expect(window.location.href).toBe("https://gateway.example/checkout");
    },
  );

  it("preserves Xendit's separate invoice request when its order has no link", async () => {
    mocks.createOrder.mockResolvedValue(order(""));
    mocks.xenditCreateOrder.mockResolvedValue({
      success: true,
      data: { invoice_url: "https://gateway.example/invoice" },
    });
    await handleCheckout("xenditPayment", {});
    expect(mocks.xenditCreateOrder).toHaveBeenCalledWith(
      expect.objectContaining({
        redirect_url: "https://storefront.example/my-account/orders",
      }),
    );
    expect(window.location.href).toBe("https://gateway.example/invoice");
  });
});
