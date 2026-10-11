import { describe, it, expect } from "vitest";
import { getItemOtps } from "@/helpers/orderOtp";
import { OrderItem } from "@/types/ApiResponse";
import orderWithReturn from "../fixtures/orderWithReturn.json";

// Real order pulled from the API: item 1747 has an approved return whose
// pickup OTP is 811614 and has not been verified yet.
const liveItem = (orderWithReturn as any).data.items[0] as OrderItem;

const makeItem = (over: Partial<OrderItem> = {}): OrderItem =>
  ({ id: 1, otp: null, otp_verified: 0, returns: [], ...over }) as OrderItem;

const makeReturn = (over: Record<string, any> = {}) =>
  ({
    id: 1,
    order_item_id: 1,
    pickup_otp: "111111",
    pickup_otp_verified: false,
    ...over,
  }) as any;

describe("getItemOtps — live order fixture", () => {
  it("surfaces the return pickup OTP that the UI previously never showed", () => {
    expect(getItemOtps(liveItem).returnPickupOtp).toBe("811614");
  });

  it("has no delivery OTP on this item (the API sent otp: null)", () => {
    expect(liveItem.otp).toBeNull();
    expect(getItemOtps(liveItem).deliveryOtp).toBeNull();
  });
});

describe("getItemOtps — delivery OTP", () => {
  it("returns the delivery OTP while it is unverified", () => {
    const otps = getItemOtps(makeItem({ otp: "123456", otp_verified: 0 }));
    expect(otps.deliveryOtp).toBe("123456");
  });

  it("hides a delivery OTP once verified (it is spent)", () => {
    const otps = getItemOtps(makeItem({ otp: "123456", otp_verified: 1 }));
    expect(otps.deliveryOtp).toBeNull();
  });

  it("is null when the item has no delivery OTP", () => {
    expect(getItemOtps(makeItem()).deliveryOtp).toBeNull();
  });
});

describe("getItemOtps — return pickup OTP", () => {
  it("returns the pickup OTP of an active return", () => {
    const item = makeItem({ returns: [makeReturn({ pickup_otp: "811614" })] });
    expect(getItemOtps(item).returnPickupOtp).toBe("811614");
  });

  it("hides the pickup OTP once verified", () => {
    const item = makeItem({
      returns: [makeReturn({ pickup_otp: "811614", pickup_otp_verified: true })],
    });
    expect(getItemOtps(item).returnPickupOtp).toBeNull();
  });

  it("is null when the return carries no pickup OTP yet", () => {
    const item = makeItem({ returns: [makeReturn({ pickup_otp: null })] });
    expect(getItemOtps(item).returnPickupOtp).toBeNull();
  });

  it("picks the newest unverified return when an item was returned twice", () => {
    const item = makeItem({
      returns: [
        makeReturn({ id: 10, pickup_otp: "111111" }),
        makeReturn({ id: 42, pickup_otp: "999999" }),
      ],
    });
    expect(getItemOtps(item).returnPickupOtp).toBe("999999");
  });

  it("skips a verified return in favour of the active one", () => {
    const item = makeItem({
      returns: [
        makeReturn({ id: 50, pickup_otp: "555555", pickup_otp_verified: true }),
        makeReturn({ id: 20, pickup_otp: "222222" }),
      ],
    });
    expect(getItemOtps(item).returnPickupOtp).toBe("222222");
  });

  it("is null when there are no returns", () => {
    expect(getItemOtps(makeItem({ returns: [] })).returnPickupOtp).toBeNull();
  });
});

describe("getItemOtps — OTPs are per item, not per product", () => {
  it("resolves independent codes for two items of the same product", () => {
    const itemA = makeItem({
      id: 1,
      otp: "111111",
      returns: [makeReturn({ id: 1, pickup_otp: "AAA111" })],
    });
    const itemB = makeItem({
      id: 2,
      otp: "222222",
      returns: [makeReturn({ id: 2, pickup_otp: "BBB222" })],
    });

    expect(getItemOtps(itemA)).toEqual({
      deliveryOtp: "111111",
      returnPickupOtp: "AAA111",
    });
    expect(getItemOtps(itemB)).toEqual({
      deliveryOtp: "222222",
      returnPickupOtp: "BBB222",
    });
  });

  it("survives a nullish item / missing returns array", () => {
    expect(getItemOtps(undefined)).toEqual({
      deliveryOtp: null,
      returnPickupOtp: null,
    });
    expect(getItemOtps({ id: 1 } as OrderItem)).toEqual({
      deliveryOtp: null,
      returnPickupOtp: null,
    });
  });
});
