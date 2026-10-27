import { beforeEach, describe, expect, it, vi } from "vitest";

import { getNotifications } from "@/routes/api";
import { fetchUnreadNotificationCount } from "@/hooks/useUnreadNotificationCount";

vi.mock("@/routes/api", () => ({ getNotifications: vi.fn() }));

const getNotificationsMock = vi.mocked(getNotifications);

beforeEach(() => getNotificationsMock.mockReset());

describe("notification unread count", () => {
  it("uses the server's total unread count when available", async () => {
    getNotificationsMock.mockResolvedValue({
      success: true,
      message: "ok",
      data: { unread_count: 7, notifications: [] },
    });

    expect(await fetchUnreadNotificationCount()).toBe(7);
    expect(getNotificationsMock).toHaveBeenCalledTimes(1);
  });

  it("caps the menu badge at 9+", async () => {
    getNotificationsMock.mockResolvedValue({
      success: true,
      message: "ok",
      data: { unread_count: 42, notifications: [] },
    });

    expect(await fetchUnreadNotificationCount()).toBe(10);
  });

  it("counts unread notifications across pages when the server omits a total", async () => {
    getNotificationsMock
      .mockResolvedValueOnce({
        success: true,
        message: "ok",
        data: {
          notifications: [{ is_read: true }, { is_read: false }],
          pagination: { last_page: 2 },
        },
      })
      .mockResolvedValueOnce({
        success: true,
        message: "ok",
        data: {
          notifications: [{ is_read: 0 }, { is_read: "0" }],
          pagination: { last_page: 2 },
        },
      });

    expect(await fetchUnreadNotificationCount()).toBe(3);
    expect(getNotificationsMock).toHaveBeenNthCalledWith(2, {
      page: 2,
      per_page: 100,
    });
  });

  it("does not show a misleading zero when the request fails", async () => {
    getNotificationsMock.mockResolvedValue({
      success: false,
      message: "Unavailable",
    });
    await expect(fetchUnreadNotificationCount()).rejects.toThrow("Unavailable");
  });
});
