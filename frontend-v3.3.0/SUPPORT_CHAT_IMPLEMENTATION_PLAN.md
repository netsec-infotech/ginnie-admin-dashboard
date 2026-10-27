# Customer Support Chat Implementation Plan

## Goal and approved scope

Add `/my-account/support/` to the existing account sidebar and mobile tabs. Follow the supplied support screenshots for the guided flow while retaining this website's layout, light/dark themes, brand colors, and component patterns. Follow `PROJECT_PRESERVATION_RULES.md` and applicable `AGENTS.md` instructions.

## Customer flow

1. Load the existing thread and resume its active session, or show recent orders, older orders, and general help.
2. Load API topics and filter `context`: order/both for an order; general/both for general help.
3. Show topic guidance, quick replies, a draft message, and optional attachments. Back navigation preserves the draft.
4. Start a session and display conversation history, session separators, timestamps, customer/support messages, protected attachments, and waiting/closure notices.
5. Provide an accessible composer, paginated older messages, failed-send retry, and connection status.
6. Offer callback requests with an editable phone and optional future time; this does not implement browser calling.
7. Resolve a conversation after confirmation; offer a 1–5-star rating with optional feedback. Retain history when starting another conversation.

## API contract

Use the existing configured Axios instance and authentication. Never embed an inspection token or backend URL in application code.

| Operation | Path under `/user/support` |
| --- | --- |
| Initial thread/recent orders | GET `/thread` |
| Older messages | GET `/thread/messages?before_message_id=…&limit=30` |
| Incremental updates | GET `/thread/updates?after_message_id=…&after_call_id=…` |
| Topics/guidance/quick replies | GET `/topics` |
| Realtime configuration (Pusher/Reverb connection setup) | GET `/realtime` |
| Create session | POST `/sessions` |
| Send message | POST `/sessions/{slug}/messages` |
| Typing/read | POST `/sessions/{slug}/typing`, `/read` |
| Callback/resolve/rating | POST `/sessions/{slug}/callbacks`, `/resolve`, `/rating` |
| Protected attachment | GET `/attachments/{media}` |

Docs: https://hypermart.eshopweb.store/docs/api#/operations/user.support.attachments.show

Five authenticated JSON GET endpoints were verified on 2026-09-08. The inspected account had no sessions, messages, calls, or attachments. Initial data contains `thread`, `recent_orders`, `polling_enabled`, `poll_interval_ms`, and `realtime`. Updates contain `messages`, `calls`, `active_ticket_id`, `message_cursor`, and `call_cursor`. Older messages contain `messages`, `has_more`, and `oldest_message_id`. Verify populated response contracts during integration; do not treat undocumented response structures as established facts.

## Implementation and reliability

- Add support-specific types, hook, components, page, and tests inside existing parent folders. Shared changes are additive: API functions, account navigation, exact protected route, and translations.
- Preserve polling as the default message transport. Honor backend polling configuration (currently 15 seconds), prevent overlap, pause when hidden/offline, and refresh on reconnect. Refresh thread metadata after actions and periodically to reconcile closures and ratings. The subsequently approved Pusher connection step is described below.
- Track server message/call cursors; deduplicate by server IDs. Use a UUID `client_message_id` per send and reuse it for retries. Preserve drafts on failure; do not automatically retry callback, resolution, or rating submissions.
- Send multipart files with limits of five attachments, 10 MB each, and 4,000 message characters. Fetch protected attachments with authentication as blobs and revoke temporary URLs. Do not render arbitrary HTML or SVG from uploaded files.
- Preserve scroll position while loading older history; follow new messages only near the bottom. Send throttled typing updates and read receipts for visible messages.
- Support validation errors, conflicts, rate limits, expired authentication, offline state, and loading/empty/error states. Never equate successful polling with staff availability.
- Keep chat state scoped to the authenticated user and clear it on logout/unmount. Do not persist private support content.

## Validation and completion

- Test context filtering, drafts/quick replies, payloads/file limits, stable retry UUIDs, polling/cursors/deduplication, history pagination, and cleanup.
- Test callbacks, resolution, ratings, protected downloads, stale sessions, and API failures.
- Check mobile/desktop, light/dark, keyboard access, and English/Hindi/Arabic/RTL.
- Run focused Vitest tests, TypeScript checking, and non-mutating ESLint checks. Build in an isolated copy if needed because existing build scripts generate files.
- Verify a populated test conversation before claiming full live integration. Never create production test chats/callbacks/ratings merely to obtain example responses without appropriate authorization.
- Preserve the pre-existing `package-lock.json` modification. Do not upgrade dependencies or refactor unrelated features.

## Implementation verification record

- Both requested Markdown documents were created before implementation. Explicit `.gitignore` exceptions make them reviewable and committable.
- With the account owner's explicit approval, one labelled test session was created with a PNG attachment, a follow-up message was sent, and the session was resolved. Live creation/message/attachment/update/resolution responses were verified. No callback request or rating was submitted to the live account.
- Populated sessions contain `id`, `slug`, `status`, `topic`, `order`, `assignee`, `messages`, `calls`, `rating`, and closure metadata. Messages use `session_id`, `client_message_id`, `sender_role`, `sender_name`, `type`, `text`, `attachments`, and `created_at`.
- Protected attachments are retrieved using their media IDs from the configured API client. Returned storage/admin/signed URLs are not used to forward authentication.
- Automated tests and an isolated browser preview exercise callbacks and ratings using mocked responses; their production mutations have not been exercised.
- The PWA configuration prepends a support-only `NetworkOnly` GET rule and retains all default runtime caching rules. Support history and protected attachments must not be persisted by the service worker.

### Completed checks

- 61 tests passed across eight support, account protection, route indexing, and API interceptor test files.
- `npx tsc --noEmit --incremental false` passed. Scoped ESLint checks passed without errors or warnings; `git diff --check` passed.
- `npm run build` and the SEO audit passed in an isolated copy. A separate `npx next build --webpack` also passed; the emitted service worker places the support `NetworkOnly` rule before the default cache rules.
- A fresh headless Chrome preview using real HeroUI/account layout components and mocked support responses passed guided topic selection, quick replies, session creation, visible read receipts, callbacks, resolution, ratings, and older-history scroll anchoring. Desktop/mobile light/dark layouts and Arabic RTL were checked without horizontal overflow.
- Existing translation values and the pre-existing `package-lock.json` modification were preserved. No packages were installed or upgraded in the project.

### PWA deployment note

The existing next-pwa integration emits its worker with webpack. The default Turbopack build does not emit `sw.js`. When deploying an installation that uses a service worker, build with `npx next build --webpack` and publish the regenerated worker alongside the application so the support cache exclusion takes effect. The project's build/deploy scripts were not changed.

### Follow-up: Pusher and Reverb connection setup

- Added the typed `/user/support/realtime` request and `useSupportRealtime`, enabled only after the authenticated thread is available and the settings API entry `broadcast_driver.value.broadcastDriver` is `"pusher"` or `"reverb"`. Reuse `SettingsContext` without a separate settings request; missing or other driver values retain polling. Driver changes disconnect/abort existing setup. The hook validates account/thread identity and uses API connection settings with a client-only dynamic `pusher-js` import.
- Config failures, unsupported drivers, and socket errors leave chat polling operational. Hidden/offline tabs release the socket; returning reconnects using fresh configuration. Unmount and account changes discard pending responses and disconnect the previous socket.
- The provided backend contract now enables authenticated private `support.thread` and presence `support.presence.thread` subscriptions, scoped by thread UUID. Admin/agent `support.typing` events drive the localized four-second indicator. Presence `client-typing` whispers accompany the existing REST typing flow. Message activity clears the indicator; message/history reconciliation and polling intervals remain unchanged. Reverb requires its API host and port and uses the same installed client with WS/WSS options selected by the API scheme.
- Added `pusher-js` 8.6.0 and its `tweetnacl` dependency without changing any existing locked package entries. The earlier no-new-packages statement describes the initial polling implementation.
- Verification covers API routing, configuration selection, Pusher/Reverb switching, Reverb HTTP/HTTPS and required host/port, connection options, stale response cleanup, identity changes, visibility/offline reconnect, and continued polling after config failure. No production chat messages were sent to test this connection setup.

### Agent typing verification

- Live Pusher connection and both documented subscriptions were accepted; the private and presence authorization requests each returned HTTP 200. No chat messages or typing whispers were sent during the live verification. Reverb uses the same subscription code but was not verified against a live Reverb server.
- Automated tests cover admin/agent roles, customer/invalid event rejection, renewed four-second expiry, stop/message/disconnect/session cleanup, form authorization and origin validation, presence whispers, and English/Hindi/Arabic indicator rendering.
