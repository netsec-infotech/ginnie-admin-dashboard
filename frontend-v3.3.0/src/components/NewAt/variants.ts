import type {
  NewAtHorizontalVariant,
  NewAtVariant,
  NewAtVerticalVariant,
} from "@/components/NewAt/types";

export interface NewAtLayout {
  containerClassName: string;
  getItemClassName: (index: number) => string;
}

/**
 * Layout belongs here rather than in NewAtCard. Adding another presentation
 * only requires a new registry entry and an update to NewAtVariant.
 */
export const NEW_AT_HORIZONTAL_LAYOUTS: Record<
  NewAtHorizontalVariant,
  NewAtLayout
> = {
  grid: {
    containerClassName:
      "grid grid-cols-[repeat(auto-fit,minmax(min(100%,10.5rem),1fr))] gap-x-3 gap-y-5 bg-transparent px-3 pb-4 pt-3 sm:grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] sm:gap-x-6 sm:px-5 sm:pb-5 sm:pt-4",
    getItemClassName: () => "min-h-80 overflow-hidden rounded-xl",
  },
  "horizontal-scroll": {
    containerClassName:
      "scrollbar-hide grid touch-pan-x snap-x snap-mandatory grid-flow-col auto-cols-[clamp(11rem,22vw,15rem)] gap-4 overflow-x-auto overscroll-x-contain bg-transparent p-4 [-webkit-overflow-scrolling:touch] sm:gap-6 sm:px-5",
    getItemClassName: () =>
      "min-h-80 snap-start overflow-hidden rounded-xl bg-transparent",
  },
  featured: {
    containerClassName:
      "grid grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))] auto-rows-[minmax(15rem,1fr)] gap-4 bg-transparent p-4 sm:gap-6 sm:p-5",
    getItemClassName: (index) =>
      index === 0
        ? "min-h-96 overflow-hidden rounded-xl bg-transparent sm:col-span-2 sm:row-span-2"
        : "min-h-80 overflow-hidden rounded-xl bg-transparent",
  },
  "compact-grid": {
    containerClassName:
      "grid grid-cols-[repeat(auto-fit,minmax(min(100%,9rem),1fr))] gap-x-2 gap-y-4 bg-transparent px-3 pb-4 pt-3 sm:grid-cols-[repeat(auto-fit,minmax(min(100%,10.5rem),1fr))] sm:gap-x-4 sm:px-5 sm:pb-5 sm:pt-4",
    getItemClassName: () =>
      "min-h-72 overflow-hidden rounded-lg bg-transparent",
  },
  mosaic: {
    containerClassName:
      "grid auto-flow-dense grid-cols-[repeat(auto-fit,minmax(min(100%,10.5rem),1fr))] auto-rows-[minmax(16rem,auto)] gap-4 bg-transparent p-4 sm:gap-6 sm:p-5",
    getItemClassName: (index) =>
      index % 5 === 0
        ? "min-h-96 overflow-hidden rounded-xl bg-transparent sm:col-span-2 sm:row-span-2"
        : "min-h-80 overflow-hidden rounded-xl bg-transparent",
  },
  spotlight: {
    containerClassName:
      "grid grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))] gap-4 bg-transparent p-4 sm:gap-6 sm:p-5",
    getItemClassName: (index) =>
      index === 0
        ? "col-span-full min-h-96 overflow-hidden rounded-xl bg-transparent sm:[&>a]:grid sm:[&>a]:grid-cols-[1.2fr_0.8fr] sm:[&>a>span:first-child]:aspect-auto sm:[&>a>span:first-child]:min-h-80 sm:[&>a>span:last-child]:justify-center sm:[&>a>span:last-child]:px-8"
        : "min-h-80 overflow-hidden rounded-xl bg-transparent",
  },
  "stacked-deck": {
    containerClassName:
      "scrollbar-hide isolate flex touch-pan-x items-start overflow-x-auto overscroll-x-contain px-8 pb-8 pt-5 [-webkit-overflow-scrolling:touch] sm:px-14 sm:pb-10 sm:pt-7",
    getItemClassName: (index) => {
      const rotations = [
        "-rotate-2",
        "rotate-1",
        "-rotate-1",
        "rotate-2",
      ] as const;

      return `relative w-[clamp(12rem,27vw,17rem)] shrink-0 overflow-hidden rounded-xl bg-transparent transition-transform duration-300 hover:z-50 hover:-translate-y-2 hover:rotate-0 focus-within:z-50 focus-within:-translate-y-2 focus-within:rotate-0 motion-reduce:transition-none ${
        index === 0 ? "z-10" : "-ms-10 sm:-ms-14"
      } ${rotations[index % rotations.length]}`;
    },
  },
  staggered: {
    containerClassName:
      "grid grid-cols-[repeat(auto-fit,minmax(min(100%,10.5rem),1fr))] items-start gap-x-4 gap-y-6 bg-transparent px-3 pb-12 pt-3 sm:grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] sm:gap-x-6 sm:px-5 sm:pb-16 sm:pt-4",
    getItemClassName: (index) =>
      `min-h-80 overflow-hidden rounded-xl bg-transparent ${
        index % 2 === 1 ? "sm:translate-y-8" : ""
      }`,
  },
  alternating: {
    containerClassName:
      "grid auto-flow-dense grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))] gap-4 bg-transparent p-4 sm:gap-6 sm:p-5",
    getItemClassName: (index) =>
      index % 4 === 0
        ? "min-h-80 overflow-hidden rounded-xl bg-transparent sm:col-span-2"
        : "min-h-80 overflow-hidden rounded-xl bg-transparent",
  },
  "gallery-strip": {
    containerClassName:
      "scrollbar-hide grid touch-pan-x snap-x snap-mandatory grid-flow-col auto-cols-[clamp(11rem,21vw,15rem)] gap-4 overflow-x-auto overscroll-x-contain bg-transparent p-4 [-webkit-overflow-scrolling:touch] sm:gap-6 sm:px-5",
    getItemClassName: (index) =>
      index % 3 === 0
        ? "col-span-2 min-h-80 snap-start overflow-hidden rounded-xl bg-transparent"
        : "min-h-80 snap-start overflow-hidden rounded-xl bg-transparent",
  },
};

export const NEW_AT_VERTICAL_LAYOUTS: Record<
  NewAtVerticalVariant,
  NewAtLayout
> = {
  "vertical-grid": {
    containerClassName:
      "mx-auto grid w-full max-w-6xl grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))] items-stretch gap-x-5 gap-y-7 bg-transparent px-3 pb-7 pt-3 sm:gap-x-6 sm:gap-y-8 sm:px-5 sm:pb-9 sm:pt-4",
    getItemClassName: () =>
      "min-h-80 overflow-hidden rounded-xl bg-transparent",
  },
  "vertical-compact": {
    containerClassName:
      "mx-auto grid w-full max-w-5xl grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] items-stretch gap-x-3 gap-y-5 bg-transparent px-3 pb-7 pt-3 sm:gap-x-4 sm:gap-y-6 sm:px-5 sm:pb-9 sm:pt-4",
    getItemClassName: () =>
      "min-h-72 overflow-hidden rounded-lg bg-transparent",
  },
  "vertical-featured": {
    containerClassName:
      "mx-auto grid w-full max-w-5xl auto-flow-dense grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] items-stretch gap-5 bg-transparent px-3 pb-7 pt-3 sm:gap-6 sm:px-5 sm:pb-9 sm:pt-4",
    getItemClassName: (index) =>
      index === 0
        ? "col-span-full min-h-80 overflow-hidden rounded-xl bg-transparent md:[&>a]:grid md:[&>a]:grid-cols-[1.15fr_0.85fr] md:[&>a>span:first-child]:aspect-auto md:[&>a>span:first-child]:min-h-80 md:[&>a>span:last-child]:justify-center md:[&>a>span:last-child]:px-8"
        : "min-h-80 overflow-hidden rounded-xl bg-transparent",
  },
  "vertical-mosaic": {
    containerClassName:
      "mx-auto grid w-full max-w-5xl auto-flow-dense grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] items-stretch gap-x-4 gap-y-7 bg-transparent px-3 pb-10 pt-3 sm:gap-x-5 sm:gap-y-8 sm:px-5 sm:pb-10 sm:pt-4",
    getItemClassName: (index) => {
      if (index % 6 === 0) {
        return "min-h-80 overflow-hidden rounded-2xl bg-transparent sm:col-span-2 sm:[&>a>span:first-child]:aspect-2/1";
      }

      return "min-h-80 overflow-hidden rounded-xl bg-transparent";
    },
  },
};

/** Combined registry consumed by NewAtSection; mode-specific registries stay reusable. */
export const NEW_AT_LAYOUTS = {
  ...NEW_AT_HORIZONTAL_LAYOUTS,
  ...NEW_AT_VERTICAL_LAYOUTS,
} satisfies Record<NewAtVariant, NewAtLayout>;
