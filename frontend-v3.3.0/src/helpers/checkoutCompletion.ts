import type { CartResponse } from "@/types/ApiResponse";

// Page-local presentation only: never persist a completed cart as an active cart.
const listeners = new Set<(cart: CartResponse) => void>();

export const subscribeCheckoutCompletion = (listener: (cart: CartResponse) => void) => {
  listeners.add(listener);
  return () => { listeners.delete(listener); };
};

export const notifyCheckoutCompletion = (cart: CartResponse | null) => {
  if (cart) listeners.forEach(listener => listener(cart));
};
