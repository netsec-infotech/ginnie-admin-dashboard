import Image from "next/image";
import type { FC } from "react";

import { NEW_AT_BACKGROUNDS } from "@/components/NewAt/backgrounds";
import NewAtCard from "@/components/NewAt/NewAtCard";
import type { NewAtSectionProps } from "@/components/NewAt/types";
import { NEW_AT_LAYOUTS } from "@/components/NewAt/variants";

const NewAtSection: FC<NewAtSectionProps> = ({
  title,
  subtitle,
  variant = "grid",
  background = NEW_AT_BACKGROUNDS.color,
  items,
  className = "",
}) => {
  if (items.length === 0) return null;

  const layout = NEW_AT_LAYOUTS[variant];
  const backgroundStyle =
    background.type === "color"
      ? { background: background.value ?? NEW_AT_BACKGROUNDS.color.value }
      : background.type === "plain"
        ? { background: background.value ?? "#ffffff" }
        : { background: "transparent" };

  return (
    <section
      aria-label={title || "New arrivals"}
      className={`mb-8 px-1 sm:px-6 ${className}`.trim()}
      data-background={background.type}
      data-variant={variant}
    >
      <div
        className="relative isolate overflow-hidden rounded-2xl"
        style={backgroundStyle}
      >
        {background.type === "image" && (
          <>
            <Image
              fill
              alt=""
              aria-hidden="true"
              className="-z-20 object-cover"
              loading="lazy"
              sizes="100vw"
              src={background.imageUrl}
              style={{ objectPosition: background.position }}
            />
            <span
              aria-hidden="true"
              className="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(255,255,255,0.08)_0%,rgba(255,255,255,0.28)_100%)]"
            />
          </>
        )}

        <div className="relative z-0">
          {(title || subtitle) && (
            <header className="relative isolate overflow-hidden bg-transparent px-4 pb-4 pt-5 text-center sm:pb-5 sm:pt-6">
              <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 -top-6 -z-10 select-none text-7xl font-black tracking-[0.12em] text-fuchsia-950/[0.035] sm:text-8xl"
              >
                NEW
              </span>
              <div className="mx-auto max-w-3xl">
                {title && (
                  <h2 className="text-xl font-black leading-tight tracking-tight text-slate-950 text-balance sm:text-2xl">
                    {title}
                  </h2>
                )}
                {subtitle && (
                  <p className="mt-1 text-xs font-medium text-slate-600 sm:text-sm">
                    {subtitle}
                  </p>
                )}
              </div>
            </header>
          )}

          <div className={layout.containerClassName}>
            {items.map((item, index) => (
              <div className={layout.getItemClassName(index)} key={item.id}>
                <NewAtCard {...item} />
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
};

export default NewAtSection;
