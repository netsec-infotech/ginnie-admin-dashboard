import {
  GOOGLE_ANALYTICS_CONSENT_STORAGE_KEY,
  isGoogleAnalyticsEnabled,
} from "@/config/analytics";
import { getTrackingSettings } from "@/lib/trackingSettings";

export type GoogleAnalyticsParams = Record<string, unknown>;
type Gtag = (...args: unknown[]) => void;

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: Gtag;
    __hyperlocalGaConsentInitialized?: boolean;
    __hyperlocalGaInitialized?: boolean;
    __hyperlocalGaMeasurementId?: string;
  }
}

function hasAnalyticsConsent(): boolean {
  if (typeof window === "undefined") return false;

  try {
    return (
      localStorage.getItem(GOOGLE_ANALYTICS_CONSENT_STORAGE_KEY) === "accepted"
    );
  } catch {
    return false;
  }
}

/**
 * Returns the dedicated GA4 command queue for the new web stream. Firebase
 * Analytics remains independent and continues sending to its own measurement
 * ID through the Firebase SDK.
 */
function getGoogleAnalytics(): Gtag | null {
  if (
    !isGoogleAnalyticsEnabled ||
    !getTrackingSettings().googleAnalyticsId ||
    typeof window === "undefined" ||
    !hasAnalyticsConsent()
  ) {
    return null;
  }

  window.dataLayer ??= [];
  window.gtag ??= function gtag() {
    window.dataLayer?.push(arguments);
  };

  if (!window.__hyperlocalGaConsentInitialized) {
    window.gtag("consent", "default", {
      ad_storage: "denied",
      analytics_storage: "denied",
      ad_user_data: "denied",
      ad_personalization: "denied",
    });
    window.gtag("consent", "update", {
      ad_storage: "denied",
      analytics_storage: "granted",
      ad_user_data: "denied",
      ad_personalization: "denied",
    });
    window.__hyperlocalGaConsentInitialized = true;
  }

  const measurementId = getTrackingSettings().googleAnalyticsId;
  if (window.__hyperlocalGaMeasurementId !== measurementId) {
    window.__hyperlocalGaInitialized = true;
    window.__hyperlocalGaMeasurementId = measurementId;
    window.gtag("js", new Date());
    window.gtag("config", getTrackingSettings().googleAnalyticsId);
  }

  return window.gtag;
}

export function sendGoogleAnalyticsEvent(
  eventName: string,
  params?: GoogleAnalyticsParams,
): void {
  getGoogleAnalytics()?.("event", eventName, {
    ...params,
    send_to: getTrackingSettings().googleAnalyticsId,
  });
}

export function setGoogleAnalyticsUserProperties(
  properties: Record<string, string>,
): void {
  getGoogleAnalytics()?.("config", getTrackingSettings().googleAnalyticsId, {
    user_properties: properties,
    send_page_view: false,
  });
}

export function setGoogleAnalyticsUserId(userId: string): void {
  getGoogleAnalytics()?.("config", getTrackingSettings().googleAnalyticsId, {
    user_id: userId || null,
    send_page_view: false,
  });
}
