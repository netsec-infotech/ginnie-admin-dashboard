import { GetServerSideProps, GetStaticPaths, GetStaticProps } from "next";
import { getSlugFromContext, isSSR } from "@/helpers/getters";
import { getAccessTokenFromContext } from "@/helpers/auth";
import { getUserLocationFromContext } from "@/helpers/functionalHelpers";
import { fetchProductDetailPageData } from "@/services/ProductDetailPageService";
import { loadTranslations } from "../../../../i18n";
import { getStaticGenerationContext, getStaticProductSlugs, getStaticProduct } from "@/services/staticGenerationService";
import ProductPage from "@/views/Products/ProductPage";

export type { ProductPageProps } from "@/views/Products/ProductPage";

const PER_PAGE = 20;

export const getRequestTimeProductProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      try {
        // Get access token and user location
        const access_token = (await getAccessTokenFromContext(context)) || "";
        const { lat = "", lng = "" } =
          (await getUserLocationFromContext(context)) || {};
        await loadTranslations(context);

        // Get product slug from URL
        const slug = getSlugFromContext(context);

        // Fetch all product page data from the service
        const data = await fetchProductDetailPageData({
          slug,
          access_token,
          lat,
          lng,
          PER_PAGE,
        });

        return {
          props: {
            ...data,
            slug,
          },
        };
      } catch (err) {
        console.error("Unexpected error in getServerSideProps:", err);
        return {
          props: {
            error:
              err instanceof Error
                ? err.message
                : "An unexpected SSR error occurred",
          },
        };
      }
    }
  : undefined;

export const getStaticPaths: GetStaticPaths = async () => ({
      paths: (await getStaticProductSlugs()).map((slug) => ({ params: { slug } })),
      fallback: isSSR() ? "blocking" : false,
    });

export const getStaticProps: GetStaticProps = async (context) => {
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
