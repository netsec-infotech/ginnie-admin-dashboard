import type { ReactNode } from "react";
import {
  act,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import StoreNotifyButton from "@/components/StoreNotifyButton";

const mocks = vi.hoisted(() => ({
  notify: vi.fn(),
  toast: vi.fn(),
  isLoggedIn: true,
}));
vi.mock("@/routes/api", () => ({ notifyStoreOpening: mocks.notify }));
vi.mock("react-redux", () => ({ useSelector: () => mocks.isLoggedIn }));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@heroui/react", () => ({
  addToast: mocks.toast,
  Button: ({
    children,
    onPress,
    isDisabled,
    isLoading,
    startContent,
    ...props
  }: {
    children: ReactNode;
    onPress: () => void;
    isDisabled: boolean;
    isLoading: boolean;
    startContent: ReactNode;
  }) => (
    <button
      {...props}
      disabled={isDisabled}
      onClick={onPress}
      data-loading={isLoading}
    >
      {startContent}
      {children}
    </button>
  ),
}));

const closedStore = { id: 42, status: { is_open: false, status: "offline" } };

beforeEach(() => {
  mocks.notify.mockReset();
  mocks.toast.mockReset();
  mocks.isLoggedIn = true;
});

describe("store opening notifications", () => {
  it("subscribes and cancels with boolean payloads after successful responses", async () => {
    mocks.notify.mockResolvedValue({ success: true });
    render(<StoreNotifyButton store={closedStore} />);
    fireEvent.click(screen.getByRole("button"));
    await waitFor(() =>
      expect(screen.getByRole("button")).toHaveAttribute(
        "aria-pressed",
        "true",
      ),
    );
    expect(mocks.notify).toHaveBeenLastCalledWith(42, true);
    fireEvent.click(screen.getByRole("button"));
    await waitFor(() =>
      expect(screen.getByRole("button")).toHaveAttribute(
        "aria-pressed",
        "false",
      ),
    );
    expect(mocks.notify).toHaveBeenLastCalledWith(42, false);
  });

  it("uses the saved subscription state and preserves it when a request fails", async () => {
    mocks.notify.mockResolvedValue({ success: false });
    render(<StoreNotifyButton store={{ ...closedStore, is_notify: true }} />);
    fireEvent.click(screen.getByRole("button"));
    await waitFor(() =>
      expect(mocks.toast).toHaveBeenCalledWith({
        title: "storeOpeningNotification.error",
        color: "danger",
      }),
    );
    expect(mocks.notify).toHaveBeenCalledWith(42, false);
    expect(screen.getByRole("button")).toHaveAttribute("aria-pressed", "true");
  });

  it("prompts guests to log in without subscribing", () => {
    mocks.isLoggedIn = false;
    const loginClick = vi.fn();
    render(
      <>
        <button id="login-btn" onClick={loginClick}>
          Login
        </button>
        <StoreNotifyButton store={closedStore} />
      </>,
    );
    fireEvent.click(
      screen.getByRole("button", { name: "storeOpeningNotification.notify" }),
    );
    expect(loginClick).toHaveBeenCalledOnce();
    expect(mocks.notify).not.toHaveBeenCalled();
  });

  it("prevents duplicate requests while saving", async () => {
    let resolve!: (value: { success: boolean }) => void;
    mocks.notify.mockReturnValue(
      new Promise((done) => {
        resolve = done;
      }),
    );
    render(<StoreNotifyButton store={closedStore} />);
    fireEvent.click(screen.getByRole("button"));
    fireEvent.click(screen.getByRole("button"));
    expect(mocks.notify).toHaveBeenCalledOnce();
    expect(screen.getByRole("button")).toBeDisabled();
    await act(async () => resolve({ success: true }));
    expect(screen.getByRole("button")).toBeEnabled();
  });

  it("hides the control when the store is open", () => {
    render(
      <StoreNotifyButton
        store={{ ...closedStore, status: { is_open: true, status: "online" } }}
      />,
    );
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });
});
