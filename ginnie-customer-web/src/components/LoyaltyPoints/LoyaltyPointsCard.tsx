import { FC } from "react";
import { Card, CardBody, CardFooter, CardHeader, Chip, Skeleton } from "@heroui/react";
import { Coins, TriangleAlert, Sparkles } from "lucide-react";
import { LoyaltyPointsSummary } from "@/types/ApiResponse";
import { useSettings } from "@/contexts/SettingsContext";
import { useTranslation } from "react-i18next";

type LoyaltyPointsCardProps = {
  summary: LoyaltyPointsSummary | null;
  loading?: boolean;
};

const LoyaltyPointsCard: FC<LoyaltyPointsCardProps> = ({
  summary,
  loading = false,
}) => {
  const { currencySymbol } = useSettings();
  const { t } = useTranslation();

  return (
    <Card
      as="div"
      disableRipple
      className="w-full min-h-48 p-2 relative overflow-hidden bg-linear-to-br from-amber-600 via-amber-500 to-amber-700 shadow-lg border border-amber-400/40"
    >
      {/* Decorative Background Icon */}
      <div className="absolute -right-6 -bottom-6 text-amber-400/30 pointer-events-none -rotate-12">
        <Coins size={180} />
      </div>
      
      {/* Subtle Glow */}
      <div className="absolute top-0 left-0 w-full h-1/2 bg-gradient-to-b from-white/20 to-transparent pointer-events-none"></div>

      <CardHeader className="flex justify-between pb-0 relative z-10">
        <div className="font-bold text-white uppercase tracking-widest text-sm flex items-center gap-2 drop-shadow-sm">
          <div className="p-1.5 bg-white/20 rounded-md backdrop-blur-sm shadow-sm border border-white/20">
            <Coins className="text-amber-50" size={18} />
          </div>
          {t("pages.loyaltyPointsPage.card.title")}
        </div>
      </CardHeader>
      <CardBody className="text-white overflow-hidden flex flex-col justify-between pb-2 relative z-10">
        {loading || !summary ? (
          <div className="flex flex-col gap-3 mt-4">
            <Skeleton className="h-12 w-32 rounded-lg bg-white/30" />
            <Skeleton className="h-6 w-48 rounded-md bg-white/20" />
          </div>
        ) : (
          <div className="mt-2 flex flex-col gap-4">
            <div className="flex items-baseline gap-2">
              <span className="font-bold text-6xl tracking-tighter drop-shadow-md">
                {Number(summary.points_balance).toLocaleString()}
              </span>
              <span className="text-lg font-medium text-amber-50/90 uppercase tracking-wide">
                {t("points")}
              </span>
            </div>
            
            <div className="flex flex-col gap-1.5">
              <div className="flex items-center gap-2 bg-black/15 w-fit px-3 py-1.5 rounded-full backdrop-blur-md border border-white/10 shadow-inner">
                <Sparkles size={14} className="text-amber-200" />
                <span className="text-sm font-medium text-white">
                  {t("pages.loyaltyPointsPage.card.worth", {
                    value: `${currencySymbol}${Number(summary.equivalent_monetary_value).toLocaleString()}`,
                  })}
                </span>
              </div>
              <span className="text-xs font-medium text-amber-900/60 pl-2">
                {t("pages.loyaltyPointsPage.card.rate", {
                  value: `${currencySymbol}${Number(summary.redemption_value).toLocaleString()}`,
                })}
              </span>
            </div>
          </div>
        )}
      </CardBody>
      {!loading && summary && summary.expiring_points_in_30_days > 0 && (
        <CardFooter className="pt-0 relative z-10 mt-2">
          <Chip
            color="danger"
            variant="solid"
            size="sm"
            startContent={<TriangleAlert size={14} />}
            classNames={{ 
              base: "bg-red-500/90 border border-red-400/50 backdrop-blur-md shadow-sm",
              content: "text-xs font-medium text-white" 
            }}
          >
            {t("pages.loyaltyPointsPage.card.expiring", {
              points: summary.expiring_points_in_30_days,
            })}
          </Chip>
        </CardFooter>
      )}
    </Card>
  );
};

export default LoyaltyPointsCard;
