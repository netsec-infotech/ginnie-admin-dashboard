/**
 * Holds the "these items were removed" payload for `RemovedItemsModal`.
 *
 * A tiny pub/sub in the shape of `maintenanceStore`, rather than redux, because
 * this data must OUTLIVE the cart state that produced it. The modal used to
 * read `cartData.removed_items` directly, which broke in two ways:
 *
 *  - the post-removal refetch replaces `cartData`, and
 *  - a removal that empties the cart dispatches `clearCart()`, nulling it,
 *
 * so the modal blanked to "No removed items" a frame after opening. Handing the
 * list over explicitly means the notice survives whatever happens to the cart.
 */
import type { RemovedItem } from "@/components/Modals/RemovedItemsModal";

type Listener = () => void;

let removedItems: RemovedItem[] = [];
const listeners = new Set<Listener>();

const emit = () => listeners.forEach((listener) => listener());

export const removedItemsStore = {
  /** Show the notice for `items`. Ignored when there is nothing to report. */
  show(items: RemovedItem[]) {
    if (!items?.length) return;
    removedItems = items;
    emit();
  },

  /** Dismiss the notice. */
  clear() {
    if (!removedItems.length) return;
    removedItems = [];
    emit();
  },

  getSnapshot: () => removedItems,

  /** Stable empty array — `useSyncExternalStore` requires a cached SSR value. */
  getServerSnapshot: (): RemovedItem[] => EMPTY,

  subscribe(listener: Listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
};

const EMPTY: RemovedItem[] = [];
