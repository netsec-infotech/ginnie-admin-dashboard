import { PromoCode } from "@/types/ApiResponse";

export type PromoRequirement = {
  key:
    | "unavailable"
    | "notStarted"
    | "expired"
    | "usageLimit"
    | "cartRequired"
    | "minimumOrder";
  amount?: string;
  remaining?: string;
};

// Use the same items total sent to /user/promos/validate, before promo discounts.
// Per-user usage is filtered by /available and checked again by /validate.
export function getPromoRequirements(
  promo: PromoCode,
  cartAmount: number | undefined,
  now = Date.now(),
): PromoRequirement[] {
  const requirements: PromoRequirement[] = [];
  if (promo.deleted_at) requirements.push({ key: "unavailable" });
  if (promo.start_date && new Date(promo.start_date).getTime() > now) {
    requirements.push({ key: "notStarted" });
  }
  if (promo.end_date && new Date(promo.end_date).getTime() < now) {
    requirements.push({ key: "expired" });
  }
  if (
    promo.max_total_usage != null &&
    promo.usage_count >= promo.max_total_usage
  ) {
    requirements.push({ key: "usageLimit" });
  }
  if (cartAmount == null || !Number.isFinite(cartAmount) || cartAmount <= 0) {
    requirements.push({ key: "cartRequired" });
  } else if (cartAmount < Number(promo.min_order_total)) {
    requirements.push({
      key: "minimumOrder",
      amount: Number(promo.min_order_total).toFixed(2),
      remaining: (Number(promo.min_order_total) - cartAmount).toFixed(2),
    });
  }
  return requirements;
}
