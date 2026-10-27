import { describe, expect, it } from "vitest";

import { getPaginationRange } from "@/helpers/pagination";

describe("getPaginationRange", () => {
  it("derives the visible range when the API returns zero metadata", () => {
    expect(
      getPaginationRange({
        from: 0,
        to: 0,
        currentPage: 1,
        perPage: 6,
        itemCount: 6,
        total: 6,
      }),
    ).toEqual({ from: 1, to: 6 });
  });

  it("derives the final page range from the number of rendered items", () => {
    expect(
      getPaginationRange({
        currentPage: 2,
        perPage: 6,
        itemCount: 3,
        total: 9,
      }),
    ).toEqual({ from: 7, to: 9 });
  });

  it("preserves valid API range metadata", () => {
    expect(
      getPaginationRange({
        from: 11,
        to: 15,
        currentPage: 2,
        perPage: 10,
        itemCount: 5,
        total: 15,
      }),
    ).toEqual({ from: 11, to: 15 });
  });

  it("returns a zero range for an empty result", () => {
    expect(
      getPaginationRange({
        currentPage: 1,
        perPage: 6,
        itemCount: 0,
        total: 0,
      }),
    ).toEqual({ from: 0, to: 0 });
  });
});
