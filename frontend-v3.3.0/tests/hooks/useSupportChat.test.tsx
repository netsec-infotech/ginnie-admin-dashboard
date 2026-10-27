import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  supportMessage,
  supportThread,
  supportTopics,
} from "../fixtures/support";
import { SupportRequestError } from "@/helpers/support";

const api = vi.hoisted(() => ({
  getSupportHistory: vi.fn(),
  getSupportRealtime: vi.fn(),
  getSupportThread: vi.fn(),
  getSupportTopics: vi.fn(),
  getSupportUpdates: vi.fn(),
  markSupportRead: vi.fn(),
  rateSupportSession: vi.fn(),
  requestSupportCallback: vi.fn(),
  resolveSupportSession: vi.fn(),
  sendSupportMessage: vi.fn(),
  setSupportTyping: vi.fn(),
  startSupportSession: vi.fn(),
}));
vi.mock("@/routes/api", () => api);
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    settings: [
      { variable: "broadcast_driver", value: { broadcastDriver: "pusher" } },
    ],
  }),
}));
import { useSupportChat } from "@/hooks/useSupportChat";

beforeEach(() => {
  vi.useFakeTimers();
  vi.resetAllMocks();
  Object.defineProperty(document, "hidden", {
    value: false,
    configurable: true,
  });
  Object.defineProperty(navigator, "onLine", {
    value: true,
    configurable: true,
  });
  api.getSupportThread.mockImplementation(async () => supportThread());
  api.getSupportTopics.mockResolvedValue(supportTopics);
  api.getSupportRealtime.mockResolvedValue({
    configured: false,
    realtime: null,
  });
  api.getSupportUpdates.mockResolvedValue({
    messages: [],
    calls: [],
    active_ticket_id: 1,
    message_cursor: 10,
    call_cursor: 0,
  });
  api.setSupportTyping.mockResolvedValue(undefined);
  api.markSupportRead.mockResolvedValue(undefined);
});
afterEach(() => vi.useRealTimers());

async function mount() {
  const view = renderHook(() => useSupportChat());
  await act(async () => {});
  return view;
}

describe("support lifecycle", () => {
  it("continues polling and preserves chat when realtime configuration fails", async () => {
    api.getSupportRealtime.mockRejectedValue(
      new Error("Socket settings unavailable"),
    );
    const view = await mount();
    expect(api.getSupportRealtime).toHaveBeenCalledOnce();
    expect(view.result.current.error).toBeNull();
    expect(view.result.current.activeSession?.slug).toBe("SUP-TEST");
    await act(() => vi.advanceTimersByTimeAsync(15000));
    expect(api.getSupportUpdates).toHaveBeenCalledOnce();
    expect(view.result.current.connection).toBe("connected");
    view.unmount();
  });
  it("loads the thread/topics and polls using server cursors and interval", async () => {
    const view = await mount();
    expect(view.result.current.activeSession?.slug).toBe("SUP-TEST");
    expect(view.result.current.topics).toHaveLength(3);
    await act(() => vi.advanceTimersByTimeAsync(14999));
    expect(api.getSupportUpdates).not.toHaveBeenCalled();
    api.getSupportUpdates.mockResolvedValue({
      messages: [supportMessage(11), supportMessage(11)],
      calls: [],
      active_ticket_id: 1,
      message_cursor: 11,
      call_cursor: 0,
    });
    await act(() => vi.advanceTimersByTimeAsync(1));
    expect(api.getSupportUpdates).toHaveBeenCalledWith(
      10,
      0,
      expect.any(AbortSignal),
    );
    expect(
      view.result.current.activeSession?.messages.map((message) => message.id),
    ).toEqual([10, 11]);
    await act(() => vi.advanceTimersByTimeAsync(15000));
    expect(api.getSupportUpdates).toHaveBeenLastCalledWith(
      11,
      0,
      expect.any(AbortSignal),
    );
    view.unmount();
  });
  it("pauses while hidden/offline, resumes on visibility, and cleans up on unmount", async () => {
    const view = await mount();
    Object.defineProperty(document, "hidden", {
      value: true,
      configurable: true,
    });
    await act(() => vi.advanceTimersByTimeAsync(15000));
    expect(api.getSupportUpdates).not.toHaveBeenCalled();
    Object.defineProperty(document, "hidden", {
      value: false,
      configurable: true,
    });
    await act(async () => {
      document.dispatchEvent(new Event("visibilitychange"));
    });
    expect(api.getSupportThread).toHaveBeenCalledTimes(2);
    Object.defineProperty(navigator, "onLine", {
      value: false,
      configurable: true,
    });
    await act(async () => {
      window.dispatchEvent(new Event("offline"));
    });
    await act(() => vi.advanceTimersByTimeAsync(15000));
    expect(view.result.current.connection).toBe("offline");
    expect(api.getSupportUpdates).not.toHaveBeenCalled();
    const signal = api.getSupportThread.mock.calls[0][0];
    view.unmount();
    expect(signal.aborted).toBe(true);
    await act(() => vi.advanceTimersByTimeAsync(60000));
    expect(api.getSupportUpdates).not.toHaveBeenCalled();
  });
  it("honors disabled polling", async () => {
    api.getSupportThread.mockResolvedValue({
      ...supportThread(),
      polling_enabled: false,
    });
    const view = await mount();
    await act(() => vi.advanceTimersByTimeAsync(60000));
    expect(api.getSupportUpdates).not.toHaveBeenCalled();
    expect(view.result.current.connection).toBe("paused");
    view.unmount();
  });

  it("uses a configured interval for the first poll as well as later polls", async () => {
    api.getSupportThread.mockResolvedValue({
      ...supportThread(),
      poll_interval_ms: 30000,
    });
    const view = await mount();
    await act(() => vi.advanceTimersByTimeAsync(29999));
    expect(api.getSupportUpdates).not.toHaveBeenCalled();
    await act(() => vi.advanceTimersByTimeAsync(1));
    expect(api.getSupportUpdates).toHaveBeenCalledTimes(1);
    view.unmount();
  });
  it("keeps older history after a full thread refresh", async () => {
    const data = supportThread();
    data.thread.message_pagination.has_more = true;
    api.getSupportThread.mockResolvedValue(data);
    api.getSupportHistory.mockResolvedValue({
      messages: [supportMessage(1)],
      has_more: false,
      oldest_message_id: 1,
    });
    const view = await mount();
    await act(async () => {
      await view.result.current.loadOlder();
    });
    expect(api.getSupportHistory).toHaveBeenCalledWith(
      10,
      expect.any(AbortSignal),
    );
    await act(async () => {
      await view.result.current.retry();
    });
    expect(
      view.result.current.activeSession?.messages.map((message) => message.id),
    ).toEqual([1, 10]);
    expect(view.result.current.data?.thread.message_pagination.has_more).toBe(
      false,
    );
    view.unmount();
  });
  it("rejects duplicate concurrent sends and keeps an acknowledged send successful if refresh fails", async () => {
    const view = await mount();
    let finish: (value: ReturnType<typeof supportMessage>) => void = () => {};
    api.sendSupportMessage.mockImplementation(
      () =>
        new Promise((resolve) => {
          finish = resolve;
        }),
    );
    const draft = {
      message: "Hello",
      attachments: [],
      client_message_id: "same-uuid",
    };
    let first: Promise<boolean>;
    await act(async () => {
      first = view.result.current.send("SUP-TEST", draft);
      expect(await view.result.current.send("SUP-TEST", draft)).toBe(false);
    });
    api.getSupportThread.mockRejectedValue(new SupportRequestError(""));
    await act(async () => {
      finish(supportMessage(11));
      expect(await first).toBe(true);
    });
    expect(api.sendSupportMessage).toHaveBeenCalledTimes(1);
    expect(
      view.result.current.activeSession?.messages.map((message) => message.id),
    ).toEqual([10, 11]);
    expect(view.result.current.error).toBeNull();
    view.unmount();
  });
  it("finishes sending before a slow thread refresh completes", async () => {
    const view = await mount();
    let finishRefresh: (
      value: ReturnType<typeof supportThread>,
    ) => void = () => {};
    api.getSupportThread.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finishRefresh = resolve;
        }),
    );
    api.sendSupportMessage.mockResolvedValue({
      ...supportMessage(11),
      client_message_id: "new-uuid",
    });
    let success = false;
    await act(async () => {
      success = await view.result.current.send("SUP-TEST", {
        message: "Hello",
        attachments: [],
        client_message_id: "new-uuid",
      });
    });
    expect(success).toBe(true);
    expect(view.result.current.pending).toBeNull();
    expect(api.getSupportThread).toHaveBeenCalledTimes(2);
    expect(view.result.current.activeSession?.messages.at(-1)?.id).toBe(11);
    await act(async () => finishRefresh(supportThread()));
    view.unmount();
  });
  it("does not let a stale poll reopen a resolved session", async () => {
    const view = await mount();
    let finish: (value: object) => void = () => {};
    api.getSupportUpdates.mockImplementation(
      () =>
        new Promise((resolve) => {
          finish = resolve;
        }),
    );
    await act(() => vi.advanceTimersByTimeAsync(15000));
    const closed = supportThread();
    closed.thread.active_ticket_id = null;
    closed.thread.sessions[0].status = "resolved";
    api.getSupportThread.mockResolvedValue(closed);
    await act(async () => {
      expect(await view.result.current.resolve("SUP-TEST")).toBe(true);
    });
    await act(async () => {
      finish({
        messages: [],
        calls: [],
        active_ticket_id: 1,
        message_cursor: 10,
        call_cursor: 0,
      });
    });
    expect(view.result.current.activeSession).toBeNull();
    view.unmount();
  });
  it("honors rate limiting and does not automatically retry writes", async () => {
    const view = await mount();
    api.requestSupportCallback.mockRejectedValue(
      new SupportRequestError("", 429, 45),
    );
    await act(async () => {
      expect(await view.result.current.callback("SUP-TEST", "+123")).toBe(
        false,
      );
    });
    await act(() => vi.advanceTimersByTimeAsync(30000));
    expect(api.getSupportUpdates).not.toHaveBeenCalled();
    await act(async () => {
      expect(await view.result.current.callback("SUP-TEST", "+123")).toBe(
        false,
      );
    });
    expect(api.requestSupportCallback).toHaveBeenCalledTimes(1);
    view.unmount();
  });
  it("throttles typing, sends a stop event, and avoids duplicate read receipts", async () => {
    const view = await mount();
    act(() => {
      view.result.current.sendTyping("SUP-TEST", true);
      view.result.current.sendTyping("SUP-TEST", true);
    });
    expect(api.setSupportTyping).toHaveBeenCalledTimes(1);
    await act(() => vi.advanceTimersByTimeAsync(1500));
    expect(api.setSupportTyping).toHaveBeenLastCalledWith("SUP-TEST", false);
    await act(async () => {
      await view.result.current.markRead("SUP-TEST", 10);
      await view.result.current.markRead("SUP-TEST", 10);
    });
    expect(api.markSupportRead).toHaveBeenCalledTimes(1);
    view.unmount();
  });
});
