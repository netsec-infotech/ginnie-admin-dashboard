import { afterEach, describe, expect, it, vi } from "vitest";
import {
  AUTH_SESSION_SYNC_KEY,
  announceAuthSessionChange,
  subscribeToAuthSessionChanges,
} from "@/lib/cookies";

afterEach(() => {
  localStorage.clear();
  vi.restoreAllMocks();
});

describe("cross-tab auth session synchronization", () => {
  it("notifies only for the dedicated auth-session storage key", () => {
    const listener = vi.fn();
    const unsubscribe = subscribeToAuthSessionChanges(listener);

    window.dispatchEvent(new StorageEvent("storage", { key: "unrelated" }));
    window.dispatchEvent(
      new StorageEvent("storage", {
        key: AUTH_SESSION_SYNC_KEY,
        newValue: "new-session",
      }),
    );

    expect(listener).toHaveBeenCalledOnce();
    unsubscribe();
    window.dispatchEvent(
      new StorageEvent("storage", {
        key: AUTH_SESSION_SYNC_KEY,
        newValue: "another-session",
      }),
    );
    expect(listener).toHaveBeenCalledOnce();
  });

  it("announces a change without copying credentials into storage", () => {
    const setItem = vi.spyOn(Storage.prototype, "setItem");

    announceAuthSessionChange();

    expect(setItem).toHaveBeenCalledOnce();
    expect(setItem.mock.calls[0][0]).toBe(AUTH_SESSION_SYNC_KEY);
    expect(setItem.mock.calls[0][1]).not.toContain("Bearer");
  });
});
