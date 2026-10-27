import { act, renderHook } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import {
  HeaderScrollProvider,
  useHeaderScroll,
} from "@/contexts/HeaderScrollContext";

describe("HeaderScrollContext", () => {
  it("shares mobile header visibility with sibling consumers", () => {
    const { result } = renderHook(() => useHeaderScroll(), {
      wrapper: HeaderScrollProvider,
    });

    expect(result.current.isMobileHeaderExpanded).toBe(true);
    expect(result.current.isMobileViewport).toBe(false);

    act(() => {
      result.current.setIsMobileViewport(true);
      result.current.setIsMobileHeaderExpanded(false);
    });

    expect(result.current.isMobileViewport).toBe(true);
    expect(result.current.isMobileHeaderExpanded).toBe(false);
  });
});
