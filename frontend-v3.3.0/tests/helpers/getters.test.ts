import { afterEach, beforeEach, describe, expect, it } from "vitest";
import {
  getDefaultTimezone,
  setActiveSystemTimezone,
  getFormattedDate,
} from "@/helpers/getters";
import { Settings } from "@/types/ApiResponse";

const ENV_KEY = "NEXT_PUBLIC_DEFAULT_TIMEZONE";
const originalEnv = process.env[ENV_KEY];

describe("getDefaultTimezone", () => {
  beforeEach(() => {
    delete process.env[ENV_KEY];
    setActiveSystemTimezone(null);
  });

  afterEach(() => {
    if (originalEnv !== undefined) {
      process.env[ENV_KEY] = originalEnv;
    } else {
      delete process.env[ENV_KEY];
    }
    setActiveSystemTimezone(null);
  });

  it("falls back to Asia/Kolkata when no settings, active timezone, or env var are set", () => {
    expect(getDefaultTimezone()).toBe("Asia/Kolkata");
  });

  it("uses process.env.NEXT_PUBLIC_DEFAULT_TIMEZONE if set", () => {
    process.env[ENV_KEY] = "Europe/London";
    expect(getDefaultTimezone()).toBe("Europe/London");
  });

  it("prioritizes activeSystemTimezone over process.env", () => {
    process.env[ENV_KEY] = "Europe/London";
    setActiveSystemTimezone("Asia/Dubai");
    expect(getDefaultTimezone()).toBe("Asia/Dubai");
  });

  it("reads systemTimezone from settings payload if passed", () => {
    process.env[ENV_KEY] = "Europe/London";
    setActiveSystemTimezone("Asia/Dubai");

    const mockSettings = [
      {
        variable: "system",
        value: {
          systemTimezone: "America/New_York",
        },
      },
    ] as unknown as Settings;

    expect(getDefaultTimezone(mockSettings)).toBe("America/New_York");
  });
});

describe("getFormattedDate with dynamic timezone", () => {
  const utcIsoDate = "2026-07-31T12:00:00.000Z";

  beforeEach(() => {
    delete process.env[ENV_KEY];
    setActiveSystemTimezone(null);
  });

  afterEach(() => {
    if (originalEnv !== undefined) {
      process.env[ENV_KEY] = originalEnv;
    } else {
      delete process.env[ENV_KEY];
    }
    setActiveSystemTimezone(null);
  });

  it("formats date in default Asia/Kolkata (+5:30) timezone", () => {
    const formatted = getFormattedDate(utcIsoDate);
    // 12:00 UTC + 5:30 = 5:30 PM
    expect(formatted).toContain("5:30:00");
    expect(formatted.toLowerCase()).toContain("pm");
  });

  it("formats date in Asia/Dubai (+4:00) when active timezone is set", () => {
    setActiveSystemTimezone("Asia/Dubai");
    const formatted = getFormattedDate(utcIsoDate);
    // 12:00 UTC + 4:00 = 4:00 PM
    expect(formatted).toContain("4:00:00");
    expect(formatted.toLowerCase()).toContain("pm");
  });

  it("formats date in UTC (+0:00) when env fallback is set to UTC", () => {
    process.env[ENV_KEY] = "UTC";
    const formatted = getFormattedDate(utcIsoDate);
    // 12:00 UTC = 12:00 PM
    expect(formatted).toContain("12:00:00");
    expect(formatted.toLowerCase()).toContain("pm");
  });

  it("honors explicit options.timeZone override", () => {
    setActiveSystemTimezone("Asia/Kolkata");
    const formatted = getFormattedDate(utcIsoDate, "en-IN", {
      timeZone: "UTC",
    });
    expect(formatted).toContain("12:00:00");
  });

  it("handles invalid or empty inputs gracefully", () => {
    expect(getFormattedDate(null)).toBe("Invalid Date");
    expect(getFormattedDate(undefined)).toBe("Invalid Date");
    expect(getFormattedDate("")).toBe("Invalid Date");
    expect(getFormattedDate("not-a-valid-date")).toBe("Invalid Date");
  });
});

