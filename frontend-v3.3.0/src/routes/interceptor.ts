import { getCookie } from "@/lib/cookies";
import {
  AxiosInstance,
  AxiosResponse,
  AxiosError,
  type InternalAxiosRequestConfig,
} from "axios";
import { maintenanceStore } from "@/stores/maintenanceStore";
import { reportApiFailure, sanitizeApiMessage } from "@/helpers/apiError";

// Helper function to clean token (remove quotes if present)
const cleanToken = (token: string): string => {
  if (!token) return "";

  // Remove surrounding quotes if they exist
  return token.replace(/^["']|["']$/g, "").trim();
};

// Bind every authenticated browser request to the credential that initiated
// it. A response from an older login must never update the newer customer's
// Redux state (or let an old 401 sign the newer customer out).
const requestSessionTokens = new WeakMap<object, string>();

const bindRequestToSession = (
  config: InternalAxiosRequestConfig,
  token: string,
): void => {
  const cleanedToken = cleanToken(token);
  if (cleanedToken) requestSessionTokens.set(config, cleanedToken);
};

const isStaleSessionResponse = (
  config: InternalAxiosRequestConfig | undefined,
): boolean => {
  if (typeof window === "undefined" || !config) return false;
  const initiatingToken = requestSessionTokens.get(config);
  if (!initiatingToken) return false;
  const activeToken = cleanToken(String(getCookie("access_token") || ""));
  return initiatingToken !== activeToken;
};

const staleSessionError = (
  response: AxiosResponse,
): AxiosError =>
  new AxiosError(
    "Discarded response from an inactive account session",
    "ERR_STALE_SESSION",
    response.config,
    response.request,
    response,
  );

const isSettingsRequest = (url: string | undefined): boolean => {
  if (!url) return false;

  try {
    const path = new URL(url, "https://local.invalid").pathname.replace(
      /\/+$/,
      "",
    );
    return path.endsWith("/settings");
  } catch {
    const path = url.split(/[?#]/, 1)[0]?.replace(/\/+$/, "");
    return path?.endsWith("/settings") ?? false;
  }
};

const isLocalizationRequest = (url: string | undefined): boolean =>
  /^\/(?:languages|language-labels)(?:\?|$)/.test(url || "");

const readLocale = (value: unknown): string | null => {
  if (typeof value !== "string") return null;
  const locale = value.trim();
  return /^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(locale) ? locale : null;
};

export const setupInterceptors = (instance: AxiosInstance): void => {
  // Request Interceptor - Combined logic
  instance.interceptors.request.use(
    (config) => {
      // First, check if we have a token in params (for SSR)
      const paramToken = config?.params?.access_token || "";

      if (isLocalizationRequest(config.url)) {
        // These web dictionaries are public. An expired customer token must
        // never turn a language refresh into a sign-out.
        config.headers?.delete("Authorization");
      } else if (paramToken) {
        const cleanedToken = cleanToken(paramToken);
        bindRequestToSession(config, cleanedToken);
        if (config.headers) {
          config.headers.set("Authorization", `Bearer ${cleanedToken}`);
        }
        // Remove the token from params to avoid sending it in the URL
        delete config.params.access_token;
      } else if (typeof window !== "undefined") {
        // If no param token and not SSR, try to get from cookies (client-side)
        const access_token = getCookie("access_token") || "";
        if (access_token) {
          bindRequestToSession(config, String(access_token));
          if (config.headers) {
            config.headers.set("Authorization", `Bearer ${access_token}`);
          }
        }
      }

      if (config?.params?.scope_category_slug === "all") {
        delete config.params.scope_category_slug;
      }

      // Resolve on every request so changing the browser language takes effect
      // immediately. SSR callers can supply a request-specific X-Locale without
      // mutating this shared Axios instance or another visitor's language.
      const cookieLang =
        typeof window !== "undefined"
          ? getCookie<string>("i18nextLng")
          : null;
      const currentLanguage =
        readLocale(config.headers.get("X-Locale")) ||
        readLocale(cookieLang) ||
        readLocale(process.env.NEXT_PUBLIC_DEFAULT_LOCALE) ||
        "en";

      config.headers.set("X-Locale", currentLanguage);
      if (!config.headers.get("Accept-Language")) {
        config.headers.set("Accept-Language", currentLanguage);
      }

      return config;
    },
    (error: AxiosError) => {
      return Promise.reject(error);
    },
  );

  // Response Interceptor
  instance.interceptors.response.use(
    (response: AxiosResponse) => {
      if (isStaleSessionResponse(response.config)) {
        return Promise.reject(staleSessionError(response));
      }

      // Only the settings probe is authoritative for maintenance recovery.
      // An unrelated concurrent success must not erase a 503 signal.
      if (
        typeof window !== "undefined" &&
        isSettingsRequest(response.config.url)
      ) {
        maintenanceStore.setMaintenance(false, null);
      }
      return response;
    },
    (error: AxiosError) => {
      // Ignore an obsolete account's failure before logging or handling it.
      // In particular, its delayed 401 must not sign out the active account.
      if (isStaleSessionResponse(error.config)) {
        return Promise.reject(error);
      }

      reportApiFailure(error);

      if (error.response) {
        switch (error.response.status) {
          case 401:
            if (typeof window !== "undefined" && !isLocalizationRequest(error.config?.url)) {
              const { handleUnauthorized } = require("@/helpers/auth");
              void handleUnauthorized();
            }
            break;
          case 403:
          case 500:
            break;
          case 503:
            // Check for maintenance mode in 503 response
            if (typeof window !== "undefined") {
              try {
                const responseData = error.response.data as {
                  maintenance?: unknown;
                  message?: unknown;
                } | null;
                // Check if response has maintenance: true
                if (
                  responseData &&
                  typeof responseData === "object" &&
                  responseData.maintenance === true
                ) {
                  const message =
                    sanitizeApiMessage(responseData.message) || null;
                  maintenanceStore.setMaintenance(true, message);
                }
              } catch (e) {
                reportApiFailure(e, "maintenance response parsing");
              }
            }
            break;
          case 422:
            /**
             * Validation response, not a system fault — the caller inspects the
             * body and decides. Several endpoints answer this way as normal
             * flow (e.g. /scheduled-delivery/.../dates for a store that doesn't
             * offer scheduling), so logging it at error level filled the
             * console on an ordinary cart view.
             */
            break;
          default:
            break;
        }
      }
      return Promise.reject(error);
    },
  );
};
