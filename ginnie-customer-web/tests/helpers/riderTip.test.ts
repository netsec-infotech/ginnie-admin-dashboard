import { describe, expect, it } from "vitest";
import {
  canTipOrder,
  getTipPaymentMethods,
  getTipPresets,
  shouldShowOrderTipBox,
  validateTipAmount,
} from "@/helpers/riderTip";
import { Order, PaymentSettings, TippingConfig } from "@/types/ApiResponse";

const config: TippingConfig = {
  enable_rider_tips: true,
  min_tip_amount: 5,
  preset_1: 10,
  preset_2: 20,
  preset_3: 50,
  max_tip_amount: 500,
};

describe("canTipOrder", () => {
  it.each([
    "pending",
    "awaiting_store_response",
    "partially_accepted",
    "preparing",
    "ready_for_pickup",
    "assigned",
    "out_for_delivery",
  ])("allows tipping while the order is active (%s)", (status) => {
    expect(canTipOrder(status)).toBe(true);
  });

  it.each(["delivered", "cancelled"])(
    "blocks tipping once the lifecycle completes (%s)",
    (status) => {
      expect(canTipOrder(status)).toBe(false);
    },
  );

  it("blocks statuses this client doesn't model (backend emits extras)", () => {
    expect(canTipOrder("rejected_by_seller")).toBe(false);
    expect(canTipOrder("")).toBe(false);
  });
});

describe("shouldShowOrderTipBox", () => {
  const order = (overrides: Partial<Order>): Order =>
    ({ status: "preparing", tipping_config: config, ...overrides }) as Order;

  it("shows for an active order with tipping enabled", () => {
    expect(shouldShowOrderTipBox(order({}))).toBe(true);
  });

  it("hides when the backend disables tipping", () => {
    expect(
      shouldShowOrderTipBox(
        order({ tipping_config: { ...config, enable_rider_tips: false } }),
      ),
    ).toBe(false);
  });

  it("hides when the backend doesn't send a config (older backends)", () => {
    expect(shouldShowOrderTipBox(order({ tipping_config: undefined }))).toBe(
      false,
    );
  });

  it("hides on delivered orders even though the API would accept a tip", () => {
    expect(shouldShowOrderTipBox(order({ status: "delivered" }))).toBe(false);
  });
});

describe("getTipPresets", () => {
  it("returns the three presets in order", () => {
    expect(getTipPresets(config)).toEqual([10, 20, 50]);
  });

  it("drops zeroed presets", () => {
    expect(getTipPresets({ ...config, preset_2: 0 })).toEqual([10, 50]);
  });
});

describe("validateTipAmount", () => {
  it("accepts amounts inside the configured range (bounds inclusive)", () => {
    expect(validateTipAmount(5, config)).toBe("ok");
    expect(validateTipAmount(500, config)).toBe("ok");
  });

  it("flags amounts outside the range", () => {
    expect(validateTipAmount(4.99, config)).toBe("below_min");
    expect(validateTipAmount(500.01, config)).toBe("above_max");
  });

  it("rejects zero, negatives and non-numbers", () => {
    expect(validateTipAmount(0, config)).toBe("invalid");
    expect(validateTipAmount(-10, config)).toBe("invalid");
    expect(validateTipAmount(NaN, config)).toBe("invalid");
  });
});

describe("getTipPaymentMethods", () => {
  const settings = (overrides: Partial<PaymentSettings>): PaymentSettings =>
    ({
      wallet: false,
      razorpayPayment: false,
      stripePayment: false,
      paystackPayment: false,
      flutterwavePayment: false,
      cod: true,
      ...overrides,
    }) as PaymentSettings;

  it("maps enabled gateways to the short names the tip endpoint expects", () => {
    expect(
      getTipPaymentMethods(
        settings({
          wallet: true,
          razorpayPayment: true,
          stripePayment: true,
          paystackPayment: true,
        }),
      ),
    ).toEqual(["wallet", "razorpay", "stripe", "paystack"]);
  });

  it("never offers COD even when checkout has it enabled", () => {
    expect(getTipPaymentMethods(settings({ cod: true }))).toEqual([]);
  });

  it("excludes Flutterwave — redirect-only, no standalone tip collection", () => {
    // The tip endpoint records without charging; Flutterwave's payment link
    // only exists during order creation, so listing it would record tips
    // nobody paid for.
    expect(
      getTipPaymentMethods(settings({ flutterwavePayment: true })),
    ).toEqual([]);
  });

  it("returns nothing without settings", () => {
    expect(getTipPaymentMethods(null)).toEqual([]);
  });
});
