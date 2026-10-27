import { useRef, useState } from "react";
import { addToast, Button } from "@heroui/react";
import { Bell, BellRing } from "lucide-react";
import { useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import type { RootState } from "@/lib/redux/store";
import type { Store } from "@/types/ApiResponse";
import { notifyStoreOpening } from "@/routes/api";

export default function StoreNotifyButton({
  store,
  className = "",
}: {
  store: Pick<Store, "id" | "status" | "is_notify">;
  className?: string;
}) {
  const { t } = useTranslation();
  const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
  const initialNotified = store.is_notify === true;
  const [state, setState] = useState({
    id: store.id,
    initialNotified,
    notified: initialNotified,
  });
  const [isLoading, setIsLoading] = useState(false);
  const pending = useRef(false);

  if (state.id !== store.id || state.initialNotified !== initialNotified) {
    setState({ id: store.id, initialNotified, notified: initialNotified });
  }

  const toggleNotification = async () => {
    if (pending.current) return;
    if (!isLoggedIn) {
      document.getElementById("login-btn")?.click();
      addToast({ title: t("cart.login_required"), color: "warning" });
      return;
    }
    pending.current = true;
    setIsLoading(true);
    const target = !state.notified;
    try {
      const response = await notifyStoreOpening(store.id, target);
      if (response.success) {
        setState((current) =>
          current.id === store.id ? { ...current, notified: target } : current,
        );
        addToast({
          title: t(
            target
              ? "storeOpeningNotification.enabled"
              : "storeOpeningNotification.cancelled",
          ),
          color: "success",
        });
      } else {
        addToast({
          title: t("storeOpeningNotification.error"),
          color: "danger",
        });
      }
    } catch {
      addToast({ title: t("storeOpeningNotification.error"), color: "danger" });
    } finally {
      pending.current = false;
      setIsLoading(false);
    }
  };

  if (store.status.is_open) return null;

  return (
    <Button
      size="sm"
      color="primary"
      variant="flat"
      radius="full"
      className={`h-7 min-w-0 max-w-full gap-1.5 border border-primary/20 px-2.5 text-xs font-medium text-primary-700 dark:text-primary-200 ${className}`}
      isLoading={isLoading}
      isDisabled={isLoading}
      aria-pressed={state.notified}
      startContent={
        state.notified ? (
          <BellRing size={14} className="shrink-0" />
        ) : (
          <Bell size={14} className="shrink-0" />
        )
      }
      onPress={toggleNotification}
    >
      <span className="truncate">
        {t(
          state.notified
            ? "storeOpeningNotification.subscribed"
            : "storeOpeningNotification.notify",
        )}
      </span>
    </Button>
  );
}
