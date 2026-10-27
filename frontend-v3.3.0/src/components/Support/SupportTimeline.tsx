import { useEffect, type RefObject } from "react";
import { CheckCircle2, Headset, Info, Phone } from "lucide-react";
import { useTranslation } from "react-i18next";
import type { SupportSession } from "@/types/support";
import SupportAttachment from "./SupportAttachment";

const systemNoticeClass =
  "mx-auto flex w-fit max-w-[90%] flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-xl bg-default-100/70 px-3 py-2 text-center text-xs leading-5 text-default-500 [overflow-wrap:anywhere]";

export default function SupportTimeline({
  sessions,
  activeId,
  viewport,
  onRead,
}: {
  sessions: SupportSession[];
  activeId: number | null;
  viewport: RefObject<HTMLDivElement | null>;
  onRead: (slug: string, id: number) => void;
}) {
  const { t, i18n } = useTranslation();
  const formatDate = (value: string, timeOnly = false) => {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? ""
      : new Intl.DateTimeFormat(i18n.language, {
          dateStyle: timeOnly ? undefined : "medium",
          timeStyle: "short",
        }).format(date);
  };
  useEffect(() => {
    const root = viewport.current;
    if (!root || !globalThis.IntersectionObserver) return;
    const observer = new IntersectionObserver(
      (entries) => {
        if (document.hidden) return;
        const visible = entries.filter((entry) => entry.isIntersecting);
        const latest = visible.reduce(
          (max, entry) =>
            Math.max(max, Number((entry.target as HTMLElement).dataset.readId)),
          0,
        );
        const active = sessions.find((session) => session.id === activeId);
        if (latest && active) onRead(active.slug, latest);
      },
      { root, threshold: 0.5 },
    );
    const observe = () => {
      root.querySelectorAll("[data-read-id]").forEach((node) => {
        observer.unobserve(node);
        observer.observe(node);
      });
    };
    observe();
    document.addEventListener("visibilitychange", observe);
    return () => {
      observer.disconnect();
      document.removeEventListener("visibilitychange", observe);
    };
  }, [sessions, activeId, viewport, onRead]);

  return (
    <div className="w-full py-4">
      {sessions.map((session) => (
        <section key={session.id} aria-label={session.slug} className="mb-6">
          {session.id !== activeId && (
            <div className="mb-6 text-center text-xs text-default-500">
              <span className="max-w-[75%] break-words">
                {session.slug} · {session.topic?.title || session.subject}
              </span>
            </div>
          )}
          {session.messages.map((message) => {
            const system = message.sender_role === "system";
            const mine = message.sender_role === "user";
            const closed = message.type === "closure";
            return (
              <div
                key={message.id}
                data-read-id={
                  !mine && session.id === activeId ? message.id : undefined
                }
                className={`mb-4 flex ${system ? "justify-center" : mine ? "justify-end" : "justify-start"}`}
              >
                {system ? (
                  <div className={systemNoticeClass}>
                    {closed ? (
                      <CheckCircle2 size={14} className="shrink-0" />
                    ) : (
                      <Info size={14} className="shrink-0" />
                    )}
                    <span
                      dir="auto"
                      className="min-w-0 whitespace-pre-wrap break-words"
                    >
                      {message.text}
                    </span>
                    <time dateTime={message.created_at} className="opacity-70">
                      {formatDate(message.created_at)}
                    </time>
                    {message.attachments.map((attachment) => (
                      <div
                        key={attachment.id}
                        className="w-full min-w-0 text-start"
                      >
                        <SupportAttachment attachment={attachment} />
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="min-w-0 max-w-[85%] sm:max-w-[75%]">
                    {!mine && (
                      <p className="mb-1.5 px-1 text-xs text-default-500">
                        {message.sender_name || t("supportChat.agent")}
                      </p>
                    )}
                    <div
                      className={`rounded-2xl px-4 py-2.5 ${mine ? "rounded-ee-md bg-primary/10 text-foreground dark:bg-primary/15" : "rounded-es-md bg-default-100 text-foreground"}`}
                    >
                      <p
                        dir="auto"
                        className="whitespace-pre-wrap break-words text-sm leading-6 [overflow-wrap:anywhere]"
                      >
                        {message.text}
                      </p>
                      {message.attachments.map((attachment) => (
                        <SupportAttachment
                          key={attachment.id}
                          attachment={attachment}
                        />
                      ))}
                    </div>
                    <time
                      dateTime={message.created_at}
                      title={formatDate(message.created_at)}
                      className={`mt-1.5 block px-1 text-[10px] text-default-500 ${mine ? "text-end" : "text-start"}`}
                    >
                      {formatDate(message.created_at, true)}
                    </time>
                  </div>
                )}
              </div>
            );
          })}
          {session.calls.map((call) => (
            <div key={call.id} className={`${systemNoticeClass} my-3`}>
              <Phone size={14} className="shrink-0" />
              <span className="min-w-0">
                {t("supportChat.callbackStatus", { status: call.status })}
                {call.scheduled_at ? ` · ${formatDate(call.scheduled_at)}` : ""}
              </span>
            </div>
          ))}
          {session.id === activeId && !session.assignee && (
            <div role="status" className={`${systemNoticeClass} my-5`}>
              <Headset size={14} className="shrink-0" />
              <span className="min-w-0">{t("supportChat.waiting")}</span>
            </div>
          )}
        </section>
      ))}
    </div>
  );
}
