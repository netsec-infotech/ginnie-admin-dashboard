import { describe, it, expect, beforeEach, vi } from "vitest";

// Mock the axios singleton the api layer builds. vi.hoisted lets the mock
// factory (hoisted above imports) share the spy we assert against.
const { mockGet } = vi.hoisted(() => ({ mockGet: vi.fn() }));

vi.mock("axios", () => ({
  default: {
    create: () => ({
      get: mockGet,
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
      interceptors: {
        request: { use: vi.fn() },
        response: { use: vi.fn() },
      },
    }),
    isAxiosError: vi.fn(),
  },
}));

import {
  getScheduledDeliveryDates,
  getScheduledDeliverySlots,
} from "@/routes/api";
import { fallbackApiRes } from "@/config/constants";

beforeEach(() => {
  mockGet.mockReset();
});

describe("getScheduledDeliveryDates", () => {
  it("calls the store-scoped dates endpoint by numeric id", () => {
    // The slug 404s on these routes — only the id works.
    mockGet.mockResolvedValue({ data: { success: true, data: {} } });
    getScheduledDeliveryDates(11);
    expect(mockGet).toHaveBeenCalledWith("/scheduled-delivery/stores/11/dates");
  });

  it("returns the store config verbatim", async () => {
    // Trimmed from the live response for store 11.
    const payload = {
      success: true,
      message: "messages.dates_retrieved_successfully",
      data: {
        is_enabled: true,
        buffer_minutes: 60,
        max_advance_booking_days: 7,
        max_days: 7,
        available_days: ["Monday", "Tuesday"],
        slots: [],
      },
    };
    mockGet.mockResolvedValue({ data: payload });

    const res = await getScheduledDeliveryDates(11);
    expect(res.data?.is_enabled).toBe(true);
    expect(res.data?.max_advance_booking_days).toBe(7);
    expect(res.data?.available_days).toEqual(["Monday", "Tuesday"]);
  });

  it("surfaces a disabled store rather than throwing", async () => {
    // A disabled store answers with a `dates: []` key an enabled one omits.
    mockGet.mockResolvedValue({
      data: {
        success: false,
        message: "Scheduled delivery is currently disabled for this store.",
        data: { is_enabled: false, dates: [] },
      },
    });

    const res = await getScheduledDeliveryDates(124);
    expect(res.data?.is_enabled).toBe(false);
  });

  it("returns the fallback instead of throwing on a network error", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    mockGet.mockRejectedValue(new Error("network"));

    await expect(getScheduledDeliveryDates(11)).resolves.toEqual(fallbackApiRes);
    vi.restoreAllMocks();
  });
});

describe("getScheduledDeliverySlots", () => {
  it("sends the date as a query param", () => {
    mockGet.mockResolvedValue({ data: { success: true, data: {} } });
    getScheduledDeliverySlots(11, "2026-07-23");

    expect(mockGet).toHaveBeenCalledWith(
      "/scheduled-delivery/stores/11/slots",
      { params: { date: "2026-07-23" } },
    );
  });

  it("returns slots with their live availability", async () => {
    mockGet.mockResolvedValue({
      data: {
        success: true,
        data: {
          is_enabled: true,
          message: null,
          slots: [
            {
              id: 11,
              label: "10:00 AM - 12:00 PM",
              start_time: "10:00:00",
              end_time: "12:00:00",
              remaining_capacity: 10,
              max_orders_reached: false,
              is_available: true,
            },
          ],
        },
      },
    });

    const res = await getScheduledDeliverySlots(11, "2026-07-23");
    expect(res.data?.slots).toHaveLength(1);
    expect(res.data?.slots[0].id).toBe(11);
    expect(res.data?.slots[0].is_available).toBe(true);
  });

  it("carries the backend's explanation for an out-of-window date", async () => {
    // Real response for a date beyond max_advance_booking_days: success TRUE
    // with an empty slot list, so the message is the only signal.
    mockGet.mockResolvedValue({
      data: {
        success: true,
        message: "Cannot schedule delivery more than the allowed days in advance.",
        data: {
          is_enabled: true,
          message:
            "Cannot schedule delivery more than the allowed days in advance.",
          slots: [],
        },
      },
    });

    const res = await getScheduledDeliverySlots(11, "2026-08-30");
    expect(res.data?.slots).toEqual([]);
    expect(res.data?.message).toMatch(/more than the allowed days/i);
  });

  it("returns the fallback instead of throwing on a network error", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    mockGet.mockRejectedValue(new Error("network"));

    await expect(
      getScheduledDeliverySlots(11, "2026-07-23"),
    ).resolves.toEqual(fallbackApiRes);
    vi.restoreAllMocks();
  });
});
