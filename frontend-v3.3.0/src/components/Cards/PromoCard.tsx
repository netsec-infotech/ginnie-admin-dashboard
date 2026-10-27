import { useSettings } from "@/contexts/SettingsContext";
import { formatDate } from "@/helpers/validator";
import { getPromoRequirements } from "@/helpers/promoEligibility";
import { PromoCode } from "@/types/ApiResponse";
import { Button, Card, CardBody, Chip, Divider, Progress } from "@heroui/react";
import { useTranslation } from "react-i18next";

interface PromoCardProps {
  promo: PromoCode;
  onApply: (promoCode: string) => void;
  isDisabled: boolean;
  cartAmount?: number;
}

const PromoCard: React.FC<PromoCardProps> = ({
  promo,
  onApply,
  isDisabled,
  cartAmount,
}) => {
  const { currencySymbol } = useSettings();
  const { t } = useTranslation();

  const getDiscountDisplay = () => {
    if (promo.discount_type === "percent") {
      return `${promo.discount_amount}%`;
    } else {
      return `${currencySymbol}${promo.discount_amount}`;
    }
  };

  const requirements = getPromoRequirements(promo, cartAmount);
  const isExpired = requirements.some(({ key }) => key === "expired");
  const cannotApply = isDisabled || requirements.length > 0;
  const usagePercentage =
    promo.max_total_usage && promo.max_total_usage > 0
      ? (promo.usage_count / promo.max_total_usage) * 100
      : 0;
  const requirementsId = `promo-requirements-${promo.id}`;

  return (
    <Card shadow="sm">
      <CardBody className="h-full px-3 sm:px-4">
        <div className="grid grid-cols-12 gap-4 h-full">
          {/* Left section - Promo Details */}
          <div className="col-span-8 flex flex-col justify-between">
            <div>
              <div className="flex items-center space-x-2 mb-1">
                <h3 className="font-bold text-sm sm:text-lg">{promo.code}</h3>
                <Chip
                  size="sm"
                  color={promo.promo_mode === "instant" ? "success" : "warning"}
                  variant="flat"
                  className="text-xs"
                  title={promo.promo_mode}
                >
                  {promo.promo_mode}
                </Chip>
                {isExpired && (
                  <Chip size="sm" color="danger" variant="flat">
                    {t("expired")}
                  </Chip>
                )}
              </div>

              <p className="text-xxs sm:text-xs text-foreground/50 mb-2">
                {promo.description}
              </p>

              <div className="flex items-center space-x-2 text-xxs sm:text-xs text-foreground/50">
                <span>
                  {promo.end_date
                    ? `${t("validTill")} ${formatDate(promo.end_date)}`
                    : t("promoEligibility.noExpiry")}
                </span>
                <Divider orientation="vertical" />
                <span>
                  {t("minOrder")}: {currencySymbol}
                  {promo.min_order_total ?? "0"}
                </span>
                {promo.max_discount_value && (
                  <>
                    <Divider orientation="vertical" />
                    <span>
                      {t("maxSave")}: {currencySymbol}
                      {promo.max_discount_value}
                    </span>
                  </>
                )}
              </div>
            </div>

            {/* Usage Progress */}
            {promo.max_total_usage != null && promo.max_total_usage > 0 && (
              <div className="mt-3">
                <div className="flex justify-between items-center text-xxs sm:text-xs text-foreground/50 mb-1">
                  <span>
                    {t("usage")}: {promo.usage_count}/{promo.max_total_usage}
                  </span>
                  <span>{Math.round(usagePercentage)}%</span>
                </div>
                <Progress
                  value={Math.min(usagePercentage, 100)}
                  color="primary"
                  size="sm"
                  className="h-1.5"
                  radius="full"
                  aria-label="Progressbar for Promo usage"
                />
              </div>
            )}
            {requirements.length > 0 && (
              <ul
                id={requirementsId}
                className="mt-3 space-y-1 text-xs text-warning-700 dark:text-warning-400"
                aria-live="polite"
              >
                {requirements.map(({ key, amount, remaining }) => (
                  <li key={key}>
                    {t(`promoEligibility.${key}`, {
                      amount,
                      remaining,
                      currency: currencySymbol,
                    })}
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Divider - Centered */}
          <div className="col-span-1 flex items-center justify-end">
            <div className="h-full w-px border-l-2 border-dotted border-divider"></div>
          </div>

          {/* Right section - Discount and Apply Button */}
          <div className="col-span-3 flex flex-col justify-between items-center h-full py-2">
            <div className="flex items-center justify-center">
              <span className="text-sm sm:text-2xl font-bold text-primary">
                {getDiscountDisplay()}
              </span>
            </div>

            <div className="flex items-end">
              <Button
                color="primary"
                variant={cannotApply ? "flat" : "solid"}
                size="sm"
                onPress={() => !cannotApply && onApply(promo.code)}
                isDisabled={cannotApply}
                aria-describedby={
                  requirements.length ? requirementsId : undefined
                }
                className="font-semibold text-xs"
              >
                {isExpired ? t("expired") : t("apply")}
              </Button>
            </div>
          </div>
        </div>
      </CardBody>
    </Card>
  );
};

export default PromoCard;
