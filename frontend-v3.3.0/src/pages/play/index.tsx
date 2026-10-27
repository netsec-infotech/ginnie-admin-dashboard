import { GetServerSideProps } from "next";
import { useTranslation } from "react-i18next";

import DynamicSEO from "@/SEO/DynamicSEO";
import { isSSR } from "@/helpers/getters";
import {
  generateBreadcrumbSchema,
  generateCollectionSchema,
} from "@/helpers/seo";
import { getSettings } from "@/routes/api";
import { NextPageWithLayout } from "@/types";
import { Settings } from "@/types/ApiResponse";
import PlayPageView from "@/views/PlayPageView";
import { loadTranslations } from "../../../i18n";
import { useSettings } from "@/contexts/SettingsContext";
import { getSiteSeo } from "@/config/siteSeo";

interface PlayPageProps {
  initialSettings?: Settings | null;
  error?: string;
}

const PlayPage: NextPageWithLayout<PlayPageProps> = () => {
  const { t } = useTranslation();
  const { webSettings } = useSettings();
  const siteName = getSiteSeo(webSettings).siteName;
  const title = t("pageTitle.play", { defaultValue: "Play" });
  const description = t("pages.play.metaDescription", {
    defaultValue: "Discover fresh Stories and short videos from nearby sellers on {{siteName}}.",
    siteName,
  });

  return (
    <>
      <DynamicSEO
        canonical="/play/"
        description={description}
        jsonLd={[
          generateCollectionSchema(title, description, "/play/"),
          generateBreadcrumbSchema([
            { name: t("pageTitle.home", { defaultValue: "Home" }), url: "/" },
            { name: title, url: "/play/" },
          ]),
        ]}
        keywords="local stories, nearby sellers, short videos, local reels"
        ogDescription={description}
        ogTitle={title}
        ogType="website"
        title={title}
      />
      <PlayPageView />
    </>
  );
};

export const getServerSideProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      try {
        await loadTranslations(context);
        const settings = await getSettings();

        return {
          props: {
            initialSettings: settings.data ?? null,
          },
        };
      } catch (err) {
        console.error("Error in Play getServerSideProps:", err);
        return {
          props: {
            initialSettings: null,
            error:
              err instanceof Error
                ? err.message
                : "An error occurred during SSR",
          },
        };
      }
    }
  : undefined;

export default PlayPage;
