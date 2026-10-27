import { beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Provider } from "react-redux";
import { configureStore } from "@reduxjs/toolkit";

import ProfileBtn from "@/components/ProfileBtn";
import { userData } from "@/types/ApiResponse";

const unread = vi.hoisted(() => ({ count: 0 }));
vi.mock("@/hooks/useUnreadNotificationCount", () => ({
  NOTIFICATIONS_UPDATED_EVENT: "notifications-updated",
  useUnreadNotificationCount: () => ({
    unreadCount: unread.count,
    refreshUnreadCount: vi.fn(),
  }),
}));

vi.mock("next/router", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ systemSettings: {} }),
}));

const renderWith = (user: Partial<userData> | null) => {
  const store = configureStore({
    reducer: { auth: (state = { user }) => state },
  });
  return render(
    <Provider store={store}>
      <ProfileBtn />
    </Provider>,
  );
};

const VERIFIED_AT = "2026-07-01T10:00:00.000Z";
const base = { id: 1, name: "Test", email: "t@example.com", mobile: "98765" };

beforeEach(() => {
  unread.count = 0;
});

describe("ProfileBtn verification badge", () => {
  it("shows the badge when verification is pending", () => {
    renderWith(base);
    expect(
      screen.getByTestId("profile-verification-badge"),
    ).toBeInTheDocument();
  });

  it("hides the badge once both are verified", () => {
    renderWith({
      ...base,
      email_verified_at: VERIFIED_AT,
      mobile_verified_at: VERIFIED_AT,
    });
    expect(
      screen.queryByTestId("profile-verification-badge"),
    ).not.toBeInTheDocument();
  });

  it("hides the badge when only mobile is verified", () => {
    renderWith({ ...base, mobile_verified_at: VERIFIED_AT });
    expect(screen.queryByTestId("profile-verification-badge")).not.toBeInTheDocument();
  });

  it("hides the badge when nobody is signed in", () => {
    renderWith(null);
    expect(
      screen.queryByTestId("profile-verification-badge"),
    ).not.toBeInTheDocument();
  });

  it("labels the badge with what is outstanding", () => {
    renderWith({ ...base, email_verified_at: VERIFIED_AT });
    expect(screen.getByTestId("profile-verification-badge")).toHaveAttribute(
      "aria-label",
      expect.stringMatching(/mobile/i),
    );
  });

  /**
   * Regression guard for a real crash.
   *
   * Nesting DropdownTrigger > Tooltip > Badge > Avatar made the trigger's
   * forwarded ref land on a component instead of a DOM node, and HeroUI's
   * ResizeObserver threw "parameter 1 is not of type 'Element'". The badge must
   * stay a SIBLING of the trigger's element, never an ancestor of it.
   */
  it("keeps the badge outside the dropdown trigger button", () => {
    renderWith(base);
    const badge = screen.getByTestId("profile-verification-badge");
    const trigger = screen.getByRole("button", { name: /test/i });

    expect(trigger).toBeInTheDocument();
    expect(badge.contains(trigger)).toBe(false);
    expect(trigger.contains(badge)).toBe(false);
  });

  it("renders the trigger as a real DOM element", () => {
    // The ref target ResizeObserver receives — if this isn't an Element, the
    // browser throws even though jsdom might not.
    renderWith(base);
    const trigger = screen.getByRole("button", { name: /test/i });
    expect(trigger).toBeInstanceOf(HTMLElement);
  });
});

describe("ProfileBtn unread indicator", () => {
  it("shows a dot only when notifications are unread", () => {
    const { unmount } = renderWith({
      ...base,
      email_verified_at: VERIFIED_AT,
      mobile_verified_at: VERIFIED_AT,
    });
    expect(
      screen.queryByTestId("profile-notification-dot"),
    ).not.toBeInTheDocument();

    unmount();
    unread.count = 3;
    renderWith({
      ...base,
      email_verified_at: VERIFIED_AT,
      mobile_verified_at: VERIFIED_AT,
    });
    expect(screen.getByTestId("profile-notification-dot")).toHaveAttribute(
      "aria-label",
      expect.stringMatching(/unread/i),
    );
  });

  it("shows the unread count beside Notifications in the open menu", async () => {
    unread.count = 10;
    renderWith({
      ...base,
      email_verified_at: VERIFIED_AT,
      mobile_verified_at: VERIFIED_AT,
    });

    await userEvent.click(screen.getByRole("button", { name: /test/i }));
    expect(await screen.findByText("9+")).toBeInTheDocument();
    expect(
      screen.getByRole("menuitem", { name: /9\+ unread notifications/i }),
    ).toBeInTheDocument();
  });
});
