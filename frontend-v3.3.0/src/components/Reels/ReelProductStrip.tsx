import type { FC, WheelEventHandler } from "react";
import { ArrowUpRight } from "lucide-react";
import { useTranslation } from "react-i18next";

import { useHorizontalDragScroll } from "@/components/Media";
import ReelProductCard from "@/components/Reels/ReelProductCard";
import type { ReelProduct } from "@/components/Reels/types";

export interface ReelProductStripProps {
  products: readonly ReelProduct[];
  seeAll?: { label: string; href: string };
  onNavigate: (href: string) => void;
}

const ReelProductStrip: FC<ReelProductStripProps> = ({
  products,
  seeAll,
  onNavigate,
}) => {
  const { t } = useTranslation();
  const { isDragging, dragHandlers } = useHorizontalDragScroll({
    stopPropagation: true,
  });
  const handleWheel: WheelEventHandler<HTMLDivElement> = (event) => {
    if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;

    event.preventDefault();
    event.stopPropagation();
    event.currentTarget.scrollLeft += event.deltaY;
  };

  if (products.length === 0) return null;

  return (
    <div className="pointer-events-auto mt-3">
      <div className="mb-2 flex items-center justify-between text-xs font-bold text-white/90">
        <span>
          {t("reels.viewer.featuredProducts", {
            defaultValue: "Featured products",
          })}
        </span>
        <span className="text-[10px] font-semibold text-white/60">
          {t("reels.viewer.productCount", {
            count: products.length,
            defaultValue:
              products.length === 1 ? "{{count}} product" : "{{count}} products",
          })}
        </span>
      </div>
      <div
        data-reel-control
        aria-label={t("reels.viewer.products", {
          defaultValue: "Products in this reel",
        })}
        className={`scrollbar-hide -mx-1 flex touch-pan-x select-none gap-2 overflow-x-auto overscroll-x-contain px-1 py-2 [-webkit-overflow-scrolling:touch] ${
          isDragging ? "cursor-grabbing" : "cursor-grab"
        }`}
        data-product-dragging={isDragging || undefined}
        role="region"
        onWheel={handleWheel}
        {...dragHandlers}
      >
        {products.map((product) => (
          <ReelProductCard
            key={product.id}
            product={product}
            onPress={(selectedProduct) => {
              if (selectedProduct.href) onNavigate(selectedProduct.href);
            }}
          />
        ))}
        {seeAll && (
          <button
            data-reel-control
            type="button"
            className="flex h-[132px] w-24 shrink-0 flex-col items-center justify-center gap-2 rounded-2xl bg-white/12 text-[11px] font-bold text-white shadow-sm backdrop-blur-xl transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-primary-400"
            onClick={() => onNavigate(seeAll.href)}
          >
            <ArrowUpRight aria-hidden="true" size={18} />
            {t("reels.viewer.seeAll", { defaultValue: "See all" })}
          </button>
        )}
      </div>
    </div>
  );
};

export default ReelProductStrip;
