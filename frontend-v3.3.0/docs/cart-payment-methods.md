# Cart payment availability

Checkout uses `GET /api/user/cart` → `data.available_payment_methods`.
The existing cart fetch passes address, coordinates, promo code, wallet,
loyalty points, and rush delivery options. No separate gateway-list request is
needed.

- When the cart supplies a list, authorize a supported method only when its entry has both
  `is_enabled: true` and `is_available: true`.
- Match `key` (for example `razorpayPayment`) or `name` (`razorpay`) to the
  implemented provider. `wallet` controls the wallet switch and wallet-only
  checkout; it is not a second payment radio option.
- When the cart omits the list (or returns `null`), use the original payment
  settings flags as a fallback, including the wallet flag and currency checks.
  An explicit list always takes precedence: an empty list permits no methods,
  and settings cannot enable a missing/disabled method or hide one the cart permits.
- Keep SDK configuration (Stripe publishable key, Razorpay key ID, etc.) in
  payment settings: the observed cart response contains no SDK keys.
- Do not infer eligibility from `allowed_currencies`, `configured_currency`, or
  `requires_account_approval`. The backend's availability result is final.
- A new cart response invalidates the modal's previous payment selection.
  Refreshing/failed cart quotes cannot start payments. Recheck eligibility
  before opening inline providers and confirming Stripe payments.
- Wallet top-ups and existing-order payments retain their own currency and
  eligibility context; the shopping cart does not authorize those flows.

In the inspected USD response, COD and Wallet were available. Stripe, Paystack,
and Flutterwave were disabled; Razorpay, Xendit, and Mercado Pago were unavailable
for the cart currency. These are observations, not hardcoded currency rules.

Backend payment/order creation must continue validating the current cart; the
client's list is a quote and cannot guarantee later server eligibility.
