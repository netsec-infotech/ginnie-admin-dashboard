import { describe, expect, it } from "vitest";
import { getUnavailableAddonSelections, isAddonAvailable } from "@/helpers/addonAvailability";
import type { AddonGroup, AddonGroupItem } from "@/types/ApiResponse";

const item: AddonGroupItem = {
  id: 1, uuid: "cheese", title: "Extra cheese", slug: "cheese",
  indicator: "veg", price: 30, cost: 10, stock: 2, is_available: true,
};
const group: AddonGroup = {
  id: 1, uuid: "extras", title: "Extras", slug: "extras",
  selection_type: "multiple", is_required: true, sort_order: 0, items: [item],
};

describe("add-on availability", () => {
  it("rejects sold-out stock even when the availability flag is true", () => {
    expect(isAddonAvailable({ ...item, stock: 0 }, 1)).toBe(false);
    expect(isAddonAvailable({ ...item, is_available: false }, 1)).toBe(false);
  });

  it("rechecks selected add-ons when quantity increases and recovers when reduced", () => {
    expect(getUnavailableAddonSelections([group], { 1: [1] }, 3)).toEqual(["Extra cheese"]);
    expect(getUnavailableAddonSelections([group], { 1: [1] }, 2)).toEqual([]);
  });

  it("blocks an impossible required group but allows skipping optional add-ons", () => {
    expect(getUnavailableAddonSelections([group], {}, 3)).toEqual(["Extras"]);
    expect(getUnavailableAddonSelections([{ ...group, is_required: false }], {}, 3)).toEqual([]);
  });

  it("allows choosing an in-stock alternative", () => {
    const alternative = { ...item, id: 2, stock: 10 };
    expect(getUnavailableAddonSelections([{ ...group, items: [item, alternative] }], { 1: [2] }, 3)).toEqual([]);
  });
});
