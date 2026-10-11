import { describe, it, expect } from "vitest";
import {
  validateName,
  validateEmail,
  validatePassword,
  validateConfirmPassword,
  validateMobile,
  formatString,
  isValidUrl,
  looksLikeEmail,
  looksLikeMobile,
} from "@/helpers/validator";

describe("validateName", () => {
  it("requires a value", () => {
    expect(validateName("")).toBe("Name is required");
  });
  it("rejects names shorter than 2 chars (after trim)", () => {
    expect(validateName(" a ")).toBe("Name must be at least 2 characters long");
  });
  it("accepts a valid name (returns empty string)", () => {
    expect(validateName("Jo")).toBe("");
  });
});

describe("validateEmail", () => {
  it("requires a value", () => {
    expect(validateEmail("")).toBe("Email is required");
  });
  it.each(["nope", "a@b", "a@b.", "@b.com", "a b@c.com"])(
    "rejects invalid email %s",
    (email) => {
      expect(validateEmail(email)).toBe("Please enter a valid email address");
    },
  );
  it("accepts a valid email", () => {
    expect(validateEmail("user@example.com")).toBe("");
  });
});

describe("validatePassword", () => {
  it("requires a value", () => {
    expect(validatePassword("")).toBe("Password is required");
  });
  it("rejects passwords under 8 chars", () => {
    expect(validatePassword("1234567")).toBe(
      "Password must be at least 8 characters long",
    );
  });
  it("accepts an 8-char password", () => {
    expect(validatePassword("12345678")).toBe("");
  });
});

describe("validateConfirmPassword", () => {
  it("requires confirmation", () => {
    expect(validateConfirmPassword("abc", "")).toBe(
      "Please confirm your password",
    );
  });
  it("rejects a mismatch", () => {
    expect(validateConfirmPassword("abc", "abd")).toBe(
      "Passwords do not match",
    );
  });
  it("accepts a match", () => {
    expect(validateConfirmPassword("abc", "abc")).toBe("");
  });
});

describe("validateMobile", () => {
  it("requires a value", () => {
    expect(validateMobile("  ")).toBe("Mobile number is required");
  });
  it("rejects the wrong number of digits", () => {
    expect(validateMobile("12345")).toBe(
      "Please enter a valid Indian mobile number",
    );
  });
  it("rejects a non-Indian leading digit", () => {
    expect(validateMobile("1234567890")).toBe(
      "Please enter a valid Indian mobile number",
    );
  });
  it("accepts a valid 10-digit Indian number", () => {
    expect(validateMobile("9876543210")).toBe("");
  });
});

describe("formatString", () => {
  it("returns empty string for non-strings", () => {
    expect(formatString(42)).toBe("");
    expect(formatString(null)).toBe("");
  });
  it("title-cases and strips digits/separators", () => {
    expect(formatString("out_for_delivery")).toBe("Out For Delivery");
    expect(formatString("ready-for-pickup2")).toBe("Ready For Pickup");
  });
});

describe("isValidUrl", () => {
  it("accepts a well-formed URL", () => {
    expect(isValidUrl("https://example.com/x")).toBe(true);
  });
  it("rejects a malformed URL", () => {
    expect(isValidUrl("not a url")).toBe(false);
  });
});

describe("looksLikeEmail / looksLikeMobile", () => {
  it("treats values with letters or @ as email-like", () => {
    expect(looksLikeEmail("hello")).toBe(true);
    expect(looksLikeEmail("a@b")).toBe(true);
    expect(looksLikeEmail("9876543210")).toBe(false);
  });
  it("treats 8+ digit letterless values as mobile-like", () => {
    expect(looksLikeMobile("98765432")).toBe(true);
    expect(looksLikeMobile("123")).toBe(false);
    expect(looksLikeMobile("98a76543")).toBe(false);
  });
});
