import { useEffect, useState, useSyncExternalStore } from "react";
import Footer from "@/components/Footer/Footer";
import FirebaseInitializer from "@/components/Functional/FirebaseInitializer";
import { Navbar } from "@/components/navbar";
import {
  getSpecificSettings,
  getWebSettings,
  getConfiguredDefaultLocation,
  isSSR,
} from "@/helpers/getters";
import { getSettings } from "@/routes/api";
import {
  Settings,
  SystemSettings,
} from "@/types/ApiResponse";
import useSWR from "swr";
import { SEOHead } from "../SEO/SEOHead";
import GoogleMapsHeadScript from "@/components/Location/GoogleMapsScriptLoader";
import { SettingsProvider } from "@/contexts/SettingsContext";
import WebMaintenanceMode from "@/components/custom/WebMaintenanceMode";
import ScrollToTopButton from "@/components/Functional/ScrollToTopButton";
import { onAppLoad } from "@/helpers/events";
import OfflinePage from "@/components/OfflinePage";
import { maintenanceStore } from "@/stores/maintenanceStore";
import { SpeedInsights } from "@vercel/speed-insights/next";
import { HeaderScrollProvider } from "@/contexts/HeaderScrollContext";
import dynamic from "next/dynamic";
const BottomNavigation = dynamic(
  () => import("@/components/Functional/BottomNavigation"),
  { ssr: false },
);
import CookieConsent from "@/components/Functional/CookieConsent";
import { store } from "@/lib/redux/store";
import { getCookie } from "@/lib/cookies";
import { handleLogout } from "@/helpers/auth";
import { sanitizeApiMessage } from "@/helpers/apiError";
const RemovedItemsModal = dynamic(
  () => import("@/components/Modals/RemovedItemsModal"),
  { ssr: false },
);
const CartLocationConfirmationModal = dynamic(
  () => import("@/components/Modals/CartLocationConfirmationModal"),
  { ssr: false },
);
const FailedItemsModal = dynamic(
  () => import("@/components/Modals/FailedItemsModal"),
  { ssr: false },
);
const DeepLinkModal = dynamic(
  () => import("@/components/Modals/DeepLinkModal"),
  { ssr: false },
);
const CompleteProfileModal = dynamic(
  () =>
    import("@/components/Modals/CompleteProfileModal").then(
      (mod) => mod.CompleteProfileModal,
    ),
  { ssr: false },
);
const subscribeToHydration = () => () => {};

export default function DefaultLayout({
  children,
  initialSettings,
}: {
  children?: React.ReactNode;
  initialSettings?: Settings | null;
}) {
  // `NEXT_PUBLIC_SSR` describes the deployment, not whether this particular
  // render is happening on a server. In export mode we still need the page and
  // its <head> to be present in the generated HTML. The server snapshot stays
  // false through hydration, then flips to true without a markup mismatch.
  const isHydrated = useSyncExternalStore(
    subscribeToHydration,
    () => true,
    () => false,
  );
  const [isOnline, setIsOnline] = useState<boolean>(true);
  const maintenanceState = useSyncExternalStore(
    maintenanceStore.subscribe,
    maintenanceStore.getState,
    maintenanceStore.getState,
  );
  useEffect(() => {
    const updateOnlineStatus = () => setIsOnline(navigator.onLine);

    // defer initial setState to next tick
    const id = requestAnimationFrame(updateOnlineStatus);

    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    return () => {
      cancelAnimationFrame(id);
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, []);

  const fetcher = async () => {
    try {
      const res = await getSettings();
      if (res.success && res.data) {
        return res.data;
      } else {
        console.error("Failed to fetch settings:", res.message);
        return null;
      }
    } catch (err) {
      console.error("Error fetching settings:", err);
      return null;
    }
  };

  const { data: settings, isLoading } = useSWR<Settings | null>(
    "/settings",
    fetcher,
    {
      // In SSR mode a failed settings request arrives as null fallback data.
      // Probe once after hydration so a 503 can activate the maintenance UI.
      revalidateOnMount: !isSSR() || initialSettings == null,
      revalidateOnFocus: true,
      focusThrottleInterval: 30000,
      revalidateOnReconnect: true,
      refreshInterval: 5 * 60 * 1000,
      fallbackData: initialSettings ?? null,
    },
  );

  const activeSettings: Settings | null = settings ?? null;
  const configuredDefaultLocation = getConfiguredDefaultLocation(
    activeSettings ? getWebSettings(activeSettings) : null,
  );
  const defaultLatitude = configuredDefaultLocation?.lat;
  const defaultLongitude = configuredDefaultLocation?.lng;

  useEffect(() => {
    if (!isLoading) {
      onAppLoad(
        defaultLatitude !== undefined && defaultLongitude !== undefined
          ? { lat: defaultLatitude, lng: defaultLongitude }
          : null,
      );
    }
  }, [defaultLatitude, defaultLongitude, isLoading]);

  useEffect(() => {
    if (store.getState().auth.isLoggedIn) return;
    const accessToken = getCookie("access_token") as string | undefined;
    if (accessToken) {
      void handleLogout(false, true);
    }
  }, []);

  const systemSettings: SystemSettings | undefined = getSpecificSettings(
    activeSettings,
    "system",
  ) as SystemSettings | undefined;

  // Priority 1: Check 503-based maintenance, Priority 2: Check settings API
  const isMaintenanceMode =
    maintenanceState.isActive || systemSettings?.webMaintenanceMode || false;

  // Priority 1: Use 503 message, Priority 2: Use settings API message
  const maintenanceMessage =
    maintenanceState.message ||
    sanitizeApiMessage(systemSettings?.webMaintenanceMessage) ||
    null;

  return (
    <div className="@container/page flex flex-col min-h-screen w-full items-center">
      {!isOnline ? (
        <OfflinePage />
      ) : isHydrated && isLoading && !isSSR() ? (
        <div className="relative h-screen w-full flex flex-col justify-center items-center overflow-hidden bg-white">
          {/* Smoke */}
          <div className="absolute left-1/2 top-1/2 -translate-x-24 translate-y-4 flex gap-2">
            <span className="smoke" />
            <span className="smoke delay-150" />
            <span className="smoke delay-300" />
          </div>
          {/* Bike GIF */}
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src="/logos/logo-loading.gif"
            alt="Loading"
            className="relative z-10 w-32 h-32"
            loading="eager"
          />
          {/* Road */}
          <div className="absolute bottom-[45%] w-72 h-0.5 overflow-hidden hidden">
            <div className="road" />
          </div>
        </div>
      ) : (
        <>
          <SEOHead settings={activeSettings} />
          {activeSettings && (
            <>
              <FirebaseInitializer settings={activeSettings} />
              <GoogleMapsHeadScript settings={activeSettings} />
            </>
          )}

          <SettingsProvider settings={activeSettings}>
            {isMaintenanceMode ? (
              // Show only maintenance page when maintenance mode is active
              <WebMaintenanceMode customMessage={maintenanceMessage} />
            ) : (
              // Show normal layout with navbar, content, and footer
              <HeaderScrollProvider>
                <Navbar />
                <main className="w-full max-w-384 min-h-[80vh] px-2 md:px-6 grow pb-4">
                  {children}
                </main>
                <Footer />
                <ScrollToTopButton />
                <BottomNavigation />
                <CookieConsent />
                <RemovedItemsModal />
                <CartLocationConfirmationModal />
                <FailedItemsModal />
                <DeepLinkModal />
                <CompleteProfileModal />
              </HeaderScrollProvider>
            )}
          </SettingsProvider>
        </>
      )}

      <SpeedInsights />
    </div>
  );
}
