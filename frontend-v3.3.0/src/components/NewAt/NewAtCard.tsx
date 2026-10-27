import Image from "next/image";
import Link from "next/link";
import type { FC } from "react";

import type { NewAtCardProps } from "@/components/NewAt/types";

/** A layout-agnostic, fully linked catalogue card. */
const NewAtCard: FC<NewAtCardProps> = ({
  brand,
  title,
  description,
  imageUrl,
  deepLink,
}) => (
  <Link
    aria-label={`Explore ${title} by ${brand}`}
    className="group relative isolate flex h-full min-h-full flex-col overflow-hidden rounded-[inherit] bg-transparent text-slate-950 outline-none focus-visible:z-10 focus-visible:outline-3 focus-visible:outline-offset-[-3px] focus-visible:outline-primary dark:bg-transparent dark:text-slate-950"
    href={deepLink}
  >
    <span className="relative block aspect-4/3 w-full shrink-0 overflow-hidden bg-transparent">
      <span className="absolute inset-x-3 top-3 z-10 block truncate text-center text-[0.62rem] font-black uppercase tracking-[0.16em] text-slate-700 sm:text-[0.68rem]">
        {brand}
      </span>
      <Image
        fill
        alt={`${title} by ${brand}`}
        className="object-contain px-3 pb-1 pt-8 transition-transform duration-500 ease-out group-hover:scale-[1.035] group-focus-visible:scale-[1.035] motion-reduce:transition-none sm:px-5"
        loading="lazy"
        sizes="(max-width: 640px) 100vw, (max-width: 1024px) 66vw, (max-width: 1536px) 50vw, 40vw"
        src={imageUrl}
      />
    </span>

    <span className="flex flex-1 flex-col items-center px-3 pb-5 pt-2 text-center sm:px-4">
      <span className="block text-sm font-extrabold leading-tight text-fuchsia-700 transition-colors group-hover:text-fuchsia-800 group-focus-visible:text-fuchsia-800 sm:text-base">
        {title}
      </span>
      <span className="mt-1.5 line-clamp-3 block max-w-60 text-[0.68rem] leading-snug text-slate-600 sm:text-xs">
        {description}
      </span>
    </span>
  </Link>
);

export default NewAtCard;
