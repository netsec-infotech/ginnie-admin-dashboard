import type { FC } from "react";

import {
  NewAtSection,
  NEW_AT_BACKGROUNDS,
  SAMPLE_NEW_AT_ITEMS,
  type NewAtBackground,
  type NewAtItem,
  type NewAtVariant,
} from "@/components/NewAt";
import { getSiteSeo } from "@/config/siteSeo";
import { useSettings } from "@/contexts/SettingsContext";

export interface HomeNewAtProps {
  siteName?: string;
  variant?: NewAtVariant;
  background?: NewAtBackground;
  items?: readonly NewAtItem[];
  title?: string;
  subtitle?: string;
}

/** Homepage placement; the section itself remains page-agnostic. */
const HomeNewAt: FC<HomeNewAtProps> = ({
  siteName,
  variant = "grid",
  background = NEW_AT_BACKGROUNDS.image,
  items = SAMPLE_NEW_AT_ITEMS,
  title,
  subtitle = "Fresh finds from local favourites, all in one place.",
}) => {
  const { webSettings } = useSettings();
  const resolvedSiteName = siteName?.trim() || getSiteSeo(webSettings).siteName;
  const resolvedTitle = title ?? `New At ${resolvedSiteName}`;

  return (
    <NewAtSection
      background={background}
      items={items}
      subtitle={subtitle}
      title={resolvedTitle}
      variant={variant}
    />
  );
};

export default HomeNewAt;
