import { afterEach, describe, expect, it, vi } from "vitest";

const MEASUREMENT_ID = "G-XGJSKGJLKJ";

async function loadGoogleAnalytics() {
  vi.resetModules();
  vi.stubEnv("NODE_ENV", "production");
  const { setTrackingSettings } = await import("@/lib/trackingSettings");
  setTrackingSettings({ googleAnalyticsId: MEASUREMENT_ID });
  localStorage.setItem("cookie_consent_choice", "accepted");
  return import("@/lib/googleAnalytics");
}

afterEach(() => {
  vi.unstubAllEnvs();
  delete window.dataLayer;
  delete window.gtag;
  delete window.__hyperlocalGaConsentInitialized;
  delete window.__hyperlocalGaInitialized;
  delete window.__hyperlocalGaMeasurementId;
  localStorage.clear();
});

describe("dedicated Google Analytics stream", () => {
  it("initializes once and sends a custom event only to the new stream", async () => {
    const { sendGoogleAnalyticsEvent } = await loadGoogleAnalytics();

    sendGoogleAnalyticsEvent("login", { method: "google" });
    sendGoogleAnalyticsEvent("search", { search_term: "tea" });

    expect(window.dataLayer).toEqual([
      expect.anything(),
      expect.anything(),
      expect.anything(),
      expect.anything(),
      expect.anything(),
      expect.anything(),
    ]);
    expect(Array.from(window.dataLayer?.[0] as IArguments)).toEqual([
      "consent",
      "default",
      expect.objectContaining({ analytics_storage: "denied" }),
    ]);
    expect(Array.from(window.dataLayer?.[1] as IArguments)).toEqual([
      "consent",
      "update",
      expect.objectContaining({ analytics_storage: "granted" }),
    ]);
    expect(Array.from(window.dataLayer?.[3] as IArguments)).toEqual([
      "config",
      MEASUREMENT_ID,
    ]);
    expect(Array.from(window.dataLayer?.[4] as IArguments)).toEqual([
      "event",
      "login",
      { method: "google", send_to: MEASUREMENT_ID },
    ]);
    expect(Array.from(window.dataLayer?.[5] as IArguments)).toEqual([
      "event",
      "search",
      { search_term: "tea", send_to: MEASUREMENT_ID },
    ]);
  });

  it("updates user data without generating another page view", async () => {
    const {
      setGoogleAnalyticsUserId,
      setGoogleAnalyticsUserProperties,
    } = await loadGoogleAnalytics();

    setGoogleAnalyticsUserId("42");
    setGoogleAnalyticsUserProperties({
      login_method: "phone_otp",
      user_type: "customer",
    });

    expect(Array.from(window.dataLayer?.at(-2) as IArguments)).toEqual([
      "config",
      MEASUREMENT_ID,
      { user_id: "42", send_page_view: false },
    ]);
    expect(Array.from(window.dataLayer?.at(-1) as IArguments)).toEqual([
      "config",
      MEASUREMENT_ID,
      {
        user_properties: {
          login_method: "phone_otp",
          user_type: "customer",
        },
        send_page_view: false,
      },
    ]);
  });

  it("does not collect before analytics consent", async () => {
    vi.resetModules();
    vi.stubEnv("NODE_ENV", "production");
    const { setTrackingSettings } = await import("@/lib/trackingSettings");
    setTrackingSettings({ googleAnalyticsId: MEASUREMENT_ID });
    const { sendGoogleAnalyticsEvent } = await import("@/lib/googleAnalytics");

    sendGoogleAnalyticsEvent("login", { method: "email" });

    expect(window.dataLayer).toBeUndefined();
  });

  it("waits for settings and routes events to the backend stream", async () => {
    const { sendGoogleAnalyticsEvent } = await loadGoogleAnalytics();
    const { setTrackingSettings } = await import("@/lib/trackingSettings");
    setTrackingSettings(null);
    sendGoogleAnalyticsEvent("search");
    expect(window.dataLayer).toBeUndefined();

    setTrackingSettings({ googleAnalyticsId: MEASUREMENT_ID });
    sendGoogleAnalyticsEvent("search");
    setTrackingSettings({ googleAnalyticsId: "G-SECOND123" });
    sendGoogleAnalyticsEvent("login");
    expect(Array.from(window.dataLayer?.at(-1) as IArguments)).toEqual([
      "event", "login", { send_to: "G-SECOND123" },
    ]);

    const commands = window.dataLayer?.map((args) => Array.from(args as IArguments));
    expect(commands).toContainEqual(["config", MEASUREMENT_ID]);
    expect(commands).toContainEqual(["config", "G-SECOND123"]);
  });
});
