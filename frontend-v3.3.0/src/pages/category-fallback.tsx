import type { GetStaticProps } from "next";
import CategoryProductsPage from "@/pages/categories/[slug]/index";
import { getStaticGenerationContext } from "@/services/staticGenerationService";

// Shared static shell and JSON payload for categories added after deployment or not pre-rendered.
export const getStaticProps: GetStaticProps = async () => {
  const { settings } = await getStaticGenerationContext();
  return {
    props: {
      clientFallback: true,
      initialSettings: settings,
      initialProducts: null,
      categorySlug: "",
    },
  };
};

export default CategoryProductsPage;

