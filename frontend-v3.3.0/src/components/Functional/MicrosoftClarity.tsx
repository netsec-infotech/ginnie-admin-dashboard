import { useEffect, useState } from "react";
import Script from "next/script";
import {
  GOOGLE_ANALYTICS_CONSENT_EVENT,
  GOOGLE_ANALYTICS_CONSENT_STORAGE_KEY,
} from "@/config/analytics";
import { isMicrosoftClarityEnabled } from "@/config/clarity";
import { useTrackingSettings } from "@/lib/trackingSettings";

declare global {
  interface Window {
    clarity?: (...args: unknown[]) => void;
  }
}

function updateClarityConsent(hasConsent: boolean): void {
  window.clarity?.("consentv2", {
    ad_Storage: "denied",
    analytics_Storage: hasConsent ? "granted" : "denied",
  });
}

/**
 * Loads Microsoft Clarity once after analytics consent. The root placement in
 * _app makes one installation cover every Next.js route and client navigation.
 */
export default function MicrosoftClarity() {
  const { microsoftClarityId: projectId } = useTrackingSettings();
  const [hasConsent, setHasConsent] = useState(false);

  useEffect(() => {
    if (!isMicrosoftClarityEnabled) return;

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
      const accepted = (event as CustomEvent<boolean>).detail === true;
      updateClarityConsent(accepted);
      setHasConsent(accepted);
    };

    window.addEventListener(GOOGLE_ANALYTICS_CONSENT_EVENT, handleConsent);
    return () => {
      window.clearTimeout(consentReadTimer);
      window.removeEventListener(
        GOOGLE_ANALYTICS_CONSENT_EVENT,
        handleConsent,
      );
    };
  }, []);

  if (!isMicrosoftClarityEnabled || !projectId || !hasConsent) return null;

  return (
    <Script id={`microsoft-clarity-${projectId}`} strategy="afterInteractive">
      {`
        (function(c,l,a,r,i,t,y){
          c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments);};
          t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
          y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "${projectId}");
        window.clarity("consentv2", {
          ad_Storage: "denied",
          analytics_Storage: "granted"
        });
      `}
    </Script>
  );
}
