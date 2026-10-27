import type { FC } from "react";

import {
  NEW_AT_BACKGROUNDS,
  type NewAtHorizontalVariant,
  type NewAtItem,
  type NewAtVerticalVariant,
} from "@/components/NewAt";
import HomeNewAt from "@/views/homePage/HomeNewAt";
import HomeNewAtVerticalShowcase, {
  HOME_NEW_AT_VERTICAL_SECTIONS,
  type HomeNewAtVerticalSectionConfig,
} from "@/views/homePage/HomeNewAtVerticalShowcase";

export interface HomeNewAtShowcaseProps {
  siteName?: string;
  /** Sets both layouts unless a background-specific variant overrides it. */
  variant?: NewAtHorizontalVariant;
  imageVariant?: NewAtHorizontalVariant;
  colorVariant?: NewAtHorizontalVariant;
  /** Applies one layout to every configured vertical section. */
  verticalVariant?: NewAtVerticalVariant;
  verticalItems?: readonly NewAtItem[];
  verticalSections?: readonly HomeNewAtVerticalSectionConfig[];
}

/** Image and color treatments share content but can use different distributions. */
const HomeNewAtShowcase: FC<HomeNewAtShowcaseProps> = ({
  siteName,
  variant,
  imageVariant,
  colorVariant,
  verticalVariant,
  verticalItems,
  verticalSections,
}) => {
  const resolvedVerticalSections = verticalVariant
    ? (verticalSections ?? [HOME_NEW_AT_VERTICAL_SECTIONS[0]]).map(
        (section) => ({
          ...section,
          variant: verticalVariant,
        }),
      )
    : verticalSections;

  return (
    <>
      <HomeNewAt
        background={NEW_AT_BACKGROUNDS.image}
        siteName={siteName}
        variant={imageVariant ?? variant ?? "grid"}
      />
      <HomeNewAt
        background={NEW_AT_BACKGROUNDS.color}
        siteName={siteName}
        variant={colorVariant ?? variant ?? "staggered"}
      />
      <HomeNewAtVerticalShowcase
        items={verticalItems}
        sections={resolvedVerticalSections}
        siteName={siteName}
      />
    </>
  );
};

export default HomeNewAtShowcase;
