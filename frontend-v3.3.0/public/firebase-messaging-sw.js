importScripts(
  "https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js",
);
importScripts(
  "https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js",
);

let messaging = null;

// Initialize Firebase dynamically when config is received
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "INIT_FIREBASE" && !messaging) {
    try {
      firebase.initializeApp(event.data.config);
      messaging = firebase.messaging();
      console.log("Service Worker: Firebase initialized successfully");
    } catch (error) {
      console.error("Firebase initialization failed:", error);
    }
  }
});

// Handle push events
self.addEventListener("push", (event) => {
  if (!event.data) {
    console.error("No data in push event");
    return;
  }

  const payload = event.data.json();
  const { title, body, icon, image } = payload.notification || {};
  const dataImage = getNotificationImageUrl(payload.data);
  const notificationOptions = {
    body: body || "You have a new message",
    icon: icon || "/favicon.ico",
    image: image || dataImage || "",
    data: payload.data || {},
    sound: "/sounds/notification.mp3",
  };
  event.waitUntil(
    self.registration.showNotification(
      title || "New Notification",
      notificationOptions,
    ),
  );

  // Send data to React frontend
  event.waitUntil(
    (async () => {
      const allClients = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true,
      });
      for (const client of allClients) {
        client.postMessage({
          type: "PUSH_EVENT",
          payload,
        });
      }
    })(),
  );
});

// Handle notification clicks
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const target = getNotificationUrl(event?.notification?.data);
  if (!target) return;
  const url = new URL(target, self.location.origin).href;
  event.waitUntil(
    (async () => {
      try {
        const allClients = await clients.matchAll({
          type: "window",
          includeUncontrolled: true,
        });
        const focused = allClients.find((client) => client.url === url);
        if (focused && "focus" in focused) {
          await focused.focus();
        } else {
          await clients.openWindow(url);
        }
      } catch (err) {
        console.error("Failed to open window:", err);
      }
    })(),
  );
});

function parseNotificationData(data) {
  if (data == null) return undefined;
  if (typeof data === "string") {
    try {
      const parsed = JSON.parse(data);
      return parsed && typeof parsed === "object" && !Array.isArray(parsed)
        ? parsed
        : undefined;
    } catch {
      return undefined;
    }
  }
  if (typeof data === "object" && !Array.isArray(data)) {
    return data;
  }
  return undefined;
}

const toStringValue = (value) => {
  if (typeof value === "string" && value.trim()) return value.trim();
  if (typeof value === "number" && !Number.isNaN(value)) return value.toString();
  return undefined;
};

const firstStringValue = (data, keys) => {
  if (!data) return undefined;
  for (const key of keys) {
    const value = toStringValue(data[key]);
    if (value) return value;
  }
  return undefined;
};

const normalizeType = (type) => (type ?? "").toString().trim().toLowerCase();

const getNotificationImageUrl = (data) => {
  const parsed = parseNotificationData(data);
  const image = firstStringValue(parsed, [
    "image",
    "image_url",
    "imageUrl",
    "thumbnail",
    "thumbnail_url",
    "icon",
  ]);
  if (!image || /[\\\u0000-\u001f\u007f]/.test(image)) return null;
  if (image.startsWith("/") && !image.startsWith("//")) return image;
  try {
    const url = new URL(image);
    return /^https?:$/.test(url.protocol) && !url.username && !url.password
      ? image
      : null;
  } catch {
    return null;
  }
};

const getOrderSlug = (data) => {
  const directSlug = firstStringValue(data, ["order_slug", "orderSlug", "slug"]);
  if (directSlug) return directSlug;

  const order = data?.order;
  return order && typeof order === "object" && !Array.isArray(order)
    ? firstStringValue(order, ["slug", "order_slug"])
    : undefined;
};

const getStoreSlug = (data) => {
  const directSlug = firstStringValue(data, ["store_slug", "storeSlug"]);
  if (directSlug) return directSlug;

  const store = data?.store;
  return store && typeof store === "object" && !Array.isArray(store)
    ? firstStringValue(store, ["slug", "store_slug"])
    : undefined;
};

const isOrderNotification = (type, data) =>
  type.includes("delivery") ||
  type.includes("order") ||
  Boolean(firstStringValue(data, ["order_slug", "orderSlug"])) ||
  Boolean(data?.order && typeof data.order === "object");

function getNotificationUrl(data) {
  const parsed = parseNotificationData(data);
  const type = normalizeType(parsed?.type);

  if (isOrderNotification(type, parsed)) {
    const orderSlug = getOrderSlug(parsed);
    return orderSlug
      ? `/my-account/orders/${encodeURIComponent(orderSlug)}`
      : "/my-account/orders";
  }

  const quickLink = firstStringValue(parsed, [
    "redirect_url",
    "url",
    "link",
    "target_url",
    "target",
  ]);
  if (quickLink && !/[\\\u0000-\u001f\u007f]/.test(quickLink)) {
    if (quickLink.startsWith("/") && !quickLink.startsWith("//")) return quickLink;
    try {
      const url = new URL(quickLink);
      if (/^https?:$/.test(url.protocol) && !url.username && !url.password) return quickLink;
    } catch {
      // Invalid links can still fall back to the notification's entity route.
    }
  }

  switch (type) {
    case "cart":
    case "cart_reminder":
    case "cart_abandoned":
    case "abandoned_cart":
    case "abandoned_cart_reminder":
      return "/cart";
    case "wallet":
    case "wallet_transaction":
    case "withdrawal":
    case "withdrawal_request":
    case "withdrawal_process":
      return "/my-account/wallet";
    case "product":
    case "product_update":
    case "product_launch":
    case "product_notification": {
      const productSlug = firstStringValue(parsed, ["product_slug", "slug", "productId", "product_id"]);
      return productSlug ? `/products/${productSlug}` : "/products/search/";
    }
    case "brand":
    case "brand_notification": {
      const brandSlug = firstStringValue(parsed, ["brand_slug", "slug", "brandId", "brand_id"]);
      return brandSlug ? `/brands/${brandSlug}` : "/brands";
    }
    case "category":
    case "category_notification": {
      const categorySlug = firstStringValue(parsed, ["category_slug", "slug", "categoryId", "category_id"]);
      return categorySlug ? `/categories/${categorySlug}` : "/categories";
    }
    case "store":
    case "store_notification":
    case "store_open":
    case "store_opened":
    case "store_online":
    case "store_back_online":
    case "store_status_updated": {
      const storeSlug = getStoreSlug(parsed) ?? firstStringValue(parsed, ["slug"]);
      return storeSlug ? `/stores/${encodeURIComponent(storeSlug)}` : "/stores";
    }
    case "feature_section":
    case "feature-section":
    case "featuresection":
    case "featured_section":
    case "featured-section":
    case "featuredsection": {
      const sectionSlug = firstStringValue(parsed, ["section_slug", "feature_section_slug", "slug"]);
      return sectionSlug ? `/feature-sections/${sectionSlug}` : "/feature-sections";
    }
    default: {
      const entityType = firstStringValue(parsed, ["entity_type", "target_type", "object_type"]);
      const entitySlug = firstStringValue(parsed, ["entity_slug", "target_slug", "object_slug", "slug"]);
      if (entityType && entitySlug) {
        if (entityType.includes("product")) return `/products/${entitySlug}`;
        if (entityType.includes("brand")) return `/brands/${entitySlug}`;
        if (entityType.includes("category")) return `/categories/${entitySlug}`;
        if (entityType.includes("store")) return `/stores/${entitySlug}`;
      }
      const storeSlug = getStoreSlug(parsed);
      return storeSlug ? `/stores/${encodeURIComponent(storeSlug)}` : null;
    }
  }
}

