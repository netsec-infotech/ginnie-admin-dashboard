import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import useScrollImageCollapse from "@/hooks/useScrollImageCollapse";

const originalWindowProperties = {
  cancelAnimationFrame: Object.getOwnPropertyDescriptor(
    window,
    "cancelAnimationFrame",
  ),
  requestAnimationFrame: Object.getOwnPropertyDescriptor(
    window,
    "requestAnimationFrame",
  ),
  scrollY: Object.getOwnPropertyDescriptor(window, "scrollY"),
};

const restoreWindowProperty = (
  property: keyof typeof originalWindowProperties,
) => {
  const descriptor = originalWindowProperties[property];
  if (descriptor) {
    Object.defineProperty(window, property, descriptor);
  } else {
    Reflect.deleteProperty(window, property);
  }
};

describe("useScrollImageCollapse", () => {
  let pendingFrame: FrameRequestCallback | null;

  beforeEach(() => {
    pendingFrame = null;
    Object.defineProperty(window, "scrollY", {
      configurable: true,
      value: 0,
      writable: true,
    });
    Object.defineProperty(window, "requestAnimationFrame", {
      configurable: true,
      value: vi.fn((callback: FrameRequestCallback) => {
        pendingFrame = callback;
        return 1;
      }),
    });
    Object.defineProperty(window, "cancelAnimationFrame", {
      configurable: true,
      value: vi.fn(),
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
    restoreWindowProperty("cancelAnimationFrame");
    restoreWindowProperty("requestAnimationFrame");
    restoreWindowProperty("scrollY");
  });

  const scrollTo = (scrollY: number) => {
    act(() => {
      window.scrollY = scrollY;
      window.dispatchEvent(new Event("scroll"));
      const frame = pendingFrame;
      pendingFrame = null;
      frame?.(performance.now());
    });
  };

  it("uses separate collapse and expand thresholds", () => {
    const { result } = renderHook(() => useScrollImageCollapse());

    expect(result.current).toBe(false);

    scrollTo(111);
    expect(result.current).toBe(false);

    scrollTo(112);
    expect(result.current).toBe(true);

    scrollTo(33);
    expect(result.current).toBe(true);

    scrollTo(32);
    expect(result.current).toBe(false);
  });

  it("coalesces repeated scroll events into one animation frame", () => {
    renderHook(() => useScrollImageCollapse());

    act(() => {
      window.dispatchEvent(new Event("scroll"));
      window.dispatchEvent(new Event("scroll"));
    });

    expect(window.requestAnimationFrame).toHaveBeenCalledOnce();
  });
});
