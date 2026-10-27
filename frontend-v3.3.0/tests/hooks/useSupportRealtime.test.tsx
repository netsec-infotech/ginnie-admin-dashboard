import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { SupportRealtimeData } from "@/types/support";

const mocks = vi.hoisted(() => ({
  getSupportRealtime: vi.fn(),
  authorizeSupportChannel: vi.fn(),
  subscribe: vi.fn(),
  unsubscribe: vi.fn(),
  channels: new Map<
    string,
    {
      name: string;
      subscribed: boolean;
      bind: ReturnType<typeof vi.fn>;
      unbind_all: ReturnType<typeof vi.fn>;
      trigger: ReturnType<typeof vi.fn>;
      events: Map<string, (event: unknown) => void>;
    }
  >(),
  connectionEvents: new Map<string, (event: unknown) => void>(),
  Pusher: vi.fn(),
  disconnect: vi.fn(),
  useSettings: vi.fn(),
}));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: mocks.useSettings,
}));
vi.mock("@/routes/api", () => ({
  getSupportRealtime: mocks.getSupportRealtime,
  authorizeSupportChannel: mocks.authorizeSupportChannel,
}));
vi.mock("pusher-js", () => ({ default: mocks.Pusher }));
import { useSupportRealtime } from "@/hooks/useSupportRealtime";

const configuration = (): SupportRealtimeData => ({
  user_id: 1,
  thread_uuid: "test-thread",
  configured: true,
  realtime: {
    driver: "pusher",
    key: "test-public-key",
    cluster: "test-cluster",
    host: null,
    port: 443,
    scheme: "https",
    auth_endpoint: "https://backend.example/api/broadcasting/auth",
  },
});

beforeEach(() => {
  vi.resetAllMocks();
  Object.defineProperty(document, "hidden", {
    value: false,
    configurable: true,
  });
  Object.defineProperty(navigator, "onLine", {
    value: true,
    configurable: true,
  });
  mocks.getSupportRealtime.mockResolvedValue(configuration());
  mocks.useSettings.mockReturnValue({
    settings: [
      { variable: "broadcast_driver", value: { broadcastDriver: "pusher" } },
    ],
  });
  mocks.channels.clear();
  mocks.connectionEvents.clear();
  mocks.authorizeSupportChannel.mockResolvedValue({ auth: "test-signature" });
  mocks.subscribe.mockImplementation((name: string) => {
    const events = new Map<string, (event: unknown) => void>();
    const channel = {
      name,
      subscribed: false,
      events,
      bind: vi.fn((event, handler) => events.set(event, handler)),
      unbind_all: vi.fn(() => events.clear()),
      trigger: vi.fn(),
    };
    mocks.channels.set(name, channel);
    return channel;
  });
  mocks.Pusher.mockImplementation(function () {
    return {
      disconnect: mocks.disconnect,
      subscribe: mocks.subscribe,
      unsubscribe: mocks.unsubscribe,
      connection: {
        bind: vi.fn((event, handler) =>
          mocks.connectionEvents.set(event, handler),
        ),
        unbind: vi.fn((event) => mocks.connectionEvents.delete(event)),
      },
    };
  });
});

afterEach(() => vi.useRealTimers());

async function mount() {
  const view = renderHook(() => useSupportRealtime("test-thread", 1, 1));
  await act(async () => {});
  return view;
}

function privateChannel() {
  return mocks.channels.get("private-support.thread.test-thread")!;
}
async function event(name: string, data: unknown) {
  await act(async () => privateChannel().events.get(name)?.(data));
}

describe("support typing events", () => {
  it("subscribes to the documented private and presence channels", async () => {
    const view = await mount();
    expect(mocks.subscribe.mock.calls.map(([name]) => name)).toEqual([
      "private-support.thread.test-thread",
      "presence-support.presence.thread.test-thread",
    ]);
    view.unmount();
    expect(mocks.unsubscribe.mock.calls.map(([name]) => name)).toEqual([
      "private-support.thread.test-thread",
      "presence-support.presence.thread.test-thread",
    ]);
  });

  it.each(["admin", "agent"])(
    "shows %s typing and expires four seconds after the last update",
    async (role) => {
      vi.useFakeTimers();
      const view = await mount();
      expect(view.result.current.agentTyping).toBe(false);
      await event("support.typing", { role, typing: true });
      expect(view.result.current.agentTyping).toBe(true);
      await act(() => vi.advanceTimersByTimeAsync(3000));
      await event("support.typing", { role, typing: true });
      await act(() => vi.advanceTimersByTimeAsync(3999));
      expect(view.result.current.agentTyping).toBe(true);
      await act(() => vi.advanceTimersByTimeAsync(1));
      expect(view.result.current.agentTyping).toBe(false);
      view.unmount();
    },
  );

  it("hides typing on stop, incoming message, or subscription failure", async () => {
    const view = await mount();
    await event("support.typing", { role: "agent", typing: true });
    await event("support.typing", { role: "agent", typing: false });
    expect(view.result.current.agentTyping).toBe(false);
    await event("support.typing", { role: "agent", typing: true });
    await event("support.activity", { activity: "message.created" });
    expect(view.result.current.agentTyping).toBe(false);
    await event("support.typing", { role: "agent", typing: true });
    await event("pusher:subscription_error", {});
    expect(view.result.current.agentTyping).toBe(false);
    view.unmount();
  });

  it.each([
    null,
    { role: "customer", typing: true },
    { role: "user", typing: true },
    { role: "agent", typing: "true" },
    { role: "agent", typing: true, session_id: 99 },
  ])("ignores unrelated or invalid typing payloads (%j)", async (payload) => {
    const view = await mount();
    await event("support.typing", payload);
    expect(view.result.current.agentTyping).toBe(false);
    view.unmount();
  });

  it("clears typing on disconnect and backgrounding", async () => {
    const view = await mount();
    await event("support.typing", { role: "admin", typing: true });
    await act(async () =>
      mocks.connectionEvents.get("state_change")?.({ current: "unavailable" }),
    );
    expect(view.result.current.agentTyping).toBe(false);
    await event("support.typing", { role: "admin", typing: true });
    Object.defineProperty(document, "hidden", {
      value: true,
      configurable: true,
    });
    await act(async () =>
      document.dispatchEvent(new Event("visibilitychange")),
    );
    expect(view.result.current.agentTyping).toBe(false);
    view.unmount();
  });

  it("clears typing on session changes and ignores callbacks from the old subscription", async () => {
    const view = renderHook(
      ({ session }) => useSupportRealtime("test-thread", 1, session),
      { initialProps: { session: 1 as number | null } },
    );
    await act(async () => {});
    const oldHandler = privateChannel().events.get("support.typing")!;
    await event("support.typing", { role: "admin", typing: true });
    view.rerender({ session: 2 });
    await act(async () => oldHandler({ role: "admin", typing: true }));
    expect(view.result.current.agentTyping).toBe(false);
    await event("support.typing", { role: "admin", typing: true });
    expect(view.result.current.agentTyping).toBe(true);
    view.rerender({ session: null });
    await act(async () => {});
    await event("support.typing", { role: "admin", typing: true });
    expect(view.result.current.agentTyping).toBe(false);
    view.unmount();
  });

  it("sends customer whispers only after presence subscription and tolerates disabled client events", async () => {
    const view = await mount();
    const channel = mocks.channels.get(
      "presence-support.presence.thread.test-thread",
    )!;
    act(() => view.result.current.whisperTyping(true));
    expect(channel.trigger).not.toHaveBeenCalled();
    channel.subscribed = true;
    await act(async () =>
      channel.events.get("pusher:subscription_succeeded")?.({}),
    );
    act(() => view.result.current.whisperTyping(true));
    act(() => view.result.current.whisperTyping(false));
    expect(channel.trigger.mock.calls).toEqual([
      ["client-typing", { role: "customer", typing: true }],
      ["client-typing", { role: "customer", typing: false }],
    ]);
    channel.trigger.mockImplementation(() => {
      throw new Error("Client events disabled");
    });
    expect(() => view.result.current.whisperTyping(true)).not.toThrow();
    view.unmount();
  });

  it("authorizes subscriptions using the API endpoint and cancels stale auth callbacks", async () => {
    const view = await mount();
    const auth =
      mocks.Pusher.mock.calls[0][1].channelAuthorization.customHandler;
    const callback = vi.fn();
    await act(async () =>
      auth(
        { socketId: "1.2", channelName: "private-support.thread.test-thread" },
        callback,
      ),
    );
    expect(mocks.authorizeSupportChannel).toHaveBeenCalledWith(
      configuration().realtime!.auth_endpoint,
      "1.2",
      "private-support.thread.test-thread",
      expect.any(AbortSignal),
    );
    expect(callback).toHaveBeenCalledWith(null, { auth: "test-signature" });
    mocks.authorizeSupportChannel.mockRejectedValue(new Error("Forbidden"));
    await act(async () =>
      auth(
        { socketId: "1.2", channelName: "private-support.thread.test-thread" },
        callback,
      ),
    );
    expect(callback).toHaveBeenLastCalledWith(expect.any(Error), null);
    let complete!: (data: unknown) => void;
    mocks.authorizeSupportChannel.mockReturnValue(
      new Promise((resolve) => {
        complete = resolve;
      }),
    );
    auth(
      { socketId: "1.2", channelName: "private-support.thread.test-thread" },
      callback,
    );
    const signal = mocks.authorizeSupportChannel.mock.calls.at(-1)![3];
    view.unmount();
    await act(async () => complete({ auth: "old-signature" }));
    expect(signal.aborted).toBe(true);
    expect(callback).toHaveBeenCalledTimes(2);
  });
});

describe("support Pusher and Reverb connections", () => {
  it.each([
    null,
    [],
    [{ variable: "broadcast_driver", value: null }],
    [{ variable: "broadcast_driver", value: { broadcastDriver: "log" } }],
    [{ variable: "broadcast_driver", value: { broadcastDriver: false } }],
    [{ variable: "other", value: { broadcastDriver: "pusher" } }],
  ])(
    "uses polling only unless settings select a supported driver (%j)",
    async (settings) => {
      mocks.useSettings.mockReturnValue({ settings });
      const view = await mount();
      expect(mocks.getSupportRealtime).not.toHaveBeenCalled();
      expect(mocks.Pusher).not.toHaveBeenCalled();
      view.unmount();
    },
  );

  it("starts after settings load, disconnects on driver changes, and can restart", async () => {
    mocks.useSettings.mockReturnValue({ settings: null });
    const view = await mount();
    expect(mocks.Pusher).not.toHaveBeenCalled();
    const select = async (driver: string) => {
      mocks.useSettings.mockReturnValue({
        settings: [
          { variable: "broadcast_driver", value: { broadcastDriver: driver } },
        ],
      });
      view.rerender();
      await act(async () => {});
    };
    await select("pusher");
    expect(mocks.Pusher).toHaveBeenCalledTimes(1);
    // A new settings response with the same selection should reuse the socket.
    await select("pusher");
    expect(mocks.Pusher).toHaveBeenCalledTimes(1);
    await select("log");
    expect(mocks.disconnect).toHaveBeenCalledTimes(1);
    expect(mocks.getSupportRealtime).toHaveBeenCalledTimes(1);
    await select("pusher");
    expect(mocks.Pusher).toHaveBeenCalledTimes(2);
    view.unmount();
  });

  it("aborts pending configuration when settings disable Pusher", async () => {
    let complete!: (data: SupportRealtimeData) => void;
    mocks.getSupportRealtime.mockReturnValue(
      new Promise((resolve) => {
        complete = resolve;
      }),
    );
    const view = await mount();
    const signal = mocks.getSupportRealtime.mock.calls[0][0];
    mocks.useSettings.mockReturnValue({ settings: [] });
    view.rerender();
    expect(signal.aborted).toBe(true);
    await act(async () => complete(configuration()));
    expect(mocks.Pusher).not.toHaveBeenCalled();
    view.unmount();
  });

  it("uses API settings without a hardcoded host, key, channel or token", async () => {
    const view = await mount();
    expect(mocks.getSupportRealtime).toHaveBeenCalledWith(
      expect.any(AbortSignal),
    );
    expect(mocks.Pusher).toHaveBeenCalledExactlyOnceWith("test-public-key", {
      cluster: "test-cluster",
      forceTLS: true,
      enabledTransports: ["ws", "wss"],
      channelAuthorization: { customHandler: expect.any(Function) },
      wssPort: 443,
    });
    view.rerender();
    expect(mocks.Pusher).toHaveBeenCalledTimes(1);
    view.unmount();
    expect(mocks.disconnect).toHaveBeenCalledTimes(1);
  });

  it("uses a custom host and the port matching the API scheme", async () => {
    const config = configuration();
    Object.assign(config.realtime!, {
      host: "socket.example",
      port: 8080,
      scheme: "http",
    });
    mocks.getSupportRealtime.mockResolvedValue(config);
    const view = await mount();
    expect(mocks.Pusher).toHaveBeenCalledWith("test-public-key", {
      cluster: "test-cluster",
      forceTLS: false,
      enabledTransports: ["ws", "wss"],
      channelAuthorization: { customHandler: expect.any(Function) },
      wsHost: "socket.example",
      wsPort: 8080,
    });
    view.unmount();
  });

  it.each(["http", "https"])(
    "connects Reverb using its API host/port over %s",
    async (scheme) => {
      mocks.useSettings.mockReturnValue({
        settings: [
          {
            variable: "broadcast_driver",
            value: { broadcastDriver: "reverb" },
          },
        ],
      });
      const config = configuration();
      Object.assign(config.realtime!, {
        driver: "reverb",
        host: "reverb.example",
        port: 8080,
        scheme,
      });
      mocks.getSupportRealtime.mockResolvedValue(config);
      const view = await mount();
      expect(mocks.Pusher).toHaveBeenCalledExactlyOnceWith("test-public-key", {
        cluster: "test-cluster",
        forceTLS: scheme === "https",
        enabledTransports: ["ws", "wss"],
        channelAuthorization: { customHandler: expect.any(Function) },
        wsHost: "reverb.example",
        wsPort: 8080,
        wssPort: 8080,
      });
      view.unmount();
      expect(mocks.disconnect).toHaveBeenCalledOnce();
    },
  );

  it.each(["host", "port"])(
    "does not connect Reverb when %s is missing",
    async (field) => {
      mocks.useSettings.mockReturnValue({
        settings: [
          {
            variable: "broadcast_driver",
            value: { broadcastDriver: "reverb" },
          },
        ],
      });
      const config = configuration();
      Object.assign(config.realtime!, {
        driver: "reverb",
        host: "reverb.example",
        port: 8080,
        [field]: null,
      });
      mocks.getSupportRealtime.mockResolvedValue(config);
      const view = await mount();
      expect(mocks.Pusher).not.toHaveBeenCalled();
      view.unmount();
    },
  );

  it("switches between Pusher and Reverb with fresh connection settings", async () => {
    const view = await mount();
    const config = configuration();
    Object.assign(config.realtime!, {
      driver: "reverb",
      key: "reverb-test-key",
      host: "reverb.example",
      port: 8080,
      scheme: "http",
    });
    mocks.useSettings.mockReturnValue({
      settings: [
        { variable: "broadcast_driver", value: { broadcastDriver: "reverb" } },
      ],
    });
    mocks.getSupportRealtime.mockResolvedValue(config);
    view.rerender();
    await act(async () => {});
    expect(mocks.disconnect).toHaveBeenCalledTimes(1);
    expect(mocks.Pusher).toHaveBeenLastCalledWith(
      "reverb-test-key",
      expect.objectContaining({
        wsHost: "reverb.example",
        wsPort: 8080,
        forceTLS: false,
      }),
    );
    mocks.useSettings.mockReturnValue({
      settings: [
        { variable: "broadcast_driver", value: { broadcastDriver: "pusher" } },
      ],
    });
    mocks.getSupportRealtime.mockResolvedValue(configuration());
    view.rerender();
    await act(async () => {});
    expect(mocks.disconnect).toHaveBeenCalledTimes(2);
    expect(mocks.Pusher).toHaveBeenCalledTimes(3);
    expect(mocks.Pusher.mock.calls[2][1]).not.toHaveProperty("wsHost");
    view.unmount();
  });

  it.each([
    "disabled",
    "other user",
    "other thread",
    "driver mismatch",
    "missing key",
    "bad port",
    "bad scheme",
    "missing settings",
  ])("keeps the socket disabled for %s", async (scenario) => {
    const config = configuration();
    if (scenario === "disabled") config.configured = false;
    if (scenario === "other user") config.user_id = 2;
    if (scenario === "other thread") config.thread_uuid = "another-thread";
    if (scenario === "driver mismatch") config.realtime!.driver = "reverb";
    if (scenario === "missing key") config.realtime!.key = "";
    if (scenario === "bad port") config.realtime!.port = 70000;
    if (scenario === "bad scheme") config.realtime!.scheme = "file";
    if (scenario === "missing settings") config.realtime = null;
    mocks.getSupportRealtime.mockResolvedValue(config);
    const view = await mount();
    expect(mocks.Pusher).not.toHaveBeenCalled();
    view.unmount();
  });

  it("does not fetch configuration before the authenticated thread is available", async () => {
    const view = renderHook(() => useSupportRealtime());
    await act(async () => {});
    expect(mocks.getSupportRealtime).not.toHaveBeenCalled();
    expect(mocks.Pusher).not.toHaveBeenCalled();
    view.unmount();
  });

  it("discards pending configuration after unmount", async () => {
    let complete!: (data: SupportRealtimeData) => void;
    mocks.getSupportRealtime.mockReturnValue(
      new Promise((resolve) => {
        complete = resolve;
      }),
    );
    const view = await mount();
    const signal = mocks.getSupportRealtime.mock.calls[0][0];
    view.unmount();
    expect(signal.aborted).toBe(true);
    await act(async () => complete(configuration()));
    expect(mocks.Pusher).not.toHaveBeenCalled();
  });

  it("disconnects when hidden or offline and reloads configuration on resume", async () => {
    const view = await mount();
    Object.defineProperty(document, "hidden", {
      value: true,
      configurable: true,
    });
    await act(async () =>
      document.dispatchEvent(new Event("visibilitychange")),
    );
    expect(mocks.disconnect).toHaveBeenCalledTimes(1);
    Object.defineProperty(document, "hidden", {
      value: false,
      configurable: true,
    });
    await act(async () =>
      document.dispatchEvent(new Event("visibilitychange")),
    );
    expect(mocks.Pusher).toHaveBeenCalledTimes(2);
    Object.defineProperty(navigator, "onLine", {
      value: false,
      configurable: true,
    });
    await act(async () => window.dispatchEvent(new Event("offline")));
    expect(mocks.disconnect).toHaveBeenCalledTimes(2);
    Object.defineProperty(navigator, "onLine", {
      value: true,
      configurable: true,
    });
    await act(async () => window.dispatchEvent(new Event("online")));
    expect(mocks.getSupportRealtime).toHaveBeenCalledTimes(3);
    view.unmount();
    expect(mocks.disconnect).toHaveBeenCalledTimes(3);
  });

  it("disconnects and fetches the new account configuration on identity changes", async () => {
    const view = renderHook(({ id, uuid }) => useSupportRealtime(uuid, id), {
      initialProps: { id: 1, uuid: "test-thread" },
    });
    await act(async () => {});
    mocks.getSupportRealtime.mockResolvedValue({
      ...configuration(),
      user_id: 2,
      thread_uuid: "second-thread",
    });
    view.rerender({ id: 2, uuid: "second-thread" });
    await act(async () => {});
    expect(mocks.disconnect).toHaveBeenCalledTimes(1);
    expect(mocks.Pusher).toHaveBeenCalledTimes(2);
    view.unmount();
  });

  it("handles config and socket errors without throwing into chat", async () => {
    mocks.getSupportRealtime.mockRejectedValue(new Error("Unavailable"));
    const view = await mount();
    expect(mocks.Pusher).not.toHaveBeenCalled();
    mocks.getSupportRealtime.mockResolvedValue(configuration());
    mocks.Pusher.mockImplementation(() => {
      throw new Error("Connection failed");
    });
    await act(async () => window.dispatchEvent(new Event("online")));
    expect(mocks.Pusher).toHaveBeenCalledTimes(1);
    view.unmount();
  });
});
