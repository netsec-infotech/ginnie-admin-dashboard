import { GetStaticPaths, GetStaticProps } from "next";

import { isSSR } from "@/helpers/getters";
import ProductPage, {
  ProductPageProps,
} from "@/pages/products/[slug]/index";
import {
  getStaticGenerationContext,
  getStaticProductSlugs,
  getStaticProduct,
} from "@/services/staticGenerationService";

/**
 * Social crawlers do not run the client-side product request. Share URLs must
 * therefore be real product pages in the export, not the generic [slug] shell
 * that previously left Slack, WhatsApp, and Discord without Open Graph data.
 * ProductPage intentionally keeps its canonical/og:url on /products/:slug/.
 */
export const getStaticPaths: GetStaticPaths = async () => ({
  paths: (await getStaticProductSlugs()).map((slug) => ({ params: { slug } })),
  fallback: isSSR() ? "blocking" : false,
});

export const getStaticProps: GetStaticProps<ProductPageProps> = async (
  context,
) => {
  const slug = context.params?.slug;
  if (!slug || Array.isArray(slug)) return { notFound: true };

  const [{ settings }, product] = await Promise.all([
    getStaticGenerationContext(),
    getStaticProduct(slug, isSSR() && context.revalidateReason !== "build"),
  ]);
  if (!product) return { notFound: true, ...(isSSR() ? { revalidate: 300 } : {}) };

  return {
    props: {
      initialProduct: product,
      initialSimilarProducts: [],
      initialSettings: settings,
      slug,
    },
    ...(isSSR() ? { revalidate: 300 } : {}),
  };
};

export default ProductPage;
