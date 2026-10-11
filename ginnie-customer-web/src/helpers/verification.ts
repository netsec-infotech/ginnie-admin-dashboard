import { userData } from "@/types/ApiResponse";

/**
 * Account verification status.
 *
 * The backend rejects checkout with "Please verify your email and mobile …"
 * — but only AFTER createOrder is submitted, which means the customer finds out
 * at the worst possible moment. These helpers let the UI surface the same state
 * up-front, on the profile and in the cart.
 *
 * This is a MIRROR of a backend rule, not the rule itself: the server remains
 * the authority and can reject an order for reasons we can't see here. Use this
 * to warn, never to hard-block — a false "unverified" reading would lock a
 * legitimate customer out of checkout entirely.
 */
export interface VerificationStatus {
  emailVerified: boolean;
  mobileVerified: boolean;
  /** Either one is outstanding. */
  isPending: boolean;
  /** Nothing to verify — both done. */
  isComplete: boolean;
}

/**
 * `*_verified_at` is a timestamp string when verified, and null/undefined when
 * not. Anything falsy — including an empty string — counts as unverified.
 *
 * A missing user (logged out, or not loaded yet) reports NOT pending: a badge
 * that appears while the session is still resolving would flash on every page
 * load, and there's nobody to act on it anyway.
 */
export const getVerificationStatus = (
  user?: userData | null,
): VerificationStatus => {
  const emailVerified = !!user?.email_verified_at;
  const mobileVerified = !!user?.mobile_verified_at;

  if (!user) {
    return {
      emailVerified: false,
      mobileVerified: false,
      isPending: false,
      isComplete: false,
    };
  }

  return {
    emailVerified,
    mobileVerified,
    isPending: !emailVerified || !mobileVerified,
    isComplete: emailVerified && mobileVerified,
  };
};

/**
 * i18n key naming what's outstanding, so the message can say "email" or
 * "mobile" specifically rather than always naming both.
 */
export const getVerificationMessageKey = (
  status: VerificationStatus,
): "both" | "email" | "mobile" | null => {
  if (!status.isPending) return null;
  if (!status.emailVerified && !status.mobileVerified) return "both";
  return status.emailVerified ? "mobile" : "email";
};
