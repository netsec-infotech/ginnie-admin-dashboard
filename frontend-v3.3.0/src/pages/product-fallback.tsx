import type { GetStaticProps } from "next";
import ProductPage from "@/views/Products/ProductPage";
import { getStaticGenerationContext } from "@/services/staticGenerationService";

// Shared static shell and JSON payload for products added after deployment.
export const getStaticProps: GetStaticProps = async () => {
  const { settings } = await getStaticGenerationContext();
  return { props: { clientFallback: true, initialSettings: settings } };
};

export default ProductPage;
