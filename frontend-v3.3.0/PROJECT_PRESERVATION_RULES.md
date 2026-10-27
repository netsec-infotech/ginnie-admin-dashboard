# Project Preservation Rules

## Authority and purpose

Preserve this project's existing features, UI, and folder organization while implementing approved additions. Follow applicable `AGENTS.md` instructions and the user's approved scope. This document supplements those instructions; it does not authorize unrelated changes.

The customer support feature is approved in `SUPPORT_CHAT_IMPLEMENTATION_PLAN.md`. That approval covers its listed additions and narrowly scoped integration changes, not a redesign of existing features.

## Existing structure baseline

This is a Next.js Pages Router / React / TypeScript web application using HeroUI, Tailwind CSS, Redux, SWR, Axios, and i18next. Retain those established patterns.

```text
src/
  SEO/          # Page metadata and indexing
  components/   # Reusable UI grouped by existing feature conventions
  config/       # Existing application configuration
  contexts/     # Existing context providers
  guards/       # Route protection
  helpers/      # Shared helpers
  hooks/        # React hooks
  layouts/      # Site and account layouts
  lib/          # Libraries, Redux, integrations
  pages/        # Next.js pages, including my-account/
  routes/       # API functions and Axios interceptors
  services/     # Existing data services
  stores/       # Existing stores
  styles/       # Existing styles and theme integration
  types/        # TypeScript contracts
  views/        # Existing page/feature views
public/locales/ # en.json, hi.json, ar.json
tests/          # Existing Vitest test organization
```

New approved feature files may be added within the appropriate existing parent folders. Do not move, rename, delete, merge, or restructure existing folders/files. Do not introduce another architecture or state-management library.

## Preserve features and UI

- Preserve account/profile, orders/returns, checkout/cart, payments, wallet, authentication, notifications, location, and all unrelated functionality.
- Preserve existing route names, API parameters and response models, validation, public interfaces, configuration, and comments unless their change is explicitly approved.
- Match existing layouts, spacing patterns, theme tokens, typography, responsive behavior, accessibility, translations, and RTL behavior. The support reference is flow guidance, not permission to redesign the website.
- Never fix one feature by removing or replacing another feature's logic. Extend existing integration points with minimum changes.
- Do not add unrelated navigation, notification integrations, order actions, or global styling during support implementation.

## Required workflow

1. Consult the code-review graph first, then inspect relevant files when the graph has no coverage.
2. Check callers, dependencies, and possible effects before editing existing logic.
3. Summarize the exact intended files and reasons before coding.
4. Keep changes within approved scope. Obtain explicit approval before an additional destructive change or an existing behavior/UI change outside that scope.
5. Preserve pre-existing work and uncommitted changes. Never reset, overwrite, or revert another developer's work.
6. Use existing dependencies first. Do not upgrade packages, change build/deployment configuration, or regenerate unrelated files.
7. Do not hardcode credentials, tokens, personal identifiers, or API hostnames. Do not log sensitive data.
8. Verify loading, success, empty, error, retry, and authentication states and test affected functionality.
9. Report changed files, their purpose, validation results, and remaining concrete limitations. Do not claim checks that were not run.

## Support implementation boundaries

Allowed shared edits: add the support navigation entry in `UserLayout`, the exact support protected route, support-only API functions/types, and support translations. New support page/components/hook/helpers/tests belong under existing parent directories.

The two requested root Markdown files have explicit `.gitignore` exceptions so they can be committed. Other Markdown ignore rules remain in place.

`next.config.ts` adds a support-only `NetworkOnly` PWA rule with `extendDefaultRuntimeCaching: true`. This prevents private support API/attachment responses from entering the service-worker cache while preserving the default rules for other resources. No deployment settings or dependency versions are changed.

Order lookup is read-only and reuses the existing API; support must not change order/payment/refund business logic. Callback requests do not alter the customer's saved phone number. Support uses existing authentication without changing token/session handling. No new global persisted chat state is required. The subsequently approved Pusher connection is scoped below.

Preserve pre-existing `package-lock.json` changes when adding approved dependencies.


## Approved mobile support full view

Support chat offers a mobile-only Expand chat / Exit full view control. `SupportChatFrame` keeps one portal container while moving it between the account layout and the document body, preserving the current topic, draft, attachments, and chat state. Expanded chat follows the visual viewport and safe-area insets, locks background interaction, and restores focus/scrolling when closed or unmounted. Escape and switching to desktop also exit full view. Support dialogs continue to use their existing HeroUI portals. Mobile composer text uses 16px to avoid input-focus zoom. This addition does not change shared navigation, authentication, API requests, or support submission logic.

## Approved order delivery OTP modes

Order delivery OTP display follows the API's optional `delivery_otp_mode`. In `order_wise` mode, show the unverified `delivery_otp` once per order on the orders card and order details; never fall back to item delivery codes. In `item_wise` mode, or legacy responses without a mode, use each item's unverified `otp` (inside the orders-card item list and beside detail items). An explicit `delivery_otp_required: false` hides delivery codes. Keep return-pickup OTP selection and verification rules independent of delivery mode. Preserve the existing order requests, refresh behavior, status handling, payment, cancellation, return, tracking, and review actions.

## Approved support Pusher and Reverb connections

`useSupportRealtime` uses the existing settings context and reads `/user/support/realtime` only after the customer thread loads and the settings entry `broadcast_driver.value.broadcastDriver` equals `"pusher"` or `"reverb"`. Missing, malformed, or other driver settings keep polling active without opening a socket. Driver changes abort pending setup and disconnect the old socket; selecting either supported driver fetches fresh connection settings. Only a matching account/thread with `configured: true` and a realtime driver matching the settings selection loads `pusher-js` (8.6.0) and opens a socket using API key, cluster, host, port, and scheme. Hide/offline/unmount/account changes abort configuration requests and disconnect the socket; returning to the page reloads configuration. Preserve all existing polling, message actions, typing/read endpoints, drafts, UI, and localization. Reverb requires an explicit API host and port; both WS and WSS ports use that port, with TLS selected by the API scheme. Unsupported drivers retain polling. No extra SDK is needed because Reverb uses the Pusher protocol.

The supplied backend contract now defines `private-support.thread.{thread_uuid}` and `presence-support.presence.thread.{thread_uuid}`. Subscribe to these exact channels with form-encoded authorization through the existing authenticated API client. Validate that `auth_endpoint` belongs to the configured backend; do not forward the account bearer token to the WebSocket host or unrelated origins. Let Axios set form Content-Type and Content-Length. The SDK re-authorizes subscriptions on reconnect.

Only admin/agent `support.typing` events show the translated typing indicator in an active session. Clear it on explicit stop, message activity, disconnect, backgrounding, session/account/driver changes, or four seconds without an update. Ignore customer events, invalid payloads, and mismatched session IDs when supplied. Send the documented presence `client-typing` whisper alongside the existing throttled REST typing request; a disabled whisper must not block REST. Keep existing message polling, actions, and polling intervals unchanged. WebSockets currently supply typing; message/history reconciliation still uses the existing API flow.

## Approved Mercado Pago settings and checkout

The settings model accepts optional `mercadoPagoPayment`, `mercadoPagoPaymentMode`, `mercadoPagoPublicKey`, and `mercadoPagoCurrencyCode` fields. The checkout payment list opts into showing Mercado Pago only when its enabled setting is exactly true. Wallet deposits and rider-tip methods are unchanged.

The approved order flow submits the existing multipart `/user/orders` request with `payment_type=mercadoPagoPayment` and all existing checkout details. Open the returned `data.payment_response.link` directly after validating it is an HTTPS URL without embedded credentials, then use the existing checkout reset before navigation. Do not call a second Mercado Pago create-order endpoint or substitute a client-generated URL. Missing/invalid links show an error without clearing checkout or reporting payment success. The gateway button guards duplicate clicks and leaves successful external navigation to the checkout helper; do not invoke the modal success navigation and overwrite the payment redirect. The backend remains responsible for confirming payment. Tests use synthetic responses; no live order or payment was created during implementation.
