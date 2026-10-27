import type { CurrencyContext, Wallet } from "@/types/currency";

const record = (value: unknown): Record<string, unknown> =>
  value && typeof value === "object" ? (value as Record<string, unknown>) : {};
const text = (value: unknown): string | undefined =>
  typeof value === "string" && value.trim() ? value : undefined;

export const getCurrencyCode = (context: unknown): string | undefined => {
  const source = record(context);
  const snapshot = record(source.currency_snapshot);
  const code =
    text(source.currency_code) ||
    text(source.order_currency_code) ||
    text(snapshot.currency_code) ||
    text(snapshot.order_currency_code) ||
    text(source.currency) ||
    text(snapshot.legacy_currency);
  return code?.trim().toUpperCase();
};

export const lineCurrency = (
  item: unknown,
  parent?: unknown,
): CurrencyContext => {
  const source = record(item);
  return {
    currency_code:
      getCurrencyCode(source) ||
      getCurrencyCode(source.variant) ||
      getCurrencyCode(source.store) ||
      getCurrencyCode(parent),
  };
};

/** No exchange-rate arithmetic: amounts are already in their record currency. */
export const formatMoney = (
  amount: unknown,
  context?: unknown,
  legacySymbol = "",
): string => {
  const value = Number(amount ?? 0);
  const code = getCurrencyCode(context);
  // Keep ISO codes explicit, including ambiguous symbols such as $.
  if (code) {
    try {
      const formatted = new Intl.NumberFormat("en", {
        style: "currency",
        currency: code,
        currencyDisplay: "code",
      }).format(Number.isFinite(value) ? value : 0);
      return formatted.replace(/\u00a0/g, " ");
    } catch {
      return `${code} ${Number.isFinite(value) ? value.toFixed(2) : "0.00"}`;
    }
  }
  return `${legacySymbol}${Number.isFinite(value) ? value.toFixed(2) : "0.00"}`;
};

export const money = (
  source: unknown,
  field: string,
  legacySymbol = "",
  parent?: unknown,
): string => {
  const data = record(source);
  const snapshot = record(record(data.currency_snapshot).formatted);
  return (
    text(data[`formatted_${field}`]) ||
    text(snapshot[field]) ||
    (field === "gateway_amount"
      ? formatMoney(
          data[field] ?? record(data.currency_snapshot).gateway_amount,
          { currency_code: gatewayCurrency(data) || gatewayCurrency(parent) },
          legacySymbol,
        )
      : formatMoney(
          data[field],
          getCurrencyCode(data) ? data : parent,
          legacySymbol,
        ))
  );
};

export const productPrice = (
  variant: unknown,
  field: "price" | "special_price",
  legacySymbol = "",
  parent?: unknown,
): string => {
  const source = record(variant);
  const displayField = `display_${field}`;
  const formatted = record(record(source.currency_snapshot).formatted);
  const useDisplay =
    source[displayField] != null ||
    text(source[`formatted_${displayField}`]) ||
    text(formatted[displayField]);
  return money(source, useDisplay ? displayField : field, legacySymbol, parent);
};

export const productAmount = (variant: unknown, special = true): number => {
  const source = record(variant);
  const field =
    special && Number(source.special_price) > 0 ? "special_price" : "price";
  return Number(source[`display_${field}`] ?? source[field] ?? 0);
};

/** Persist a guest quote with the store currency and any backend-authored label. */
export const productPriceFields = (variant: unknown, parent?: unknown) => {
  const source = record(variant);
  const field = Number(source.special_price) > 0 ? "special_price" : "price";
  const display = `display_${field}`;
  const formatted = record(record(source.currency_snapshot).formatted);
  const selected =
    source[display] != null ||
    text(source[`formatted_${display}`]) ||
    text(formatted[display])
      ? display
      : field;
  return {
    price: productAmount(source),
    currency_code: getCurrencyCode(source) || getCurrencyCode(parent),
    formatted_price:
      text(source[`formatted_${selected}`]) || text(formatted[selected]),
  };
};

export const paymentMethodKey = (method: string): string =>
  method
    .replace(/Payment$/i, "")
    .replace(/[^a-z]/gi, "")
    .toLowerCase();

export const isPaymentMethodEnabled = (settings: unknown, method: string): boolean =>
  Object.entries(record(settings)).some(([key, enabled]) =>
    enabled === true && paymentMethodKey(key) === paymentMethodKey(method),
  );

/** An explicitly empty contextual list means no gateways are available. */
export const isPaymentMethodAvailable = (
  context: unknown,
  method: string,
): boolean => {
  const methods = (context as CurrencyContext | undefined)
    ?.available_payment_methods;
  if (!Array.isArray(methods)) return true;
  return methods.some(
    (entry) =>
      [entry.key, entry.name].some((key) => key && paymentMethodKey(key) === paymentMethodKey(method)) &&
      entry.is_enabled === true &&
      entry.is_available === true,
  );
};

export const paymentUnavailableMessage = (
  context: unknown,
  method: string,
): string | undefined => {
  const methods = (context as CurrencyContext | undefined)
    ?.available_payment_methods;
  return (
    methods?.find(
      (entry) =>
        [entry.key, entry.name].some((key) => key && paymentMethodKey(key) === paymentMethodKey(method)),
    )?.message || undefined
  );
};

export const canCheckoutCurrency = (context: unknown): boolean => {
  if (!context || typeof context !== "object") return false;
  const source = record(context);
  if ("currency_code" in source || "currency_codes" in source) {
    const code = text(source.currency_code)?.trim().toUpperCase();
    return (
      Boolean(code) && Array.isArray(source.currency_codes) &&
      source.currency_codes.length === 1 &&
      text(source.currency_codes[0])?.trim().toUpperCase() === code
    );
  }
  return true; // Old APIs do not provide currency context.
};

export const getWalletContext = (user: unknown): Wallet => {
  const source = record(user);
  if (source.wallet && typeof source.wallet === "object")
    return source.wallet as Wallet;
  return {
    ...source,
    balance: (source.wallet_balance ?? 0) as string | number,
    formatted_balance:
      text(source.formatted_wallet_balance) || text(source.formatted_balance),
    currency_code: (source.wallet_currency_code ?? source.currency_code) as
      | string
      | null
      | undefined,
    currency_snapshot: (source.wallet_currency_snapshot ??
      source.currency_snapshot) as Wallet["currency_snapshot"],
  };
};

/** Cart eligibility takes precedence; older responses fall back to settings. */
export const isCheckoutPaymentMethodAvailable = (
  cart: unknown,
  method: string,
  paymentSettings?: unknown,
): boolean =>
  canCheckoutCurrency(cart) &&
  (Array.isArray(record(cart).available_payment_methods)
    ? isPaymentMethodAvailable(cart, method)
    : isPaymentMethodEnabled(paymentSettings, method));

export const canUseCheckoutWallet = (cart: unknown, wallet: unknown, paymentSettings?: unknown): boolean => {
  if (!isCheckoutPaymentMethodAvailable(cart, "wallet", paymentSettings)) return false;
  if (!Array.isArray(record(cart).available_payment_methods)) return canUseWallet(cart, wallet);
  const cartCode = getCurrencyCode(cart);
  const walletCode = getCurrencyCode(wallet);
  // The cart has already validated wallet eligibility. Older profiles may
  // omit wallet currency; an explicit mismatch must still prevent spending.
  return !cartCode || !walletCode || cartCode === walletCode;
};

export const canUseWallet = (cart: unknown, wallet: unknown): boolean => {
  const source = record(cart);
  // Historical orders have one stored currency and do not have currency_codes.
  if (("currency_codes" in source && !canCheckoutCurrency(source)) ||
    ("currency_code" in source && !text(source.currency_code)) ||
    !isPaymentMethodAvailable(cart, "wallet"))
    return false;
  const cartCode = getCurrencyCode(cart);
  const walletCode = getCurrencyCode(wallet);
  return !cartCode || Boolean(walletCode && cartCode === walletCode);
};

export const gatewayCurrency = (context: unknown): string | undefined => {
  const source = record(context);
  return (
    text(source.gateway_currency_code)?.trim().toUpperCase() ||
    text(record(source.currency_snapshot).gateway_currency_code)
      ?.trim()
      .toUpperCase() ||
    getCurrencyCode(source)
  );
};

/** Guest carts keep separate totals; a legacy unknown currency is its own group. */
export const guestCurrencyTotals = (
  items: Array<{
    currency_code?: string | null;
    price: number;
    quantity: number;
    addons?: Array<{ price: number }>;
  }>,
): Array<{ currency_code: string | undefined; amount: number }> => {
  const totals = new Map<string | undefined, number>();
  for (const item of items) {
    const code = getCurrencyCode(item);
    const amount =
      (item.price +
        (item.addons || []).reduce(
          (sum, addon) => sum + (addon.price || 0),
          0,
        )) *
      item.quantity;
    totals.set(code, (totals.get(code) || 0) + amount);
  }
  return Array.from(totals, ([currency_code, amount]) => ({
    currency_code,
    amount,
  }));
};
