import { userData } from "@/types/ApiResponse";

/**
 * Account verification status.
 *
 * Checkout requires mobile verification. Email verification is optional.
 * Surface the required mobile status in the profile and cart before checkout.
 *
 * This is a MIRROR of a backend rule, not the rule itself: the server remains
 * the authority and can reject an order for reasons we can't see here. Use this
 * to warn, never to hard-block — a false "unverified" reading would lock a
 * legitimate customer out of checkout entirely.
 */
export interface VerificationStatus {
  emailVerified: boolean;
  mobileVerified: boolean;
  /** Required mobile verification is outstanding. */
  isPending: boolean;
  /** Required mobile verification is complete. */
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
    isPending: !mobileVerified,
    isComplete: mobileVerified,
  };
};

/**
 * Only mobile verification needs a checkout warning.
 */
export const getVerificationMessageKey = (
  status: VerificationStatus,
): "mobile" | null => {
  return status.isPending ? "mobile" : null;
};
