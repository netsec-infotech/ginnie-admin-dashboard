import { describe, expect, it } from "vitest";
import { canCancelOrderItem } from "@/helpers/orderCancellation";

const product = { is_cancelable: 1, cancelable_till: "preparing" };

describe("order item cancellation", () => {
  it.each([
    "pending",
    "awaiting_store_response",
    "accepted",
    "approved",
    "preparing",
  ])("allows cancellation through Preparing at %s", (status) => {
    expect(canCancelOrderItem({ status, product }, status)).toBe(true);
  });
  it.each([
    "ready_for_pickup",
    "assigned",
    "out_for_delivery",
    "delivered",
    "cancelled",
    "returned",
    "rejected_by_seller",
    "unknown",
  ])("does not offer cancellation at %s", (status) => {
    expect(canCancelOrderItem({ status, product }, status)).toBe(false);
  });
  it("uses each item's progress in a mixed-store order", () => {
    expect(
      canCancelOrderItem({ status: "accepted", product }, "partially_accepted"),
    ).toBe(true);
    expect(
      canCancelOrderItem(
        { status: "cancelled", product },
        "partially_accepted",
      ),
    ).toBe(false);
    expect(
      canCancelOrderItem(
        { status: "out_for_delivery", product },
        "partially_accepted",
      ),
    ).toBe(false);
  });
  it("honours an earlier cutoff and disabled or missing policies", () => {
    expect(
      canCancelOrderItem(
        {
          status: "preparing",
          product: { ...product, cancelable_till: "accepted" },
        },
        "preparing",
      ),
    ).toBe(false);
    expect(
      canCancelOrderItem(
        { product: { ...product, is_cancelable: "0" } },
        "pending",
      ),
    ).toBe(false);
    expect(canCancelOrderItem({}, "pending")).toBe(false);
    expect(canCancelOrderItem({ product }, "cancelled")).toBe(false);
  });
});
