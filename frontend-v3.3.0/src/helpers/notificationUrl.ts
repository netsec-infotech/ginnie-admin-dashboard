export type NotificationData = Record<string, unknown> | string | null | undefined;

export const parseNotificationData = (data?: NotificationData): Record<string, unknown> | undefined => {
  if (data == null) return undefined;
  if (typeof data === "string") {
    try {
      const parsed: unknown = JSON.parse(data);
      return parsed && typeof parsed === "object" && !Array.isArray(parsed)
        ? parsed as Record<string, unknown>
        : undefined;
    } catch {
      return undefined;
    }
  }
  if (typeof data === "object" && !Array.isArray(data)) {
    return data as Record<string, unknown>;
  }
  return undefined;
};

const toStringValue = (value: unknown): string | undefined => {
  if (typeof value === "string" && value.trim()) return value.trim();
  if (typeof value === "number" && !Number.isNaN(value)) return value.toString();
  return undefined;
};

const firstStringValue = (
  data: Record<string, unknown> | undefined,
  keys: readonly string[],
): string | undefined => {
  if (!data) return undefined;
  for (const key of keys) {
    const value = toStringValue(data[key]);
    if (value) return value;
  }
  return undefined;
};

const normalizeType = (type?: string): string => (type ?? "").toString().trim().toLowerCase();

export const getNotificationImageUrl = (
  rawData?: NotificationData,
): string | null => {
  const data = parseNotificationData(rawData);
  const image = firstStringValue(data, [
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

const getOrderSlug = (
  data: Record<string, unknown> | undefined,
): string | undefined => {
  const directSlug = firstStringValue(data, ["order_slug", "orderSlug", "slug"]);
  if (directSlug) return directSlug;

  const order = data?.order;
  return order && typeof order === "object" && !Array.isArray(order)
    ? firstStringValue(order as Record<string, unknown>, ["slug", "order_slug"])
    : undefined;
};

const getStoreSlug = (
  data: Record<string, unknown> | undefined,
): string | undefined => {
  const directSlug = firstStringValue(data, ["store_slug", "storeSlug"]);
  if (directSlug) return directSlug;

  const store = data?.store;
  return store && typeof store === "object" && !Array.isArray(store)
    ? firstStringValue(store as Record<string, unknown>, ["slug", "store_slug"])
    : undefined;
};

const isOrderNotification = (type: string, data?: Record<string, unknown>) =>
  type.includes("delivery") ||
  type.includes("order") ||
  Boolean(firstStringValue(data, ["order_slug", "orderSlug"])) ||
  Boolean(data?.order && typeof data.order === "object");

export const getNotificationRedirectUrl = (
  rawData?: NotificationData,
  notificationType?: string,
): string | null => {
  const data = parseNotificationData(rawData);
  const type = normalizeType(notificationType ?? (data?.type as string | undefined));

  if (isOrderNotification(type, data)) {
    const orderSlug = getOrderSlug(data);
    return orderSlug
      ? `/my-account/orders/${encodeURIComponent(orderSlug)}`
      : "/my-account/orders";
  }

  const quickLink = firstStringValue(data, [
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
      const productSlug = firstStringValue(data, ["product_slug", "slug", "productId", "product_id"]);
      return productSlug ? `/products/${productSlug}` : "/products/search/";
    }
    case "brand":
    case "brand_notification": {
      const brandSlug = firstStringValue(data, ["brand_slug", "slug", "brandId", "brand_id"]);
      return brandSlug ? `/brands/${brandSlug}` : "/brands";
    }
    case "category":
    case "category_notification": {
      const categorySlug = firstStringValue(data, ["category_slug", "slug", "categoryId", "category_id"]);
      return categorySlug ? `/categories/${categorySlug}` : "/categories";
    }
    case "store":
    case "store_notification":
    case "store_open":
    case "store_opened":
    case "store_online":
    case "store_back_online":
    case "store_status_updated": {
      const storeSlug = getStoreSlug(data) ?? firstStringValue(data, ["slug"]);
      return storeSlug ? `/stores/${encodeURIComponent(storeSlug)}` : "/stores";
    }
    case "feature_section":
    case "feature-section":
    case "featuresection":
    case "featured_section":
    case "featured-section":
    case "featuredsection": {
      const sectionSlug = firstStringValue(data, ["section_slug", "feature_section_slug", "slug"]);
      return sectionSlug ? `/feature-sections/${sectionSlug}` : "/feature-sections";
    }
    default: {
      const entityType = firstStringValue(data, ["entity_type", "target_type", "object_type"]);
      const entitySlug = firstStringValue(data, ["entity_slug", "target_slug", "object_slug"]);
      if (entityType && entitySlug) {
        if (entityType.includes("product")) return `/products/${entitySlug}`;
        if (entityType.includes("brand")) return `/brands/${entitySlug}`;
        if (entityType.includes("category")) return `/categories/${entitySlug}`;
        if (entityType.includes("store")) return `/stores/${entitySlug}`;
      }
      const storeSlug = getStoreSlug(data);
      return storeSlug ? `/stores/${encodeURIComponent(storeSlug)}` : null;
    }
  }
};
