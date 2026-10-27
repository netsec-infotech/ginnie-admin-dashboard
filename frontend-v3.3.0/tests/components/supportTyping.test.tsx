import type { ReactNode } from "react";
import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import en from "../../public/locales/en.json";
import hi from "../../public/locales/hi.json";
import ar from "../../public/locales/ar.json";
import { supportSession, supportThread } from "../fixtures/support";

const mocks = vi.hoisted(() => ({ useSupportChat: vi.fn(), language: "en" }));
vi.mock("@/hooks/useSupportChat", () => ({
  useSupportChat: mocks.useSupportChat,
}));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string) =>
      key
        .split(".")
        .reduce<unknown>(
          (value, part) => (value as Record<string, unknown>)?.[part],
          { en, hi, ar }[mocks.language as "en" | "hi" | "ar"],
        ) ?? key,
  }),
}));
vi.mock("@heroui/react", () => ({
  Button: ({
    children,
    onPress,
    "aria-label": label,
  }: {
    children?: ReactNode;
    onPress?: () => void;
    "aria-label"?: string;
  }) => (
    <button aria-label={label} onClick={onPress}>
      {children}
    </button>
  ),
  Spinner: () => null,
}));
vi.mock("@/components/Support/SupportChatFrame", () => ({
  default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));
vi.mock("@/components/Support/SupportTimeline", () => ({
  default: ({ sessions }: { sessions: { id: number; slug: string }[] }) => (
    <div data-testid="timeline">
      {sessions.map((session) => (
        <span key={session.id}>{session.slug}</span>
      ))}
    </div>
  ),
}));
vi.mock("@/components/Support/SupportComposer", () => ({
  default: () => <textarea aria-label="Message" />,
}));
vi.mock("@/components/Support/SupportStartFlow", () => ({
  default: () => <div data-testid="order-picker" />,
}));
vi.mock("@/components/Support/SupportSessionActions", () => ({
  SupportCallbackDialog: () => null,
  SupportResolveDialog: () => null,
  SupportRating: () => null,
}));
import SupportChatView from "@/views/SupportChatView";

beforeEach(() => {
  mocks.language = "en";
  mocks.useSupportChat.mockReturnValue({
    data: supportThread(),
    activeSession: supportSession(),
    pending: null,
    loading: false,
    connection: "connected",
    agentTyping: true,
  });
});

describe("support typing indicator", () => {
  it("separates previous conversations from the new-chat flow without remounting it", () => {
    mocks.useSupportChat.mockReturnValue({
      ...mocks.useSupportChat(),
      activeSession: null,
    });
    render(<SupportChatView />);
    const picker = screen.getByTestId("order-picker");
    expect(picker).toBeVisible();
    expect(screen.queryByTestId("timeline")).not.toBeInTheDocument();
    fireEvent.click(
      screen.getByRole("button", { name: en.supportChat.previousChats }),
    );
    expect(picker).not.toBeVisible();
    expect(screen.getByTestId("timeline")).toHaveTextContent("SUP-TEST");
    fireEvent.click(
      screen.getByRole("button", { name: en.supportChat.startChat }),
    );
    expect(screen.getByTestId("order-picker")).toBe(picker);
    expect(picker).toBeVisible();
    expect(screen.queryByTestId("timeline")).not.toBeInTheDocument();
  });
  it("shows only the active conversation until history is opened", () => {
    const data = supportThread();
    data.thread.sessions.unshift({
      ...supportSession(),
      id: 2,
      slug: "PREVIOUS-CHAT",
      status: "closed",
    });
    mocks.useSupportChat.mockReturnValue({ ...mocks.useSupportChat(), data });
    render(<SupportChatView />);
    expect(screen.getByTestId("timeline")).not.toHaveTextContent(
      "PREVIOUS-CHAT",
    );
    fireEvent.click(
      screen.getByRole("button", { name: en.supportChat.previousChats }),
    );
    expect(screen.getByTestId("timeline")).toHaveTextContent("PREVIOUS-CHAT");
    expect(screen.getByTestId("timeline")).not.toHaveTextContent("SUP-TEST");
    expect(screen.queryByRole("textbox")).not.toBeInTheDocument();
    fireEvent.click(
      screen.getByRole("button", { name: en.supportChat.backToChat }),
    );
    expect(screen.getByRole("textbox")).toBeInTheDocument();
    expect(screen.getByTestId("timeline")).toHaveTextContent("SUP-TEST");
  });
  it("opens at the order picker and preserves its scroll position on refresh", () => {
    const height = vi
      .spyOn(HTMLElement.prototype, "scrollHeight", "get")
      .mockReturnValue(1000);
    const bounds = vi
      .spyOn(HTMLElement.prototype, "getBoundingClientRect")
      .mockImplementation(function (this: HTMLElement) {
        return {
          top:
            this.firstElementChild?.getAttribute("data-testid") ===
            "order-picker"
              ? 200
              : 0,
        } as DOMRect;
      });
    try {
      const chat = { ...mocks.useSupportChat(), activeSession: null };
      mocks.useSupportChat.mockReturnValue(chat);
      const view = render(<SupportChatView />);
      const viewport =
        screen.getByTestId("order-picker").parentElement!.parentElement!;
      expect(viewport.scrollTop).toBe(200);
      viewport.scrollTop = 400;
      mocks.useSupportChat.mockReturnValue({ ...chat, data: { ...chat.data } });
      view.rerender(<SupportChatView />);
      expect(viewport.scrollTop).toBe(400);
      mocks.useSupportChat.mockReturnValue({
        ...chat,
        activeSession: supportSession(),
      });
      view.rerender(<SupportChatView />);
      expect(viewport.scrollTop).toBe(1000);
    } finally {
      height.mockRestore();
      bounds.mockRestore();
    }
  });
  it.each(["en", "hi", "ar"])(
    "announces the translated agent typing status in %s",
    (language) => {
      mocks.language = language;
      render(<SupportChatView />);
      const text = { en, hi, ar }[language as "en" | "hi" | "ar"].supportChat
        .agentTyping;
      const indicator = screen.getByText(text).closest('[role="status"]');
      expect(indicator).toHaveAttribute("aria-live", "polite");
      expect(
        screen.getByRole("textbox", { name: "Message" }),
      ).toBeInTheDocument();
    },
  );
  it("hides the indicator when typing stops or the session closes", () => {
    const chat = mocks.useSupportChat();
    const view = render(<SupportChatView />);
    expect(screen.getByText(en.supportChat.agentTyping)).toBeInTheDocument();
    mocks.useSupportChat.mockReturnValue({ ...chat, agentTyping: false });
    view.rerender(<SupportChatView />);
    expect(
      screen.queryByText(en.supportChat.agentTyping),
    ).not.toBeInTheDocument();
    mocks.useSupportChat.mockReturnValue({ ...chat, activeSession: null });
    view.rerender(<SupportChatView />);
    expect(
      screen.queryByText(en.supportChat.agentTyping),
    ).not.toBeInTheDocument();
  });
});
