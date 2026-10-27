import { useCallback, useEffect, useRef, useState } from "react";
import type Pusher from "pusher-js";
import type { Channel, Options } from "pusher-js";
import { authorizeSupportChannel, getSupportRealtime } from "@/routes/api";
import { useSettings } from "@/contexts/SettingsContext";

export function useSupportRealtime(
  threadUuid?: string,
  userId?: number,
  activeSessionId?: number | null,
) {
  const [typingScope, setTypingScope] = useState<{
    threadUuid: string;
    userId: number;
    sessionId: number;
    driver: string;
  } | null>(null);
  const presence = useRef<Channel | null>(null);
  const whisperTyping = useCallback((typing: boolean) => {
    if (document.hidden || !navigator.onLine || !presence.current?.subscribed)
      return;
    try {
      presence.current.trigger("client-typing", { role: "customer", typing });
    } catch {
      // Client events can be disabled by the server; REST typing remains active.
    }
  }, []);
  const { settings } = useSettings();
  // Keep the new setting local to support without changing the legacy tuple.
  const entries: readonly { variable: string; value: unknown }[] | null =
    settings;
  const value = entries?.find(
    (entry) => entry.variable === "broadcast_driver",
  )?.value;
  const broadcastDriver =
    value &&
    typeof value === "object" &&
    "broadcastDriver" in value &&
    typeof value.broadcastDriver === "string"
      ? value.broadcastDriver
      : null;

  useEffect(() => {
    if (
      !threadUuid ||
      !userId ||
      (broadcastDriver !== "pusher" && broadcastDriver !== "reverb")
    )
      return;

    let request: AbortController | null = null;
    let client: Pusher | null = null;
    let privateChannel: Channel | null = null;
    let presenceChannel: Channel | null = null;
    let typingTimer: ReturnType<typeof setTimeout> | undefined;

    const clearTyping = () => {
      clearTimeout(typingTimer);
      setTypingScope(null);
    };

    const stop = () => {
      request?.abort();
      request = null;
      clearTyping();
      presence.current = null;
      privateChannel?.unbind_all();
      presenceChannel?.unbind_all();
      if (privateChannel) client?.unsubscribe(privateChannel.name);
      if (presenceChannel) client?.unsubscribe(presenceChannel.name);
      client?.connection.unbind("state_change", connectionChanged);
      client?.disconnect();
      client = null;
      privateChannel = null;
      presenceChannel = null;
    };

    const connectionChanged = ({ current }: { current: string }) => {
      if (current !== "connected") {
        clearTyping();
        presence.current = null;
      }
    };

    const connect = async () => {
      if (document.hidden || !navigator.onLine) {
        stop();
        return;
      }
      if (request || client) return;
      const owner = new AbortController();
      request = owner;
      try {
        const config = await getSupportRealtime(owner.signal);
        const settings = config?.realtime;
        if (
          owner.signal.aborted ||
          config?.configured !== true ||
          config.user_id !== userId ||
          config.thread_uuid !== threadUuid ||
          settings?.driver !== broadcastDriver ||
          !settings.key?.trim() ||
          !settings.cluster?.trim() ||
          !settings.auth_endpoint?.trim() ||
          (broadcastDriver === "reverb" &&
            (!settings.host?.trim() || settings.port == null)) ||
          !["http", "https"].includes(settings.scheme ?? "") ||
          (settings.port != null &&
            (!Number.isInteger(settings.port) ||
              settings.port < 1 ||
              settings.port > 65535))
        )
          return;

        const options: Options = {
          cluster: settings.cluster.trim(),
          forceTLS: settings.scheme === "https",
          enabledTransports: ["ws", "wss"],
          channelAuthorization: {
            customHandler: ({ socketId, channelName }, callback) => {
              void authorizeSupportChannel(
                settings.auth_endpoint!,
                socketId,
                channelName,
                owner.signal,
              ).then(
                (auth) => {
                  if (!owner.signal.aborted) callback(null, auth);
                },
                () => {
                  if (!owner.signal.aborted)
                    callback(
                      new Error("Support channel authorization failed."),
                      null,
                    );
                },
              );
            },
          },
        };
        if (settings.host?.trim()) options.wsHost = settings.host.trim();
        if (settings.port != null) {
          if (broadcastDriver === "reverb") {
            options.wsPort = settings.port;
            options.wssPort = settings.port;
          } else if (options.forceTLS) options.wssPort = settings.port;
          else options.wsPort = settings.port;
        }

        // Reverb speaks the Pusher protocol; both use this browser-only SDK.
        const { default: PusherClient } = await import("pusher-js");
        if (owner.signal.aborted) return;
        client = new PusherClient(settings.key.trim(), options);
        client.connection.bind("state_change", connectionChanged);
        privateChannel = client.subscribe(
          `private-support.thread.${threadUuid}`,
        );
        presenceChannel = client.subscribe(
          `presence-support.presence.thread.${threadUuid}`,
        );
        presenceChannel.bind("pusher:subscription_succeeded", () => {
          if (!owner.signal.aborted) presence.current = presenceChannel;
        });
        privateChannel.bind("pusher:subscription_error", clearTyping);
        presenceChannel.bind("pusher:subscription_error", () => {
          presence.current = null;
        });
        privateChannel.bind("support.typing", (event: unknown) => {
          if (
            owner.signal.aborted ||
            document.hidden ||
            !navigator.onLine ||
            !activeSessionId ||
            !event ||
            typeof event !== "object" ||
            !("role" in event) ||
            !["admin", "agent"].includes(String(event.role)) ||
            !("typing" in event) ||
            typeof event.typing !== "boolean" ||
            ("session_id" in event && event.session_id !== activeSessionId)
          )
            return;
          clearTyping();
          if (event.typing) {
            setTypingScope({
              threadUuid,
              userId,
              sessionId: activeSessionId,
              driver: broadcastDriver,
            });
            typingTimer = setTimeout(clearTyping, 4000);
          }
        });
        privateChannel.bind("support.activity", (event: unknown) => {
          if (
            owner.signal.aborted ||
            !event ||
            typeof event !== "object" ||
            !("activity" in event)
          )
            return;
          if (
            ["message.created", "thread.cleared"].includes(
              String(event.activity),
            )
          )
            clearTyping();
        });
        // pusher-js re-subscribes and re-authorizes these channels on reconnect.
      } catch {
        // An optional socket/config failure must not interrupt polling or drafts.
        if (request === owner) stop();
      } finally {
        if (request === owner && !client) request = null;
      }
    };

    const resume = () => void connect();
    resume();
    window.addEventListener("online", resume);
    window.addEventListener("offline", stop);
    document.addEventListener("visibilitychange", resume);
    return () => {
      stop();
      window.removeEventListener("online", resume);
      window.removeEventListener("offline", stop);
      document.removeEventListener("visibilitychange", resume);
    };
  }, [threadUuid, userId, broadcastDriver, activeSessionId]);

  const agentTyping = Boolean(
    typingScope &&
    typingScope.threadUuid === threadUuid &&
    typingScope.userId === userId &&
    typingScope.sessionId === activeSessionId &&
    typingScope.driver === broadcastDriver,
  );
  return { agentTyping, whisperTyping };
}
