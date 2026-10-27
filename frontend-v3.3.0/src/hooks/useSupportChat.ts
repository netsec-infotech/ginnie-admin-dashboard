import { useCallback, useEffect, useRef, useState } from "react";
import { useSupportRealtime } from "@/hooks/useSupportRealtime";
import {
  getSupportHistory,
  getSupportThread,
  getSupportTopics,
  getSupportUpdates,
  markSupportRead,
  rateSupportSession,
  requestSupportCallback,
  resolveSupportSession,
  sendSupportMessage,
  setSupportTyping,
  startSupportSession,
} from "@/routes/api";
import {
  addSupportMessages,
  mergeSupportThread,
  SupportRequestError,
} from "@/helpers/support";
import type {
  StartSupportSession,
  SupportAction,
  SupportDraft,
  SupportThreadData,
  SupportTopic,
} from "@/types/support";

export function useSupportChat() {
  const [data, setData] = useState<SupportThreadData | null>(null);
  const { agentTyping, whisperTyping } = useSupportRealtime(
    data?.thread.uuid,
    data?.thread.customer.id,
    data?.thread.active_ticket_id,
  );
  const [topics, setTopics] = useState<SupportTopic[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingHistory, setLoadingHistory] = useState(false);
  const [pending, setPending] = useState<SupportAction | null>(null);
  const [error, setError] = useState<SupportRequestError | null>(null);
  const [connection, setConnection] = useState<
    "connecting" | "connected" | "offline" | "paused" | "retrying"
  >("connecting");
  const current = useRef<SupportThreadData | null>(null);
  const lifecycle = useRef<AbortController | null>(null);
  const refreshing = useRef<Promise<boolean> | null>(null);
  const revision = useRef(0);
  const polling = useRef(false);
  const acting = useRef(false);
  const older = useRef(false);
  const messageCursor = useRef(0);
  const callCursor = useRef(0);
  const lastRefresh = useRef(0);
  const retryAt = useRef(0);
  const reads = useRef(new Map<string, number>());
  const reading = useRef(new Set<string>());
  const typing = useRef<{
    slug: string;
    lastSent: number;
    timer: ReturnType<typeof setTimeout> | null;
  } | null>(null);

  const commit = useCallback((value: SupportThreadData) => {
    current.current = value;
    setData(value);
  }, []);

  const fail = useCallback((cause: unknown) => {
    const failure =
      cause instanceof SupportRequestError
        ? cause
        : new SupportRequestError("");
    retryAt.current =
      Date.now() +
      Math.max(failure.retryAfter * 1000, failure.status === 429 ? 30000 : 0);
    setError(failure);
    return failure;
  }, []);

  const refresh = useCallback(
    async (silent = false) => {
      if (refreshing.current) return refreshing.current;
      const owner = lifecycle.current;
      if (!owner || owner.signal.aborted) return false;
      const requestRevision = revision.current;
      const request = (async () => {
        try {
          const next = await getSupportThread(owner.signal);
          if (owner.signal.aborted || requestRevision !== revision.current)
            return false;
          const merged = mergeSupportThread(current.current, next);
          commit(merged);
          messageCursor.current = Math.max(
            messageCursor.current,
            ...merged.thread.sessions.flatMap((session) =>
              session.messages.map((message) => message.id),
            ),
          );
          callCursor.current = Math.max(
            callCursor.current,
            ...merged.thread.sessions.flatMap((session) =>
              session.calls.map((call) => call.id),
            ),
          );
          lastRefresh.current = Date.now();
          setConnection(next.polling_enabled ? "connected" : "paused");
          setError(null);
          return true;
        } catch (cause) {
          if (!owner.signal.aborted) {
            if (!silent) fail(cause);
            setConnection(navigator.onLine ? "retrying" : "offline");
          }
          return false;
        } finally {
          if (!owner.signal.aborted) setLoading(false);
        }
      })();
      refreshing.current = request;
      const success = await request;
      if (refreshing.current === request) refreshing.current = null;
      return success;
    },
    [commit, fail],
  );

  const loadTopics = useCallback(async () => {
    const owner = lifecycle.current;
    if (!owner) return;
    try {
      const next = await getSupportTopics(owner.signal);
      if (!owner.signal.aborted) setTopics(next);
    } catch (cause) {
      if (!owner.signal.aborted) fail(cause);
    }
  }, [fail]);

  const retry = useCallback(async () => {
    if (Date.now() < retryAt.current) return;
    // Keep topic errors visible even when the thread request succeeds.
    await refresh();
    await loadTopics();
  }, [refresh, loadTopics]);

  useEffect(() => {
    const owner = new AbortController();
    lifecycle.current = owner;
    let timer: ReturnType<typeof setTimeout>;
    const tick = async () => {
      try {
        if (
          owner.signal.aborted ||
          document.hidden ||
          !navigator.onLine ||
          acting.current ||
          refreshing.current ||
          polling.current ||
          Date.now() < retryAt.current
        )
          return;
        const thread = current.current;
        if (!thread) {
          await retry();
          return;
        }
        if (!thread.polling_enabled) return;
        polling.current = true;
        const requestRevision = revision.current;
        const updates = await getSupportUpdates(
          messageCursor.current,
          callCursor.current,
          owner.signal,
        );
        if (owner.signal.aborted || requestRevision !== revision.current)
          return;
        // Unknown sessions need their metadata before messages can be associated.
        const known = new Set(
          current.current?.thread.sessions.map((session) => session.id),
        );
        const needsRefresh =
          updates.active_ticket_id !==
            current.current?.thread.active_ticket_id ||
          updates.calls.length > 0 ||
          updates.messages.some((message) => !known.has(message.session_id)) ||
          Date.now() - lastRefresh.current >= 60000;
        if (needsRefresh && !(await refresh())) return;
        if (owner.signal.aborted || requestRevision !== revision.current)
          return;
        if (current.current)
          commit(addSupportMessages(current.current, updates.messages));
        // Do not advance past messages whose session could not be refreshed.
        if (
          updates.messages.every((message) =>
            current.current?.thread.sessions.some(
              (session) => session.id === message.session_id,
            ),
          )
        ) {
          messageCursor.current = Math.max(
            messageCursor.current,
            updates.message_cursor,
          );
          callCursor.current = Math.max(
            callCursor.current,
            updates.call_cursor,
          );
        }
        setConnection("connected");
      } catch (cause) {
        if (!owner.signal.aborted) {
          fail(cause);
          setConnection(navigator.onLine ? "retrying" : "offline");
        }
      } finally {
        polling.current = false;
        if (!owner.signal.aborted)
          timer = setTimeout(
            tick,
            Math.max(1000, current.current?.poll_interval_ms || 15000),
          );
      }
    };
    void retry().then(() => {
      if (!owner.signal.aborted) {
        timer = setTimeout(
          tick,
          Math.max(1000, current.current?.poll_interval_ms || 15000),
        );
      }
    });
    const reconnect = () => {
      if (!document.hidden && navigator.onLine && !acting.current) void retry();
    };
    const offline = () => setConnection("offline");
    window.addEventListener("online", reconnect);
    window.addEventListener("offline", offline);
    document.addEventListener("visibilitychange", reconnect);
    return () => {
      owner.abort();
      clearTimeout(timer);
      refreshing.current = null;
      if (typing.current?.timer) clearTimeout(typing.current.timer);
      if (typing.current)
        void setSupportTyping(typing.current.slug, false).catch(
          () => undefined,
        );
      typing.current = null;
      window.removeEventListener("online", reconnect);
      window.removeEventListener("offline", offline);
      document.removeEventListener("visibilitychange", reconnect);
    };
  }, [commit, fail, refresh, retry]);

  const loadOlder = useCallback(async () => {
    const pagination = current.current?.thread.message_pagination;
    const owner = lifecycle.current;
    if (
      !owner ||
      older.current ||
      !pagination?.has_more ||
      !pagination.oldest_message_id ||
      Date.now() < retryAt.current
    )
      return;
    older.current = true;
    setLoadingHistory(true);
    try {
      const history = await getSupportHistory(
        pagination.oldest_message_id,
        owner.signal,
      );
      if (owner.signal.aborted || !current.current) return;
      const unknown = history.messages.some(
        (message) =>
          !current.current?.thread.sessions.some(
            (session) => session.id === message.session_id,
          ),
      );
      if (unknown) await refresh();
      if (owner.signal.aborted || !current.current) return;
      const next = addSupportMessages(current.current, history.messages);
      next.thread.message_pagination = {
        has_more: history.has_more,
        oldest_message_id: history.oldest_message_id,
      };
      commit(next);
    } catch (cause) {
      if (!owner.signal.aborted) fail(cause);
    } finally {
      older.current = false;
      if (!owner.signal.aborted) setLoadingHistory(false);
    }
  }, [commit, fail, refresh]);

  const perform = useCallback(
    async (
      action: SupportAction,
      work: () => Promise<void>,
      backgroundRefresh = false,
    ) => {
      const owner = lifecycle.current;
      if (
        !owner ||
        owner.signal.aborted ||
        acting.current ||
        Date.now() < retryAt.current
      )
        return false;
      acting.current = true;
      revision.current += 1;
      setPending(action);
      setError(null);
      try {
        // Other actions wait for an in-flight refresh before reconciling a write.
        if (!backgroundRefresh && refreshing.current) await refreshing.current;
        if (owner.signal.aborted) return false;
        await work();
        if (owner.signal.aborted) return false;
        if (backgroundRefresh) {
          // A successful write is complete once the server acknowledges it.
          // Reconcile thread metadata without keeping the composer busy.
          void Promise.resolve(refreshing.current).then(() => refresh(true));
        } else {
          await refresh();
        }
        return true;
      } catch (cause) {
        if (!owner.signal.aborted) {
          if (cause instanceof SupportRequestError && cause.status === 409)
            await refresh();
          fail(cause);
        }
        return false;
      } finally {
        acting.current = false;
        if (!owner.signal.aborted) setPending(null);
      }
    },
    [fail, refresh],
  );

  const start = (draft: StartSupportSession) =>
    perform("start", async () => {
      const session = await startSupportSession(draft);
      if (lifecycle.current?.signal.aborted || !current.current) return;
      commit({
        ...current.current,
        thread: {
          ...current.current.thread,
          active_ticket_id: session.id,
          sessions: [
            ...current.current.thread.sessions.filter(
              (item) => item.id !== session.id,
            ),
            session,
          ],
        },
      });
    });
  const send = (slug: string, draft: SupportDraft) =>
    perform(
      "send",
      async () => {
        const message = await sendSupportMessage(slug, draft);
        if (!lifecycle.current?.signal.aborted && current.current)
          commit(addSupportMessages(current.current, [message]));
      },
      true,
    );
  const resolve = (slug: string) =>
    perform("resolve", async () => {
      await resolveSupportSession(slug);
      if (!lifecycle.current?.signal.aborted && current.current)
        commit({
          ...current.current,
          thread: {
            ...current.current.thread,
            active_ticket_id: null,
            sessions: current.current.thread.sessions.map((session) =>
              session.slug === slug
                ? {
                    ...session,
                    status: "resolved",
                    resolved_at: new Date().toISOString(),
                  }
                : session,
            ),
          },
        });
    });
  const rate = (slug: string, score: number, feedback: string) =>
    perform("rating", async () => {
      await rateSupportSession(slug, score, feedback);
      if (!lifecycle.current?.signal.aborted && current.current)
        commit({
          ...current.current,
          thread: {
            ...current.current.thread,
            sessions: current.current.thread.sessions.map((session) =>
              session.slug === slug
                ? { ...session, rating: { score } }
                : session,
            ),
          },
        });
    });
  const callback = (slug: string, phone: string, date?: string) =>
    perform("callback", () => requestSupportCallback(slug, phone, date));

  const sendTyping = useCallback(
    (slug: string, value: boolean) => {
      if (Date.now() < retryAt.current) return;
      const previous = typing.current;
      if (previous?.timer) clearTimeout(previous.timer);
      if (previous && previous.slug !== slug) {
        void setSupportTyping(previous.slug, false).catch(() => undefined);
        whisperTyping(false);
      }
      if (!value) {
        if (previous) void setSupportTyping(slug, false).catch(() => undefined);
        whisperTyping(false);
        typing.current = null;
        return;
      }
      const lastSent = previous?.slug === slug ? previous.lastSent : 0;
      const shouldSend = Date.now() - lastSent > 3000;
      if (shouldSend) {
        void setSupportTyping(slug, true).catch(() => undefined);
        whisperTyping(true);
      }
      typing.current = {
        slug,
        lastSent: shouldSend ? Date.now() : lastSent,
        timer: setTimeout(() => {
          void setSupportTyping(slug, false).catch(() => undefined);
          whisperTyping(false);
          typing.current = null;
        }, 1500),
      };
    },
    [whisperTyping],
  );

  const markRead = useCallback(async (slug: string, id: number) => {
    const owner = lifecycle.current;
    if (
      !owner ||
      owner.signal.aborted ||
      document.hidden ||
      !navigator.onLine ||
      (reads.current.get(slug) ?? 0) >= id ||
      reading.current.has(slug)
    )
      return;
    reading.current.add(slug);
    try {
      await markSupportRead(slug, id);
      if (!owner.signal.aborted) reads.current.set(slug, id);
    } catch {
      /* Retry the visible receipt on the next visibility/poll update. */
    } finally {
      reading.current.delete(slug);
    }
  }, []);

  const activeSession =
    data?.thread.sessions.find(
      (session) => session.id === data.thread.active_ticket_id,
    ) ?? null;
  return {
    data,
    topics,
    activeSession,
    loading,
    loadingHistory,
    pending,
    error,
    connection,
    agentTyping,
    retry,
    loadOlder,
    start,
    send,
    resolve,
    rate,
    callback,
    sendTyping,
    markRead,
  };
}
