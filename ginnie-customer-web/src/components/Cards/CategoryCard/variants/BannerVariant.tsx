import { Image } from "@heroui/react";
import { ArrowRight } from "lucide-react";
import Link from "next/link";
import { FC } from "react";
import { CategoryCardProps } from "../types";
import { categoryLink, useSafeBackgroundColor } from "../utils";

/**
 * banner variant — wide card with a floating product image, depth glow,
 * and a polished text/CTA block. Upgraded from the original flat layout.
 */
const BannerVariant: FC<CategoryCardProps> = ({
  category,
  onClick,
  className = "",
}) => {
  const { bgColor, light } = useSafeBackgroundColor(category.background_color);

  const titleClass = light ? "text-gray-900" : "text-white";
  const subClass = light ? "text-gray-500" : "text-white/60";
  const ctaClass = light
    ? "text-gray-900 bg-black/8 hover:bg-black/14"
    : "text-white bg-white/15 hover:bg-white/25";

  // Derive a subtle radial glow color from the background
  const glowColor = bgColor
    ? `${bgColor}55` // 33% opacity version of the bg for the glow
    : "rgba(255,255,255,0.08)";

  return (
    <Link
      href={categoryLink(category)}
      onClick={onClick}
      title={category.title}
      className={`group block w-full ${className}`}
    >
      <div
        className="relative overflow-hidden w-full h-28 sm:h-32 rounded-2xl transition-all duration-300 ease-out
          hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0 active:shadow-md
          border border-white/10"
        style={{ backgroundColor: bgColor || "transparent" }}
      >
        {/* Fallback gradient when no bgColor */}
        {!bgColor && (
          <div className="absolute inset-0 bg-gradient-to-br from-primary/20 via-primary/10 to-transparent dark:from-primary/30" />
        )}

        {/* Left-to-right gradient fade so product image blends cleanly */}
        <div
          className="absolute inset-0 z-[1]"
          style={{
            background: bgColor
              ? `linear-gradient(to right, ${bgColor} 30%, ${bgColor}cc 55%, transparent 80%)`
              : "linear-gradient(to right, rgba(0,0,0,0.6) 20%, transparent 70%)",
          }}
        />

        {/* Noise texture overlay for tactility */}
        <div
          className="absolute inset-0 z-[1] opacity-[0.04] mix-blend-overlay pointer-events-none"
          style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E")`,
          }}
        />

        {/* Top shimmer line — glass reflection effect */}
        <div
          className="absolute top-0 left-0 right-0 h-px z-[2] opacity-30"
          style={{
            background:
              "linear-gradient(to right, transparent 0%, rgba(255,255,255,0.8) 40%, rgba(255,255,255,0.8) 60%, transparent 100%)",
          }}
        />

        {/* Banner / background image at very low opacity */}
        {(category.banner || category.background_image) && (
          <Image
            src={category.banner || category.background_image}
            alt=""
            removeWrapper
            className="absolute inset-0 w-full h-full object-cover opacity-10 z-0"
            loading="lazy"
          />
        )}

        {/* Radial glow behind the product image */}
        {category.image && (
          <div
            className="absolute right-0 top-0 bottom-0 w-3/5 z-[2] pointer-events-none"
            style={{
              background: `radial-gradient(ellipse at 80% 50%, ${glowColor} 0%, transparent 70%)`,
            }}
          />
        )}

        {/* Product image — anchored to right 45% of card, never overlaps text */}
        {category.image && (
          <div className="absolute right-0 top-0 bottom-0 w-[45%] flex items-end justify-center z-[3] pb-0 pr-1">
            <Image
              src={category.image}
              alt={category.title}
              removeWrapper
              className="h-[105%] sm:h-[112%] w-auto max-w-full object-contain
                drop-shadow-2xl
                transition-transform duration-500 ease-out
                group-hover:scale-[1.07] group-hover:-translate-y-1"
              loading="lazy"
            />
          </div>
        )}

        {/* Text block — width capped so it never collides with the product image */}
        <div
          className="absolute left-0 top-0 bottom-0 z-[4] flex flex-col justify-center pl-4 sm:pl-5 gap-1"
          style={{ maxWidth: "55%" }}
        >
          {/* Category title */}
          <h3
            className={`font-extrabold text-sm sm:text-base leading-tight tracking-tight ${titleClass}`}
          >
            {category.title}
          </h3>

          {/* Product count — plain secondary text, no pill, saves vertical space */}
          {category.product_count > 0 && (
            <p className={`text-[11px] font-medium leading-none ${subClass}`}>
              {category.product_count} Products
            </p>
          )}

          {/* CTA — single row, never wraps */}
          <div
            className={`inline-flex items-center gap-1 self-start mt-1.5 px-2.5 py-1 rounded-lg
              text-xs font-semibold whitespace-nowrap
              transition-all duration-200 ${ctaClass}`}
          >
            Shop now
            <ArrowRight
              size={11}
              className="transition-transform duration-200 group-hover:translate-x-0.5"
            />
          </div>
        </div>
      </div>
    </Link>
  );
};

export default BannerVariant;
