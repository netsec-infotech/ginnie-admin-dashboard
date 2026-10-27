import type { GetStaticProps } from "next";
import BrandProductsPage from "@/pages/brands/[slug]/index";
import { getStaticGenerationContext } from "@/services/staticGenerationService";

// Shared static shell and JSON payload for brands added after deployment.
export const getStaticProps: GetStaticProps = async () => {
  const { settings } = await getStaticGenerationContext();
  return {
    props: {
      clientFallback: true,
      initialSettings: settings,
      initialProducts: null,
      brandSlug: "",
    },
  };
};

export default BrandProductsPage;

