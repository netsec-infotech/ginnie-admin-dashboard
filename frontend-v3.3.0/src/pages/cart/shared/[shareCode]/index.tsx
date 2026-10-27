import type { GetStaticPaths, GetStaticProps } from "next";
import { isSSR } from "@/helpers/getters";
import { getStaticGenerationContext } from "@/services/staticGenerationService";
import SharedCartPageView from "@/views/SharedCartPageView";
import { getSharedCart } from "@/routes/api";
import { isSharedCartCode } from "@/helpers/sharedCart";

// Shared codes are created after deployment. Apache serves this generic shell
// in export mode; SSR can generate the same public shell for any code on demand.
export const getStaticPaths: GetStaticPaths = async () => ({
  paths: isSSR() ? [] : [{ params: { shareCode: "__shared-cart__" } }],
  fallback: isSSR() || process.env.NODE_ENV === "development" ? "blocking" : false,
});

export const getStaticProps: GetStaticProps = async ({ params }) => {
  const { settings } = await getStaticGenerationContext();
  const code = params?.shareCode;
  const runtime = isSharedCartCode(code);
  const res = runtime ? await getSharedCart(code) : null;
  const valid = res?.success && res.data && res.data.share_code === code && !res.data.is_expired && Date.parse(res.data.expires_at) > Date.now();
  return { props: { initialSettings: settings, initialShareCode: runtime ? code : null, initialSharedCart: valid ? res.data : null }, ...(isSSR() ? { revalidate: 30 } : {}) };
};

export default SharedCartPageView;
