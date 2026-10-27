import { useLayoutEffect, useRef, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";

export default function SupportChatFrame({
  expanded,
  onExpandedChange,
  label,
  children,
}: {
  expanded: boolean;
  onExpandedChange: (expanded: boolean) => void;
  label: string;
  children: ReactNode;
}) {
  const placeholder = useRef<HTMLDivElement>(null);
  const frame = useRef<HTMLDivElement>(null);
  // SupportChatView mounts after hydration. Keep one portal target so moving
  // between the account layout and body never remounts the chat or its draft.
  const [host] = useState(() =>
    typeof document === "undefined" ? null : document.createElement("div"),
  );

  useLayoutEffect(() => {
    if (!host) return;
    host.style.setProperty("height", "100%");
    placeholder.current?.append(host);
    return () => host.remove();
  }, [host]);

  useLayoutEffect(() => {
    if (!expanded || !host) return;
    const slot = placeholder.current;
    const previousFocus = document.activeElement;
    const app = document.getElementById("__next");
    const previousInert = app?.inert ?? false;
    const previousOverflow = document.body.style.overflow;
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;
    document.body.append(host);
    if (app) app.inert = true;
    document.body.style.overflow = "hidden";
    host.style.setProperty("position", "fixed");
    // Body-level placement covers site navigation. Later HeroUI portals at
    // the same layer can still display callback, resolve and image dialogs.
    host.style.setProperty("z-index", "50");
    const visual = window.visualViewport;
    const resize = () => {
      host.style.setProperty("top", `${visual?.offsetTop ?? 0}px`);
      host.style.setProperty("left", `${visual?.offsetLeft ?? 0}px`);
      host.style.setProperty(
        "width",
        `${visual?.width ?? window.innerWidth}px`,
      );
      host.style.setProperty(
        "height",
        `${visual?.height ?? window.innerHeight}px`,
      );
    };
    resize();
    visual?.addEventListener("resize", resize);
    visual?.addEventListener("scroll", resize);
    window.addEventListener("resize", resize);
    const desktop = window.matchMedia("(min-width: 768px)");
    const collapseOnDesktop = () => {
      if (desktop.matches) onExpandedChange(false);
    };
    desktop.addEventListener("change", collapseOnDesktop);
    frame.current
      ?.querySelector<HTMLButtonElement>("[data-chat-expand]")
      ?.focus({ preventScroll: true });
    const keydown = (event: KeyboardEvent) => {
      const root = frame.current;
      // Nested HeroUI dialogs own their keyboard handling and focus scope.
      if (
        !root ||
        !root.contains(event.target as Node) ||
        event.defaultPrevented
      )
        return;
      if (event.key === "Escape") {
        event.preventDefault();
        onExpandedChange(false);
      } else if (event.key === "Tab") {
        const items = Array.from(
          root.querySelectorAll<HTMLElement>(
            'button:not(:disabled), input:not(:disabled):not([type="hidden"]), textarea:not(:disabled), a[href], [tabindex="0"]',
          ),
        ).filter((item) => item.getClientRects().length > 0);
        const first = items[0];
        const last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first?.focus();
        }
      }
    };
    document.addEventListener("keydown", keydown);
    return () => {
      visual?.removeEventListener("resize", resize);
      visual?.removeEventListener("scroll", resize);
      window.removeEventListener("resize", resize);
      desktop.removeEventListener("change", collapseOnDesktop);
      document.removeEventListener("keydown", keydown);
      if (app) app.inert = previousInert;
      document.body.style.overflow = previousOverflow;
      host.removeAttribute("style");
      host.style.setProperty("height", "100%");
      slot?.append(host);
      window.scrollTo(scrollX, scrollY);
      if (previousFocus instanceof HTMLElement && previousFocus.isConnected) {
        previousFocus.focus({ preventScroll: true });
      }
    };
  }, [expanded, host, onExpandedChange]);

  return (
    <div
      ref={placeholder}
      className="h-[min(80dvh,850px)] min-h-[480px] w-full min-w-0"
    >
      {host &&
        createPortal(
          <div
            ref={frame}
            role={expanded ? "dialog" : undefined}
            aria-modal={expanded || undefined}
            aria-label={expanded ? label : undefined}
            className={`flex h-full min-h-0 w-full min-w-0 flex-col overflow-hidden bg-content1 ${expanded ? "pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] ps-[env(safe-area-inset-left)] pe-[env(safe-area-inset-right)]" : "rounded-3xl border border-divider shadow-sm"}`}
          >
            {children}
          </div>,
          host,
        )}
    </div>
  );
}
