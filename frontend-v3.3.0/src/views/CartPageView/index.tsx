import { FC, lazy, Suspense, useCallback, useEffect, useState } from "react";
import { useSelector } from "react-redux";
import { RootState } from "@/lib/redux/store";
import CartAdditionalInfo from "./CartAdditionalInfo";
import CheckoutSection from "./CheckoutSection";
import CartPageEmpty from "../empty/CartPageEmpty";
import ConfettiTrigger from "@/components/Functional/ConfettiTrigger";
import SaveForLaterItems from "./SaveForLaterItems";
import { subscribeCheckoutCompletion } from "@/helpers/checkoutCompletion";
import type { CartResponse } from "@/types/ApiResponse";
import { useRouter } from "next/router";
import { useTranslation } from "react-i18next";
const PendingImportedCart = lazy(() => import("@/components/Cart/PendingImportedCart"));
const GuestCartView = lazy(() => import("@/components/Cart/GuestCartView"));

const CartPageView: FC = () => {
  const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
  const { cartData, pendingImportedCart } = useSelector((state: RootState) => state.cart);
  const [completedCart, setCompletedCart] = useState<CartResponse | null>(null);
  const { t } = useTranslation();
  const router = useRouter();
  const finishCelebration = useCallback(() => {
    void router.replace("/my-account/orders");
  }, [router]);
  useEffect(() => subscribeCheckoutCompletion(setCompletedCart), []);
  const displayedCart = completedCart || cartData;

  if (!isLoggedIn) return <Suspense fallback={null}><GuestCartView /></Suspense>;

  if (!completedCart && pendingImportedCart) {
    return <Suspense fallback={<p role="status">{t("cartSharing.refreshNeeded")}</p>}><PendingImportedCart cart={pendingImportedCart} /></Suspense>;
  }

  if (!displayedCart || displayedCart.items.length === 0) {
    return (
      <div className="flex gap-2 flex-col">
        <CartPageEmpty />
        <SaveForLaterItems moreProductsInline={true} />
      </div>
    );
  }

  return (
    <>
      <fieldset
        disabled={!!completedCart}
        inert={!!completedCart}
        aria-hidden={completedCart ? true : undefined}
        onClickCapture={completedCart ? (event) => { event.preventDefault(); event.stopPropagation(); } : undefined}
        onKeyDownCapture={completedCart ? (event) => { event.preventDefault(); event.stopPropagation(); } : undefined}
        className={`m-0 min-w-0 border-0 p-0 w-full flex flex-col md:flex-row gap-4 ${completedCart ? "opacity-60 pointer-events-none select-none" : ""}`}
      >
        <div className="w-full md:w-[60%] lg:w-[65%]">
          <div className="mt-4">
            <CartAdditionalInfo cart={displayedCart} />
          </div>
        </div>

        {/* Checkout Section */}
        <div className="w-full md:w-[40%] lg:w-[35%] md:max-w-md">
          <CheckoutSection cart={displayedCart} completed={!!completedCart} />
        </div>
      </fieldset>
      {completedCart && (
        <>
          <p role="status" className="sr-only">{t("checkout.success")}</p>
          <ConfettiTrigger onComplete={finishCelebration} />
        </>
      )}
    </>
  );
};

export default CartPageView;
