const STORAGE_KEY = "hosted-checkout-return";
const MAX_AGE = 24 * 60 * 60 * 1000;
const PAYMENT_ATTEMPT_PREFIX = "hyperlocal:payment-attempt:";
const PAYMENT_TAB_ID_KEY = "hyperlocal:payment-tab-id";
const COLLECTING_TTL = 10 * 60 * 1000;
const PAID_PENDING_TTL = 24 * 60 * 60 * 1000;
const COMPLETED_TTL = 30 * 60 * 1000;
const activePaymentOwners = new Set<string>();
const ownerLockReleases = new Map<string, () => void>();

type PaymentAttemptStatus = "collecting" | "paid_pending" | "completed";

type PaymentAttemptRecord = {
  owner: string;
  tabId?: string;
  windowName: string;
  previousWindowName?: string;
  status: PaymentAttemptStatus;
  createdAt: number;
  expiresAt: number;
  ownerLock?: boolean;
};

const ownerLockName = (owner: string) =>
  `${PAYMENT_ATTEMPT_PREFIX}owner:${owner}`;

const holdOwnerLock = async (owner: string): Promise<boolean> => {
  if (!navigator.locks?.query) return false;
  return new Promise<boolean>((resolve, reject) => {
    void navigator.locks
      .request(ownerLockName(owner), { ifAvailable: true }, async (lock) => {
        if (!lock) {
          resolve(false);
          return;
        }
        await new Promise<void>((release) => {
          ownerLockReleases.set(owner, release);
          resolve(true);
        });
      })
      .catch(reject);
  });
};

// Web Locks are released by the browser when the owning document disappears.
// A stored collecting record alone must not keep a closed tab alive.
export async function recoverAbandonedPaymentAttempt(
  scope: string | null,
): Promise<void> {
  if (typeof window === "undefined" || !scope || !navigator.locks?.query)
    return;
  const storageKey = `${PAYMENT_ATTEMPT_PREFIX}${hashPaymentScope(scope)}`;
  const attempt = readPaymentAttempt(storageKey);
  if (attempt?.status !== "collecting" || !attempt.ownerLock) return;
  let locks: LockManagerSnapshot;
  try {
    locks = await navigator.locks.query();
  } catch {
    return;
  }
  if (locks.held?.some((lock) => lock.name === ownerLockName(attempt.owner)))
    return;
  const current = readPaymentAttempt(storageKey);
  if (current?.owner === attempt.owner && current.status === "collecting") {
    localStorage.removeItem(storageKey);
    activePaymentOwners.delete(attempt.owner);
  }
}

export type PaymentAttemptLease = {
  storageKey: string;
  owner: string;
  tabId: string;
  windowName: string;
  previousWindowName: string;
};

const getPaymentTabId = (): string => {
  const existing = window.sessionStorage.getItem(PAYMENT_TAB_ID_KEY);
  if (existing) return existing;

  const tabId =
    window.crypto?.randomUUID?.() ||
    `${Date.now()}:${Math.random().toString(36)}`;
  window.sessionStorage.setItem(PAYMENT_TAB_ID_KEY, tabId);
  return tabId;
};

const hashPaymentScope = (scope: string): string => {
  let hash = 2166136261;
  for (let index = 0; index < scope.length; index += 1) {
    hash ^= scope.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return (hash >>> 0).toString(36);
};

export function paymentAttemptScope({
  usageType,
  userId,
  cartId,
  walletTransactionId,
  orderId,
}: {
  usageType: "order" | "wallet" | "tip";
  userId: unknown;
  cartId?: unknown;
  walletTransactionId?: unknown;
  orderId?: unknown;
}): string | null {
  const target =
    usageType === "wallet"
      ? walletTransactionId
      : usageType === "tip"
        ? orderId
        : cartId;
  if (userId === null || userId === undefined || !String(userId) || !target) {
    return null;
  }
  return `${usageType}:${String(userId)}:${String(target)}`;
}

const readPaymentAttempt = (
  storageKey: string,
): PaymentAttemptRecord | null => {
  try {
    const value = JSON.parse(localStorage.getItem(storageKey) || "null");
    if (
      !value ||
      typeof value.owner !== "string" ||
      typeof value.windowName !== "string" ||
      !["collecting", "paid_pending", "completed"].includes(value.status) ||
      typeof value.createdAt !== "number" ||
      typeof value.expiresAt !== "number"
    ) {
      return null;
    }
    if (
      value.expiresAt <= Date.now() &&
      !(value.status === "collecting" && value.ownerLock)
    ) {
      localStorage.removeItem(storageKey);
      return null;
    }
    return value as PaymentAttemptRecord;
  } catch {
    return null;
  }
};

const claimPaymentAttempt = async (
  storageKey: string,
  verifyClaim: boolean,
): Promise<PaymentAttemptLease | null> => {
  try {
    const tabId = getPaymentTabId();
    const existing = readPaymentAttempt(storageKey);
    const belongsToThisTab =
      existing?.tabId === tabId ||
      (!existing?.tabId && existing?.windowName === window.name);

    // sessionStorage survives a reload but is isolated per tab. Reuse the
    // existing collecting lease after a refresh instead of treating this tab
    // as its own competitor. Paid/pending attempts remain deliberately locked.
    if (existing && belongsToThisTab && existing.status === "collecting") {
      // The same live document can call acquire twice before React state has
      // settled. Only a newly loaded document (whose module memory is empty)
      // may reclaim the session-scoped lease.
      if (activePaymentOwners.has(existing.owner)) return null;
      const refreshed = {
        ...existing,
        tabId,
        previousWindowName: existing.previousWindowName || "",
        expiresAt: Date.now() + COLLECTING_TTL,
      } satisfies PaymentAttemptRecord;
      localStorage.setItem(storageKey, JSON.stringify(refreshed));
      window.name = existing.windowName;
      activePaymentOwners.add(existing.owner);
      return {
        storageKey,
        owner: existing.owner,
        tabId,
        windowName: existing.windowName,
        previousWindowName: refreshed.previousWindowName,
      };
    }
    if (existing) return null;

    const owner =
      window.crypto?.randomUUID?.() ||
      `${Date.now()}:${Math.random().toString(36)}`;
    // One stable target name per browser tab lets independent payment scopes
    // (for example an order tip and a later cart checkout) point back to the
    // same real tab without overwriting each other's focus target.
    const windowName = `hyperlocal-payment-tab-${tabId}`;
    const previousWindowName = window.name;
    const now = Date.now();
    const record: PaymentAttemptRecord = {
      owner,
      tabId,
      windowName,
      previousWindowName,
      status: "collecting",
      createdAt: now,
      expiresAt: now + COLLECTING_TTL,
    };
    record.ownerLock = await holdOwnerLock(owner);
    localStorage.setItem(storageKey, JSON.stringify(record));
    activePaymentOwners.add(owner);
    window.name = windowName;
    // localStorage has no atomic compare-and-set. Without Web Locks, allow
    // racing tabs to finish their writes, then only the last owner proceeds.
    if (verifyClaim) {
      await new Promise((resolve) => setTimeout(resolve, 30));
    }
    if (readPaymentAttempt(storageKey)?.owner === owner) {
      return { storageKey, owner, tabId, windowName, previousWindowName };
    }
    activePaymentOwners.delete(owner);
    ownerLockReleases.get(owner)?.();
    ownerLockReleases.delete(owner);
    if (window.name === windowName) window.name = previousWindowName;
    return null;
  } catch {
    // Fail closed: if a shared lease cannot be established, collecting money
    // is less safe than asking the customer to retry in one active tab.
    return null;
  }
};

export async function acquirePaymentAttempt(
  scope: string | null,
): Promise<PaymentAttemptLease | null> {
  if (typeof window === "undefined" || !scope) return null;
  await recoverAbandonedPaymentAttempt(scope);
  const storageKey = `${PAYMENT_ATTEMPT_PREFIX}${hashPaymentScope(scope)}`;
  if (navigator.locks) {
    return navigator.locks.request(
      `${storageKey}:claim`,
      { ifAvailable: true },
      (lock) => (lock ? claimPaymentAttempt(storageKey, false) : null),
    );
  }
  return claimPaymentAttempt(storageKey, true);
}

export function hasActivePaymentAttempt(scope: string | null): boolean {
  if (typeof window === "undefined" || !scope) return false;
  return Boolean(
    readPaymentAttempt(`${PAYMENT_ATTEMPT_PREFIX}${hashPaymentScope(scope)}`),
  );
}

// A successfully imported cart starts a new purchase even when the backend
// reuses its cart ID. Only a fully completed purchase may be retired here.
export function clearCompletedPaymentAttempt(scope: string | null): void {
  if (typeof window === "undefined" || !scope) return;
  const storageKey = `${PAYMENT_ATTEMPT_PREFIX}${hashPaymentScope(scope)}`;
  const attempt = readPaymentAttempt(storageKey);
  if (attempt?.status === "completed") {
    try {
      localStorage.removeItem(storageKey);
    } catch {
      /* Preserve the lock if browser storage is unavailable. */
    }
  }
}

export function ownsPaymentAttempt(lease: PaymentAttemptLease): boolean {
  if (typeof window === "undefined") return false;
  const attempt = readPaymentAttempt(lease.storageKey);
  return Boolean(attempt && attempt.owner === lease.owner);
}

export function hasCompetingPaymentAttempt(scope: string | null): boolean {
  if (typeof window === "undefined" || !scope) return false;
  const attempt = readPaymentAttempt(
    `${PAYMENT_ATTEMPT_PREFIX}${hashPaymentScope(scope)}`,
  );
  if (!attempt) return false;
  try {
    return attempt.tabId
      ? attempt.tabId !== getPaymentTabId()
      : attempt.windowName !== window.name;
  } catch {
    return attempt.windowName !== window.name;
  }
}

export function focusPaymentAttempt(scope: string | null): boolean {
  if (typeof window === "undefined" || !scope) return false;
  const storageKey = `${PAYMENT_ATTEMPT_PREFIX}${hashPaymentScope(scope)}`;
  const attempt = readPaymentAttempt(storageKey);
  if (!attempt?.windowName) return false;

  let belongsToThisTab = window.name === attempt.windowName;
  try {
    belongsToThisTab = attempt.tabId
      ? attempt.tabId === getPaymentTabId()
      : belongsToThisTab;
  } catch {
    /* Fall back to the named-window identity. */
  }

  if (belongsToThisTab) {
    window.focus();
    return true;
  }

  const paymentTab = window.open("", attempt.windowName);
  if (!paymentTab || paymentTab.closed) return false;

  try {
    // A missing named tab causes window.open to create a blank one. Close that
    // instead of sending the customer to an empty page for a stale lease.
    if (paymentTab.location.href === "about:blank") {
      paymentTab.close();
      // The named owner no longer exists. Remove only the exact record we
      // inspected so a newer attempt can never be cleared accidentally.
      if (
        attempt.status === "collecting" &&
        readPaymentAttempt(storageKey)?.owner === attempt.owner
      ) {
        localStorage.removeItem(storageKey);
      }
      return false;
    }
  } catch {
    // Cross-origin provider pages cannot expose their URL, but can be focused.
  }

  paymentTab.focus();
  return true;
}

const transitionPaymentAttempt = (
  lease: PaymentAttemptLease,
  status: PaymentAttemptStatus,
  ttl: number,
): void => {
  try {
    const current = readPaymentAttempt(lease.storageKey);
    if (!current || current.owner !== lease.owner) return;
    localStorage.setItem(
      lease.storageKey,
      JSON.stringify({
        ...current,
        status,
        expiresAt: Date.now() + ttl,
      } satisfies PaymentAttemptRecord),
    );
  } catch {
    /* The in-component lock remains active if storage becomes unavailable. */
  }
};

export const markPaymentAttemptPaidPending = (
  lease: PaymentAttemptLease,
): void => transitionPaymentAttempt(lease, "paid_pending", PAID_PENDING_TTL);

// Hosted checkout intentionally leaves this document. Its collecting record
// must retain the existing timeout rather than be mistaken for an orphan.
export function preservePaymentAttemptForRedirect(lease: PaymentAttemptLease): void {
  const current = readPaymentAttempt(lease.storageKey);
  if (current?.owner !== lease.owner || current.status !== "collecting") return;
  localStorage.setItem(lease.storageKey, JSON.stringify({ ...current, ownerLock: false }));
  ownerLockReleases.get(lease.owner)?.();
  ownerLockReleases.delete(lease.owner);
}

export const completePaymentAttempt = (lease: PaymentAttemptLease): void => {
  transitionPaymentAttempt(lease, "completed", COMPLETED_TTL);
  activePaymentOwners.delete(lease.owner);
  ownerLockReleases.get(lease.owner)?.();
  ownerLockReleases.delete(lease.owner);
};

export const releasePaymentAttempt = (lease: PaymentAttemptLease): void => {
  activePaymentOwners.delete(lease.owner);
  ownerLockReleases.get(lease.owner)?.();
  ownerLockReleases.delete(lease.owner);
  try {
    const current = readPaymentAttempt(lease.storageKey);
    if (current?.owner === lease.owner && current.status === "collecting") {
      localStorage.removeItem(lease.storageKey);
    }
  } catch {
    /* Best effort; the short collecting TTL is the final recovery path. */
  }
};

export interface PaymentReturnAttempt {
  transactionUuid?: string;
  orderId?: string;
  slug?: string;
  userId: string;
  createdAt: number;
}

// This is UI context only. Payment outcomes always come from the authenticated API.
export function rememberPaymentReturn(
  transactionUuid: unknown,
  userId: unknown,
): void;
/** @deprecated Compatibility with hosted checkouts created before transaction-first orders. */
export function rememberPaymentReturn(
  orderId: unknown,
  slug: unknown,
  userId: unknown,
): void;
export function rememberPaymentReturn(
  identifier: unknown,
  slugOrUserId: unknown,
  legacyUserId?: unknown,
) {
  const isLegacy = legacyUserId !== undefined;
  const userId = isLegacy ? legacyUserId : slugOrUserId;
  if (!identifier || !userId) return;
  const attempt: PaymentReturnAttempt = isLegacy
    ? {
        orderId: String(identifier),
        slug: typeof slugOrUserId === "string" ? slugOrUserId : "",
        userId: String(userId),
        createdAt: Date.now(),
      }
    : {
        transactionUuid: String(identifier),
        userId: String(userId),
        createdAt: Date.now(),
      };
  if (isLegacy && !attempt.slug) return;
  try {
    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(attempt));
  } catch {
    /* Storage restrictions must never prevent payment. */
  }
}

export function readPaymentReturn(userId: string): PaymentReturnAttempt | null {
  try {
    const value = JSON.parse(
      window.sessionStorage.getItem(STORAGE_KEY) || "null",
    );
    if (!value) return null;
    const hasTransaction =
      typeof value.transactionUuid === "string" && value.transactionUuid;
    const hasLegacyOrder =
      typeof value.orderId === "string" &&
      typeof value.slug === "string" &&
      value.slug;
    if (
      (!hasTransaction && !hasLegacyOrder) ||
      value.userId !== userId ||
      typeof value.createdAt !== "number" ||
      value.createdAt > Date.now() ||
      Date.now() - value.createdAt > MAX_AGE
    ) {
      window.sessionStorage.removeItem(STORAGE_KEY);
      return null;
    }
    return value;
  } catch {
    return null;
  }
}

export function clearPaymentReturn(attempt: PaymentReturnAttempt) {
  try {
    const current = readPaymentReturn(attempt.userId);
    if (
      current?.transactionUuid === attempt.transactionUuid &&
      current?.orderId === attempt.orderId &&
      current?.createdAt === attempt.createdAt
    ) {
      window.sessionStorage.removeItem(STORAGE_KEY);
    }
  } catch {
    /* Best effort when browser storage is unavailable. */
  }
}

export function paymentTransactionReturnState(transaction?: {
  status?: string;
  payment_status?: string;
  order?: { slug?: string } | null;
}): "paid" | "failed" | "pending" {
  const paymentState = paymentReturnState(transaction?.payment_status);
  const status = transaction?.status?.toLowerCase();
  if (
    paymentState === "failed" ||
    status === "failed" ||
    status === "cancelled" ||
    status === "canceled" ||
    status === "expired"
  ) {
    return "failed";
  }
  if (
    status === "order_created" &&
    paymentState === "paid" &&
    transaction?.order?.slug
  ) {
    return "paid";
  }
  return "pending";
}

export function paymentReturnState(
  status?: string,
): "paid" | "failed" | "pending" {
  switch (status?.toLowerCase()) {
    case "paid":
    case "completed":
    case "successful":
    case "success":
      return "paid";
    case "failed":
    case "cancelled":
    case "canceled":
    case "expired":
      return "failed";
    default:
      return "pending";
  }
}
