import { describe, expect, it } from "vitest";
import { getPromoRequirements } from "@/helpers/promoEligibility";
import { PromoCode } from "@/types/ApiResponse";

const promo: PromoCode = {
  id: 1,
  code: "SAVE",
  description: "Save on your order",
  start_date: null,
  end_date: null,
  discount_type: "flat",
  discount_amount: "10",
  promo_mode: "instant",
  usage_count: 0,
  individual_use: 0,
  max_total_usage: null,
  max_usage_per_user: 1,
  min_order_total: "100.00",
  max_discount_value: null,
  deleted_at: null,
  created_at: "",
  updated_at: "",
};

describe("getPromoRequirements", () => {
  it("explains the shortfall and unlocks at the exact minimum", () => {
    expect(getPromoRequirements(promo, 75.5)).toEqual([
      { key: "minimumOrder", amount: "100.00", remaining: "24.50" },
    ]);
    expect(getPromoRequirements(promo, 100)).toEqual([]);
    expect(getPromoRequirements(promo, 150)).toEqual([]);
  });

  it.each([undefined, 0, -1, NaN])(
    "blocks an unavailable or empty cart (%s)",
    (amount) => {
      expect(getPromoRequirements(promo, amount)).toEqual([
        { key: "cartRequired" },
      ]);
    },
  );

  it("allows null dates and limits without guessing per-user usage", () => {
    expect(
      getPromoRequirements({ ...promo, min_order_total: null }, 10),
    ).toEqual([]);
  });

  it("checks start and expiry boundaries", () => {
    const dated = {
      ...promo,
      start_date: "2026-09-17T00:00:00Z",
      end_date: "2026-09-18T00:00:00Z",
    };
    const start = Date.parse(dated.start_date);
    const end = Date.parse(dated.end_date);
    expect(getPromoRequirements(dated, 100, start - 1)).toEqual([
      { key: "notStarted" },
    ]);
    expect(getPromoRequirements(dated, 100, start)).toEqual([]);
    expect(getPromoRequirements(dated, 100, end)).toEqual([]);
    expect(getPromoRequirements(dated, 100, end + 1)).toEqual([
      { key: "expired" },
    ]);
  });

  it("blocks exhausted usage including a zero limit, matching available promos", () => {
    expect(
      getPromoRequirements(
        { ...promo, max_total_usage: 1, usage_count: 1 },
        100,
      ),
    ).toEqual([{ key: "usageLimit" }]);
    expect(getPromoRequirements({ ...promo, max_total_usage: 0 }, 100)).toEqual(
      [{ key: "usageLimit" }],
    );
    expect(getPromoRequirements({ ...promo, max_total_usage: 1 }, 100)).toEqual(
      [],
    );
  });

  it("returns all unmet requirements", () => {
    expect(
      getPromoRequirements(
        {
          ...promo,
          deleted_at: "2026-09-17",
          max_total_usage: 1,
          usage_count: 1,
        },
        50,
      ).map(({ key }) => key),
    ).toEqual(["unavailable", "usageLimit", "minimumOrder"]);
  });
});
