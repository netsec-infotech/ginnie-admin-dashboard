import type { FC } from "react";

import {
  NEW_AT_BACKGROUNDS,
  SAMPLE_NEW_AT_VERTICAL_ITEMS,
  type NewAtBackground,
  type NewAtItem,
  type NewAtVerticalVariant,
} from "@/components/NewAt";
import HomeNewAt from "@/views/homePage/HomeNewAt";

export interface HomeNewAtVerticalSectionConfig {
  id: string;
  variant: NewAtVerticalVariant;
  background: NewAtBackground;
  title?: string;
  subtitle?: string;
  items?: readonly NewAtItem[];
}

/**
 * Optional homepage presets are plain configuration. A CMS can select one
 * later without changing the vertical renderer or NewAtCard.
 */
export const HOME_NEW_AT_VERTICAL_SECTIONS = [
  {
    id: "mixed-category-grid",
    variant: "vertical-grid",
    background: NEW_AT_BACKGROUNDS.marketPaper,
    title: "New Across Pet, Pantry & Home",
    subtitle: "Pet care, pantry, bakery and home essentials, newly nearby.",
  },
  {
    id: "compact-everyday-picks",
    variant: "vertical-compact",
    background: {
      type: "color",
      value: "linear-gradient(145deg, #eff9f1 0%, #f8fbef 50%, #fff7e8 100%)",
    },
    title: "Quick Picks Across Pet, Pantry & Home",
    subtitle: "More categories in a compact, easy-to-scan multi-row grid.",
  },
  {
    id: "featured-neighbourhood-finds",
    variant: "vertical-featured",
    background: NEW_AT_BACKGROUNDS.homeBotanical,
    title: "Featured Finds for Every Routine",
    subtitle: "Larger highlights lead into fresh food, wellness and home picks.",
  },
  {
    id: "mixed-category-mosaic",
    variant: "vertical-mosaic",
    background: {
      type: "color",
      value: "linear-gradient(145deg, #f1f4ff 0%, #f8f0ff 50%, #fff3ed 100%)",
    },
    title: "A Little Bit of Everything",
    subtitle: "A playful multi-row mix from independent local favourites.",
  },
] as const satisfies readonly HomeNewAtVerticalSectionConfig[];

/** The homepage renders one intentional mode; the remaining presets are opt-in. */
export const DEFAULT_HOME_NEW_AT_VERTICAL_SECTIONS = [
  HOME_NEW_AT_VERTICAL_SECTIONS[0],
] as const satisfies readonly HomeNewAtVerticalSectionConfig[];

export interface HomeNewAtVerticalShowcaseProps {
  siteName?: string;
  items?: readonly NewAtItem[];
  sections?: readonly HomeNewAtVerticalSectionConfig[];
}

/** Config-driven vertical mode using the same layout-agnostic NewAtCard. */
const HomeNewAtVerticalShowcase: FC<HomeNewAtVerticalShowcaseProps> = ({
  siteName,
  items = SAMPLE_NEW_AT_VERTICAL_ITEMS,
  sections,
}) => {
  const resolvedSections: readonly HomeNewAtVerticalSectionConfig[] =
    sections ?? DEFAULT_HOME_NEW_AT_VERTICAL_SECTIONS;

  return (
    <>
      {resolvedSections.map((section) => (
        <HomeNewAt
          background={section.background}
          items={section.items ?? items}
          key={section.id}
          siteName={siteName}
          subtitle={section.subtitle}
          title={section.title}
          variant={section.variant}
        />
      ))}
    </>
  );
};

export default HomeNewAtVerticalShowcase;
