import { useRef, useState } from "react";
import { useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import { addToast, Button, Input, Modal, ModalBody, ModalContent, ModalFooter, ModalHeader } from "@heroui/react";
import { Copy, Share2 } from "lucide-react";
import { shareCart } from "@/routes/api";
import { getCookie } from "@/lib/cookies";
import type { RootState } from "@/lib/redux/store";
import type { SharedCartData } from "@/types/sharedCart";

export default function ShareCartButton({ onLogin, compact = false }: { onLogin?: () => void; compact?: boolean }) {
  const { t, i18n } = useTranslation();
  const { isLoggedIn, access_token } = useSelector((state: RootState) => state.auth);
  const { cartData, pendingImportedCart, isLoading: cartLoading } = useSelector((state: RootState) => state.cart);
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<{ data: SharedCartData; token: string } | null>(null);
  const busy = useRef(false);
  const data = isLoggedIn && result?.token === access_token ? result.data : null;
  const hasItems = !!(pendingImportedCart ?? cartData)?.items.length;

  const openShare = () => {
    if (!isLoggedIn || !getCookie("access_token")) {
      if (onLogin) onLogin();
      else document.getElementById("login-btn")?.click();
      return;
    }
    setTitle(t("cartSharing.defaultTitle"));
    setResult(null);
    setOpen(true);
  };

  const createLink = async () => {
    const token = getCookie<string>("access_token");
    if (busy.current || !isLoggedIn || !token || !title.trim() || !hasItems) return;
    busy.current = true;
    setLoading(true);
    try {
      const res = await shareCart({ title: title.trim() });
      if (getCookie("access_token") !== token) return;
      if (!res.success || !res.data) {
        addToast({ title: t("cartSharing.shareFailed"), color: "danger" });
        return;
      }
      // Only web links can be handed to the clipboard or native share sheet.
      const url = new URL(res.data.share_url);
      if (!/^https?:$/.test(url.protocol)) throw new Error("Invalid shared cart URL");
      setResult({ data: res.data, token });
    } catch {
      addToast({ title: t("cartSharing.shareFailed"), color: "danger" });
    } finally {
      busy.current = false;
      setLoading(false);
    }
  };

  const copyLink = async () => {
    if (!data) return;
    try {
      await navigator.clipboard.writeText(data.share_url);
      addToast({ title: t("cartSharing.copied"), color: "success" });
    } catch {
      addToast({ title: t("cartSharing.copyFailed"), color: "warning" });
    }
  };

  const nativeShare = async () => {
    if (!data) return;
    if (!navigator.share) {
      await copyLink();
      return;
    }
    try {
      await navigator.share({ title: data.title, text: data.title, url: data.share_url });
    } catch (error) {
      if (!(error instanceof Error && error.name === "AbortError")) {
        addToast({ title: t("cartSharing.nativeShareFailed"), color: "warning" });
      }
    }
  };

  const expiry = data ? new Date(data.expires_at) : null;
  const expiresAt = expiry && Number.isFinite(expiry.getTime())
    ? expiry.toLocaleString(i18n.resolvedLanguage || i18n.language)
    : null;

  return (
    <>
      <Button variant={compact ? "light" : "flat"} size={compact ? "sm" : "md"} className={compact ? "h-6 text-xs" : undefined} color="primary" startContent={<Share2 size={compact ? 14 : 16} />} onPress={openShare} isDisabled={isLoggedIn && (!hasItems || cartLoading)}>
        {t("cartSharing.shareCart")}
      </Button>
      <Modal isOpen={open && isLoggedIn} onOpenChange={setOpen} isDismissable={!loading} isKeyboardDismissDisabled={loading} hideCloseButton={loading}>
        <ModalContent>
          <ModalHeader>{t("cartSharing.shareCart")}</ModalHeader>
          <ModalBody>
            {data ? (
              <>
                <p className="font-semibold">{data.title}</p>
                <p className="text-sm text-default-500">{t("cartSharing.linkReady", { count: data.items_count })}</p>
                <Input label={t("cartSharing.linkLabel")} value={data.share_url} isReadOnly onFocus={(event) => event.target.select()} />
                {expiresAt && <p className="text-xs text-default-500">{t("cartSharing.expiresAt", { date: expiresAt })}</p>}
              </>
            ) : (
              <>
                <p className="text-sm text-default-500">{t("cartSharing.shareDescription")}</p>
                <Input label={t("cartSharing.titleLabel")} value={title} onValueChange={setTitle} isRequired isDisabled={loading} />
              </>
            )}
          </ModalBody>
          <ModalFooter>
            {data ? (
              <>
                <Button variant="flat" startContent={<Copy size={16} />} onPress={copyLink}>{t("cartSharing.copyLink")}</Button>
                <Button color="primary" startContent={<Share2 size={16} />} onPress={nativeShare}>{t("cartSharing.shareLink")}</Button>
              </>
            ) : (
              <Button color="primary" onPress={createLink} isLoading={loading} isDisabled={!title.trim() || !hasItems || cartLoading}>{t("cartSharing.createLink")}</Button>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}
