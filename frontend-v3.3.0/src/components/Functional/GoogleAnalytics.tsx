import { useEffect, useState } from "react";
import Script from "next/script";
import {
  GOOGLE_ANALYTICS_CONSENT_EVENT,
  GOOGLE_ANALYTICS_CONSENT_STORAGE_KEY,
  isGoogleAnalyticsEnabled,
} from "@/config/analytics";
import { useTrackingSettings } from "@/lib/trackingSettings";

/**
 * Loads GA4 once after analytics consent. GA4 Enhanced Measurement observes
 * Next.js history changes and records SPA page views.
 */
export default function GoogleAnalytics() {
  const { googleAnalyticsId: measurementId } = useTrackingSettings();
  const [hasConsent, setHasConsent] = useState(false);

  useEffect(() => {
    if (!isGoogleAnalyticsEnabled) return;

    const readStoredConsent = () => {
      try {
        return (
          localStorage.getItem(GOOGLE_ANALYTICS_CONSENT_STORAGE_KEY) ===
          "accepted"
        );
      } catch {
        return false;
      }
    };

    const consentReadTimer = window.setTimeout(() => {
      setHasConsent(readStoredConsent());
    }, 0);

    const handleConsent = (event: Event) => {
      setHasConsent((event as CustomEvent<boolean>).detail === true);
    };

    window.addEventListener(GOOGLE_ANALYTICS_CONSENT_EVENT, handleConsent);
    return () => {
      window.clearTimeout(consentReadTimer);
      window.removeEventListener(GOOGLE_ANALYTICS_CONSENT_EVENT, handleConsent);
    };
  }, []);

  if (!isGoogleAnalyticsEnabled || !measurementId || !hasConsent) return null;

  return (
    <>
      <Script
        id={`google-analytics-${measurementId}`}
        src={`https://www.googletagmanager.com/gtag/js?id=${measurementId}`}
        strategy="afterInteractive"
      />
      <Script
        id={`google-analytics-config-${measurementId}`}
        strategy="afterInteractive"
      >
        {`
          window.dataLayer = window.dataLayer || [];
          window.gtag = window.gtag || function gtag(){dataLayer.push(arguments);};
          if (!window.__hyperlocalGaConsentInitialized) {
            window.gtag('consent', 'default', {
              ad_storage: 'denied',
              analytics_storage: 'denied',
              ad_user_data: 'denied',
              ad_personalization: 'denied'
            });
            window.gtag('consent', 'update', {
              ad_storage: 'denied',
              analytics_storage: 'granted',
              ad_user_data: 'denied',
              ad_personalization: 'denied'
            });
            window.__hyperlocalGaConsentInitialized = true;
          }
          if (window.__hyperlocalGaMeasurementId !== '${measurementId}') {
            window.__hyperlocalGaInitialized = true;
            window.__hyperlocalGaMeasurementId = '${measurementId}';
            window.gtag('js', new Date());
            window.gtag('config', '${measurementId}');
          }
        `}
      </Script>
    </>
  );
}
