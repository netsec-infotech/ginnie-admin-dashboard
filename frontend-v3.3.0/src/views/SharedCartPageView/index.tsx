import { useEffect } from "react";
import { useRouter } from "next/router";
import { useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import dynamic from "next/dynamic";
import PageHead from "@/SEO/PageHead";
import SharedCartImportModal from "@/components/Cart/SharedCartImportModal";
import { getSharedCartCode } from "@/helpers/sharedCart";
import { updateCartData } from "@/helpers/updators";
import type { RootState } from "@/lib/redux/store";
import type { SharedCartPreview } from "@/types/sharedCart";

const CartPageView = dynamic(() => import("@/views/CartPageView"), { ssr: false });

export default function SharedCartPageView({ initialSharedCart = null, initialShareCode = null }: { initialSharedCart?: SharedCartPreview | null; initialShareCode?: string | null }) {
  const { t } = useTranslation();
  const router = useRouter();
  const { access_token, isLoggedIn } = useSelector((state: RootState) => state.auth);
  const code = router.isReady ? getSharedCartCode(router.asPath) : initialShareCode;
  useEffect(() => { if (isLoggedIn) void updateCartData(true, false, 0, false, false); }, [isLoggedIn, access_token]);
  return (
    <>
      <PageHead pageTitle={t("pageTitle.cart")} noindex />
      <div className="min-h-[60vh] py-6">
        <h1 className="text-xl font-semibold">{t("pageTitle.cart")}</h1>
        <CartPageView />
      </div>
      {router.isReady && <SharedCartImportModal key={`${code}:${access_token || "guest"}`} code={code} initialData={initialSharedCart} onClose={() => { void router.replace("/cart/"); }} />}
    </>
  );
}
