import type { GetStaticProps } from "next";
import StoreProductsPage from "@/pages/stores/[slug]/index";
import { getStaticGenerationContext } from "@/services/staticGenerationService";

// Shared static shell for stores outside the build location or added later.
export const getStaticProps: GetStaticProps = async () => {
  const { settings } = await getStaticGenerationContext();
  return {
    props: {
      clientFallback: true,
      initialSettings: settings,
      initialProducts: null,
      initialStore: null,
      storeSlug: "",
    },
  };
};

export default StoreProductsPage;
