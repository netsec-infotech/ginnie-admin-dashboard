type PendingConfirmation = { key: string };
let pending: PendingConfirmation | null = null;
let answer: ((confirmed: boolean) => void) | null = null;
let result: Promise<boolean> | null = null;
const listeners = new Set<() => void>();

export const cartLocationConfirmationStore = {
  request(key: string): Promise<boolean> {
    if (pending?.key === key && result) return result;
    // A new destination supersedes any unanswered request.
    answer?.(false);
    pending = { key };
    result = new Promise<boolean>((resolve) => { answer = resolve; });
    listeners.forEach((listener) => listener());
    return result;
  },
  respond(confirmed: boolean, key = pending?.key) {
    if (key !== pending?.key) return;
    const resolve = answer;
    pending = null;
    answer = null;
    result = null;
    listeners.forEach((listener) => listener());
    resolve?.(confirmed);
  },
  getSnapshot: () => pending,
  getServerSnapshot: () => null,
  subscribe(listener: () => void) {
    listeners.add(listener);
    return () => { listeners.delete(listener); };
  },
};
