import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  acquirePaymentAttempt,
  clearPaymentReturn,
  completePaymentAttempt,
  focusPaymentAttempt,
  hasActivePaymentAttempt,
  hasCompetingPaymentAttempt,
  markPaymentAttemptPaidPending,
  paymentAttemptScope,
  paymentReturnState,
  paymentTransactionReturnState,
  readPaymentReturn,
  releasePaymentAttempt,
  recoverAbandonedPaymentAttempt,
  preservePaymentAttemptForRedirect,
  rememberPaymentReturn,
} from "@/helpers/paymentReturn";

beforeEach(() => {
  sessionStorage.clear();
  localStorage.clear();
  window.name = "";
  vi.restoreAllMocks();
});

describe("abandoned collecting payment recovery", () => {
  const scope = "order:7:cart-42";

  it("preserves hosted checkout when navigation releases the document's browser locks", async () => {
    const lease = await acquirePaymentAttempt(scope);
    const record = JSON.parse(localStorage.getItem(lease!.storageKey)!);
    localStorage.setItem(lease!.storageKey, JSON.stringify({ ...record, ownerLock: true }));
    preservePaymentAttemptForRedirect(lease!);
    const descriptor = Object.getOwnPropertyDescriptor(navigator, "locks");
    Object.defineProperty(navigator, "locks", { configurable: true, value: {
      query: vi.fn().mockResolvedValue({ held: [], pending: [] }),
    } });
    try {
      await recoverAbandonedPaymentAttempt(scope);
      expect(hasActivePaymentAttempt(scope)).toBe(true);
    } finally {
      if (descriptor) Object.defineProperty(navigator, "locks", descriptor);
      else Reflect.deleteProperty(navigator, "locks");
    }
    releasePaymentAttempt(lease!);
  });

  it("holds browser ownership until an unpaid attempt is released", async () => {
    const held = new Set<string>();
    const descriptor = Object.getOwnPropertyDescriptor(navigator, "locks");
    Object.defineProperty(navigator, "locks", {
      configurable: true,
      value: {
        query: async () => ({ held: [...held].map((name) => ({ name })) }),
        request: async (
          name: string,
          _options: unknown,
          callback: (lock: { name: string }) => Promise<unknown>,
        ) => {
          held.add(name);
          try {
            return await callback({ name });
          } finally {
            held.delete(name);
          }
        },
      },
    });
    try {
      const lease = await acquirePaymentAttempt(scope);
      expect(lease).not.toBeNull();
      expect(held.has(`hyperlocal:payment-attempt:owner:${lease!.owner}`)).toBe(
        true,
      );
      await recoverAbandonedPaymentAttempt(scope);
      expect(hasActivePaymentAttempt(scope)).toBe(true);
      expect(await acquirePaymentAttempt(scope)).toBeNull();
      releasePaymentAttempt(lease!);
      await Promise.resolve();
      await Promise.resolve();
      expect(held.size).toBe(0);
      expect(hasActivePaymentAttempt(scope)).toBe(false);
    } finally {
      if (descriptor) Object.defineProperty(navigator, "locks", descriptor);
      else Reflect.deleteProperty(navigator, "locks");
    }
  });

  it("releases an orphaned collecting record before starting another payment", async () => {
    const first = await acquirePaymentAttempt(scope);
    expect(first).not.toBeNull();
    const record = JSON.parse(localStorage.getItem(first!.storageKey)!);
    localStorage.setItem(
      first!.storageKey,
      JSON.stringify({ ...record, ownerLock: true }),
    );
    const query = vi.fn().mockResolvedValue({ held: [], pending: [] });
    const descriptor = Object.getOwnPropertyDescriptor(navigator, "locks");
    Object.defineProperty(navigator, "locks", {
      configurable: true,
      value: { query },
    });
    try {
      await recoverAbandonedPaymentAttempt(scope);
      expect(hasActivePaymentAttempt(scope)).toBe(false);
    } finally {
      if (descriptor) Object.defineProperty(navigator, "locks", descriptor);
      else Reflect.deleteProperty(navigator, "locks");
    }
    expect(await acquirePaymentAttempt(scope)).not.toBeNull();
  });

  it("keeps a live collecting owner locked even beyond the old ten-minute timeout", async () => {
    const first = await acquirePaymentAttempt(scope);
    const record = JSON.parse(localStorage.getItem(first!.storageKey)!);
    localStorage.setItem(
      first!.storageKey,
      JSON.stringify({ ...record, ownerLock: true, expiresAt: Date.now() - 1 }),
    );
    const descriptor = Object.getOwnPropertyDescriptor(navigator, "locks");
    Object.defineProperty(navigator, "locks", {
      configurable: true,
      value: {
        query: vi
          .fn()
          .mockResolvedValue({
            held: [
              { name: `hyperlocal:payment-attempt:owner:${first!.owner}` },
            ],
          }),
      },
    });
    try {
      await recoverAbandonedPaymentAttempt(scope);
      expect(hasActivePaymentAttempt(scope)).toBe(true);
    } finally {
      if (descriptor) Object.defineProperty(navigator, "locks", descriptor);
      else Reflect.deleteProperty(navigator, "locks");
    }
    releasePaymentAttempt(first!);
  });

  it("does not clear a received payment when its named tab has disappeared", async () => {
    const first = await acquirePaymentAttempt(scope);
    markPaymentAttemptPaidPending(first!);
    sessionStorage.clear();
    window.name = "another-tab";
    const close = vi.fn();
    vi.spyOn(window, "open").mockReturnValue({
      closed: false,
      close,
      location: { href: "about:blank" },
    } as unknown as Window);
    expect(focusPaymentAttempt(scope)).toBe(false);
    expect(close).toHaveBeenCalledOnce();
    expect(hasActivePaymentAttempt(scope)).toBe(true);
    expect(await acquirePaymentAttempt(scope)).toBeNull();
  });
});

describe("cross-tab payment attempt leases", () => {
  const scope = paymentAttemptScope({
    usageType: "order",
    userId: 7,
    cartId: "cart-42",
  });

  it("allows only one simultaneous collector for the same cart", async () => {
    const attempts = await Promise.all([
      acquirePaymentAttempt(scope),
      acquirePaymentAttempt(scope),
    ]);
    expect(attempts.filter(Boolean)).toHaveLength(1);
  });

  it("locks tips per order without contaminating cart checkout", async () => {
    const tipScope = paymentAttemptScope({
      usageType: "tip",
      userId: 7,
      orderId: "order-42",
    });
    const otherTipScope = paymentAttemptScope({
      usageType: "tip",
      userId: 7,
      orderId: "order-43",
    });

    const checkoutLease = await acquirePaymentAttempt(scope);
    const tipLease = await acquirePaymentAttempt(tipScope);
    expect(checkoutLease).not.toBeNull();
    expect(tipLease).not.toBeNull();
    expect(tipLease!.windowName).toBe(checkoutLease!.windowName);
    expect(await acquirePaymentAttempt(tipScope)).toBeNull();
    expect(await acquirePaymentAttempt(otherTipScope)).not.toBeNull();
  });

  it("allows another attempt after an unpaid collector releases", async () => {
    const first = await acquirePaymentAttempt(scope);
    expect(first).not.toBeNull();
    expect(hasActivePaymentAttempt(scope)).toBe(true);
    expect(hasCompetingPaymentAttempt(scope)).toBe(false);
    sessionStorage.clear();
    window.name = "another-tab";
    expect(hasCompetingPaymentAttempt(scope)).toBe(true);
    window.name = first!.windowName;
    releasePaymentAttempt(first!);
    expect(hasActivePaymentAttempt(scope)).toBe(false);
    expect(await acquirePaymentAttempt(scope)).not.toBeNull();
  });

  it("reuses a collecting lease when its tab refreshes", async () => {
    const first = await acquirePaymentAttempt(scope);
    expect(first).not.toBeNull();

    // A reload resets component memory and may reset window.name, while the
    // tab's sessionStorage identity survives.
    window.name = "";
    vi.resetModules();
    const refreshedPaymentReturn = await import("@/helpers/paymentReturn");
    const resumed = await refreshedPaymentReturn.acquirePaymentAttempt(scope);

    expect(resumed).toEqual(
      expect.objectContaining({ owner: first!.owner, tabId: first!.tabId }),
    );
    expect(window.name).toBe(first!.windowName);
    expect(refreshedPaymentReturn.hasCompetingPaymentAttempt(scope)).toBe(
      false,
    );
  });

  it("keeps paid-pending and completed carts blocked across tabs", async () => {
    const paid = await acquirePaymentAttempt(scope);
    expect(paid).not.toBeNull();
    markPaymentAttemptPaidPending(paid!);
    releasePaymentAttempt(paid!);
    expect(await acquirePaymentAttempt(scope)).toBeNull();

    localStorage.clear();
    const completed = await acquirePaymentAttempt(scope);
    expect(completed).not.toBeNull();
    completePaymentAttempt(completed!);
    releasePaymentAttempt(completed!);
    expect(await acquirePaymentAttempt(scope)).toBeNull();
  });

  it("fails closed when shared browser storage is unavailable", async () => {
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
      throw new Error("blocked");
    });
    expect(await acquirePaymentAttempt(scope)).toBeNull();
  });

  it("focuses the named tab that owns the payment", async () => {
    const lease = await acquirePaymentAttempt(scope);
    expect(lease).not.toBeNull();
    const paymentTab = {
      closed: false,
      close: vi.fn(),
      focus: vi.fn(),
      location: { href: "https://shop.example/cart" },
    };
    const open = vi
      .spyOn(window, "open")
      .mockReturnValue(paymentTab as unknown as Window);
    sessionStorage.clear();
    window.name = "blocked-tab";

    expect(focusPaymentAttempt(scope)).toBe(true);
    expect(open).toHaveBeenCalledWith("", lease!.windowName);
    expect(paymentTab.focus).toHaveBeenCalledOnce();
  });

  it("closes a blank tab created for a stale payment owner", async () => {
    expect(await acquirePaymentAttempt(scope)).not.toBeNull();
    const paymentTab = {
      closed: false,
      close: vi.fn(),
      focus: vi.fn(),
      location: { href: "about:blank" },
    };
    vi.spyOn(window, "open").mockReturnValue(paymentTab as unknown as Window);
    sessionStorage.clear();
    window.name = "blocked-tab";

    expect(focusPaymentAttempt(scope)).toBe(false);
    expect(paymentTab.close).toHaveBeenCalledOnce();
    expect(paymentTab.focus).not.toHaveBeenCalled();
    expect(hasActivePaymentAttempt(scope)).toBe(false);
  });
});

describe("hosted checkout return context", () => {
  it("persists a transaction UUID without requiring an order", () => {
    rememberPaymentReturn("transaction-42", 7);
    expect(readPaymentReturn("7")).toEqual(
      expect.objectContaining({
        transactionUuid: "transaction-42",
        userId: "7",
      }),
    );
  });
  it("survives a return in the same tab and clears on dismissal", () => {
    rememberPaymentReturn(42, "order-42", 7);
    const attempt = readPaymentReturn("7");
    expect(attempt?.slug).toBe("order-42");
    clearPaymentReturn(attempt!);
    expect(readPaymentReturn("7")).toBeNull();
  });
  it("does not expose another customer's attempt", () => {
    rememberPaymentReturn(42, "order-42", 7);
    expect(readPaymentReturn("8")).toBeNull();
  });
  it("expires old attempts", () => {
    rememberPaymentReturn(42, "order-42", 7);
    vi.spyOn(Date, "now").mockReturnValue(Date.now() + 25 * 60 * 60 * 1000);
    expect(readPaymentReturn("7")).toBeNull();
  });
  it("does not clear a newer payment when an older one resolves", () => {
    rememberPaymentReturn(42, "order-42", 7);
    const old = readPaymentReturn("7")!;
    rememberPaymentReturn(43, "order-43", 7);
    clearPaymentReturn(old);
    expect(readPaymentReturn("7")?.orderId).toBe("43");
  });
  it("cannot block checkout when storage is unavailable", () => {
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
      throw new Error("blocked");
    });
    expect(() => rememberPaymentReturn(42, "order-42", 7)).not.toThrow();
  });
  it.each([
    undefined,
    "pending",
    "unpaid",
    "processing",
    "unknown",
    "refunded",
  ])("does not claim success or failure for %s", (status) => {
    expect(paymentReturnState(status)).toBe("pending");
  });
  it.each(["paid", "completed", "successful"])(
    "recognizes confirmed %s",
    (status) => {
      expect(paymentReturnState(status)).toBe("paid");
    },
  );
  it.each(["failed", "cancelled", "expired"])(
    "recognizes confirmed %s",
    (status) => {
      expect(paymentReturnState(status)).toBe("failed");
    },
  );

  it("requires both completed payment and a created order", () => {
    expect(
      paymentTransactionReturnState({
        status: "processing",
        payment_status: "completed",
        order: null,
      }),
    ).toBe("pending");
    expect(
      paymentTransactionReturnState({
        status: "order_created",
        payment_status: "pending",
        order: { slug: "order-42" },
      }),
    ).toBe("pending");
    expect(
      paymentTransactionReturnState({
        status: "order_created",
        payment_status: "completed",
        order: { slug: "order-42" },
      }),
    ).toBe("paid");
  });
});
