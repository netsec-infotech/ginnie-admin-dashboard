// src/contexts/SettingsContext.tsx
import React, { createContext, useContext, useEffect } from "react";
import {
  AppSettings,
  AuthenticationSettings,
  HomeGeneralSettings,
  LoyaltyPointSettings,
  PaymentSettings,
  Settings,
  SystemSettings,
} from "@/types/ApiResponse";
import {
  getSpecificSettings,
  getWebSettings,
  getConfiguredDefaultLocation,
  isHomeBuilderLayout,
  setActiveSystemTimezone,
  getDefaultTimezone,
} from "@/helpers/getters";
import { staticLat, staticLng } from "@/config/constants";
import { applyPrimaryColor, applyTextColor } from "@/helpers/theme";
import { setTrackingSettings } from "@/lib/trackingSettings";

type LatLng = {
  lat: number;
  lng: number;
};

type SettingsContextType = {
  settings: Settings | null;
  webSettings: ReturnType<typeof getWebSettings> | null;
  paymentSettings: PaymentSettings | null;
  authSettings: AuthenticationSettings | null;
  homeGeneralSettings: HomeGeneralSettings | null;
  systemSettings: SystemSettings | null;
  appSettings: AppSettings | null;
  loyaltySettings: LoyaltyPointSettings | null;

  systemVendorType: "single" | "multiple";
  isSingleVendor: boolean;
  /** Backend selected the section-based /home-layout homepage. */
  isHomeBuilder: boolean;
  currencySymbol: string;
  currency: string;
  defaultLocation: LatLng | null;
  hasDefaultLocation: boolean;
  demoMode: boolean;
  systemTimezone: string;
};

const SettingsContext = createContext<SettingsContextType>({
  settings: null,
  webSettings: null,
  paymentSettings: null,
  authSettings: null,
  homeGeneralSettings: null,
  systemSettings: null,
  appSettings: null,
  loyaltySettings: null,
  systemVendorType: "multiple",
  isSingleVendor: false,
  // Legacy homepage is the no-backend-opt-in default — see isHomeBuilderLayout.
  isHomeBuilder: false,
  currencySymbol: "",
  currency: "",
  defaultLocation: null,
  hasDefaultLocation: false,
  demoMode: false,
  systemTimezone: "Asia/Kolkata",
});

export const useSettings = () => useContext(SettingsContext);

export const SettingsProvider = ({
  settings,
  children,
}: {
  settings: Settings | null;
  children: React.ReactNode;
}) => {
  const webSettings = settings ? getWebSettings(settings) : null;
  useEffect(() => {
    setTrackingSettings(webSettings ?? null);
  }, [webSettings]);
  const systemSettings = settings
    ? (getSpecificSettings(settings, "system") as SystemSettings)
    : null;

  const paymentSettings = settings
    ? (getSpecificSettings(settings, "payment") as PaymentSettings)
    : null;

  const authSettings = settings
    ? (getSpecificSettings(
        settings,
        "authentication",
      ) as AuthenticationSettings)
    : null;

  const homeGeneralSettings = settings
    ? (getSpecificSettings(
        settings,
        "home_general_settings",
      ) as HomeGeneralSettings)
    : null;

  const appSettings = settings
    ? (getSpecificSettings(settings, "app") as AppSettings)
    : null;

  const loyaltySettings = settings
    ? ((getSpecificSettings(settings, "loyalty_point") as LoyaltyPointSettings) ??
      null)
    : null;

  /**
   * Repaint the brand colour from `web.primaryColor`.
   *
   * An effect rather than inline: it mutates document.documentElement, which is
   * outside React's tree and unavailable during SSR. The build-time theme stays
   * the default, so a missing or malformed value simply leaves it in place.
   */
  useEffect(() => {
    applyPrimaryColor(webSettings?.primaryColor);
  }, [webSettings?.primaryColor]);

  useEffect(() => {
    applyTextColor(webSettings?.fontColor);
    return () => { applyTextColor(undefined); };
  }, [webSettings?.fontColor]);

  const systemVendorType = systemSettings?.systemVendorType || "multiple";
  const isSingleVendor = systemVendorType === "single";
  // Same predicate the server uses to pick the homepage, so SSR markup and the
  // client agree and hydration can't mismatch.
  const isHomeBuilder = isHomeBuilderLayout(settings);
  const currencySymbol = systemSettings?.currencySymbol || "";
  const currency = systemSettings?.currency || "";
  const demoMode = systemSettings?.demoMode || false;
  const systemTimezone =
    systemSettings?.systemTimezone || getDefaultTimezone(settings);

  useEffect(() => {
    if (systemSettings?.systemTimezone) {
      setActiveSystemTimezone(systemSettings.systemTimezone);
    }
  }, [systemSettings?.systemTimezone]);

  const configuredDefaultLocation = getConfiguredDefaultLocation(webSettings);
  const hasDefaultLocation = configuredDefaultLocation !== null;

  const defaultLocation = configuredDefaultLocation ?? {
    lat: staticLat,
    lng: staticLng,
  };

  return (
    <SettingsContext.Provider
      value={{
        settings,
        webSettings,
        defaultLocation,
        hasDefaultLocation,
        currencySymbol,
        currency,
        paymentSettings,
        authSettings,
        homeGeneralSettings,
        systemSettings,
        appSettings,
        loyaltySettings,
        systemVendorType,
        isSingleVendor,
        isHomeBuilder,
        demoMode,
        systemTimezone,
      }}
    >
      {children}
    </SettingsContext.Provider>
  );
};
