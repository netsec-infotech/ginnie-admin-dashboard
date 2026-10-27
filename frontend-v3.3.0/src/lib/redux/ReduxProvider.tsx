import { ReactNode, FC, useEffect, useRef } from "react";
import { PersistGate } from "redux-persist/integration/react";
import { createServerStore, persistor, store } from "./store";
import { Provider } from "react-redux";
import {
  CART_SYNC_STORAGE_KEY,
  getCookie,
  setCookie,
  subscribeToAuthSessionChanges,
} from "@/lib/cookies";
import { logout } from "@/lib/redux/slices/authSlice";
import { clearCart } from "@/lib/redux/slices/cartSlice";
import Router from "next/router";
import {
  setOfflineCart,
  type OfflineCartItem,
} from "@/lib/redux/slices/offlineCartSlice";
import { activateLanguage, languagesReceived } from "@/lib/redux/slices/localizationSlice";
import { applyLanguageSnapshot } from "../../../i18n";
import type { LocalizationSnapshot, WebLanguage } from "@/types/localization";

type ProviderProps = {
  children: ReactNode;
  initialLocalization?: LocalizationSnapshot;
  initialLanguages?: WebLanguage[];
  initialDefaultLocale?: string;
};

type CartSyncMessage = {
  version: 1;
  origin: string;
  mode: "authenticated" | "guest";
  signature: string;
  items?: OfflineCartItem[];
};

const snapshotSignature = (value: unknown): string => {
  const serialized = JSON.stringify(value) ?? "undefined";
  let hash = 2166136261;
  for (let index = 0; index < serialized.length; index += 1) {
    hash ^= serialized.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return (hash >>> 0).toString(36);
};

const authenticatedCartContents = (
  cart: Pick<NonNullable<ReturnType<typeof store.getState>["cart"]["cartData"]>, "id" | "items"> | null | undefined,
) =>
  cart
    ? {
        id: cart.id,
        items: cart.items
          .map((item) => ({
            id: item.id,
            productVariantId: item.product_variant_id,
            quantity: item.quantity,
            saveForLater: item.save_for_later,
            addons: (item.addons || [])
              .map((addon) => ({
                groupId: addon.addon_group_id,
                itemId: addon.addon_item_id,
              }))
              .sort(
                (left, right) =>
                  left.groupId - right.groupId || left.itemId - right.itemId,
              ),
          }))
          .sort((left, right) => left.id - right.id),
      }
    : null;

const currentCartSnapshot = () => {
  const state = store.getState();
  return state.auth.isLoggedIn
    ? {
        mode: "authenticated" as const,
        signature: snapshotSignature(
          authenticatedCartContents(state.cart.pendingImportedCart ?? state.cart.cartData),
        ),
      }
    : {
        mode: "guest" as const,
        signature: snapshotSignature(state.offlineCart.items),
        items: state.offlineCart.items,
      };
};

const ReduxProvider: FC<ProviderProps> = ({
  children,
  initialLocalization,
  initialLanguages,
  initialDefaultLocale,
}) => {
  // The server must emit the page for SEO while PersistGate waits for browser
  // storage. Reusing the page as its loading state makes the browser's first
  // render match that server HTML exactly.
  const isServerRender = typeof window === "undefined";
  const appliedInitialState = useRef(false);
  const activeStore = isServerRender && initialLocalization
    ? createServerStore(
        initialLocalization,
        initialLanguages || [],
        initialDefaultLocale || initialLocalization.locale,
      )
    : store;
  if (!isServerRender && initialLocalization && !appliedInitialState.current) {
    // Seed before the first hydrated child reads Redux or i18next.
    store.dispatch(languagesReceived({
      languages: initialLanguages || [],
      defaultLocale: initialDefaultLocale || initialLocalization.locale,
    }));
    store.dispatch(activateLanguage(initialLocalization));
    applyLanguageSnapshot(initialLocalization);
    setCookie("i18nextLng", initialLocalization.locale, { expires: 365 });
    appliedInitialState.current = true;
  }

  useEffect(() => {
    let navigationTimer: ReturnType<typeof setTimeout> | undefined;
    let cartRefreshTimer: ReturnType<typeof setTimeout> | undefined;
    const origin =
      window.crypto?.randomUUID?.() ||
      `${Date.now()}:${Math.random().toString(36)}`;
    let previousCart = currentCartSnapshot();

    const unsubscribeCart = store.subscribe(() => {
      const current = currentCartSnapshot();
      if (
        current.mode === previousCart.mode &&
        current.signature === previousCart.signature
      ) {
        return;
      }
      previousCart = current;
      const message: CartSyncMessage = {
        version: 1,
        origin,
        mode: current.mode,
        signature: current.signature,
        ...(current.mode === "guest" ? { items: current.items } : {}),
      };
      try {
        localStorage.setItem(CART_SYNC_STORAGE_KEY, JSON.stringify(message));
      } catch {
        // A blocked storage area disables cross-tab sync only. Redux and the
        // server cart remain authoritative in the current tab.
      }
    });

    const handleCartStorage = (event: StorageEvent) => {
      if (event.key !== CART_SYNC_STORAGE_KEY || !event.newValue) return;
      let message: CartSyncMessage;
      try {
        message = JSON.parse(event.newValue) as CartSyncMessage;
      } catch {
        return;
      }
      if (
        message.version !== 1 ||
        message.origin === origin ||
        (message.mode !== "authenticated" && message.mode !== "guest") ||
        typeof message.signature !== "string"
      ) {
        return;
      }

      const state = store.getState();
      const cookieToken = String(getCookie("access_token") || "");
      if (message.mode === "authenticated") {
        // Ignore cart traffic during an account transition. The auth-session
        // listener will reconcile that identity first.
        if (
          !state.auth.isLoggedIn ||
          !cookieToken ||
          state.auth.access_token !== cookieToken ||
          snapshotSignature(authenticatedCartContents(state.cart.pendingImportedCart ?? state.cart.cartData)) ===
            message.signature
        ) {
          return;
        }
        clearTimeout(cartRefreshTimer);
        cartRefreshTimer = setTimeout(() => {
          void import("@/helpers/updators").then(({ updateCartData }) =>
            updateCartData(true, false),
          );
        }, 50);
        return;
      }

      if (
        state.auth.isLoggedIn ||
        cookieToken ||
        !Array.isArray(message.items) ||
        snapshotSignature(message.items) !== message.signature ||
        snapshotSignature(state.offlineCart.items) === message.signature
      ) {
        return;
      }
      // Set the expected snapshot before dispatch so our store subscriber does
      // not echo a remotely received guest-cart update back to every tab.
      previousCart = {
        mode: "guest",
        signature: message.signature,
        items: message.items,
      };
      store.dispatch(setOfflineCart(message.items));
    };

    window.addEventListener("storage", handleCartStorage);
    const unsubscribe = subscribeToAuthSessionChanges(() => {
      // Redux Persist writes just after the login/logout dispatch. Give that
      // write a moment to settle, then rebuild this tab from the authoritative
      // cookie + persisted state instead of mixing two account identities.
      clearTimeout(navigationTimer);
      navigationTimer = setTimeout(() => {
        const hasToken = Boolean(getCookie("access_token"));
        const isAccountPage =
          window.location.pathname === "/my-account" ||
          window.location.pathname.startsWith("/my-account/");
        if (!hasToken) {
          // A logout in another tab can be reconciled without reloading the
          // current Turbopack module graph. This avoids stale chunk factories
          // during development and clears private UI immediately.
          store.dispatch(logout());
          store.dispatch(clearCart());
          if (isAccountPage) void Router.replace("/");
          return;
        }

        // A different tab signed in. Rehydrate that new identity rather than
        // mixing its cookie with this tab's in-memory account.
        if (store.getState().auth.access_token !== getCookie("access_token")) {
          window.location.reload();
        }
      }, 50);
    });
    return () => {
      unsubscribe();
      unsubscribeCart();
      window.removeEventListener("storage", handleCartStorage);
      clearTimeout(navigationTimer);
      clearTimeout(cartRefreshTimer);
    };
  }, []);

  return (
    <Provider store={activeStore}>
      {isServerRender ? (
        children
      ) : (
        <PersistGate loading={children} persistor={persistor}>
          {children}
        </PersistGate>
      )}
    </Provider>
  );
};

export default ReduxProvider;
