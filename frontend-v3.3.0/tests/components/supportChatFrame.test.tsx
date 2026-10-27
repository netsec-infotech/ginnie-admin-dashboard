import { useState } from "react";
import {
  act,
  cleanup,
  fireEvent,
  render,
  screen,
} from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import SupportChatFrame from "@/components/Support/SupportChatFrame";

let visual: EventTarget & {
  width: number;
  height: number;
  offsetTop: number;
  offsetLeft: number;
};
let desktop: EventTarget & { matches: boolean };
let app: HTMLDivElement;

function Flow() {
  const [expanded, setExpanded] = useState(false);
  return (
    <SupportChatFrame
      expanded={expanded}
      onExpandedChange={setExpanded}
      label="Support chat"
    >
      <button data-chat-expand onClick={() => setExpanded(!expanded)}>
        {expanded ? "Exit full view" : "Expand chat"}
      </button>
      <input aria-label="Draft" defaultValue="Draft to preserve" />
      <input aria-label="Attachment" type="file" />
    </SupportChatFrame>
  );
}

beforeEach(() => {
  visual = Object.assign(new EventTarget(), {
    width: 390,
    height: 844,
    offsetTop: 0,
    offsetLeft: 0,
  });
  desktop = Object.assign(new EventTarget(), { matches: false });
  vi.stubGlobal("visualViewport", visual);
  vi.stubGlobal("matchMedia", () => desktop);
  vi.stubGlobal("scrollTo", vi.fn());
  app = document.createElement("div");
  app.id = "__next";
  document.body.append(app);
});
afterEach(() => {
  cleanup();
  app.remove();
  vi.unstubAllGlobals();
});

describe("support fullscreen frame", () => {
  it("preserves the same draft and file input while expanding and collapsing", () => {
    render(<Flow />, { container: app });
    const draft = screen.getByLabelText("Draft");
    const attachment = screen.getByLabelText("Attachment");
    const file = new File(["receipt"], "receipt.txt");
    fireEvent.change(draft, { target: { value: "Unsent message" } });
    fireEvent.change(attachment, { target: { files: [file] } });
    fireEvent.click(screen.getByRole("button", { name: "Expand chat" }));
    expect(screen.getByRole("dialog").parentElement?.parentElement).toBe(
      document.body,
    );
    expect(app.inert).toBe(true);
    expect(document.body.style.overflow).toBe("hidden");
    expect(screen.getByLabelText("Draft")).toBe(draft);
    expect(draft).toHaveValue("Unsent message");
    fireEvent.click(screen.getByRole("button", { name: "Exit full view" }));
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(app.contains(draft)).toBe(true);
    expect(screen.getByLabelText("Attachment")).toBe(attachment);
    expect((attachment as HTMLInputElement).files?.[0]).toBe(file);
    expect(app.inert).toBe(false);
    expect(document.body.style.overflow).toBe("");
  });
  it("follows the visible viewport when the keyboard shrinks and pans it, and restores on unmount", () => {
    const { unmount } = render(<Flow />, { container: app });
    fireEvent.click(screen.getByRole("button", { name: "Expand chat" }));
    const host = screen.getByRole("dialog").parentElement;
    act(() => {
      visual.height = 410;
      visual.offsetTop = 35;
      visual.dispatchEvent(new Event("resize"));
    });
    expect(host?.style.height).toBe("410px");
    expect(host?.style.top).toBe("35px");
    unmount();
    expect(host?.isConnected).toBe(false);
    expect(app.inert).toBe(false);
    expect(document.body.style.overflow).toBe("");
    expect(vi.mocked(window.scrollTo)).toHaveBeenCalled();
  });
  it("exits with Escape or a desktop resize without intercepting an external dialog", () => {
    render(<Flow />, { container: app });
    fireEvent.click(screen.getByRole("button", { name: "Expand chat" }));
    fireEvent.keyDown(document.body, { key: "Escape" });
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    fireEvent.keyDown(screen.getByLabelText("Draft"), { key: "Escape" });
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Expand chat" }));
    act(() => {
      desktop.matches = true;
      desktop.dispatchEvent(new Event("change"));
    });
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});
