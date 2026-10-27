export interface CurrencySnapshot {
  currency_code?: string | null;
  order_currency_code?: string | null;
  gateway_currency_code?: string | null;
  legacy_currency?: string | null;
  base_currency_code?: string | null;
  display_currency_code?: string | null;
  display_exchange_rate?: number | string | null;
  gateway_amount?: number | string | null;
  gateway_exchange_rate?: number | string | null;
  formatted?: Record<string, string | null | undefined>;
}

export interface ContextualPaymentMethod {
  key: string;
  name?: string;
  label?: string;
  mode?: string;
  is_enabled: boolean;
  is_available: boolean;
  currency_code?: string | null;
  configured_currency?: string | null;
  allowed_currencies?: string[];
  requires_account_approval?: boolean;
  reason_code?: string | null;
  message?: string | null;
}

/** Optional fields keep older API responses readable during migration. */
export interface CurrencyContext {
  currency_code?: string | null;
  currency?: string | null;
  currency_codes?: string[];
  currency_label?: string | null;
  currency_snapshot?: CurrencySnapshot | null;
  available_payment_methods?: ContextualPaymentMethod[];
  base_currency_code?: string | null;
  display_currency_code?: string | null;
  display_exchange_rate?: number | string | null;
  order_currency_code?: string | null;
  gateway_currency_code?: string | null;
  gateway_amount?: number | string | null;
  gateway_exchange_rate?: number | string | null;
  [field: `formatted_${string}`]: string | null | undefined;
}

export interface Wallet extends CurrencyContext {
  id?: number;
  balance: number | string;
  blocked_balance?: number | string;
}
