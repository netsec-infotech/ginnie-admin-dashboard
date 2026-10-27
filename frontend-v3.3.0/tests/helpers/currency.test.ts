import { describe, expect, it } from "vitest";
import {
  canCheckoutCurrency,
  canUseWallet,
  canUseCheckoutWallet,
  isCheckoutPaymentMethodAvailable,
  formatMoney,
  gatewayCurrency,
  getCurrencyCode,
  guestCurrencyTotals,
  isPaymentMethodAvailable,
  money,
  lineCurrency,
  productAmount,
  productPrice,
  productPriceFields,
} from "@/helpers/currency";
import offlineCartReducer, {
  setOfflineCart,
  type OfflineCartItem,
} from "@/lib/redux/slices/offlineCartSlice";
import { generateProductSchema } from "@/helpers/seo";
import type { Product } from "@/types/ApiResponse";

describe("record money", () => {
  const order = {
    currency_code: "AED",
    base_currency_code: "USD",
    subtotal: "100",
    formatted_subtotal: "AED د.إ 100.00",
    currency_snapshot: { formatted: { subtotal: "snapshot subtotal" } },
  };
  it("uses backend formatting verbatim without prepending the global symbol", () => {
    expect(money(order, "subtotal", "$")).toBe("AED د.إ 100.00");
  });
  it("uses the historical snapshot before raw amounts", () => {
    expect(money({ ...order, formatted_subtotal: null }, "subtotal", "₹")).toBe(
      "snapshot subtotal",
    );
  });
  it("uses record currency rather than accounting currency or the global symbol", () => {
    expect(
      money(
        { ...order, formatted_subtotal: null, currency_snapshot: null },
        "subtotal",
        "$",
      ),
    ).toBe("AED 100.00");
  });
  it("inherits order currency for line items and refunds", () => {
    expect(money({ refund_amount: 25 }, "refund_amount", "$", order)).toBe(
      "AED 25.00",
    );
  });
  it("uses variant currency when a saved cart item has no top-level currency", () => {
    const item = { total_item_price: 40, variant: { currency_code: "USD" } };
    expect(money(item, "total_item_price", "₹", lineCurrency(item))).toBe(
      "USD 40.00",
    );
  });
  it("falls back to the legacy symbol only when no record currency exists", () => {
    expect(money({ amount: 12.5 }, "amount", "₹")).toBe("₹12.50");
  });
  it("respects the currency precision without exchanging the amount", () => {
    expect(formatMoney(1250, { currency_code: "JPY" })).toBe("JPY 1,250");
    expect(formatMoney(1.125, { currency_code: "KWD" })).toBe("KWD 1.125");
  });
  it("keeps transaction and gateway currencies distinct", () => {
    const transaction = {
      currency: "AED",
      gateway_currency_code: "USD",
      formatted_amount: "AED د.إ 100.00",
      formatted_gateway_amount: "USD $ 27.23",
    };
    expect(getCurrencyCode(transaction)).toBe("AED");
    expect(gatewayCurrency(transaction)).toBe("USD");
    expect(money(transaction, "amount")).toBe("AED د.إ 100.00");
    expect(money(transaction, "gateway_amount")).toBe("USD $ 27.23");
    expect(
      money(
        {
          currency: "AED",
          gateway_currency_code: "USD",
          gateway_amount: 27.23,
        },
        "gateway_amount",
      ),
    ).toBe("USD 27.23");
  });
  it("prefers store display prices, including tax", () => {
    const variant = {
      currency_code: "AED",
      price: 100,
      special_price: 90,
      display_price: 105,
      display_special_price: 95,
      formatted_display_special_price: "AED د.إ 95.00",
    };
    expect(productPrice(variant, "special_price", "$")).toBe("AED د.إ 95.00");
    expect(productAmount(variant)).toBe(95);
    expect(productAmount(variant, false)).toBe(105);
    expect(productAmount({ ...variant, special_price: 0 })).toBe(105);
    expect(productPriceFields(variant)).toEqual({
      price: 95,
      currency_code: "AED",
      formatted_price: "AED د.إ 95.00",
    });
  });
});

describe("currency checkout policy", () => {
  it("uses cart eligibility before settings and falls back only when the list is missing", () => {
    expect(isCheckoutPaymentMethodAvailable({}, "stripe")).toBe(false);
    expect(isCheckoutPaymentMethodAvailable({}, "stripe", {stripePayment: true})).toBe(true);
    expect(isCheckoutPaymentMethodAvailable({}, "stripePayment", {stripePayment: false})).toBe(false);
    expect(isCheckoutPaymentMethodAvailable({}, "razorpay", {stripePayment: true})).toBe(false);
    expect(isCheckoutPaymentMethodAvailable(null, "stripe", {stripePayment: true})).toBe(false);
    expect(isCheckoutPaymentMethodAvailable({available_payment_methods: null}, "stripe", {stripePayment: true})).toBe(true);
    const cart = {currency_code: "USD", currency_codes: ["USD"], available_payment_methods: [
      {key: "stripePayment", name: "stripe", is_enabled: true, is_available: true},
      {key: "razorpayPayment", is_enabled: true, is_available: false},
      {key: "cod", is_enabled: false, is_available: true},
    ]};
    expect(isCheckoutPaymentMethodAvailable(cart, "stripe")).toBe(true);
    expect(isCheckoutPaymentMethodAvailable(cart, "stripe", {stripePayment: false})).toBe(true);
    expect(isCheckoutPaymentMethodAvailable(cart, "razorpay")).toBe(false);
    expect(isCheckoutPaymentMethodAvailable(cart, "razorpay", {razorpayPayment: true})).toBe(false);
    expect(isCheckoutPaymentMethodAvailable({...cart, available_payment_methods: []}, "stripe", {stripePayment: true})).toBe(false);
    expect(isCheckoutPaymentMethodAvailable(cart, "cod")).toBe(false);
    expect(isCheckoutPaymentMethodAvailable(cart, "paystack")).toBe(false);
    expect(isCheckoutPaymentMethodAvailable({...cart, currency_codes: ["USD", "INR"]}, "stripe")).toBe(false);
  });
  it("uses backend wallet validation when a legacy profile omits wallet currency", () => {
    const cart = {currency_code: "USD", currency_codes: ["USD"], available_payment_methods: [{key: "wallet", is_enabled: true, is_available: true}]};
    expect(canUseCheckoutWallet(cart, {})).toBe(true);
    expect(canUseCheckoutWallet(cart, {currency_code: "USD"})).toBe(true);
    expect(canUseCheckoutWallet(cart, {currency_code: "INR"})).toBe(false);
    expect(canUseCheckoutWallet({...cart, available_payment_methods: []}, {})).toBe(false);
  });
  it("preserves the original settings and wallet currency checks for older cart responses", () => {
    expect(canUseCheckoutWallet({}, {}, {wallet: true})).toBe(true);
    expect(canUseCheckoutWallet({}, {}, {wallet: false})).toBe(false);
    const cart = {currency_code: "USD", currency_codes: ["USD"]};
    expect(canUseCheckoutWallet(cart, {currency_code: "USD"}, {wallet: true})).toBe(true);
    expect(canUseCheckoutWallet(cart, {}, {wallet: true})).toBe(false);
    expect(canUseCheckoutWallet(cart, {currency_code: "INR"}, {wallet: true})).toBe(false);
  });
  it.each([
    [null, false],
    [{ currency_code: "AED" }, false],
    [{ currency_code: "USD", currency_codes: ["AED"] }, false],
    [{ currency_code: "AED", currency_codes: ["AED"] }, true],
    [{ currency_code: null, currency_codes: ["AED", "USD"] }, false],
    [
      {
        currency_code: null,
        currency_snapshot: { order_currency_code: "AED" },
      },
      false,
    ],
    [{ currency_code: "AED", currency_codes: [] }, false],
    [{ currency_code: "AED", currency_codes: ["AED", "USD"] }, false],
    [{}, true],
  ])("validates cart context %j", (cart, expected) => {
    expect(canCheckoutCurrency(cart)).toBe(expected);
  });
  it("treats contextual availability as final, including an empty list", () => {
    expect(
      isPaymentMethodAvailable(
        { available_payment_methods: [] },
        "stripePayment",
      ),
    ).toBe(false);
    expect(isPaymentMethodAvailable({}, "stripePayment")).toBe(true);
    expect(
      isPaymentMethodAvailable(
        {
          available_payment_methods: [
            {
              key: "razorpay",
              is_enabled: true,
              is_available: true,
              currency_code: "AED",
            },
            { key: "stripe", is_enabled: true, is_available: false },
          ],
        },
        "razorpayPayment",
      ),
    ).toBe(true);
  });
  it("does not compare or spend a wallet in another or unknown currency", () => {
    const cart = { currency_code: "AED" };
    expect(canUseWallet(cart, { currency_code: "USD" })).toBe(false);
    expect(canUseWallet(cart, {})).toBe(false);
    expect(canUseWallet(cart, { currency_code: "AED" })).toBe(true);
    expect(
      canUseWallet(
        { ...cart, available_payment_methods: [] },
        { currency_code: "AED" },
      ),
    ).toBe(false);
  });
});

it("keeps guest cart totals separate and never produces a combined mixed-currency subtotal", () => {
  const item = {
    id: "1",
    price: 10,
    quantity: 2,
    minQuantity: 1,
    maxQuantity: 10,
    stepSize: 1,
    stock: 10,
  } as OfflineCartItem;
  const items = [
    { ...item, currency_code: "AED" },
    {
      ...item,
      id: "2",
      currency_code: "USD",
      addons: [{ price: 5 }],
    } as OfflineCartItem,
  ];
  expect(guestCurrencyTotals(items)).toEqual([
    { currency_code: "AED", amount: 20 },
    { currency_code: "USD", amount: 30 },
  ]);
  const state = offlineCartReducer(undefined, setOfflineCart(items));
  expect(state.items).toHaveLength(2);
  expect(state.totalQuantity).toBe(4);
  expect(state.subtotal).toBe(0);
});

it("emits separate SEO offers for stores in different currencies", () => {
  const product = {
    slug: "rice",
    variants: [
      {
        currency_code: "AED",
        price: 100,
        special_price: 0,
        display_price: 105,
        stock: 1,
      },
      { currency_code: "USD", price: 30, special_price: 25, stock: 1 },
    ],
  } as unknown as Product;
  const schema = generateProductSchema(
    product,
    "https://example.com",
    "INR",
  ) as { offers: unknown };
  expect(schema.offers).toMatchObject([
    { priceCurrency: "AED", price: 105 },
    { priceCurrency: "USD", price: 25 },
  ]);
});
