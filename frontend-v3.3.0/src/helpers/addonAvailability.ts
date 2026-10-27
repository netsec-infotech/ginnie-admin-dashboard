import type { AddonGroup, AddonGroupItem } from "@/types/ApiResponse";

export const isAddonAvailable = (item: AddonGroupItem, quantity: number) =>
  item.is_available && Number(item.stock) >= quantity;

export const getUnavailableAddonSelections = (
  groups: AddonGroup[],
  selections: Record<number, number[]>,
  quantity: number,
): string[] =>
  groups.flatMap((group) => {
    const selected = selections[group.id] || [];
    const unavailable = group.items.filter(
      (item) => selected.includes(item.id) && !isAddonAvailable(item, quantity),
    );
    if (unavailable.length) return unavailable.map((item) => item.title);
    if (group.is_required && !group.items.some((item) => isAddonAvailable(item, quantity))) {
      return [group.title];
    }
    return [];
  });
