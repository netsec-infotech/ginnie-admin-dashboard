import {
  OrderItem,
  OrderItemReturnRequest,
  OrderStatus,
} from "@/types/ApiResponse";

export const fallbackPaginateRes = {
  success: false,
  message: "An error occurred.",
  data: {
    current_page: 0,
    data: [],
    per_page: 0,
    total: 0,
  },
};
export const fallbackPaginateResOfProductReviews = {
  success: false,
  message: "An error occurred.",
  data: {
    current_page: 0,
    data: {
      total_reviews: 0,
      average_rating: "0",
      ratings_breakdown: {
        "1_star": "0",
        "2_star": "0",
        "3_star": "0",
        "4_star": "0",
        "5_star": "0",
      },
      reviews: [],
    },
    per_page: 0,
    total: 0,
  },
};

export const fallbackApiRes = {
  success: false,
  message: "An error occurred.",
  data: undefined,
};

export const fallbackBannerRes = {
  success: false,
  message: "An error occurred.",
  data: {
    current_page: 0,
    data: { top: [], carousel: [], sidebar: [] },
    per_page: 0,
    total: 0,
  },
};

export const orderStatusColorMap = (
  status: OrderStatus | undefined
): "default" | "primary" | "secondary" | "success" | "warning" | "danger" => {
  switch (status) {
    case "awaiting_store_response":
      return "warning";
    case "ready_for_pickup":
      return "secondary";
    case "assigned":
      return "primary";
    case "out_for_delivery":
      return "warning";
    case "delivered":
      return "success";
    case "cancelled":
      return "danger";
    default:
      return "default";
  }
};
export const orderStatusPriority: Record<string, number> = {
  pending: 1,
  awaiting_store_response: 2,
  partially_accepted: 3,
  accepted: 3,
  approved: 3,
  rejected_by_seller: 3.5,
  preparing: 4,
  ready_for_pickup: 5,
  assigned: 6,
  out_for_delivery: 7,
  delivered: 8,
  cancelled: 9,
  returned: 10,
};

export const staticProfileImage = "/images/default-user-avatar-v2.webp";

export const TILE_LAYERS = [
  "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
  "https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png",
  "https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
  "https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}",
  "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png",
  "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png",
  "https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}.png",
  "https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}.png",
  "https://tiles.stadiamaps.com/tiles/terrain/{z}/{x}/{y}.png",
];

export const staticLat = 23.242;
export const staticLng = 69.6669;

export const demoEmail = "user@gmail.com";
export const demoPassword = "12345678";
export const demoNumber = "9000000000";

type ChipColor =
  | "default"
  | "primary"
  | "secondary"
  | "success"
  | "warning"
  | "danger";

/**
 * Colour for a return request's `return_status`.
 *
 * Separate from `orderStatusColorMap` because the two vocabularies don't
 * overlap — "seller_approved" and "refund_processed" are return states, not
 * order states, and would both fall through to grey there.
 */
export const getReturnStatusColor = (status?: string | null): ChipColor => {
  if (!status) return "default";

  const map: Record<string, ChipColor> = {
    // In flight
    pending: "warning",
    requested: "warning",
    initiating: "warning",
    initiated: "warning",
    processing: "warning",
    reviewing: "warning",
    // Progressing — the store has accepted it but the goods aren't back yet.
    seller_approved: "primary",
    approved: "primary",
    picked_up: "primary",
    received: "primary",
    store_confirmed: "primary",
    // Settled in the customer's favour
    completed: "success",
    processed: "success",
    refunded: "success",
    refund_processed: "success",
    resolved: "success",
    // Closed without a refund
    cancelled: "danger",
    canceled: "danger",
    declined: "danger",
    rejected: "danger",
    seller_rejected: "danger",
    store_rejected: "danger",
    failed: "danger",
  };

  return map[status.toLowerCase()] ?? "default";
};

/**
 * The return request that should speak for an item, or null if there isn't one.
 *
 * Picks the most recently updated rather than `returns[0]`: the array order
 * isn't guaranteed, and an item can accumulate several requests over time (a
 * rejected one followed by a fresh attempt). The newest is the live one.
 */
export const getActiveReturnRequest = (
  item?: Pick<OrderItem, "returns"> | null,
): OrderItemReturnRequest | null => {
  const requests = item?.returns;
  if (!Array.isArray(requests) || !requests.length) return null;

  return requests.reduce((latest, current) => {
    const latestAt = Date.parse(latest.updated_at || latest.created_at || "");
    const currentAt = Date.parse(current.updated_at || current.created_at || "");

    // NaN-safe: an unparseable date never wins, so a good one always survives.
    if (Number.isNaN(currentAt)) return latest;
    if (Number.isNaN(latestAt)) return current;
    return currentAt > latestAt ? current : latest;
  });
};

/**
 * What to show on an item's status chip.
 *
 * Once a return exists for an item, that request's status supersedes the item's
 * own — an item stays `delivered` forever, so showing that alongside an active
 * return told the customer nothing about the thing they're actually waiting on.
 */
export const getItemDisplayStatus = (
  item: Pick<OrderItem, "status" | "returns">,
): { status: string; color: ChipColor; isReturn: boolean } => {
  const activeReturn = getActiveReturnRequest(item);

  if (activeReturn?.return_status) {
    return {
      status: activeReturn.return_status,
      color: getReturnStatusColor(activeReturn.return_status),
      isReturn: true,
    };
  }

  return {
    status: item.status,
    color: orderStatusColorMap(item.status),
    isReturn: false,
  };
};
