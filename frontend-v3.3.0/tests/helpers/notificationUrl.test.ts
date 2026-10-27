import { describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";
import {
  getNotificationImageUrl,
  getNotificationRedirectUrl as foregroundRedirect,
  type NotificationData,
} from "@/helpers/notificationUrl";

describe("notification images", () => {
  it.each([
    [{ image: "https://cdn.example.com/order.webp" }, "https://cdn.example.com/order.webp"],
    [{ image_url: "/images/order.png" }, "/images/order.png"],
    ['{"thumbnail_url":"https://cdn.example.com/thumb.jpg"}', "https://cdn.example.com/thumb.jpg"],
  ])("reads supported image payloads", (data, expected) => {
    expect(getNotificationImageUrl(data)).toBe(expected);
  });

  it.each(["javascript:alert(1)", "//other.example/image.png", "not a url"])(
    "rejects an unsafe image URL (%s)",
    (image) => {
      expect(getNotificationImageUrl({ image })).toBeNull();
    },
  );
});

const listeners = new Map<string, (event: unknown) => void>();
const focus = vi.fn();
const openWindow = vi.fn();
const worker = {
  importScripts: vi.fn(),
  URL,
  self: {
    location: { origin: "https://shop.example" },
    addEventListener: (name: string, callback: (event: unknown) => void) =>
      listeners.set(name, callback),
  },
  clients: {
    matchAll: async () => [
      { url: "https://shop.example/my-account/orders/123", focus },
    ],
    openWindow,
  },
  getNotificationUrl: undefined as unknown as (
    data: NotificationData,
  ) => string | null,
};
runInNewContext(
  readFileSync("public/firebase-messaging-sw.js", "utf8"),
  worker,
);
const backgroundRedirect = (data?: NotificationData, type?: string) =>
  worker.getNotificationUrl(
    type ? { ...(typeof data === "object" ? data : {}), type } : data,
  );

describe.each([
  ["foreground", foregroundRedirect],
  ["background", backgroundRedirect],
] as const)(
  "%s notification destinations",
  (_name, getNotificationRedirectUrl) => {
    it.each(["cart", "cart_reminder", "cart_abandoned", "abandoned_cart", "abandoned_cart_reminder"])(
      "opens the cart for %s notifications without metadata",
      (type) => {
        expect(getNotificationRedirectUrl(null, type)).toBe("/cart");
      },
    );
    it.each(["store", "store_notification", "store_open", "store_opened", "store_online", "store_back_online", "store_status_updated"])(
      "opens the store detail page for %s notifications",
      (type) => {
        expect(getNotificationRedirectUrl({ store_slug: "beauty-essentials-7", store_id: 7 }, type))
          .toBe("/stores/beauty-essentials-7");
      },
    );
    it("opens stores from generic system notifications and nested store data", () => {
      expect(getNotificationRedirectUrl('{"type":"system","store_slug":"beauty-essentials-7"}'))
        .toBe("/stores/beauty-essentials-7");
      expect(getNotificationRedirectUrl({ store: { slug: "api-test-store-2" } }, "store_online"))
        .toBe("/stores/api-test-store-2");
    });
    it("encodes store slugs and avoids using numeric IDs as slugs", () => {
      expect(getNotificationRedirectUrl({ store_slug: "store/7?offer" }, "store_online"))
        .toBe("/stores/store%2F7%3Foffer");
      expect(getNotificationRedirectUrl({ store_id: 7 }, "store_online"))
        .toBe("/stores");
    });
    it("preserves an explicit entity destination when store metadata is also present", () => {
      expect(getNotificationRedirectUrl({
        entity_type: "product",
        entity_slug: "fresh-milk",
        store_slug: "beauty-essentials-7",
      }, "general")).toBe("/products/fresh-milk");
    });
    it("opens product search when a product notification omits its slug", () => {
      expect(getNotificationRedirectUrl({}, "product_notification")).toBe(
        "/products/search/",
      );
    });
    it("reads JSON metadata and preserves entity destinations", () => {
      expect(
        getNotificationRedirectUrl(
          '{"type":"order_update","order_slug":"ORD-2026-001"}',
        ),
      ).toBe("/my-account/orders/ORD-2026-001");
      expect(
        getNotificationRedirectUrl({ product_slug: "fresh-milk" }, "product"),
      ).toBe("/products/fresh-milk");
    });
    it.each(["feature_section", "featured_section", "featured-section"])(
      "opens a %s destination",
      (type) => {
        expect(
          getNotificationRedirectUrl({ slug: "daily-favourites" }, type),
        ).toBe("/feature-sections/daily-favourites");
      },
    );
    it("uses the order slug for order-status notification variants", () => {
      expect(
        getNotificationRedirectUrl(
          { slug: "fresh-order-42", order_id: 123 },
          "order_status_updated",
        ),
      ).toBe("/my-account/orders/fresh-order-42");
      expect(
        getNotificationRedirectUrl(
          { order: { slug: "nested-order-slug" } },
          "delivery_status_update",
        ),
      ).toBe("/my-account/orders/nested-order-slug");
    });
    it("does not send numeric order IDs to the slug detail route", () => {
      expect(
        getNotificationRedirectUrl({ order_id: 123 }, "order_update"),
      ).toBe("/my-account/orders");
    });
    it.each([null, undefined, "null", "[]", "false", "broken JSON"])(
      "handles malformed or empty metadata (%s)",
      (data) => {
        expect(getNotificationRedirectUrl(data)).toBeNull();
      },
    );
    it.each(["/my-account/orders/123", "https://shop.example/offers"])(
      "accepts navigable links (%s)",
      (url) => {
        expect(getNotificationRedirectUrl({ url })).toBe(url);
      },
    );
    it.each([
      "javascript:alert(1)",
      "data:text/html,test",
      "//other.example",
      "/\\other.example",
      "https://user:secret@example.com",
      "not a link",
    ])("ignores invalid links and uses the entity fallback (%s)", (url) => {
      expect(getNotificationRedirectUrl({ url, type: "wallet" })).toBe(
        "/my-account/wallet",
      );
    });
  },
);

it("focuses an existing order tab when a background notification uses a relative URL", async () => {
  let completed: Promise<unknown> | undefined;
  listeners.get("notificationclick")?.({
    notification: {
      close: vi.fn(),
      data: { type: "order_update", order_slug: "123" },
    },
    waitUntil: (promise: Promise<unknown>) => {
      completed = promise;
    },
  });
  await completed;
  expect(focus).toHaveBeenCalledOnce();
  expect(openWindow).not.toHaveBeenCalled();
});
