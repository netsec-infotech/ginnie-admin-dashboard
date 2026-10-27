import { beforeEach, describe, expect, it, vi } from "vitest";
import type { FirebaseInstance } from "@/lib/firebase";
import type { OrderItem } from "@/types/ApiResponse";

const mocks = vi.hoisted(() => ({
  logEvent: vi.fn(),
  sendGoogleAnalyticsEvent: vi.fn(),
  setGoogleAnalyticsUserId: vi.fn(),
  setGoogleAnalyticsUserProperties: vi.fn(),
}));

vi.mock("firebase/analytics", () => ({
  logEvent: mocks.logEvent,
  setUserId: vi.fn(),
  setUserProperties: vi.fn(),
}));

vi.mock("@/lib/googleAnalytics", () => ({
  sendGoogleAnalyticsEvent: mocks.sendGoogleAnalyticsEvent,
  setGoogleAnalyticsUserId: mocks.setGoogleAnalyticsUserId,
  setGoogleAnalyticsUserProperties: mocks.setGoogleAnalyticsUserProperties,
}));

describe("Firebase and Google Analytics event mirroring", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.stubEnv("NODE_ENV", "production");
  });

  it("sends a custom event to both independent analytics streams", async () => {
    const { setFirebaseInstance, trackLogin } = await import("@/lib/analytics");
    const analytics = {};
    setFirebaseInstance({
      analytics,
    } as FirebaseInstance);

    trackLogin("google");

    expect(mocks.sendGoogleAnalyticsEvent).toHaveBeenCalledWith("login", {
      method: "google",
    });
    expect(mocks.logEvent).toHaveBeenCalledWith(analytics, "login", {
      method: "google",
    });
  });

  it("keeps manual page views Firebase-only to avoid duplicating GA4 history tracking", async () => {
    const { setFirebaseInstance, trackPageView } =
      await import("@/lib/analytics");
    const analytics = {};
    setFirebaseInstance({
      analytics,
    } as FirebaseInstance);

    trackPageView("/brands", "Brands");

    expect(mocks.sendGoogleAnalyticsEvent).not.toHaveBeenCalled();
    expect(mocks.logEvent).toHaveBeenCalledWith(analytics, "page_view", {
      page_path: "/brands",
      page_title: "Brands",
    });
  });

  it("sends the same purchase payload to both streams", async () => {
    const { setFirebaseInstance, trackPurchase } =
      await import("@/lib/analytics");
    const analytics = {};
    setFirebaseInstance({
      analytics,
    } as FirebaseInstance);
    const orderItems = [
      {
        sku: "TEA-42",
        product_id: 42,
        title: "Green Tea",
        store: { name: "Hyperlocal Store" },
        seller_name: "Hyperlocal Store",
        promo_discount: "0",
        discount: "0",
        product_variant_id: 7,
        product: { name: "Green Tea" },
        variant_title: "250g",
        store_id: 3,
        price: "125",
        quantity: 2,
        tax_amount: "10",
      },
    ] as unknown as OrderItem[];

    trackPurchase("ORDER-1", 260, "INR", "", 0, orderItems);

    const expectedPayload = expect.objectContaining({
      transaction_id: "ORDER-1",
      value: 260,
      currency: "INR",
      items: [
        expect.objectContaining({
          item_id: "TEA-42",
          item_name: "Green Tea",
          price: 125,
          quantity: 2,
        }),
      ],
    });
    expect(mocks.sendGoogleAnalyticsEvent).toHaveBeenCalledWith(
      "purchase",
      expectedPayload,
    );
    expect(mocks.logEvent).toHaveBeenCalledWith(
      analytics,
      "purchase",
      expectedPayload,
    );
  });
});
