import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen } from "@testing-library/react";

import LoadMoreSentinel from "@/components/Functional/LoadMoreSentinel";

/** Minimal IntersectionObserver stand-in — jsdom ships none. */
class MockIntersectionObserver {
  static instances: MockIntersectionObserver[] = [];

  targets: Element[] = [];
  disconnected = false;

  constructor(
    private callback: IntersectionObserverCallback,
    readonly options?: IntersectionObserverInit,
  ) {
    MockIntersectionObserver.instances.push(this);
  }

  observe(target: Element) {
    this.targets.push(target);
  }

  unobserve(target: Element) {
    this.targets = this.targets.filter((t) => t !== target);
  }

  disconnect() {
    this.targets = [];
    this.disconnected = true;
  }

  takeRecords() {
    return [];
  }

  /** Fire the callback as the browser would when the marker scrolls in/out. */
  fire(isIntersecting = true) {
    this.callback(
      this.targets.map((target) => ({ target, isIntersecting })) as never,
      this as never,
    );
  }
}

/** The observer still watching something — the one a real scroll would fire. */
const liveObserver = () =>
  [...MockIntersectionObserver.instances]
    .reverse()
    .find((o) => o.targets.length > 0);

beforeEach(() => {
  MockIntersectionObserver.instances = [];
  vi.stubGlobal("IntersectionObserver", MockIntersectionObserver);
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("LoadMoreSentinel", () => {
  it("asks for the next page when it scrolls into view", () => {
    const onVisible = vi.fn();
    render(<LoadMoreSentinel onVisible={onVisible} />);

    liveObserver()?.fire(true);

    expect(onVisible).toHaveBeenCalledTimes(1);
  });

  it("stays quiet while it is out of view", () => {
    const onVisible = vi.fn();
    render(<LoadMoreSentinel onVisible={onVisible} />);

    liveObserver()?.fire(false);

    expect(onVisible).not.toHaveBeenCalled();
  });

  it("observes nothing once the feed is exhausted", () => {
    const onVisible = vi.fn();
    render(<LoadMoreSentinel onVisible={onVisible} disabled />);

    expect(liveObserver()).toBeUndefined();
    expect(onVisible).not.toHaveBeenCalled();
  });

  it("stops observing when the feed runs out", () => {
    const onVisible = vi.fn();
    const { rerender } = render(<LoadMoreSentinel onVisible={onVisible} />);
    const observer = liveObserver();

    rerender(<LoadMoreSentinel onVisible={onVisible} disabled />);

    expect(observer?.disconnected).toBe(true);
  });

  it("re-observes after being re-mounted further down the list", () => {
    // Appending a page moves the marker between different list items, which
    // re-mounts it. Watching the old, detached node would stop the feed dead
    // after a single page.
    const onVisible = vi.fn();
    const { rerender } = render(
      <div key="page-1">
        <LoadMoreSentinel onVisible={onVisible} />
      </div>,
    );

    rerender(
      <div key="page-2">
        <LoadMoreSentinel onVisible={onVisible} />
      </div>,
    );

    const observer = liveObserver();
    expect(observer?.targets[0]).toBe(screen.getByTestId("load-more-sentinel"));

    observer?.fire(true);
    expect(onVisible).toHaveBeenCalledTimes(1);
  });

  it("does not rebuild the observer when the callback identity changes", () => {
    const { rerender } = render(<LoadMoreSentinel onVisible={vi.fn()} />);
    expect(MockIntersectionObserver.instances).toHaveLength(1);

    // useInfiniteData hands back a new loadMore on every render.
    rerender(<LoadMoreSentinel onVisible={vi.fn()} />);
    expect(MockIntersectionObserver.instances).toHaveLength(1);
  });

  it("calls the newest callback, not the one bound when it mounted", () => {
    const stale = vi.fn();
    const fresh = vi.fn();

    const { rerender } = render(<LoadMoreSentinel onVisible={stale} />);
    rerender(<LoadMoreSentinel onVisible={fresh} />);

    liveObserver()?.fire(true);

    expect(fresh).toHaveBeenCalledTimes(1);
    expect(stale).not.toHaveBeenCalled();
  });

  it("renders harmlessly where IntersectionObserver is missing", () => {
    vi.stubGlobal("IntersectionObserver", undefined);
    const onVisible = vi.fn();

    expect(() => render(<LoadMoreSentinel onVisible={onVisible} />)).not.toThrow();
    expect(screen.getByTestId("load-more-sentinel")).toBeInTheDocument();
  });

  it("passes rootMargin through so callers can widen the trigger", () => {
    render(<LoadMoreSentinel onVisible={vi.fn()} rootMargin="300px" />);
    expect(liveObserver()?.options?.rootMargin).toBe("300px");
  });
});
