import type { FC } from "react";
import { useTranslation } from "react-i18next";
import { Sparkles } from "lucide-react";

import SectionHeading from "@/components/SectionHeading";
import type {
  HomeLayoutPlayImageRow,
  HomeLayoutSection,
} from "@/types/ApiResponse";
import PlayImageRow from "@/views/homePage/playImage/PlayImageRow";
import {
  asPlayImageRecord,
  normalizePlayImageGap,
  resolvePlayImageRow,
} from "@/views/homePage/playImage/playImageConfig";

interface PlayImageSectionProps {
  section: HomeLayoutSection;
  rows: HomeLayoutPlayImageRow[];
  isLoading?: boolean;
  priority?: boolean;
  compactLegacyBannerOnDesktop?: boolean;
}

const loadingRow: HomeLayoutPlayImageRow = {
  sourceIndex: 0,
  row_index: 0,
  title: null,
  style: "grid",
  config: {},
  items: [],
};

/** Complete Play Image composition, kept independent from legacy banners. */
const PlayImageSection: FC<PlayImageSectionProps> = ({
  section,
  rows,
  isLoading = false,
  priority = false,
  compactLegacyBannerOnDesktop = false,
}) => {
  const { t } = useTranslation();
  const sourceRows = isLoading && rows.length === 0 ? [loadingRow] : rows;
  const resolvedRows = sourceRows.map((row) =>
    resolvePlayImageRow(section, row),
  );
  const spacing = asPlayImageRecord(section.config?.spacing);
  const itemGap = spacing.item_gap;
  const rowGap = normalizePlayImageGap(
    asPlayImageRecord(itemGap).vertical ?? itemGap,
  );

  return (
    <>
      {section.title && (
        <div className="mb-4 flex w-full items-center justify-between">
          <SectionHeading
            title={section.title}
            description=""
            icon={<Sparkles size={16} className="text-white" />}
          />
        </div>
      )}

      <div data-testid={`home-play-image-${section.id}`}>
        {resolvedRows.map((resolvedRow, renderedRowIndex) => {
          const row =
            resolvedRow.title === section.title
              ? { ...resolvedRow, title: null }
              : resolvedRow;

          return (
            <PlayImageRow
              key={`${section.id}-${row.row_index}-${row.sourceIndex}`}
              sectionId={section.id}
              row={row}
              isLoading={isLoading}
              priorityFirst={priority && renderedRowIndex === 0}
              trailingGap={
                renderedRowIndex < resolvedRows.length - 1 ? rowGap : 0
              }
              compactLegacyBannerOnDesktop={compactLegacyBannerOnDesktop}
              fallbackAlt={(itemIndex) =>
                row.title ||
                section.title ||
                t("home.banners.alt", {
                  defaultValue: `Promotional image ${itemIndex + 1}`,
                })
              }
            />
          );
        })}
      </div>
    </>
  );
};

export default PlayImageSection;
