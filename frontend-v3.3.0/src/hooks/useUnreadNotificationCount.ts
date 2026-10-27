import { useCallback } from "react";
import useSWR from "swr";
import { useSelector } from "react-redux";

import { getNotifications } from "@/routes/api";
import type { RootState } from "@/lib/redux/store";

const PAGE_SIZE = 100;
const MAX_DISPLAY_COUNT = 10;

type NotificationPage = {
  notifications?: Array<{ is_read?: boolean | number | string }>;
  unread_count?: number;
  pagination?: {
    last_page?: number;
    per_page?: number;
    total?: number;
  };
};

export const notificationUnreadKey = (userId: number) =>
  `/api/user/notifications/unread-count/${userId}`;

export const NOTIFICATIONS_UPDATED_EVENT = "notifications-updated";

export const fetchUnreadNotificationCount = async (): Promise<number> => {
  let unread = 0;
  let page = 1;

  while (unread < MAX_DISPLAY_COUNT) {
    const response = await getNotifications({ page, per_page: PAGE_SIZE });
    if (!response.success || !response.data) {
      throw new Error(response.message || "Failed to load notifications");
    }

    const data = response.data as NotificationPage;
    if (typeof data.unread_count === "number" && data.unread_count >= 0) {
      return Math.min(data.unread_count, MAX_DISPLAY_COUNT);
    }

    const notifications = data.notifications ?? [];
    if (notifications.length === 0) break;
    unread += notifications.filter(
      (notification) =>
        notification.is_read === false ||
        notification.is_read === 0 ||
        notification.is_read === "0",
    ).length;

    const lastPage = Number(data.pagination?.last_page);
    const total = Number(data.pagination?.total);
    const perPage = Number(data.pagination?.per_page);
    if (Number.isFinite(lastPage) && lastPage > 0) {
      if (page >= lastPage) break;
    } else if (
      Number.isFinite(total) &&
      Number.isFinite(perPage) &&
      perPage > 0
    ) {
      if (page >= Math.ceil(total / perPage)) break;
    } else if (notifications.length < PAGE_SIZE) {
      break;
    }
    page += 1;
  }

  return Math.min(unread, MAX_DISPLAY_COUNT);
};

export const useUnreadNotificationCount = () => {
  const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
  const userId = useSelector((state: RootState) => state.auth.user?.id);
  const key = isLoggedIn && userId ? notificationUnreadKey(userId) : null;
  const { data, mutate } = useSWR<number>(key, fetchUnreadNotificationCount, {
    refreshInterval: 60_000,
    revalidateOnFocus: true,
    revalidateOnReconnect: true,
  });

  const refreshUnreadCount = useCallback(() => mutate(), [mutate]);
  const setUnreadCount = useCallback(
    (count: number) => mutate(count, false),
    [mutate],
  );
  const decrementUnreadCount = useCallback(
    () =>
      mutate(
        (current) =>
          current == null || current >= MAX_DISPLAY_COUNT
            ? fetchUnreadNotificationCount()
            : Math.max(0, current - 1),
        false,
      ),
    [mutate],
  );

  return {
    unreadCount: data ?? 0,
    refreshUnreadCount,
    setUnreadCount,
    decrementUnreadCount,
  };
};
