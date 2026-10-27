import { act, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useHorizontalDragScroll } from "@/components/Media/hooks/useHorizontalDragScroll";

const Rail = () => {
  const { dragHandlers } = useHorizontalDragScroll({ momentum: true });
  return <div data-testid="rail" {...dragHandlers}><button>Open reel</button></div>;
};

describe("horizontal drag momentum", () => {
  let now: number;
  let frames: Map<number, FrameRequestCallback>;
  let frameId: number;

  beforeEach(() => {
    now = 0;
    frameId = 0;
    frames = new Map();
    vi.spyOn(performance, "now").mockImplementation(() => now);
    vi.spyOn(window, "requestAnimationFrame").mockImplementation((callback) => {
      frames.set(++frameId, callback);
      return frameId;
    });
    vi.spyOn(window, "cancelAnimationFrame").mockImplementation((id) => {
      frames.delete(id);
    });
    vi.stubGlobal("matchMedia", vi.fn(() => ({ matches: false })));
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  const nextFrame = () => {
    now += 16;
    const pending = [...frames.values()];
    frames.clear();
    act(() => pending.forEach((callback) => callback(now)));
  };

  const drag = (endX = 100, finish = "pointerUp") => {
    const rail = screen.getByTestId("rail");
    rail.scrollLeft = 200;
    fireEvent.pointerDown(rail, { button: 0, pointerId: 1, pointerType: "mouse", clientX: 150 });
    now += 20;
    fireEvent.pointerMove(rail, { pointerId: 1, clientX: endX });
    if (finish === "pointerCancel") fireEvent.pointerCancel(rail, { pointerId: 1 });
    else fireEvent.pointerUp(rail, { button: 0, pointerId: 1, clientX: endX });
    return rail;
  };

  it.each([100, 200])("glides in the drag direction and slows to a stop (%s)", (endX) => {
    render(<Rail />);
    const rail = drag(endX);
    const releasedAt = rail.scrollLeft;
    nextFrame();
    const firstStep = rail.scrollLeft - releasedAt;
    expect(Math.sign(firstStep)).toBe(endX < 150 ? 1 : -1);
    const afterFirst = rail.scrollLeft;
    nextFrame();
    expect(Math.abs(rail.scrollLeft - afterFirst)).toBeLessThan(Math.abs(firstStep));
    for (let i = 0; i < 100; i++) nextFrame();
    expect(frames.size).toBe(0);
  });

  it("stops a glide immediately when another gesture begins", () => {
    render(<Rail />);
    const rail = drag();
    nextFrame();
    fireEvent.pointerDown(rail, { button: 0, pointerId: 2, pointerType: "mouse", clientX: 100 });
    const stoppedAt = rail.scrollLeft;
    nextFrame();
    expect(rail.scrollLeft).toBe(stoppedAt);
    expect(frames.size).toBe(0);
  });

  it("does not coast after a cancelled drag or a pause before release", () => {
    render(<Rail />);
    const rail = drag(100, "pointerCancel");
    expect(frames.size).toBe(0);
    fireEvent.pointerDown(rail, { button: 0, pointerId: 2, clientX: 150 });
    now += 20;
    fireEvent.pointerMove(rail, { pointerId: 2, clientX: 100 });
    now += 100;
    fireEvent.pointerUp(rail, { button: 0, pointerId: 2 });
    expect(frames.size).toBe(0);
  });

  it("respects reduced motion and leaves touch scrolling to the browser", () => {
    vi.stubGlobal("matchMedia", vi.fn(() => ({ matches: true })));
    render(<Rail />);
    const rail = drag();
    expect(frames.size).toBe(0);
    const beforeTouch = rail.scrollLeft;
    fireEvent.pointerDown(rail, { button: 0, pointerId: 2, pointerType: "touch", clientX: 150 });
    fireEvent.pointerMove(rail, { pointerId: 2, pointerType: "touch", clientX: 50 });
    fireEvent.pointerUp(rail, { button: 0, pointerId: 2, pointerType: "touch" });
    expect(rail.scrollLeft).toBe(beforeTouch);
    expect(frames.size).toBe(0);
  });

  it("cancels the animation on unmount", () => {
    const { unmount } = render(<Rail />);
    drag();
    expect(frames.size).toBe(1);
    unmount();
    expect(frames.size).toBe(0);
  });
});
