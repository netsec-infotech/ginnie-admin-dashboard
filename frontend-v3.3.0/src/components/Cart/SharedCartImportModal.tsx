import { useCallback, useEffect, useRef, useState } from "react";
import { useDispatch, useSelector, useStore } from "react-redux";
import { useTranslation } from "react-i18next";
import { Button, Image, Modal, ModalBody, ModalContent, ModalFooter, ModalHeader, Spinner } from "@heroui/react";
import { ArrowRight, Check, CheckCheck, CircleAlert, Package, Plus, RefreshCw, ShieldCheck, ShoppingBag, ShoppingCart } from "lucide-react";
import { getProductBySlug, getSharedCart, importSharedCart } from "@/routes/api";
import { useSettings } from "@/contexts/SettingsContext";
import { getConfiguredDefaultLocation } from "@/helpers/getters";
import { sharedItemToGuestItem } from "@/helpers/guestSharedCart";
import { updateCartData } from "@/helpers/updators";
import { invalidateCartLocationConsent } from "@/helpers/cartLocationConfirmation";
import { clearCompletedPaymentAttempt, paymentAttemptScope } from "@/helpers/paymentReturn";
import { getCookie } from "@/lib/cookies";
import { setImportedCart } from "@/lib/redux/slices/cartSlice";
import offlineCartReducer, { addOfflineCartItem, setOfflineCart } from "@/lib/redux/slices/offlineCartSlice";
import { clearScheduledDelivery } from "@/lib/redux/slices/checkoutSlice";
import type { RootState } from "@/lib/redux/store";
import type { Product } from "@/types/ApiResponse";
import type { CartImportAction, CartImportSummary, SharedCartPreview, SharedCartPreviewItem } from "@/types/sharedCart";

export default function SharedCartImportModal({ code, initialData = null, onClose }: {
  code: string | null; initialData?: SharedCartPreview | null; onClose: () => void;
}) {
  const { t } = useTranslation();
  const dispatch = useDispatch();
  const store = useStore<RootState>();
  const { webSettings } = useSettings();
  const auth = useSelector((state: RootState) => state.auth);
  const location = getCookie<{ lat: number; lng: number }>("userLocation") || getConfiguredDefaultLocation(webSettings);
  const latitude = location?.lat;
  const longitude = location?.lng;
  const [data, setData] = useState(initialData?.share_code === code ? initialData : null);
  const [reading, setReading] = useState(true);
  const [loading, setLoading] = useState(false);
  const [action, setAction] = useState<CartImportAction>("merge");
  const [summary, setSummary] = useState<CartImportSummary | null>(null);
  const [error, setError] = useState<string | null>(code ? null : "cartSharing.invalidLink");
  const [refreshNeeded, setRefreshNeeded] = useState(false);
  const [hasMoreBelow, setHasMoreBelow] = useState(false);
  const busy = useRef(false);
  const active = useRef(true);
  const scrollBodyRef = useRef<HTMLDivElement>(null);
  const updateScrollHint = useCallback(() => {
    const body = scrollBodyRef.current;
    setHasMoreBelow(!!body && body.scrollHeight - body.scrollTop - body.clientHeight > 2);
  }, []);
  useEffect(() => { active.current = true; return () => { active.current = false; }; }, []);
  useEffect(() => {
    const body = scrollBodyRef.current;
    if (!body) return;
    updateScrollHint();
    const frame = requestAnimationFrame(updateScrollHint);
    const observer = typeof ResizeObserver === "undefined" ? null : new ResizeObserver(updateScrollHint);
    observer?.observe(body);
    for (const child of body.children) observer?.observe(child);
    return () => {
      cancelAnimationFrame(frame);
      observer?.disconnect();
    };
  }, [action, data, error, reading, refreshNeeded, summary, updateScrollHint]);
  useEffect(() => {
    if (!code) return;
    let cancelled = false;
    void getSharedCart(code, { latitude, longitude }).then((res) => {
      if (cancelled) return;
      if (!res.success || !res.data || res.data.is_expired || Date.parse(res.data.expires_at) <= Date.now()) {
        setData(null);
        setError(res.data?.is_expired || res.status === 404 || res.status === 410 ? "cartSharing.invalidLink" : "cartSharing.previewFailed");
      } else {
        setData(res.data);
        setError(null);
      }
      setReading(false);
    });
    return () => { cancelled = true; };
  }, [code, latitude, longitude]);

  const importCart = async () => {
    if (!code || !data || reading || busy.current || summary) return;
    const token = getCookie<string>("access_token");
    const signedIn = auth.isLoggedIn && !!token;
    if (!!token !== auth.isLoggedIn) { setError("cartSharing.sessionChanged"); return; }
    const sameSession = () => active.current && getCookie("access_token") === token && store.getState().auth.isLoggedIn === signedIn && (!signedIn || store.getState().auth.access_token === token);
    busy.current = true;
    setLoading(true);
    setError(null);
    try {
      if (signedIn) {
        const res = await importSharedCart(code, action);
        if (!sameSession()) return;
        if (!res.success || !res.data) {
          setError(res.status === 404 || res.status === 410 ? "cartSharing.invalidLink" : "cartSharing.importFailed");
          return;
        }
        clearCompletedPaymentAttempt(paymentAttemptScope({
          usageType: "order",
          userId: store.getState().auth.user?.id,
          cartId: res.data.cart.uuid || res.data.cart.id,
        }));
        dispatch(setImportedCart(res.data.cart));
        dispatch(clearScheduledDelivery());
        invalidateCartLocationConsent();
        setSummary(res.data.import_summary);
        const refreshed = await updateCartData(true, false, 0, false, false);
        if (sameSession()) setRefreshNeeded(!refreshed?.success);
      } else {
        const res = await getSharedCart(code, { latitude, longitude });
        if (!sameSession()) return;
        if (!res.success || !res.data || res.data.is_expired || Date.parse(res.data.expires_at) <= Date.now()) {
          setError(res.data?.is_expired || res.status === 404 || res.status === 410 ? "cartSharing.invalidLink" : "cartSharing.importFailed");
          return;
        }
        const products = new Map<string, Product>();
        const staged = [];
        const skipped: SharedCartPreviewItem[] = [];
        for (const item of res.data.items) {
          if (!item.is_available || item.is_deliverable === false || !item.is_store_online || !item.store_is_accepting_orders) { skipped.push(item); continue; }
          let product = products.get(item.product_slug);
          if (!product) {
            const productRes = await getProductBySlug({ slug: item.product_slug, latitude, longitude });
            if (!sameSession()) return;
            if (!productRes.success || !productRes.data) { setError("cartSharing.importFailed"); return; }
            product = productRes.data;
            products.set(item.product_slug, product);
          }
          const guestItem = sharedItemToGuestItem(item, product);
          if (guestItem) staged.push(guestItem);
          else skipped.push(item);
        }
        if (!sameSession()) return;
        // Stage validation before committing either operation. Merge keeps edits
        // made during the request; replace starts from an empty cart.
        let nextCart = action === "replace"
          ? offlineCartReducer(undefined, setOfflineCart([]))
          : store.getState().offlineCart;
        const result: CartImportSummary = { imported_count: 0, updated_count: 0, unchanged_count: 0, skipped_count: skipped.length,
          imported_items: [], updated_items: [], unchanged_items: [], skipped_items: skipped.map((item) => ({ ...item, quantity: item.shared_quantity })) };
        for (const item of staged) {
          const previous = nextCart.items.find((entry) => entry.id === item.id);
          nextCart = offlineCartReducer(nextCart, addOfflineCartItem(item));
          if (!previous) result.imported_count++;
          else if (previous.quantity === nextCart.items.find((entry) => entry.id === item.id)?.quantity) result.unchanged_count++;
          else result.updated_count++;
        }
        dispatch(setOfflineCart(nextCart.items));
        setSummary(result);
      }
    } catch {
      if (sameSession()) setError("cartSharing.importFailed");
    } finally {
      if (active.current) { busy.current = false; setLoading(false); }
    }
  };

  const summaryStats = summary ? [
    { label: "cartSharing.added", count: summary.imported_count, icon: Plus, style: "border-success-200 bg-success-50 text-success-700 dark:border-success/25 dark:bg-success/10 dark:text-success" },
    { label: "cartSharing.updated", count: summary.updated_count, icon: RefreshCw, style: "border-primary-200 bg-primary-50 text-primary-700 dark:border-primary/25 dark:bg-primary/10 dark:text-primary-200" },
    { label: "cartSharing.unchanged", count: summary.unchanged_count, icon: CheckCheck, style: "border-default-200 bg-default-50 text-default-600" },
    { label: "cartSharing.skipped", count: summary.skipped_count, icon: CircleAlert, style: summary.skipped_count > 0 ? "border-warning-200 bg-warning-50 text-warning-700 dark:border-warning/25 dark:bg-warning/10 dark:text-warning" : "border-default-200 bg-default-50 text-default-600" },
  ] : [];
  const allSkipped = !!summary && summary.skipped_count > 0 && summary.imported_count + summary.updated_count + summary.unchanged_count === 0;
  const HeaderIcon = summary ? (allSkipped ? CircleAlert : Check) : ShoppingBag;

  return (
    <Modal isOpen onClose={onClose} isDismissable={!loading} isKeyboardDismissDisabled={loading} hideCloseButton={loading} scrollBehavior="inside" size="lg"
      classNames={{
        backdrop: "bg-black/45 backdrop-blur-sm",
        base: "mx-3 overflow-hidden rounded-2xl border border-default-200/60 bg-content1 shadow-2xl",
        closeButton: "end-4 top-4 z-10 rounded-full bg-content1/80 text-default-600 hover:bg-default-100",
      }}>
      <ModalContent>
        <ModalHeader className={`flex items-center gap-4 border-b border-default-100 px-5 pb-6 pt-7 pe-12 sm:px-7 sm:pe-14 ${summary ? (allSkipped ? "bg-gradient-to-br from-warning-50 to-content1 dark:from-warning/10" : "bg-gradient-to-br from-success-50 to-content1 dark:from-success/10") : "bg-gradient-to-br from-primary-50 to-content1 dark:from-primary/15"}`}>
          <div aria-hidden="true" className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-content1 bg-content1 shadow-sm ${summary ? (allSkipped ? "text-warning-600 dark:text-warning" : "text-success-600 dark:text-success") : "text-primary"}`}>
            <HeaderIcon size={28} strokeWidth={1.8} />
          </div>
          <div className="min-w-0">
            <p className="mb-1.5 text-xs font-medium text-default-500 dark:text-default-400">{t("cartSharing.receivedTitle")}</p>
            <h2 className="break-words text-xl font-semibold leading-snug text-foreground">{data?.title || t("cartSharing.defaultTitle")}</h2>
            {data && !summary && <span className="mt-2 inline-flex rounded-full bg-primary-100/60 px-2.5 py-1 text-xs font-medium text-primary-700 dark:bg-primary/15 dark:text-primary-200">{t("cartSharing.itemsCount", { count: data.summary.total_items })}</span>}
          </div>
        </ModalHeader>
        <ModalBody ref={scrollBodyRef} onScroll={updateScrollHint} className="shared-cart-scroll-body gap-5 px-5 py-6 sm:px-7">
          {reading && code ? <div className="flex min-h-36 items-center justify-center"><Spinner label={t("loading")} /></div> : summary ? (
            <>
              <div>
                <p role="status" className="text-lg font-semibold text-foreground">{t("cartSharing.importSuccess")}</p>
                <p className="mt-1 text-sm leading-relaxed text-default-500 dark:text-default-400">{t("cartSharing.resultDescription")}</p>
              </div>
              <dl className="grid grid-cols-2 gap-3">
                {summaryStats.map(({ label, count, icon: Icon, style }) => (
                  <div key={label} className={`flex flex-col rounded-2xl border p-4 ${style}`}>
                    <dt className="flex items-center gap-2 text-xs font-medium"><Icon aria-hidden="true" size={16} />{t(label)}</dt>
                    <dd className="mt-2 text-3xl font-semibold leading-none tabular-nums">{count}</dd>
                  </div>
                ))}
              </dl>
              {summary.skipped_count > 0 && <div className="flex gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning/25 dark:bg-warning/10 dark:text-warning"><CircleAlert aria-hidden="true" size={18} className="mt-0.5 shrink-0" /><div className="min-w-0"><p className="font-medium">{t("cartSharing.skippedDescription")}</p><ul className="mt-2 list-disc space-y-1 break-words ps-4">{summary.skipped_items.map((item, index) => <li key={index}>{item.product_name}</li>)}</ul></div></div>}
              {refreshNeeded && <p role="status" className="rounded-xl bg-warning-50 p-3 text-sm leading-relaxed text-warning-800 dark:bg-warning/10 dark:text-warning">{t("cartSharing.refreshNeeded")}</p>}
            </>
          ) : data && (
            <>
              <p className="text-sm leading-relaxed text-default-600">{t("cartSharing.reviewDescription")}</p>
              <fieldset disabled={loading || reading} className="min-w-0">
                <legend className="mb-3 text-sm font-semibold text-foreground">{t("cartSharing.importChoice")}</legend>
                <div className="grid gap-3 sm:grid-cols-2">
                  {(["merge", "replace"] as const).map((option) => {
                    const selected = action === option;
                    const Icon = option === "merge" ? Plus : RefreshCw;
                    return <label key={option} className={`relative flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-colors focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 ${selected ? "border-primary bg-primary-50/60 dark:bg-primary/10" : "border-default-200 bg-content1 hover:bg-default-50"} ${loading ? "cursor-wait opacity-60" : ""}`}>
                      <input type="radio" name="cart-import-action" value={option} checked={selected} onChange={() => setAction(option)} className="sr-only" aria-labelledby={`cart-import-${option}-title`} aria-describedby={`cart-import-${option}-description`} />
                      <Icon aria-hidden="true" size={20} className={`mt-0.5 shrink-0 ${selected ? "text-primary" : "text-default-500"}`} />
                      <span className="min-w-0 flex-1">
                        <span id={`cart-import-${option}-title`} className="flex items-center justify-between gap-2 text-sm font-semibold">{t(`cartSharing.${option}Title`)}<span aria-hidden="true" className={`flex h-4 w-4 shrink-0 items-center justify-center rounded-full border ${selected ? "border-primary bg-primary text-white" : "border-default-300"}`}>{selected && <Check size={12} />}</span></span>
                        <span id={`cart-import-${option}-description`} className="mt-1.5 block text-xs leading-relaxed text-default-600">{t(`cartSharing.${option}Description`)}</span>
                      </span>
                    </label>;
                  })}
                </div>
              </fieldset>
              <div role={action === "replace" ? "status" : undefined} className={`flex items-start gap-2.5 rounded-xl p-3.5 ${action === "replace" ? "border border-warning-200 bg-warning-50 text-warning-800 dark:border-warning/25 dark:bg-warning/10 dark:text-warning" : "bg-primary-50/70 text-primary-800 dark:bg-primary/10 dark:text-primary-200"}`}>
                {action === "replace" ? <CircleAlert aria-hidden="true" size={18} className="mt-0.5 shrink-0" /> : <ShieldCheck aria-hidden="true" size={18} className="mt-0.5 shrink-0" />}
                <div><p className="text-xs font-medium">{t(action === "replace" ? "cartSharing.replaceWarning" : "cartSharing.itemsPreserved")}</p><p className="mt-1 text-xs leading-relaxed text-default-500 dark:text-default-400">{t("cartSharing.locationNotice")}</p></div>
              </div>
              <ul className="flex flex-col gap-3">
                {data.items.map((item, index) => {
                  const unavailable = !item.is_available || item.is_deliverable === false || !item.is_store_online || !item.store_is_accepting_orders;
                  return <li key={`${item.product_variant_id}-${index}`} className={`flex items-start gap-3 rounded-2xl border p-3.5 ${unavailable ? "border-warning-200 bg-warning-50/40 dark:border-warning/25 dark:bg-warning/10" : "border-default-200 bg-default-50/50"}`}>
                    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-content1 sm:h-20 sm:w-20">
                      {item.product_image ? <Image src={item.product_image} alt={item.product_name} classNames={{ wrapper: "!h-full !w-full" }} className="h-full w-full rounded-xl object-contain p-1" /> : <Package aria-hidden="true" size={26} className="text-default-300" />}
                    </div>
                    <div className="min-w-0 flex-1 py-0.5">
                      <p className="break-words text-sm font-semibold leading-snug">{item.product_name}</p>
                      {item.variant_title && item.variant_title !== item.product_name && <p className="mt-1 break-words text-xs leading-relaxed text-default-500 dark:text-default-400">{item.variant_title}</p>}
                      {item.store_name && <p className="mt-1 break-words text-xs text-default-500 dark:text-default-400">{item.store_name}</p>}
                      <span className="mt-2 inline-flex rounded-lg border border-default-200 bg-content1 px-2 py-1 text-xs font-medium text-default-600">{t("cartSharing.sharedQuantity", { count: item.shared_quantity })}</span>
                      {unavailable && <p className="mt-2 text-xs leading-relaxed text-warning-800 dark:text-warning">{t("cartSharing.itemUnavailable")}</p>}
                    </div>
                  </li>;
                })}
              </ul>
            </>
          )}
          {error && <div role="alert" className="flex gap-2.5 rounded-xl border border-danger-200 bg-danger-50 p-3.5 text-sm text-danger-700"><CircleAlert aria-hidden="true" size={18} className="mt-0.5 shrink-0" /><p>{t(error)}</p></div>}
        </ModalBody>
        <ModalFooter className={`gap-3 border-t border-default-100 bg-default-50/50 px-5 py-4 sm:px-7 ${hasMoreBelow ? "shadow-[0_-12px_16px_-12px_rgba(0,0,0,0.18)]" : ""}`}>
          {summary ? <Button color="primary" className="h-12 w-full rounded-xl font-semibold shadow-sm" startContent={<ShoppingCart aria-hidden="true" size={18} />} endContent={<ArrowRight aria-hidden="true" size={17} className="rtl:rotate-180" />} onPress={onClose} isDisabled={loading}>{t("cartSharing.viewCart")}</Button> : <>
            <Button variant="light" className="h-12 shrink-0 rounded-xl px-4 text-default-600" onPress={onClose} isDisabled={loading}>{t("cartSharing.notNow")}</Button>
            {data && <Button color="primary" className="h-12 min-w-0 flex-1 whitespace-normal rounded-xl px-3 font-semibold shadow-sm" startContent={!loading && <ShoppingCart aria-hidden="true" size={18} className="shrink-0" />} onPress={importCart} isLoading={loading} isDisabled={reading}>{t(action === "replace" ? "cartSharing.replaceCart" : "cartSharing.importCart")}</Button>}
          </>}
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
