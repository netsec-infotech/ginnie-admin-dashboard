import { describe, expect, it } from "vitest";

import type {
  HomeLayoutPlayImageRow,
  HomeLayoutSection,
} from "@/types/ApiResponse";
import {
  getAutoPlayImageSpan,
  getPlayImageGridSpan,
  getPlayImageSlidesPerView,
  normalizePlayImageGap,
  resolvePlayImageRow,
} from "@/views/homePage/playImage/playImageConfig";

describe("Play Image configuration", () => {
  it.each([
    [1, 12],
    [2, 6],
    [3, 4],
    [4, 3],
    [5, 2],
    [6, 2],
    [7, 1],
    [12, 1],
    [13, 1],
  ])("allocates %i auto items as %i/12 spans", (items, span) => {
    expect(getAutoPlayImageSpan(items)).toBe(span);
  });

  it("honors explicit spans and clamps malformed API values", () => {
    expect(getPlayImageGridSpan("8", 3)).toBe(8);
    expect(getPlayImageGridSpan(20, 3)).toBe(12);
    expect(getPlayImageGridSpan("4", 1)).toBe(12);
    expect(getPlayImageGridSpan("0", 3)).toBe(4);
    expect(getPlayImageGridSpan("auto", 3)).toBe(4);
    expect(getPlayImageGridSpan("invalid", 3)).toBe(4);
  });

  it("preserves fractional scroll counts and safe gap fallbacks", () => {
    expect(getPlayImageSlidesPerView("2.5", 2)).toBe(2.5);
    expect(getPlayImageSlidesPerView("1.25", 1)).toBe(1.25);
    expect(getPlayImageSlidesPerView("auto", 2)).toBe(2);
    expect(normalizePlayImageGap("12")).toBe(12);
    expect(normalizePlayImageGap("-100")).toBe(0);
  });

  it("merges section, configured-row, and content-row values by row_index", () => {
    const section = {
      id: 657,
      type: "play_image",
      title: null,
      style: "grid",
      config: {
        border_radius: "10",
        grid_gap: "24",
        rows: [
          { row_index: "99", grid_gap: "99" },
          {
            row_index: "7",
            style: "scroll",
            grid_gap: "16",
            items_to_show: "2.5",
          },
        ],
      },
      background_image: null,
      hero_media: null,
      card_background_image: null,
      content: {},
    } satisfies HomeLayoutSection;
    const row = {
      sourceIndex: 0,
      row_index: "7",
      title: null,
      style: null,
      config: { grid_gap: "8" },
      items: [],
    } satisfies HomeLayoutPlayImageRow;

    expect(resolvePlayImageRow(section, row)).toMatchObject({
      style: "scroll",
      config: {
        border_radius: "10",
        grid_gap: "8",
        items_to_show: "2.5",
      },
    });
    expect(resolvePlayImageRow(section, { ...row, style: "grid" }).style).toBe(
      "grid",
    );
  });
});
