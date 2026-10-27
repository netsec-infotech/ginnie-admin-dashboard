import {
  announceAuthSessionChange,
  deleteCookie,
  getCookie,
  setCookie,
} from "@/lib/cookies";
import {
  clearRecaptchaVerifier,
  FirebaseInstance,
  getFirebaseErrorMessage,
  initializeRecaptchaVerifier,
} from "@/lib/firebase";
import {
  googleLogin,
  appleLogin,
  login,
  registerUser,
  verifyUser,
  logout as logoutApi,
} from "@/routes/api";
import { ApiResponse, userData } from "@/types/ApiResponse";
import { addToast } from "@heroui/react";
import { FirebaseError } from "firebase/app";
import {
  ConfirmationResult,
  RecaptchaVerifier,
  signInWithPhoneNumber,
  signInWithPopup,
  User,
} from "firebase/auth";
import { GetServerSidePropsContext } from "next";
import { logout, login as ReduxLogin } from "@/lib/redux/slices/authSlice";
import { AppDispatch } from "@/lib/redux/store";
import { parse } from "cookie";
import { store } from "@/lib/redux/store";
import Router from "next/router";
import {
  updateCartData,
  updateDataOnAuth,
  syncOfflineCartToServer,
} from "./updators";
import { clearCart } from "@/lib/redux/slices/cartSlice";
import { clearRecentlyViewed } from "@/lib/redux/slices/recentlyViewedSlice";
import i18n from "../../i18n";
import { mutate } from "swr";
import {
  setAnalyticsUserId,
  setAnalyticsUserProperties,
  trackLogin,
  trackSignUp,
} from "@/lib/analytics";
import { phoneLogin } from "@/routes/api";
import { getLoginSuccessToast } from "@/helpers/loginToast";

declare global {
  interface Window {
    recaptchaVerifier: RecaptchaVerifier;
    firebaseInstance?: FirebaseInstance;
    confirmationResult?: ConfirmationResult;
    prefillRegisterEmail?: string;
    prefillUserName?: string;
    prefillRegisterFromGoogle?: boolean;
  }
}

// Define proper types for field errors
interface FieldErrors {
  email?: string;
  phone?: string;
  password?: string;
  confirmPassword?: string;
  name?: string;
}

type GoogleLoginOptions = {
  setIsLoading?: (loading: boolean) => void;
  onOpenChange?: () => void;
  context?: "login" | "register";
};

export const handleGoogleLogin = async ({
  setIsLoading = () => {},
  onOpenChange = () => {},
  context = "login",
}: GoogleLoginOptions): Promise<void> => {
  try {
    setIsLoading(true);
    const firebaseInstance = window.firebaseInstance;

    if (!firebaseInstance) {
      addToast({ title: "Firebase not initialized", color: "danger" });
      return console.error("Firebase not initialized");
    }

    const result = await signInWithPopup(
      firebaseInstance.auth,
      firebaseInstance.googleProvider,
    );
    const user: User = result.user;

    const idToken = await user.getIdToken();
    const friends_code = (getCookie("friend_code") as string) || undefined;

    const res: ApiResponse<userData> = await googleLogin({
      idToken: idToken || "",
      device_type: "web",
      friends_code,
    });

    if (res.success && res.data) {
      setCookie("user", res?.data);
      setCookie("access_token", res?.access_token || "");

      store.dispatch(
        ReduxLogin({
          user: res.data,
          access_token: res?.access_token || "",
        }),
      );
      announceAuthSessionChange();

      // Clear recently viewed products for new users
      if (res.data.new_user) {
        store.dispatch(clearRecentlyViewed());
      }

      // Sync offline cart items to server
      await syncOfflineCartToServer();

      updateDataOnAuth();
      updateCartData(false, false, 0);

      // Track analytics
      setAnalyticsUserId(res.data.id.toString());
      setAnalyticsUserProperties({
        login_method: "google",
        user_type: "customer",
      });
      trackLogin("google");

      addToast(getLoginSuccessToast(res.data.name));

      // Check if user needs to complete profile (missing mobile)
      if (res.data.new_user || !res.data.mobile) {
        if (context === "login") {
          onOpenChange();
          setTimeout(() => {
            window.dispatchEvent(new CustomEvent("open-complete-profile"));
          }, 500);
        } else {
          window.dispatchEvent(new CustomEvent("open-complete-profile"));
        }
      } else {
        onOpenChange();
      }
    } else {
      addToast({
        title: i18n.t("error"),
        description: res.message || i18n.t("something_went_wrong"),
        color: "danger",
      });
    }
  } catch (error) {
    console.error("Google login error:", error);
    let errorMessage: string = "Failed to login with Google. Please try again.";

    if (error instanceof Error) {
      if (error.message.includes("popup-closed")) {
        errorMessage = "Login was cancelled.";
      } else if (error.message.includes("popup-blocked")) {
        errorMessage = "Popup was blocked. Please allow popups and try again.";
      } else {
        errorMessage = error.message;
      }
    }

    addToast({
      title: "Error",
      description: errorMessage,
      color: "danger",
    });
  } finally {
    setIsLoading(false);
  }
};

export const handleAppleLogin = async ({
  setIsLoading = () => {},
  onOpenChange = () => {},
  context = "login",
}: GoogleLoginOptions): Promise<void> => {
  try {
    setIsLoading(true);
    const firebaseInstance = window.firebaseInstance;

    if (!firebaseInstance) {
      addToast({ title: "Firebase not initialized", color: "danger" });
      return console.error("Firebase not initialized");
    }

    const result = await signInWithPopup(
      firebaseInstance.auth,
      firebaseInstance.appleProvider,
    );
    const user: User = result.user;

    const idToken = await user.getIdToken();
    const friends_code = (getCookie("friend_code") as string) || undefined;

    const res: ApiResponse<userData> = await appleLogin({
      idToken: idToken || "",
      device_type: "web",
      friends_code,
    });

    if (res.success && res.data) {
      setCookie("user", res?.data);
      setCookie("access_token", res?.access_token || "");

      store.dispatch(
        ReduxLogin({
          user: res.data,
          access_token: res?.access_token || "",
        }),
      );
      announceAuthSessionChange();

      // Clear recently viewed products for new users
      if (res.data.new_user) {
        store.dispatch(clearRecentlyViewed());
      }

      // Sync offline cart items to server
      await syncOfflineCartToServer();

      updateDataOnAuth();
      updateCartData(false, false, 0);

      // Track analytics
      setAnalyticsUserId(res.data.id.toString());
      setAnalyticsUserProperties({
        login_method: "apple",
        user_type: "customer",
      });
      trackLogin("apple");

      addToast(getLoginSuccessToast(res.data.name));

      // Check if user needs to complete profile (missing mobile)
      if (res.data.new_user || !res.data.mobile) {
        if (context === "login") {
          onOpenChange();
          setTimeout(() => {
            window.dispatchEvent(new CustomEvent("open-complete-profile"));
          }, 500);
        } else {
          window.dispatchEvent(new CustomEvent("open-complete-profile"));
        }
      } else {
        onOpenChange();
      }
    } else {
      addToast({
        title: i18n.t("error"),
        description: res.message || i18n.t("something_went_wrong"),
        color: "danger",
      });
    }
  } catch (error) {
    console.error("Apple login error:", error);

    addToast({
      title: "Error",
      description: getFirebaseErrorMessage(error as FirebaseError),
      color: "danger",
    });
  } finally {
    setIsLoading(false);
  }
};

export const handleSignUp = async (
  phoneNumber: string,
  firebaseInstance: FirebaseInstance,
): Promise<boolean> => {
  try {
    // Clear any existing reCAPTCHA first to prevent conflicts
    clearRecaptchaVerifier(firebaseInstance);

    // Initialize the reCAPTCHA verifier
    const recaptchaVerifier = initializeRecaptchaVerifier(firebaseInstance);
    if (!recaptchaVerifier) {
      addToast({
        title: i18n.t("signup_toast.recaptcha_error_title"),
        description: i18n.t("signup_toast.recaptcha_error_desc"),
        color: "danger",
      });
      return false;
    }

    // Validate phone number format
    const phoneRegex = /^\+[1-9]\d{1,14}$/;
    if (!phoneRegex.test(phoneNumber)) {
      addToast({
        title: i18n.t("signup_toast.invalid_phone_title"),
        description: i18n.t("signup_toast.invalid_phone_desc"),
        color: "danger",
      });
      return false;
    }

    // Send OTP
    const confirmationResult = await signInWithPhoneNumber(
      firebaseInstance.auth,
      phoneNumber,
      recaptchaVerifier,
    );

    // Store confirmation result for later verification
    window.confirmationResult = confirmationResult;

    addToast({
      title: i18n.t("signup_toast.otp_sent_title"),
      description: i18n.t("signup_toast.otp_sent_desc"),
      color: "success",
    });
    return true;
  } catch (error) {
    const errorMsg = getFirebaseErrorMessage(error as FirebaseError);
    console.error("Sign-up error:", errorMsg);
    addToast({
      title: i18n.t("signup_toast.signup_error_title"),
      description: errorMsg,
      color: "danger",
    });
    return false;
  }
};

// Updated handleResendOtp function
export const handleResendOtp = async (
  phoneNumber: string,
  firebaseInstance: FirebaseInstance,
): Promise<boolean> => {
  try {
    // Clear previous confirmation result if exists
    if (window.confirmationResult) {
      window.confirmationResult = undefined;
    }

    // Always clear and reinitialize reCAPTCHA for resend
    clearRecaptchaVerifier(firebaseInstance);
    const recaptchaVerifier = initializeRecaptchaVerifier(firebaseInstance);

    if (!recaptchaVerifier) {
      addToast({
        title: i18n.t("resend_otp_toast.recaptcha_error_title"),
        description: i18n.t("resend_otp_toast.recaptcha_error_desc"),
        color: "danger",
      });
      return false;
    }

    // Validate phone number format
    const phoneRegex = /^\+[1-9]\d{1,14}$/;
    if (!phoneRegex.test(phoneNumber)) {
      addToast({
        title: i18n.t("resend_otp_toast.invalid_phone_title"),
        description: i18n.t("resend_otp_toast.invalid_phone_desc"),
        color: "danger",
      });
      return false;
    }

    // Resend OTP
    const confirmationResult = await signInWithPhoneNumber(
      firebaseInstance.auth,
      phoneNumber,
      recaptchaVerifier,
    );

    // Store new confirmation result
    window.confirmationResult = confirmationResult;

    addToast({
      title: i18n.t("resend_otp_toast.otp_resent_title"),
      description: i18n.t("resend_otp_toast.otp_resent_desc"),
      color: "success",
    });

    return true;
  } catch (error) {
    const errorMsg = getFirebaseErrorMessage(error as FirebaseError);
    console.error("Resend OTP error:", errorMsg);
    addToast({
      title: i18n.t("resend_otp_toast.resend_otp_error_title"),
      description: errorMsg,
      color: "danger",
    });
    return false;
  }
};

// Check Email Already there or not
export const checkEmailExists = async (
  email: string,
  setIsCheckingEmail: (value: boolean) => void,
  setFieldErrors: (callback: (prev: FieldErrors) => FieldErrors) => void,
) => {
  if (!email || !email.includes("@")) return;

  setIsCheckingEmail(true);
  setFieldErrors((prev) => ({ ...prev, email: "" }));

  try {
    const response = await verifyUser({
      type: "email",
      value: email,
    });

    if (response.success || response.data?.exists) {
      setFieldErrors((prev) => ({
        ...prev,
        email: i18n.t("email_check.email_exists"),
      }));
    }

    return response.data?.exists;
  } catch (error) {
    console.error("Error checking email:", error);
    if (error && typeof error === "object" && "response" in error) {
      const errorResponse = (
        error as { response: { data?: { message?: string } } }
      ).response;
      if (errorResponse?.data?.message !== "User not found") {
        setFieldErrors((prev) => ({
          ...prev,
          email: i18n.t("email_check.check_error"),
        }));
      }
    }
  } finally {
    setIsCheckingEmail(false);
  }
};

// Check Number Already there or not
export const checkPhoneExists = async (
  phone: string,
  setIsCheckingPhone: (value: boolean) => void,
  setFieldErrors: (callback: (prev: FieldErrors) => FieldErrors) => void,
) => {
  if (!phone) return;

  setIsCheckingPhone(true);
  setFieldErrors((prev) => ({ ...prev, phone: "" }));

  try {
    const response = await verifyUser({
      type: "mobile",
      value: phone,
    });

    if (response.success || response.data?.exists) {
      setFieldErrors((prev) => ({
        ...prev,
        phone: i18n.t("phone_check.phone_exists"),
      }));
    }
    return response.data?.exists;
  } catch (error) {
    console.error("Error checking phone:", error);
    if (error && typeof error === "object" && "response" in error) {
      const errorResponse = (
        error as { response: { data?: { message?: string } } }
      ).response;
      if (errorResponse?.data?.message !== "User not found") {
        setFieldErrors((prev) => ({
          ...prev,
          phone: i18n.t("phone_check.check_error"),
        }));
      }
    }
  } finally {
    setIsCheckingPhone(false);
  }
};

// Final user Register and Login
export const handleRegisterUser = async (
  {
    name,
    email,
    mobile,
    iso_2,
    country,
    password,
    password_confirmation,
    friends_code,
  }: {
    name: string;
    email: string;
    mobile: number | string;
    iso_2: string;
    country: string;
    password: string;
    password_confirmation: string;
    friends_code?: string | number;
  },
  dispatch: AppDispatch,
  idToken?: string,
) => {
  try {
    const response = await registerUser({
      email,
      mobile,
      name,
      iso_2,
      country,
      password,
      password_confirmation,
      friends_code,
    });

    if (response.success) {
      // Track sign up analytics
      trackSignUp("email");

      // Clear recently viewed products — new user shouldn't see guest session history
      store.dispatch(clearRecentlyViewed());

      addToast({
        title: i18n.t("register_toast.success_title"),
        description: i18n.t("register_toast.success_desc"),
        color: "success",
      });

      // Attempt login immediately after registration
      if (idToken) {
        await handlePhoneLogin({
          idToken,
          dispatch,
          name,
          friends_code: friends_code?.toString(),
          renderToast: false,
        });
      } else {
        await handleLoginUser(
          {
            email,
            password,
            renderToast: false,
            mobile: mobile?.toString() || "",
          },
          dispatch,
        );
      }
    } else {
      addToast({
        title: i18n.t("register_toast.failed_title"),
        description: response.message,
        color: "danger",
      });
    }

    return response;
  } catch (error) {
    console.error("Registration Error:", error);
    addToast({
      title: i18n.t("register_toast.error_title"),
      description: i18n.t("register_toast.error_desc"),
      color: "danger",
    });

    return { success: false, data: null, message: "An Error Occur !" };
  }
};

// Login

export const handleLoginUser = async (
  {
    email,
    password,
    mobile,
    renderToast = true,
  }: {
    email: string | undefined;
    password: string;
    mobile: string | undefined;
    renderToast?: boolean;
  },
  dispatch: AppDispatch,
) => {
  try {
    const fcm_token = localStorage.getItem("fcm-token") || "";
    const response: ApiResponse<userData> = await login({
      email,
      password,
      mobile,
      fcm_token,
      device_type: "web",
    });

    if (response.success && response.data) {
      setCookie("user", response?.data);
      setCookie("access_token", response?.access_token || "");
      dispatch(
        ReduxLogin({
          user: response.data,
          access_token: response?.access_token || "",
        }),
      );
      announceAuthSessionChange();

      // Sync offline cart items to server
      await syncOfflineCartToServer();

      updateDataOnAuth();
      updateCartData(false, false, 0);

      // Track analytics
      setAnalyticsUserId(response.data.id.toString());
      setAnalyticsUserProperties({
        login_method: email ? "email" : "phone",
        user_type: "customer",
      });
      trackLogin(email ? "email" : "phone");

      if (renderToast) {
        addToast(getLoginSuccessToast(response.data.name));
      }
    } else {
      if (renderToast) {
        addToast({
          title: i18n.t("login_modal.login_failed_toast"),
          color: "danger",
        });
      }
    }

    return response;
  } catch (error) {
    console.error("Login Error:", error);
    if (renderToast) {
      addToast({
        title: "Login Error",
        description: "An unexpected error occurred. Please try again later.",
        color: "danger",
      });
    }
  }
};

// logout

let unauthorizedCleanupPromise: Promise<void> | null = null;

const warnSessionCleanupFailure = (action: string): void => {
  console.warn(`[Auth] Unable to ${action}; continuing local sign-out.`);
};

const runSessionCleanupStep = (action: string, step: () => void): void => {
  try {
    step();
  } catch {
    warnSessionCleanupFailure(action);
  }
};

const isAccountCacheKey = (key: unknown): boolean => {
  if (Array.isArray(key)) return key.some(isAccountCacheKey);
  if (key && typeof key === "object") {
    // The transaction tables use parameter objects as their entire SWR key.
    return "transaction_type" in key ||
      ("key" in key && key.key === "loyalty-points");
  }
  return typeof key === "string" && (
    key === "user-data" ||
    key.startsWith("addresses:") ||
    /^\/api\/(?:user|orders|wishlists|notifications)(?:[/?]|$)/.test(key) ||
    /^\/(?:save-for-later|cart|promo-codes|scheduled-delivery)(?:[/?]|$)/.test(key)
  );
};

const clearLocalSession = async (): Promise<void> => {
  if (typeof window === "undefined") return;

  // Authentication state and cookies are authoritative. Each cleanup step is
  // isolated so blocked storage or an optional integration cannot abort the
  // rest of sign-out and leave a half-authenticated customer session behind.
  runSessionCleanupStep("clear authentication state", () => {
    store.dispatch(logout());
  });
  runSessionCleanupStep("clear the access-token cookie", () => {
    deleteCookie("access_token");
  });
  runSessionCleanupStep("clear the user cookie", () => {
    deleteCookie("user");
  });
  runSessionCleanupStep("synchronize signed-out browser tabs", () => {
    announceAuthSessionChange();
  });
  runSessionCleanupStep("clear shopping-list storage", () => {
    localStorage.removeItem("shoppingListActiveKeywordString");
    localStorage.removeItem("shoppingListKeywords");
  });
  runSessionCleanupStep("clear the analytics identity", () => {
    setAnalyticsUserId("");
  });
  runSessionCleanupStep("clear the cart", () => {
    store.dispatch(clearCart());
  });
  runSessionCleanupStep("clear checkout attachments", () => {
    (window as Window & { __cartAttachments?: object }).__cartAttachments = {};
  });
  runSessionCleanupStep("clear recently viewed items", () => {
    store.dispatch(clearRecentlyViewed());
  });
  try {
    // Account pages share SWR keys between logins. Invalidate their responses
    // and pending results while preserving mounted public feeds/search results.
    await mutate(isAccountCacheKey, undefined, { revalidate: false });
  } catch {
    warnSessionCleanupFailure("clear cached account data");
  }
  try {
    void updateDataOnAuth().catch(() => {
      warnSessionCleanupFailure("refresh signed-out page data");
    });
  } catch {
    warnSessionCleanupFailure("refresh signed-out page data");
  }

  const currentPath = window.location.pathname;
  if (currentPath === "/my-account" || currentPath.startsWith("/my-account/")) {
    try {
      await Router.push("/");
    } catch {
      // Local auth state is already cleared; use a hard navigation as the
      // final recovery path instead of leaving a rejected promise behind.
      try {
        window.location.assign(new URL("/", window.location.origin));
      } catch {
        warnSessionCleanupFailure("navigate away from the account page");
      }
    }
  }
};

/**
 * Expire a rejected session locally without calling the protected logout API.
 * Multiple requests can fail with 401 together, so cleanup is single-flight.
 */
export const handleUnauthorized = (): Promise<void> => {
  if (typeof window === "undefined") return Promise.resolve();
  if (unauthorizedCleanupPromise) return unauthorizedCleanupPromise;

  let hasLocalSession = store.getState().auth.isLoggedIn;
  if (!hasLocalSession) {
    try {
      hasLocalSession = Boolean(getCookie("access_token"));
    } catch {
      // Cookie access can be blocked. Running idempotent cleanup is safer than
      // assuming an unauthorized session is already gone.
      hasLocalSession = true;
    }
  }
  if (!hasLocalSession) return Promise.resolve();

  unauthorizedCleanupPromise = clearLocalSession().finally(() => {
    unauthorizedCleanupPromise = null;
  });

  return unauthorizedCleanupPromise;
};

export const handleLogout = async (
  renderToast: boolean,
  forceLogout: boolean = false,
) => {
  if (typeof window === "undefined") return;
  if (!store.getState().auth.isLoggedIn && !forceLogout) return;

  let accessToken: string | null = null;
  let fcmToken: string | undefined;
  try {
    accessToken = (getCookie("access_token") as string) || null;
  } catch {
    warnSessionCleanupFailure("read the access-token cookie");
  }
  try {
    fcmToken = localStorage.getItem("fcm-token") || undefined;
  } catch {
    warnSessionCleanupFailure("read notification storage");
  }

  // Local cleanup is authoritative. A network/server failure must never leave
  // the customer appearing signed in or prevent navigation away from account UI.
  await clearLocalSession();

  try {
    await logoutApi(accessToken, { fcm_token: fcmToken });
  } catch {
    // Local cleanup already completed; a failed best-effort server notification
    // must not turn a successful customer sign-out into an unhandled error.
    console.warn("[Auth] Server sign-out notification failed.");
  }

  if (renderToast) {
    addToast({
      title: i18n.t("logout_toast.success_title"),
      description: i18n.t("logout_toast.success_desc"),
      color: "success",
    });
  }
};

export const getAccessTokenFromContext = async (
  context: GetServerSidePropsContext,
): Promise<string | null> => {
  try {
    const cookies = parse(context.req.headers.cookie || "");

    const token = cookies.access_token;

    if (token && typeof token === "string" && token.trim().length > 0) {
      // Remove surrounding quotes if they exist
      let cleanToken = token.trim();

      // Check if token starts and ends with quotes, remove them
      if (cleanToken.startsWith('"') && cleanToken.endsWith('"')) {
        cleanToken = cleanToken.slice(1, -1);
      }
      if (cleanToken.startsWith("'") && cleanToken.endsWith("'")) {
        cleanToken = cleanToken.slice(1, -1);
      }
      return cleanToken;
    }

    console.log("No valid token found in SSR context");
    return null;
  } catch (error) {
    console.error("Error getting access token from context:", error);
    return null;
  }
};
export const handlePhoneLogin = async ({
  idToken,
  dispatch,
  name,
  friends_code,
  renderToast = true,
}: {
  idToken: string;
  dispatch: AppDispatch;
  name?: string | null;
  friends_code?: string | null;
  renderToast?: boolean;
}) => {
  try {
    const fcm_token = localStorage.getItem("fcm-token") || undefined;
    const access_token = (getCookie("access_token") as string) || undefined;
    const response = await phoneLogin({
      idToken,
      name,
      friends_code,
      fcm_token,
      device_type: "web",
      access_token,
    });

    if (response && response.success && response.data) {
      setCookie("user", response.data);
      setCookie("access_token", response.access_token || "");

      dispatch(
        ReduxLogin({
          user: response.data,
          access_token: response.access_token || "",
        }),
      );
      announceAuthSessionChange();

      // Clear recently viewed products for new users
      if (response.data.new_user) {
        dispatch(clearRecentlyViewed());
      }

      await syncOfflineCartToServer();

      updateDataOnAuth();
      updateCartData(false, false, 0);

      setAnalyticsUserId(response.data.id.toString());
      setAnalyticsUserProperties({
        login_method: "phone_otp",
        user_type: "customer",
      });
      trackLogin("phone_otp");

      if (renderToast) {
        addToast(getLoginSuccessToast(response.data.name));
      }
      return response;
    } else if (response) {
      if (renderToast) {
        addToast({
          title: i18n.t("login_modal.errors.verification_failed_title"),
          description: response.message || "Login failed. Please try again.",
          color: "danger",
        });
      }
    }
  } catch (error) {
    const errorMsg =
      error instanceof Error ? error.message : "Failed to verify OTP";
    console.error("OTP verification error:", errorMsg);
    if (renderToast) {
      addToast({
        title: i18n.t("login_modal.errors.verification_failed_title"),
        description: errorMsg,
        color: "danger",
      });
    }
  }
};
