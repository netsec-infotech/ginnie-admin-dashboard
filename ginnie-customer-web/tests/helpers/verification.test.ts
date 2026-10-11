import { describe, expect, it } from "vitest";
import {
  getVerificationMessageKey,
  getVerificationStatus,
} from "@/helpers/verification";
import { userData } from "@/types/ApiResponse";

const user = (overrides: Partial<userData> = {}): userData =>
  ({
    id: 1,
    name: "Test",
    email: "test@example.com",
    mobile: "9876543210",
    ...overrides,
  }) as userData;

const VERIFIED_AT = "2026-07-01T10:00:00.000Z";

describe("getVerificationStatus", () => {
  it("reports complete when both timestamps are present", () => {
    const status = getVerificationStatus(
      user({ email_verified_at: VERIFIED_AT, mobile_verified_at: VERIFIED_AT }),
    );
    expect(status).toMatchObject({
      emailVerified: true,
      mobileVerified: true,
      isPending: false,
      isComplete: true,
    });
  });

  it("reports pending when only email is verified", () => {
    const status = getVerificationStatus(
      user({ email_verified_at: VERIFIED_AT, mobile_verified_at: null }),
    );
    expect(status.isPending).toBe(true);
    expect(status.isComplete).toBe(false);
    expect(status.mobileVerified).toBe(false);
  });

  it("reports pending when only mobile is verified", () => {
    const status = getVerificationStatus(
      user({ email_verified_at: null, mobile_verified_at: VERIFIED_AT }),
    );
    expect(status.isPending).toBe(true);
    expect(status.emailVerified).toBe(false);
  });

  it("reports pending when neither is verified", () => {
    expect(getVerificationStatus(user()).isPending).toBe(true);
  });

  /**
   * A badge that appears while the session is still resolving would flash on
   * every page load — and there's nobody to act on it anyway.
   */
  it.each([
    ["null", null],
    ["undefined", undefined],
  ])("reports NOT pending for a %s user", (_label, value) => {
    const status = getVerificationStatus(value as userData | null);
    expect(status.isPending).toBe(false);
    expect(status.isComplete).toBe(false);
  });

  it.each([
    ["an empty string", ""],
    ["null", null],
    ["undefined", undefined],
  ])("treats %s as unverified", (_label, value) => {
    const status = getVerificationStatus(
      user({
        email_verified_at: value as string | null,
        mobile_verified_at: VERIFIED_AT,
      }),
    );
    expect(status.emailVerified).toBe(false);
    expect(status.isPending).toBe(true);
  });

  it("isPending and isComplete are always opposites for a real user", () => {
    const cases = [
      {},
      { email_verified_at: VERIFIED_AT },
      { mobile_verified_at: VERIFIED_AT },
      { email_verified_at: VERIFIED_AT, mobile_verified_at: VERIFIED_AT },
    ];
    for (const overrides of cases) {
      const status = getVerificationStatus(user(overrides));
      expect(status.isPending).toBe(!status.isComplete);
    }
  });
});

describe("getVerificationMessageKey", () => {
  it("names only what's actually outstanding", () => {
    const both = getVerificationStatus(user());
    const emailOnly = getVerificationStatus(
      user({ mobile_verified_at: VERIFIED_AT }),
    );
    const mobileOnly = getVerificationStatus(
      user({ email_verified_at: VERIFIED_AT }),
    );

    expect(getVerificationMessageKey(both)).toBe("both");
    expect(getVerificationMessageKey(emailOnly)).toBe("email");
    expect(getVerificationMessageKey(mobileOnly)).toBe("mobile");
  });

  it("returns null when there is nothing to verify", () => {
    const complete = getVerificationStatus(
      user({ email_verified_at: VERIFIED_AT, mobile_verified_at: VERIFIED_AT }),
    );
    expect(getVerificationMessageKey(complete)).toBeNull();
  });

  it("returns null for a logged-out user", () => {
    // Drives whether the badge/notice renders at all — must be null, not "both".
    expect(getVerificationMessageKey(getVerificationStatus(null))).toBeNull();
  });
});
