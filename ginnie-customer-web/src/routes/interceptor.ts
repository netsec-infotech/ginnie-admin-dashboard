import { getCookie } from "@/lib/cookies";
import { AxiosInstance, AxiosResponse, AxiosError } from "axios";
import { maintenanceStore } from "@/stores/maintenanceStore";

// Helper function to clean token (remove quotes if present)
const cleanToken = (token: string): string => {
  if (!token) return "";

  // Remove surrounding quotes if they exist
  return token.replace(/^["']|["']$/g, "").trim();
};

export const setupInterceptors = (instance: AxiosInstance): void => {
  // Request Interceptor - Combined logic
  instance.interceptors.request.use(
    (config) => {
      // First, check if we have a token in params (for SSR)
      const paramToken = config?.params?.access_token || "";

      if (paramToken) {
        const cleanedToken = cleanToken(paramToken);
        if (config.headers) {
          config.headers.set("Authorization", `Bearer ${cleanedToken}`);
        }
        // Remove the token from params to avoid sending it in the URL
        delete config.params.access_token;
      } else if (typeof window !== "undefined") {
        // If no param token and not SSR, try to get from cookies (client-side)
        const access_token = getCookie("access_token") || "";
        if (access_token) {
          if (config.headers) {
            config.headers.set("Authorization", `Bearer ${access_token}`);
          }
        }
      }

      if (config?.params?.scope_category_slug === "all") {
        delete config.params.scope_category_slug;
      }

      // Inject Accept-Language header dynamically
      const cookieLang =
        typeof window !== "undefined"
          ? (getCookie<string>("i18nextLng") as string | null)
          : null;
      const currentLanguage =
        typeof cookieLang === "string" && cookieLang ? cookieLang : "en";

      if (config.headers && !config.headers.get("Accept-Language")) {
        config.headers.set("Accept-Language", currentLanguage);
      }

      return config;
    },
    (error: AxiosError) => {
      return Promise.reject(error);
    }
  );

  // Response Interceptor
  instance.interceptors.response.use(
    (response: AxiosResponse) => {
      // Clear maintenance state on successful response (non-503)
      if (typeof window !== "undefined") {
        maintenanceStore.setMaintenance(false, null);
      }
      return response;
    },
    (error: AxiosError) => {
      if (error.response) {
        switch (error.response.status) {
          case 401:
            console.error("Unauthorized request");
            if (typeof window !== "undefined") {
              const { handleLogout } = require("@/helpers/auth");
              handleLogout(false);
            }
            break;
          case 403:
            console.error("Forbidden access");
            break;
          case 500:
            console.error("Server error");
            break;
          case 503:
            // Check for maintenance mode in 503 response
            if (typeof window !== "undefined") {
              try {
                const responseData = error.response.data;
                // Check if response has maintenance: true
                if (
                  responseData &&
                  typeof responseData === "object" &&
                  (responseData as any).maintenance === true
                ) {
                  const message =
                    (responseData as any).message || null;
                  maintenanceStore.setMaintenance(true, message);
                } else {
                  // If 503 but no maintenance flag, clear maintenance state
                  maintenanceStore.setMaintenance(false, null);
                }
              } catch (e) {
                console.error("Error parsing 503 response:", e);
                maintenanceStore.setMaintenance(false, null);
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
            console.warn(`Validation error: ${error.response.status}`);
            if (typeof window !== "undefined") {
              maintenanceStore.setMaintenance(false, null);
            }
            break;
          default:
            console.error(`Error: ${error.response.status}`);
            // Clear maintenance state for other errors
            if (typeof window !== "undefined") {
              maintenanceStore.setMaintenance(false, null);
            }
        }
      } else if (error.request) {
        console.error("No response received from server");
      } else {
        console.error("Request setup error:", error.message);
      }
      return Promise.reject(error);
    }
  );
};
