import { guestCurrencyTotals, formatMoney } from "@/helpers/currency";
import { Button, Image } from "@heroui/react";
import { Minus, Plus, Trash2 } from "lucide-react";
import Link from "next/link";
import { useDispatch, useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import { useSettings } from "@/contexts/SettingsContext";
import { removeOfflineCartItem, updateOfflineCartItemQuantity } from "@/lib/redux/slices/offlineCartSlice";
import type { RootState } from "@/lib/redux/store";
import CartPageEmpty from "@/views/empty/CartPageEmpty";

export default function GuestCartView() {
  const { t } = useTranslation();
  const dispatch = useDispatch();
  const { currencySymbol } = useSettings();
  const { items } = useSelector((state: RootState) => state.offlineCart);
  if (!items.length) return <CartPageEmpty />;
  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 py-6">
      {items.map((item) => (
        <div key={item.id} className="flex items-center gap-4 rounded-xl border border-default-200 p-4">
          {item.image && <Image src={item.image} alt={item.name} className="h-16 w-16 object-contain" />}
          <div className="min-w-0 flex-1">
            <Link href={`/products/${item.slug}/`} className="text-sm font-semibold">{item.name}</Link>
            <p className="text-xs text-default-500">{item.storeName}</p>
            {!!item.addons?.length && <p className="text-xs text-default-500">{item.addons.map((addon) => addon.title).join(", ")}</p>}
            <div className="mt-2 flex items-center gap-3">
              <Button isIconOnly size="sm" variant="flat" aria-label={t("cartSharing.decreaseQuantity", { name: item.name })}
                isDisabled={item.quantity <= item.minQuantity} onPress={() => dispatch(updateOfflineCartItemQuantity({ id: item.id, quantity: item.quantity - item.stepSize }))}><Minus size={14} /></Button>
              <span className="text-sm">{item.quantity}</span>
              <Button isIconOnly size="sm" variant="flat" aria-label={t("cartSharing.increaseQuantity", { name: item.name })}
                isDisabled={item.quantity + item.stepSize > Math.min(item.stock, item.maxQuantity)} onPress={() => dispatch(updateOfflineCartItemQuantity({ id: item.id, quantity: item.quantity + item.stepSize }))}><Plus size={14} /></Button>
            </div>
          </div>
          <div className="flex flex-col items-end gap-2">
            <span className="text-sm font-semibold">{formatMoney((item.price + (item.addons || []).reduce((total, addon) => total + addon.price, 0)) * item.quantity, item, currencySymbol)}</span>
            <Button isIconOnly size="sm" variant="light" color="danger" aria-label={t("cartSharing.removeItem", { name: item.name })} onPress={() => dispatch(removeOfflineCartItem(item.id))}><Trash2 size={16} /></Button>
          </div>
        </div>
      ))}
      <div className="flex items-center justify-between rounded-xl border border-default-200 p-4">
        <span>{t("checkout.itemsTotal")}</span><strong>{guestCurrencyTotals(items).map((total) => <span className="block" key={total.currency_code || "legacy"}>{formatMoney(total.amount, total, currencySymbol)}</span>)}</strong>
      </div>
      <Button color="primary" onPress={() => document.getElementById("login-btn")?.click()}>{t("cartSharing.signInToCheckout")}</Button>
    </div>
  );
}
