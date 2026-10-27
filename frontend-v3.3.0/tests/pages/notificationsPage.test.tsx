import type { ReactNode } from "react";
import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  push: vi.fn(),
  markRead: vi.fn(),
  revalidate: vi.fn(),
  notifications: [] as Array<Record<string, unknown>>,
}));

vi.mock("next/router", () => ({
  useRouter: () => ({ query: {}, push: mocks.push }),
}));
vi.mock("swr", () => ({
  default: () => ({
    data: { notifications: mocks.notifications },
    isLoading: false,
    mutate: mocks.revalidate,
  }),
}));
vi.mock("@/routes/api", () => ({
  getNotifications: vi.fn(),
  markNotificationRead: mocks.markRead,
  markAllNotificationsRead: vi.fn(),
}));
vi.mock("@/hooks/useUnreadNotificationCount", () => ({
  useUnreadNotificationCount: () => ({
    decrementUnreadCount: vi.fn(),
    setUnreadCount: vi.fn(),
  }),
}));
vi.mock("@/layouts/UserLayout", () => ({
  default: ({ children }: { children: ReactNode }) => <>{children}</>,
}));
vi.mock("@/SEO/PageHead", () => ({ default: () => null }));
vi.mock("@/components/custom/MyBreadcrumbs", () => ({ default: () => null }));
vi.mock("@/components/custom/PageHeader", () => ({ default: () => null }));

import NotificationsPage from "@/pages/my-account/notifications";

const baseNotification = {
  id: "notification-1",
  is_read: true,
  title: "Store is Back Online",
  message: "Your store is accepting orders.",
  created_at: "2026-10-13T00:00:00Z",
  metadata: null,
};

beforeEach(() => {
  vi.clearAllMocks();
  mocks.push.mockResolvedValue(true);
  mocks.markRead.mockResolvedValue({ success: true });
  mocks.notifications = [];
});

describe("notification navigation", () => {
  it.each([
    { metadata: '{"store_slug":"beauty-essentials-7"}' },
    { data: { store_slug: "beauty-essentials-7" } },
    { store_slug: "beauty-essentials-7" },
    { store: { slug: "beauty-essentials-7" } },
  ])("opens the store from read notifications using %j", (fields) => {
    mocks.notifications = [{ ...baseNotification, type: "store_online", ...fields }];
    render(<NotificationsPage />);

    const notification = screen.getByRole("button", { name: /Store is Back Online/ });
    expect(notification).toBeEnabled();
    fireEvent.click(notification);

    expect(mocks.push).toHaveBeenCalledWith("/stores/beauty-essentials-7");
    expect(mocks.markRead).not.toHaveBeenCalled();
  });

  it("opens the cart even after the reminder has been read", () => {
    mocks.notifications = [{
      ...baseNotification,
      title: "You left something in your cart",
      type: "cart_reminder",
    }];
    render(<NotificationsPage />);
    fireEvent.click(screen.getByRole("button", { name: /You left something in your cart/ }));

    expect(mocks.push).toHaveBeenCalledWith("/cart");
    expect(mocks.markRead).not.toHaveBeenCalled();
  });

  it("navigates immediately while an unread notification is being marked read", () => {
    mocks.markRead.mockReturnValue(new Promise(() => {}));
    mocks.notifications = [{
      ...baseNotification,
      is_read: false,
      type: "store_online",
      metadata: '{"store_slug":"beauty-essentials-7"}',
    }];
    render(<NotificationsPage />);
    fireEvent.click(screen.getByRole("button", { name: /Store is Back Online/ }));

    expect(mocks.markRead).toHaveBeenCalledWith("notification-1");
    expect(mocks.push).toHaveBeenCalledWith("/stores/beauty-essentials-7");
  });
});
