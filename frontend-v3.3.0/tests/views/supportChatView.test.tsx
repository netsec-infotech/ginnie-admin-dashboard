/* eslint @typescript-eslint/no-unused-vars: ["warn", { "varsIgnorePattern": "^_", "argsIgnorePattern": "^_" }] */
import { act, fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { ReactNode } from "react";
import { supportThread } from "../fixtures/support";
import SupportChatView from "@/views/SupportChatView";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock("@heroui/react", () => ({
  Button: ({
    children,
    onPress,
    isDisabled,
    isIconOnly: _icon,
    isLoading: _loading,
    startContent: _start,
    color: _color,
    variant: _variant,
    radius: _radius,
    size: _size,
    ...props
  }: {
    children?: ReactNode;
    onPress?: () => void;
    isDisabled?: boolean;
    [key: string]: unknown;
  }) => (
    <button {...props} disabled={isDisabled} onClick={onPress}>
      {children}
    </button>
  ),
  Spinner: () => null,
}));
vi.mock("@/components/Support/SupportChatFrame", () => ({
  default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));
vi.mock("@/components/Support/SupportComposer", () => ({
  default: ({
    message,
    onMessage,
    onSubmit,
  }: {
    message: string;
    onMessage: (value: string) => void;
    onSubmit: () => void;
  }) => (
    <div>
      <input
        aria-label="draft"
        value={message}
        onChange={(event) => onMessage(event.target.value)}
      />
      <button onClick={onSubmit}>Send message</button>
    </div>
  ),
}));
vi.mock("@/components/Support/SupportTimeline", () => ({
  default: () => null,
}));
vi.mock("@/components/Support/SupportStartFlow", () => ({
  default: () => null,
}));
vi.mock("@/components/Support/SupportSessionActions", () => ({
  SupportCallbackDialog: () => null,
  SupportRating: () => null,
  SupportResolveDialog: () => null,
}));

const thread = supportThread();
const send = vi.fn();
vi.mock("@/hooks/useSupportChat", () => ({
  useSupportChat: () => ({
    data: thread,
    activeSession: thread.thread.sessions[0],
    topics: [],
    loading: false,
    loadingHistory: false,
    pending: null,
    error: null,
    connection: "connected",
    agentTyping: false,
    send,
    sendTyping: vi.fn(),
    markRead: vi.fn(),
    retry: vi.fn(),
    loadOlder: vi.fn(),
  }),
}));

describe("active support chat", () => {
  it("shows a message immediately, keeps the next draft, and retries with the same ID", async () => {
    let finish: (success: boolean) => void = () => {};
    send.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finish = resolve;
        }),
    );
    send.mockResolvedValueOnce(true);
    render(<SupportChatView />);
    fireEvent.change(screen.getByRole("textbox", { name: "draft" }), {
      target: { value: "First message" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Send message" }));
    await act(async () => {});
    const firstId = send.mock.calls[0][1].client_message_id;
    expect(screen.getByText("First message")).toBeInTheDocument();
    expect(screen.getByText("supportChat.sending")).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: "draft" })).toHaveValue("");
    fireEvent.change(screen.getByRole("textbox", { name: "draft" }), {
      target: { value: "Next message" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Send message" }));
    expect(screen.getByText("Next message")).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: "draft" })).toHaveValue("");
    expect(send).toHaveBeenCalledTimes(1);
    await act(async () => finish(false));
    expect(send).toHaveBeenCalledTimes(2);
    expect(send.mock.calls[1][1].client_message_id).not.toBe(firstId);
    expect(screen.getByText("supportChat.sendFailed")).toBeInTheDocument();
    fireEvent.change(screen.getByRole("textbox", { name: "draft" }), {
      target: { value: "Third draft" },
    });
    send.mockResolvedValueOnce(true);
    fireEvent.click(screen.getByRole("button", { name: "supportChat.retry" }));
    await act(async () => {});
    expect(send.mock.calls[2][1].client_message_id).toBe(firstId);
    expect(screen.getByRole("textbox", { name: "draft" })).toHaveValue(
      "Third draft",
    );
    await act(async () => {});
    expect(screen.queryByText("First message")).not.toBeInTheDocument();
  });
});
