import { useLayoutEffect, useRef, useState } from "react";
import { Button, Spinner } from "@heroui/react";
import {
  CheckCircle2,
  Headset,
  Maximize2,
  Minimize2,
  Phone,
  RefreshCw,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { useSupportChat } from "@/hooks/useSupportChat";
import SupportChatFrame from "@/components/Support/SupportChatFrame";
import SupportComposer from "@/components/Support/SupportComposer";
import SupportStartFlow from "@/components/Support/SupportStartFlow";
import SupportTimeline from "@/components/Support/SupportTimeline";
import {
  SupportCallbackDialog,
  SupportRating,
  SupportResolveDialog,
} from "@/components/Support/SupportSessionActions";
import type { SupportDraft } from "@/types/support";

interface OutgoingMessage {
  slug: string;
  draft: SupportDraft;
  failed: boolean;
}

export default function SupportChatView() {
  const { t } = useTranslation();
  const chat = useSupportChat();
  const [expanded, setExpanded] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [message, setMessage] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const [outgoing, setOutgoing] = useState<OutgoingMessage[]>([]);
  const [dialog, setDialog] = useState<{
    kind: "callback" | "resolve";
    slug: string;
  } | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const submission = useRef<{ scope: string; id: string } | null>(null);
  const sendQueue = useRef<Promise<void>>(Promise.resolve());
  const viewport = useRef<HTMLDivElement>(null);
  const startFlow = useRef<HTMLDivElement>(null);
  const showingStartFlow = useRef(false);
  const nearBottom = useRef(true);
  const historyPosition = useRef<{ height: number; top: number } | null>(null);
  const [isNearBottom, setIsNearBottom] = useState(true);
  const [lastSeenMessageId, setLastSeenMessageId] = useState(0);
  const { data, activeSession, pending } = chat;
  const sessions = data?.thread.sessions ?? [];
  const lastSession = sessions.at(-1);
  const previousSessions = sessions.filter(
    (session) => session.id !== activeSession?.id,
  );
  const lastMessageId = Math.max(
    0,
    ...sessions.flatMap((session) => session.messages.map((item) => item.id)),
  );
  const hasNewMessages = !isNearBottom && lastMessageId > lastSeenMessageId;
  const busy = pending != null && pending !== "send";
  const sending = pending === "send";
  const updateMessage = (value: string) => {
    setMessage(value);
    submission.current = null;
    if (activeSession)
      chat.sendTyping(activeSession.slug, Boolean(value.trim()));
  };
  const updateFiles = (value: File[]) => {
    setFiles(value);
    submission.current = null;
  };
  const draft = (scope: string): SupportDraft => {
    if (submission.current?.scope !== scope) {
      submission.current = { scope, id: crypto.randomUUID() };
    }
    return {
      message,
      attachments: files,
      client_message_id: submission.current.id,
    };
  };
  const clear = () => {
    setMessage("");
    setFiles([]);
    submission.current = null;
    nearBottom.current = true;
    setIsNearBottom(true);
    if (viewport.current)
      viewport.current.scrollTop = viewport.current.scrollHeight;
  };
  const sendActive = (slug: string, value: SupportDraft) => {
    setOutgoing((previous) => [
      ...previous.filter(
        (item) => item.draft.client_message_id !== value.client_message_id,
      ),
      { slug, draft: value, failed: false },
    ]);
    // Show each message now, but post them to the server in order.
    sendQueue.current = sendQueue.current
      .then(() => chat.send(slug, value))
      .then((success) => {
        setOutgoing((previous) =>
          success
            ? previous.filter(
                (item) =>
                  item.draft.client_message_id !== value.client_message_id,
              )
            : previous.map((item) =>
                item.draft.client_message_id === value.client_message_id
                  ? { ...item, failed: true }
                  : item,
              ),
        );
      })
      .catch(() => {
        setOutgoing((previous) =>
          previous.map((item) =>
            item.draft.client_message_id === value.client_message_id
              ? { ...item, failed: true }
              : item,
          ),
        );
      });
  };
  const submit = async (topicId?: number, orderId?: number) => {
    const value = draft(
      activeSession?.slug ?? `start:${topicId}:${orderId ?? "general"}`,
    );
    if (activeSession) {
      chat.sendTyping(activeSession.slug, false);
      sendActive(activeSession.slug, value);
      clear();
      return;
    }
    const success =
      topicId != null
        ? await chat.start({ ...value, topic_id: topicId, order_id: orderId })
        : false;
    if (success) clear();
  };

  useLayoutEffect(() => {
    const root = viewport.current;
    if (!root) return;
    if (historyPosition.current) {
      // The loading-state render happens before the older rows arrive.
      // Keep the anchor until that request has finished and the DOM is updated.
      if (chat.loadingHistory) return;
      const previous = historyPosition.current;
      root.scrollTop = previous.top + root.scrollHeight - previous.height;
      historyPosition.current = null;
    } else if (!showHistory && startFlow.current && !showingStartFlow.current) {
      // The new-chat flow always opens at the order picker.
      root.scrollTop +=
        startFlow.current.getBoundingClientRect().top -
        root.getBoundingClientRect().top;
    } else if (!showHistory && activeSession && nearBottom.current) {
      root.scrollTop = root.scrollHeight;
    }
    showingStartFlow.current = !showHistory && startFlow.current != null;
  }, [
    data,
    activeSession,
    lastMessageId,
    chat.loadingHistory,
    showHistory,
    outgoing,
  ]);

  const loadOlder = async () => {
    const root = viewport.current;
    if (root)
      historyPosition.current = {
        height: root.scrollHeight,
        top: root.scrollTop,
      };
    await chat.loadOlder();
  };
  const errorText =
    chat.error?.status === 429
      ? t("supportChat.rateLimited", { seconds: chat.error.retryAfter || 30 })
      : chat.error?.message || t("supportChat.actionFailed");
  const composer = {
    message,
    files,
    onMessage: updateMessage,
    onFiles: updateFiles,
    busy,
    disabled: chat.connection === "offline",
    onBlur: () => {
      if (activeSession) chat.sendTyping(activeSession.slug, false);
    },
  };

  return (
    <SupportChatFrame
      expanded={expanded}
      onExpandedChange={setExpanded}
      label={t("supportChat.title")}
    >
      <header className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-divider bg-content1 px-4 py-4 sm:px-6">
        <div className="flex min-w-0 flex-1 items-center gap-3">
          <span className="rounded-2xl bg-primary p-2.5 text-primary-foreground">
            <Headset size={22} strokeWidth={1.75} />
          </span>
          <div className="min-w-0">
            <h1 className="text-sm font-semibold sm:text-base">
              {t("supportChat.title")}
            </h1>
            <p className="mt-0.5 break-words text-xs text-default-500">
              {activeSession
                ? activeSession.topic?.title || activeSession.subject
                : t("supportChat.assistant")}
            </p>
          </div>
        </div>
        <Button
          data-chat-expand
          className="shrink-0 text-xs md:hidden"
          size="sm"
          variant="flat"
          color="primary"
          startContent={
            expanded ? <Minimize2 size={16} /> : <Maximize2 size={16} />
          }
          aria-expanded={expanded}
          onPress={() => setExpanded((value) => !value)}
        >
          {t(expanded ? "supportChat.collapseChat" : "supportChat.expandChat")}
        </Button>
        <div className="flex flex-wrap items-center gap-2 max-md:w-full max-md:gap-1">
          <span
            role="status"
            className={`inline-flex items-center gap-2 rounded-full px-3 py-2 text-xs max-md:px-2 max-md:py-1.5 ${chat.connection === "connected" ? "bg-success/10 text-success" : "bg-default-100 text-default-500"}`}
          >
            <span className="h-2 w-2 rounded-full bg-current" />
            {t(`supportChat.connection.${chat.connection}`)}
          </span>
          {activeSession && !showHistory && (
            <>
              <Button
                className="text-xs max-md:min-w-0 max-md:px-2"
                size="sm"
                variant="bordered"
                radius="full"
                startContent={<Phone size={15} />}
                isDisabled={busy || sending}
                onPress={() =>
                  setDialog({ kind: "callback", slug: activeSession.slug })
                }
              >
                {t("supportChat.callback")}
              </Button>
              <Button
                className="text-xs max-md:min-w-0 max-md:px-2"
                size="sm"
                color="success"
                variant="flat"
                radius="full"
                startContent={<CheckCircle2 size={15} />}
                isDisabled={busy || sending}
                onPress={() =>
                  setDialog({ kind: "resolve", slug: activeSession.slug })
                }
              >
                {t("supportChat.resolve")}
              </Button>
            </>
          )}
          <Button
            className="text-xs"
            size="sm"
            variant="light"
            isIconOnly
            aria-label={t("supportChat.refresh")}
            isDisabled={busy || sending}
            onPress={() => void chat.retry()}
          >
            <RefreshCw size={16} />
          </Button>
        </div>
      </header>
      {data &&
        (previousSessions.length > 0 ||
          data.thread.message_pagination.has_more) && (
          <div className="flex shrink-0 items-center justify-end border-b border-divider px-4 py-2 sm:px-6">
            <Button
              size="sm"
              variant="light"
              color="primary"
              className="text-xs"
              onPress={() => {
                setShowHistory((value) => !value);
                historyPosition.current = null;
                nearBottom.current = true;
                setIsNearBottom(true);
                if (viewport.current) viewport.current.scrollTop = 0;
              }}
            >
              {t(
                showHistory
                  ? activeSession
                    ? "supportChat.backToChat"
                    : "supportChat.startChat"
                  : "supportChat.previousChats",
              )}
            </Button>
          </div>
        )}
      {chat.error && (
        <div
          role="alert"
          className="flex items-center justify-between gap-3 border-b border-danger/20 bg-danger/5 px-4 py-2 text-sm text-danger"
        >
          <span>{errorText}</span>
          <Button
            className="text-xs"
            size="sm"
            variant="light"
            color="danger"
            isDisabled={busy || sending}
            onPress={() => void chat.retry()}
          >
            {t("supportChat.retry")}
          </Button>
        </div>
      )}
      {notice && (
        <div
          role="status"
          className="flex items-center justify-between gap-3 border-b border-success/20 bg-success/5 px-4 py-2 text-sm text-success"
        >
          <span>{notice}</span>
          <Button
            className="text-xs"
            size="sm"
            variant="light"
            onPress={() => setNotice(null)}
          >
            {t("supportChat.dismiss")}
          </Button>
        </div>
      )}
      <div
        ref={viewport}
        className="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-default-50/60 px-4 py-2 sm:px-6"
        onScroll={() => {
          const root = viewport.current;
          if (root) {
            nearBottom.current =
              root.scrollHeight - root.scrollTop - root.clientHeight < 100;
            setIsNearBottom(nearBottom.current);
            if (nearBottom.current) setLastSeenMessageId(lastMessageId);
          }
        }}
      >
        {chat.loading && (
          <div className="flex min-h-64 items-center justify-center">
            <Spinner label={t("supportChat.loading")} />
          </div>
        )}
        {!chat.loading && !data && (
          <div className="flex min-h-64 flex-col items-center justify-center gap-4 text-center text-default-500">
            <p>{t("supportChat.loadFailed")}</p>
            <Button
              color="primary"
              variant="flat"
              onPress={() => void chat.retry()}
            >
              {t("supportChat.retry")}
            </Button>
          </div>
        )}
        {data && (
          <>
            {showHistory && (
              <h2 className="mx-auto w-full max-w-4xl pt-5 text-lg font-semibold">
                {t("supportChat.previousChats")}
              </h2>
            )}
            {(showHistory || activeSession) &&
              data.thread.message_pagination.has_more && (
                <div className="text-center">
                  <Button
                    className="text-xs"
                    variant="light"
                    size="sm"
                    isLoading={chat.loadingHistory}
                    onPress={() => void loadOlder()}
                  >
                    {t("supportChat.olderMessages")}
                  </Button>
                </div>
              )}
            {(showHistory || activeSession) && (
              <SupportTimeline
                sessions={
                  showHistory
                    ? previousSessions
                    : activeSession
                      ? [activeSession]
                      : []
                }
                activeId={showHistory ? null : (activeSession?.id ?? null)}
                viewport={viewport}
                onRead={chat.markRead}
              />
            )}
            {activeSession &&
              !showHistory &&
              outgoing
                .filter(
                  (item) =>
                    item.slug === activeSession.slug &&
                    !activeSession.messages.some(
                      (message) =>
                        message.client_message_id ===
                        item.draft.client_message_id,
                    ),
                )
                .map((item) => (
                  <div
                    key={item.draft.client_message_id}
                    className="mb-4 flex justify-end"
                  >
                    <div className="min-w-0 max-w-[85%] sm:max-w-[75%]">
                      <div className="rounded-2xl rounded-ee-md bg-primary/10 px-4 py-2.5 text-foreground dark:bg-primary/15">
                        {item.draft.message && (
                          <p
                            dir="auto"
                            className="whitespace-pre-wrap break-words text-sm leading-6 [overflow-wrap:anywhere]"
                          >
                            {item.draft.message}
                          </p>
                        )}
                        {item.draft.attachments.map((file, index) => (
                          <p
                            key={`${file.name}-${index}`}
                            className="truncate text-xs text-default-500"
                          >
                            {file.name}
                          </p>
                        ))}
                      </div>
                      <div className="mt-1.5 flex items-center justify-end gap-2 px-1 text-xs text-default-500">
                        <span role="status">
                          {t(
                            item.failed
                              ? "supportChat.sendFailed"
                              : "supportChat.sending",
                          )}
                        </span>
                        {item.failed && (
                          <Button
                            type="button"
                            size="sm"
                            variant="light"
                            color="danger"
                            isDisabled={busy}
                            onPress={() => sendActive(item.slug, item.draft)}
                          >
                            {t("supportChat.retry")}
                          </Button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
            {!activeSession && (
              <div ref={startFlow} hidden={showHistory}>
                <SupportStartFlow
                  orders={data.recent_orders}
                  topics={chat.topics}
                  composer={composer}
                  onRetry={() => void chat.retry()}
                  onStart={(topicId, orderId) => {
                    void submit(topicId, orderId);
                  }}
                />
              </div>
            )}
            {showHistory &&
              !activeSession &&
              lastSession?.rating == null &&
              lastSession &&
              (lastSession.resolved_at || lastSession.closed_at) && (
                <SupportRating
                  key={lastSession.slug}
                  busy={busy}
                  onSubmit={async (score, feedback) => {
                    const success = await chat.rate(
                      lastSession.slug,
                      score,
                      feedback,
                    );
                    if (success) setNotice(t("supportChat.ratingSaved"));
                    return success;
                  }}
                />
              )}
          </>
        )}
      </div>
      {!showHistory && activeSession && hasNewMessages && (
        <div className="border-t border-divider p-2 text-center">
          <Button
            className="text-xs"
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => {
              if (viewport.current)
                viewport.current.scrollTop = viewport.current.scrollHeight;
              nearBottom.current = true;
              setIsNearBottom(true);
              setLastSeenMessageId(lastMessageId);
            }}
          >
            {t("supportChat.newMessages")}
          </Button>
        </div>
      )}
      {activeSession && !showHistory && (
        <div className="shrink-0 bg-content1 px-4 py-3 sm:px-6 sm:py-5">
          <div className="w-full">
            {chat.agentTyping && (
              <div
                role="status"
                aria-live="polite"
                className="mb-2 flex items-center gap-2 text-xs text-default-500"
              >
                <span
                  aria-hidden="true"
                  className="flex gap-1 motion-safe:animate-pulse"
                >
                  <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                  <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                  <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                </span>
                {t("supportChat.agentTyping")}
              </div>
            )}
            <SupportComposer {...composer} onSubmit={() => void submit()} />
          </div>
        </div>
      )}
      {dialog?.kind === "callback" && activeSession?.slug === dialog.slug && (
        <SupportCallbackDialog
          phone={data?.thread.customer.phone || ""}
          busy={busy}
          onClose={() => setDialog(null)}
          onSubmit={async (phone, date) => {
            const success = await chat.callback(dialog.slug, phone, date);
            if (success) setNotice(t("supportChat.callbackRequested"));
            return success;
          }}
        />
      )}
      {dialog?.kind === "resolve" && activeSession?.slug === dialog.slug && (
        <SupportResolveDialog
          busy={busy}
          onClose={() => setDialog(null)}
          onSubmit={() => chat.resolve(dialog.slug)}
        />
      )}
    </SupportChatFrame>
  );
}
