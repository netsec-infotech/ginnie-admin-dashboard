import {
  initializeApp,
  getApps,
  getApp,
  FirebaseApp,
  FirebaseOptions,
  FirebaseError,
} from "firebase/app";
import {
  getAuth,
  Auth,
  GoogleAuthProvider,
  OAuthProvider,
  RecaptchaVerifier,
} from "firebase/auth";
import { getAnalytics, Analytics, isSupported } from "firebase/analytics";
import { addToast } from "@heroui/react";

// Define the Firebase instance type
export interface FirebaseInstance {
  app: FirebaseApp;
  auth: Auth;
  googleProvider: GoogleAuthProvider;
  appleProvider: OAuthProvider;
  recaptchaVerifier?: RecaptchaVerifier | null;
  analytics?: Analytics | null;
}

let firebaseApp: FirebaseApp | null = null;
let cachedFirebaseInstance: FirebaseInstance | null = null;

export function initializeFirebase(
  firebaseConfig: FirebaseOptions,
): FirebaseInstance | null {
  try {
    // Return cached instance if available
    if (cachedFirebaseInstance) {
      return cachedFirebaseInstance;
    }

    // Validate configuration
    if (!firebaseConfig || typeof firebaseConfig !== "object") {
      const errorMsg = "Invalid or missing Firebase configuration";
      console.error(errorMsg);
      return null;
    }

    // Initialize Firebase only if it hasn't been initialized
    if (!getApps().length) {
      try {
        firebaseApp = initializeApp(firebaseConfig);
        console.log("Firebase initialized successfully");
      } catch (initError) {
        const errorMsg = `Failed to initialize Firebase: ${
          initError instanceof Error ? initError.message : "Unknown error"
        }`;
        console.error(errorMsg);
        return null;
      }
    } else {
      try {
        firebaseApp = getApp();
        console.log("Using existing Firebase app");
      } catch (getAppError) {
        const errorMsg = `Failed to get existing Firebase app: ${
          getAppError instanceof Error ? getAppError.message : "Unknown error"
        }`;
        console.error(errorMsg);
        return null;
      }
    }

    // Initialize Firebase Authentication and providers
    let auth: Auth;
    let googleProvider: GoogleAuthProvider;
    let appleProvider: OAuthProvider;

    try {
      auth = getAuth(firebaseApp);
      googleProvider = new GoogleAuthProvider();
      appleProvider = new OAuthProvider("apple.com");
    } catch (authError) {
      const errorMsg = `Failed to initialize Firebase Auth: ${
        authError instanceof Error ? authError.message : "Unknown error"
      }`;
      console.error(errorMsg);
      addToast({
        title: "Firebase Auth Error",
        description: errorMsg,
        color: "danger",
      });
      return null;
    }

    // Create instance without RecaptchaVerifier and Analytics first
    const instance: FirebaseInstance = {
      app: firebaseApp,
      auth,
      googleProvider,
      appleProvider,
      analytics: null,
    };

    // Cache the instance
    cachedFirebaseInstance = instance;

    // Initialize Analytics asynchronously and update the instance
    if (typeof window !== "undefined") {
      isSupported()
        .then((supported) => {
          if (supported && firebaseApp) {
            const analytics = getAnalytics(firebaseApp);
            instance.analytics = analytics;
            console.log("Firebase Analytics initialized successfully");
          }
        })
        .catch((error) => {
          console.warn("Firebase Analytics not supported:", error);
        });
    }

    return instance;
  } catch (error) {
    const errorMsg = `Firebase setup failed: ${
      error instanceof Error ? error.message : "Unknown error occurred"
    }`;
    console.error("Firebase initialization error:", errorMsg);
    addToast({
      title: "Firebase Setup Error",
      description: errorMsg,
      color: "danger",
    });
    return null;
  }
}

//function to properly clear reCAPTCHA

export function clearRecaptchaVerifier(
  firebaseInstance: FirebaseInstance,
): void {
  try {
    if (firebaseInstance.recaptchaVerifier) {
      // Clear the reCAPTCHA widget
      firebaseInstance.recaptchaVerifier.clear();
      firebaseInstance.recaptchaVerifier = null;
    }

    // Only the per-verifier holders are disposable. This used to select
    // `[id^="recaptcha-container"]`, a prefix match that also caught the
    // timestamped holders — so each call tore down the node the *current*
    // verifier was still bound to. The holders now use their own prefix.
    removeRecaptchaHolders();

    console.log("reCAPTCHA verifier cleared and containers removed");
  } catch (error) {
    console.warn("Error clearing reCAPTCHA verifier:", error);
    // Force-remove the holders even if clearing the verifier threw.
    removeRecaptchaHolders();
  }
}

/** Drop every offscreen holder created by `initializeRecaptchaVerifier`. */
function removeRecaptchaHolders(): void {
  document
    .querySelectorAll('[id^="recaptcha-holder-"]')
    .forEach((container) => container.remove());
}

// Updated initializeRecaptchaVerifier function
export function initializeRecaptchaVerifier(
  firebaseInstance: FirebaseInstance,
): RecaptchaVerifier | null {
  try {
    // Always clear any existing RecaptchaVerifier first
    if (firebaseInstance.recaptchaVerifier) {
      clearRecaptchaVerifier(firebaseInstance);
    }

    // Create a new RecaptchaVerifier with a unique ID.
    //
    // The container must stay renderable. An "invisible" reCAPTCHA still has to
    // lay out and execute in the DOM — invisible means "no checkbox", not "no
    // element". Inside a `display: none` node it never runs, resolves without a
    // token, and the SDK then posts `captchaResponse: "NO_RECAPTCHA"`, which the
    // identitytoolkit endpoint rejects with CAPTCHA_CHECK_FAILED - MALFORMED.
    // Park it offscreen instead so it renders but is never seen.
    const uniqueId = "recaptcha-holder-" + Date.now();
    const uniqueContainer = document.createElement("div");
    uniqueContainer.id = uniqueId;
    uniqueContainer.style.position = "absolute";
    uniqueContainer.style.left = "-9999px";
    uniqueContainer.style.top = "0";
    uniqueContainer.style.width = "1px";
    uniqueContainer.style.height = "1px";
    uniqueContainer.style.overflow = "hidden";
    document.body.appendChild(uniqueContainer);

    const recaptchaVerifier = new RecaptchaVerifier(
      firebaseInstance.auth,
      uniqueId,
      {
        size: "invisible",
        callback: () => {
          console.log("reCAPTCHA verified");
        },
        "expired-callback": () => {
          console.log("reCAPTCHA expired");
          addToast({
            title: "reCAPTCHA Expired",
            description: "Please refresh and try again",
            color: "warning",
          });
        },
        "error-callback": () => {
          addToast({
            title: "reCAPTCHA Error",
            description: "Please refresh and try again",
            color: "danger",
          });
        },
      },
    );

    // Cache the new verifier in the Firebase instance
    firebaseInstance.recaptchaVerifier = recaptchaVerifier;

    return recaptchaVerifier;
  } catch (error) {
    const errorMsg = `Failed to initialize RecaptchaVerifier: ${
      error instanceof Error ? error.message : "Unknown error"
    }`;
    console.error(errorMsg);
    addToast({
      title: "reCAPTCHA Setup Error",
      description: errorMsg,
      color: "danger",
    });
    return null;
  }
}

export const getFirebaseErrorMessage = (error: FirebaseError) => {
  const errorCode = error?.code;
  switch (errorCode) {
    case "auth/invalid-phone-number":
      return "Invalid phone number format. Please check and try again.";
    case "auth/invalid-verification-code":
      return "Invalid verification code. Please enter the correct code.";
    case "auth/code-expired":
      return "Verification code has expired. Please request a new one.";
    case "auth/too-many-requests":
      return "Too many attempts. Please try again later.";
    case "auth/quota-exceeded":
      return "SMS quota exceeded. Please try again later.";
    case "auth/missing-verification-code":
      return "Please enter the verification code.";
    case "auth/network-request-failed":
      return "Network error. Please check your connection and try again.";
    case "auth/captcha-check-failed":
      return "reCAPTCHA verification failed. Please try again.";
    case "auth/operation-not-allowed":
      return "This login method is not enabled in Firebase Console. Please enable it in Authentication > Sign-in method.";
    default:
      return error.message || "An error occurred. Please try again.";
  }
};
