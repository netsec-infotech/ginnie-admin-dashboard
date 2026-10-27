import { useSyncExternalStore } from "react";
import type { WebSettings } from "@/types/ApiResponse";

const emptySettings = { googleAnalyticsId: "", microsoftClarityId: "" };
let trackingSettings = emptySettings;
const listeners = new Set<() => void>();

const subscribe = (listener: () => void) => {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
};

export const getTrackingSettings = () => trackingSettings;

/** Called only in the browser, when backend web settings become available. */
export function setTrackingSettings(
  settings: Pick<
    WebSettings,
    "googleAnalyticsId" | "microsoftClarityId"
  > | null,
): void {
  const googleId =
    typeof settings?.googleAnalyticsId === "string"
      ? settings.googleAnalyticsId.trim()
      : "";
  const clarityId =
    typeof settings?.microsoftClarityId === "string"
      ? settings.microsoftClarityId.trim()
      : "";
  const next = {
    googleAnalyticsId: /^G-[A-Z0-9]+$/.test(googleId) ? googleId : "",
    microsoftClarityId: /^[a-z0-9]+$/i.test(clarityId) ? clarityId : "",
  };
  if (
    next.googleAnalyticsId === trackingSettings.googleAnalyticsId &&
    next.microsoftClarityId === trackingSettings.microsoftClarityId
  )
    return;
  trackingSettings = next;
  listeners.forEach((listener) => listener());
}

export function useTrackingSettings() {
  return useSyncExternalStore(
    subscribe,
    getTrackingSettings,
    () => emptySettings,
  );
}
