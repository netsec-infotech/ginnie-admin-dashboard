# Hyper Local Play, Stories, and Reels UI Plan

## Document status

- **Purpose:** Authoritative product and UX reference for Stories on Home and the
  dedicated Play experience.
- **Product:** Hyper Local customer storefront.
- **Experience name:** **Play** (not Reels).
- **Dedicated route:** `/play/`.
- **Content model:** Customer view-only; published by approved sellers and Hyper
  Local administrators.
- **Last updated:** 2026-08-27.

This document records the agreed product decisions and current static
implementation. Future design, frontend, backend, and QA work should refer to it
unless a later product decision explicitly replaces part of it.

## 1. Confirmed decisions

1. Stories and Reels are consumption-only for customers. Approved sellers and
   administrators publish content through their management surfaces; storefront
   customers cannot create, upload, edit, delete, remix, or draft it.
2. Stories support clearly identified clickable links to Hyper Local or approved
   external destinations.
3. The dedicated experience is called **Play** and uses `/play/`.
4. The Play page contains a Stories tray at the top and Reels below it.
5. Home shows Stories directly below the hero banner.
6. Home shows a compact **Play/Reels preview directly below Stories**. It is a
   poster-led horizontal discovery rail; selecting a card opens the immersive
   Reel viewer.
7. On desktop, Play starts with a responsive Reel grid. Selecting a card opens
   the immersive Reel player.
8. Play's placement in the primary mobile or desktop navigation is intentionally
   **deferred**. Do not replace Stores or another navigation item without a later
   product decision.

## 2. Product role

### Current implementation status

The first static Home Stories phase is implemented:

- Stories render immediately below the hero in both Home Builder and legacy
  Home layouts.
- Static fixtures demonstrate unseen, partially viewed, fully viewed, image,
  video, internal-link, buffering/error, and unavailable states.
- Seller fixtures use full-bleed uploaded photography; admin fixtures may use
  designed campaign creatives. The source header identifies Seller or Official
  publisher status.
- Each uploaded seller Story supports optional seller-authored `title` and
  `subtitle` fields. They appear over a protected lower-media gradient above the
  optional CTA, with responsive line limits to keep the image legible.
- Each Story exposes its view total, an animated customer like toggle, and
  native sharing with a copy-link fallback. The separate “24h” label is omitted
  because the Story itself expires after 24 hours. Static-phase likes are
  persisted locally under `hyperlocal-story-likes-v1`; backend totals and
  authenticated reactions will replace this fixture state later.
- Story progress and active video remain paused for the full lifetime of the
  native share sheet, including when that system dialog moves the pointer away
  from the viewer or resolves the Web Share promise before its window closes.
  Losing browser focus or hiding the document also pauses playback until the
  viewer is active again.
- Viewing progress is stored locally under `hyperlocal-story-progress-v1` until
  the backend Stories API and authenticated viewing history are available.
- The responsive Story viewer supports automatic progress, previous/next
  navigation, press-and-hold pause, swipe dismissal/navigation, keyboard
  controls, muted video, internal CTAs, and return-context preservation.
- Home orders sources as unseen, partially viewed, then fully viewed, with
  reduced-motion-aware layout animation when state changes.
- The tray supports native touch scrolling plus thresholded pointer dragging;
  ordinary clicks remain distinct from drag gestures.
- The viewer uses a lower media-caption treatment, source-accented CTA styling,
  a dark blurred backdrop, hover/focus navigation controls, and a one-time
  locally persisted gesture guide.
- Home Stories links to the dedicated `/play/` experience. Backend-managed
  content and primary navigation placement remain outside this implementation
  phase.

The first static Home Reels phase is also implemented:

- Ten seller/admin Reel fixtures appear in a responsive horizontal rail directly
  below Stories in both Home layouts.
- Home cards render local 9:16 posters only. They never mount or autoplay video.
- Selecting a card opens a portrait viewer with vertical swipe, Up/Down and J/K
  navigation, tap/Space pause, mute, like, share, views, optional internal
  product links, retry, and reduced-motion support.
- The immersive viewer uses a familiar right-side action rail, a short lower
  caption, a compact horizontally scrollable product strip, and a secondary pill
  CTA instead of a full-width banner action.
- Only the active Reel video is mounted. It uses `preload="metadata"`, keeps its
  poster visible while buffering, and uses a restrained delayed loading state.
  A persistent playback bar is intentionally omitted after UX review.
- The immediately following Reel may warm its poster and video metadata. All
  prior media is paused, detached, and released when the active Reel changes.
- Backend-managed content, pagination, and analytics remain future work.

The first static dedicated Play page is implemented:

- `/play/` uses the standard Hyper Local breadcrumb and plain page header inside
  the normal page container, matching catalog pages such as Categories.
- The shared Stories tray appears first, preserving the same seen-state storage,
  sorting, gestures, direct links, and immersive viewer as Home.
- Ten poster-only Reel cards render in a balanced, uniform 9:16 grid. The grid
  grows from two columns on mobile to three on tablet, four on intermediate
  desktop widths, and five when the main content container permits.
- Reel cards show duration, source, distance/delivery signals, and an optional
  product-count indicator. They do not show likes or comments and never mount
  video before selection.
- Selecting a card or opening a `?reel=<id>` link uses the existing immersive
  Reel viewer, so buffering, swipe/wheel/keyboard navigation, sharing, likes,
  optional products, optional CTA, and clean-view hold remain consistent with
  Home.
- The route is SSR-compatible, statically exportable, localized in English,
  Hindi, and Arabic, canonicalized for search, and included in sitemap
  generation.

Stories and Play are discovery surfaces for nearby commerce, not a separate
social network. Content should help customers answer questions such as:

- What is new or available near me?
- Which nearby store carries this product?
- What offer is active today?
- Can this store deliver to my selected location?
- What does the store, service, or product look like?

Familiar social patterns may be used where they reduce learning effort, but the
visual language must remain consistent with Hyper Local. Reels may expose a
customer like toggle; creation, saving, following, commenting, remixing, and
seller/customer social profiles remain excluded.

## 3. Information architecture

### 3.1 Home

Recommended vertical order:

1. Existing sticky header: location, search, account/cart controls.
2. Existing category tabs.
3. Existing hero banner.
4. **Stories tray.**
5. **Play/Reels preview rail.**
6. Existing commerce sections: categories, stores, brands, products, offers,
   delivery information, and other backend-managed Home sections.

The hero remains connected to the existing category/header surface. Stories sit
immediately after the hero and before the first ordinary commerce rail.

Home remains commerce-first:

- Stories scroll away with the page and are not sticky.
- Home Reels remain a compact preview rail; the full discovery experience
  belongs on Play.
- An empty or failed Stories module must not block the rest of Home.

### 3.2 Play (`/play/`)

Recommended vertical order:

1. Standard Hyper Local header or a compact Play header.
2. Page title: **Play**.
3. Stories tray.
4. Reels section.
5. Pagination/loading boundary.

Mobile uses a vertically immersive Reel experience after Reel selection.
Desktop and larger tablets use a responsive Reel discovery grid first, then an
immersive player after selection.

### 3.3 Entry points

Confirmed entry points:

- Tapping a Story on Home.
- Tapping a Story on Play.
- Direct Story or Reel links.
- Opening `/play/` directly.

Deferred entry points:

- A Play item in the mobile bottom navigation.
- A Play item in the desktop navigation.

## 4. Stories tray

### 4.1 Content and ordering

Each tray item represents a grouped Story source such as a store, brand,
campaign, neighborhood, or Hyper Local announcement. It does not represent the
customer's own profile.

Recommended ranking order:

1. Currently active and deliverable to the selected location.
2. Admin-pinned campaigns.
3. Unseen Stories.
4. Selected Home category relevance.
5. Distance, availability, and delivery eligibility.
6. Freshness.
7. Fully seen Stories.

Content outside the selected delivery area should normally be omitted. New data
must not reorder items beneath a customer while they are actively browsing the
tray.

### 4.2 Visual specification

#### Mobile: below 768px

- Full-width horizontal rail.
- Outer horizontal padding: 16px.
- Item gap: 10–12px.
- Cover diameter: 60–64px.
- Ring width: 3px with a 2px surface-colored gap around the cover.
- Item width: approximately 72–80px.
- Label: one line, 12–13px, ellipsized.
- Show a partially visible final item when the tray overflows.

#### Tablet: 768–1023px

- Use the page content container.
- Cover diameter: 64–68px.
- Maintain touch-friendly spacing and horizontal scrolling.
- Show navigation controls only when overflow exists and pointer input is
  available.

#### Desktop: 1024px and above

- Align with the existing Home or Play content container.
- Cover diameter: 68–72px.
- Support pointer drag, wheel/trackpad, and keyboard navigation.
- Show subtle previous/next controls when overflow exists.
- Keep a small number of Stories left-aligned; do not stretch or center them.

### 4.3 Ring states

- **Unseen:** high-contrast Hyper Local brand-accent ring.
- **Partially seen:** segmented accent and neutral treatment where item counts
  remain visually readable.
- **Fully seen:** neutral gray ring with slightly reduced cover emphasis.
- **Focused:** visible focus outline outside the state ring.
- **Unavailable:** do not keep a dead entry in the tray after refresh.

State cannot be communicated by color alone. Accessible labels should include
the source and state, for example, “Fresh Basket, three unseen Stories.”

### 4.4 Labels and metadata

Use customer-friendly source labels:

- Store or brand name.
- Campaign title.
- Neighborhood.
- “Weekend Deals” or “Near You.”

Do not use social usernames unless that is genuinely the source's public name.
The full label remains available to assistive technology and may be exposed on
desktop hover/focus.

### 4.5 Explicitly prohibited UI

Never show:

- “Your Story.”
- Add or plus badges.
- Camera or upload buttons.
- Draft, publishing, processing, or upload-error states.
- Editing/deletion controls.
- Reply, reaction, follow, or comment actions.

## 5. Story viewer

### 5.1 Mobile presentation

- Full-screen, edge-to-edge media surface.
- Respect top and bottom safe-area insets.
- Segmented progress bars at the top.
- Source identity and concise hyperlocal context below the progress bars.
- Explicit close control.
- High-contrast CTA near the bottom when a link is configured.
- Hide the standard header and bottom navigation while the viewer is open.

Conceptual structure:

```text
┌─────────────────────────────┐
│ segmented progress       ×  │
│ source · 1.2 km · Open      │
│                             │
│        image / video        │
│                             │
│       optional caption      │
│                             │
│     [View offer / Shop]     │
└─────────────────────────────┘
```

### 5.2 Desktop presentation

- Darkened modal or dedicated immersive overlay.
- Centered portrait viewer approximately 420–480px wide.
- Optional dimmed previous/next previews at the sides.
- Close control remains clearly visible.
- Left/Right arrows navigate, Space pauses/resumes, and Escape closes.
- Focus remains trapped within the viewer until it closes.
- Use a dark blurred backdrop that preserves focus on the portrait Story.
- Keep edge navigation targets large, but reveal their arrow icons only on
  viewer hover or keyboard focus.

### 5.3 Navigation and timing

- Tap/click left region: previous item.
- Tap/click right region: next item.
- Horizontal swipe changes sources: right opens the next source and left opens
  the previous source. Taps continue advancing items within the current source.
- Press and hold: pause; release: resume.
- Image duration: approximately five seconds by default, extended when necessary
  for meaningful text.
- Video progress follows playback duration.
- Buffering, app focus loss, CTA activation, and accessibility reading pause
  progress.
- Finishing a source advances to the next eligible source.
- Closing returns to the exact originating page and tray position.

CTA controls take priority over viewer navigation zones so link taps can never
accidentally advance the Story.

### 5.4 Story links

Prefer one obvious CTA per Story:

- Shop now.
- View product.
- Visit store.
- Browse offer.
- Get directions.
- Learn more.

Rules:

- Use a high-contrast button or clearly labelled chip; do not use invisible
  hotspots.
- Internal product, store, category, search, and offer links stay within Hyper
  Local.
- Approved external links expose their destination domain and use the
  platform's external-link flow.
- Invalid, unsafe, or expired links are disabled with a short explanation.
- Activating a link pauses the Story.
- Returning restores the same Story and its prior playback intent.

### 5.5 Seen state

- Mark an individual Story seen after meaningful display.
- Mark the source fully seen only after all currently active items are seen.
- Update the tray after closing without reordering or shifting it.
- Preserve seen state across ordinary navigation and sessions where practical.

## 6. Play Reels discovery

### 6.1 Home preview

- A single horizontally scrollable 9:16 rail sits directly below Stories.
- Cards show poster, duration, short title, source, and view count; media does
  not autoplay or mount in the rail.
- The rail supports touch scrolling, trackpad/wheel input, pointer dragging,
  scroll snapping, keyboard focus, and reduced motion.
- Selecting a card opens the same immersive viewer behavior planned for Play.
- `See all` points to `/play/`; the dedicated page may remain reserved until its
  later implementation phase.

### 6.2 Mobile

The Reel section uses a compact two-column 9:16 grid. Selecting a Reel opens the
vertical immersive player, which shows one Reel per viewport.

- Swipe up/down: next/previous Reel.
- Tap media: pause/resume.
- Visible mute/unmute control.
- Loop the active Reel until the customer navigates.
- Do not advance automatically merely because playback finishes.
- Keep the product/store CTA visible without covering essential media.
- Share may be offered for distributing curated content.

### 6.3 Tablet

- Two or three Reel columns depending on available width.
- Every card preserves the same 9:16 ratio without stretching.
- Selection opens a centered immersive player.
- Touch, pointer, and keyboard input must all remain supported.

### 6.4 Desktop

Play opens with a responsive Reel grid:

- All cards use the same portrait geometry. Priority is expressed through order
  and metadata instead of changing card spans.
- Three columns at smaller desktop widths.
- Four or five columns when the content container permits.
- Card width generally falls between 220px and 280px.
- Poster frames load before video.
- Optional silent hover preview begins only after a short intent delay and stops
  immediately when hover/focus leaves.
- Keyboard focus exposes the same information as hover.

Selecting a card opens an immersive player:

- Centered portrait media approximately 420–520px wide.
- Source, caption, hyperlocal metadata, and CTA in a side panel when space
  permits.
- Up/Down or J/K navigates.
- Space pauses/resumes.
- M toggles mute.
- Escape returns to the grid and restores the originating card position.

### 6.5 Reel card content

- 9:16 poster or cover.
- Duration badge.
- Short title or caption preview.
- Store, brand, or campaign source.
- Up to two useful local signals, for example `1.2 km · 20–30 min`.
- Optional offer or product indicator.

Do not show like or comment counts on discovery cards. The immersive viewer may
show likes in its action rail. Comments and Save are currently omitted. Show view
counts only when the data is trustworthy and useful; location and commercial
relevance have higher priority.

### 6.6 Reel player overlays

Recommended hierarchy:

1. Media.
2. Subtle top and bottom readability gradients.
3. Back/close and mute controls.
4. Source identity and concise caption.
5. Hyperlocal metadata.
6. Compact horizontal product strip with square imagery, one-line name, price,
   and an optional internal product destination. The strip supports direct touch
   swiping, pointer dragging, trackpad/wheel input, and momentum-like scrolling.
7. Optional small store/category CTA beneath the product strip.
8. Right-side action rail for Like, Share, and sound.
9. Do not add a persistent timeline solely for decoration. If seeking becomes a
   future requirement, use a deliberate accessible scrubber treatment.

Never show creation, remix, follow, comments, Save, or draft controls. Like must
represent real toggle state.

## 7. Hyperlocal metadata rules

Useful signals include:

- Neighborhood.
- Distance.
- Delivery estimate.
- “Delivers to you.”
- Store open/closed state.
- Offer expiry.
- Product availability.

Show no more than two signals at once on a card or compact viewer header. Detailed
information belongs in the linked product/store destination.

Changing the selected location or Home category refreshes eligible Stories and
Reels while retaining seen state for content that remains available.

## 8. State restoration and navigation

Opening a Story or Reel should preserve:

- Originating route.
- Page scroll offset.
- Horizontal tray or grid position.
- Selected source and media item.
- Playback time where practical.
- Playing/paused and sound intent.

Browser Back closes the viewer before leaving the originating page. Returning
from a linked product or store restores the viewer or page position where
practical. A direct link to expired content shows an unavailable state with a
clear route to Play or Home.

## 9. Loading, empty, error, and offline states

### 9.1 Stories

- **Loading:** circular covers and short label skeletons using final dimensions.
- **Empty on Home:** hide the whole module so commerce content moves up naturally.
- **Empty on Play:** show a compact message; Reels remain available.
- **Partial failure:** keep successful entries and use a neutral cover fallback.
- **Tray failure:** provide a compact retry on Play; collapse the module on Home
  after retry fails.
- **Direct unavailable Story:** explain that it is unavailable and offer Return
  to Home or Open Play.

### 9.2 Reels

- **Loading:** stable 9:16 poster-shaped skeletons.
- **Player loading:** keep the poster visible with a restrained delayed spinner.
- **Empty:** show a compact Play empty state with links to categories or nearby
  stores.
- **Playback failure:** retain source information and any available CTA; offer
  Retry and Next.
- **Pagination failure:** keep existing Reels usable and show retry at the
  boundary.
- **Offline:** play cached content, then show a finite offline boundary instead
  of an endless loader.

## 10. Accessibility

- Minimum target size: 44×44px; prefer 48×48px for primary mobile controls.
- Provide captions for spoken video content.
- Never place essential offer information only inside media.
- Use descriptive screen-reader labels for source, state, position, playback,
  duration, sound, and destination.
- Do not move focus when the next Story or Reel is preloaded.
- Trap focus inside desktop viewers and restore focus to the originating item.
- Provide visible keyboard focus.
- Every gesture has a visible or keyboard-accessible equivalent.
- Reduced-motion mode removes decorative transitions, hover autoplay, zooming,
  and automatic photo panning.
- Paused and muted states are communicated with text or accessible labels, not
  iconography alone.
- Ensure adequate contrast over both bright and dark media.
- Mirror directional layout appropriately for Arabic/RTL.

## 11. Performance perception

Loading priority:

1. Visible Story covers and Reel posters.
2. Active media.
3. Immediately adjacent Story/Reel.
4. Next likely content.
5. Distant content only when needed.

Additional rules:

- Never autoplay Story or Reel video in the Home tray.
- Only the active immersive Reel may play.
- Use poster frames immediately and retain them during buffering or media
  eviction.
- Feed responses should contain Reel identity, publisher, counts, metadata,
  poster URL, and media URL; media bytes are fetched from those URLs separately.
- A Reel card never requests its video merely because it appears in the feed.
- The active video uses HTTP byte-range/progressive buffering and
  `preload="metadata"`; playback must start from an initial buffer instead of
  waiting for the complete file.
- The media host/CDN must answer byte ranges with `206 Partial Content` and a
  valid `Content-Range`. A plain MP4 host that ignores ranges may still transfer
  the complete file despite the frontend preload setting.
- YouTube Shorts-style adaptive chunks require backend/CDN-generated HLS or DASH
  manifests and multiple encoded renditions. Until those exist, use verified
  range-capable progressive MP4 delivery.
- Load an image or cover as one resource because the current backend does not
  provide smaller responsive variants.
- Preload at most the immediately following Reel's poster and video metadata.
  Stop, detach, and release previous media when moving forward.
- Pagination limits feed records only; it does not authorize eager media
  downloads. Fetch the next record page shortly before the current page is
  exhausted.
- Keep the poster visible and show a delayed loading indicator until the active
  video has buffered enough to play. On slow connections, retain useful source
  information and any available CTA while playback waits.
- Mount and request video only for the active Story. Use metadata-only preload,
  HTTP range-capable delivery, and progressive buffering instead of downloading
  every Story video when the tray opens.
- Pause playback when the tab/app is hidden.
- Delay desktop hover previews until user intent is clear.
- Adapt media quality and preloading to connection conditions.
- Avoid layout shift by reserving final media and label dimensions.

## 12. Visual design alignment

- Use Hyper Local's existing typography, brand color, semantic colors, and icon
  family.
- Use branded Story rings rather than copying another platform's gradient.
- Keep Reel cards consistent with existing card radius and surface treatments.
- Prefer tonal separation and media gradients over heavy shadows.
- Support the existing light and dark themes.
- Use safe-area padding for full-screen viewers and the existing responsive page
  container for trays and grids.

## 13. Component inventory

This is a product-level inventory, not an implementation prescription:

- Reusable Stories section with caller-provided groups and progress scope.
- Reusable Reels section with caller-provided items and rail/grid layout.
- Play page.
- Stories tray.
- Story source item and state ring.
- Story viewer.
- Story progress indicator.
- Story CTA.
- Story empty/error/unavailable states.
- Reel discovery grid.
- Reel card.
- Immersive Reel player.
- Reel metadata/CTA panel.
- Media poster, loading, buffering, and error states.
- Location/distance badge.
- External-link confirmation.
- Viewer navigation controls.

### 13.1 Reusable implementation boundary

The canonical discovery components live outside page-specific views:

- `src/components/Stories/StoriesSection.tsx`
- `src/components/Reels/ReelsSection.tsx`

Both components accept their media collections, section identity, spacing,
labels, destination, shared-item selection, and navigation behavior through
props. Open media is tracked by its stable content ID, so a refreshed or
reordered backend result does not silently switch the selected item. Story
progress can be locally persisted with an isolated storage key, kept in memory,
or controlled by the caller through viewed IDs and a view callback. This allows
Home, Play, category pages, seller pages, and future backend-driven surfaces to
reuse the same cards, sorting, gestures, viewers, and accessibility behavior
without copying implementation code.

The files under `src/views/homePage/HomeStories.tsx` and
`src/views/homePage/HomeReels.tsx` are intentionally thin Home configuration
wrappers. New non-Home surfaces should import the canonical section components
directly.

## 14. Interaction inventory

Customers may:

- Scroll and select Stories.
- Navigate, pause, resume, or close a Story.
- Follow a clearly labelled Story link.
- Browse the Play Reel grid.
- Open, pause, resume, mute, or navigate Reels.
- Like a Reel.
- Horizontally browse Reel-linked products and open an internal product result.
- Open a linked product, store, category, or offer.
- Share a Reel when enabled.
- Use hover previews on supported desktop devices.
- Use keyboard navigation and accessible alternatives.

Customers may not:

- Create, upload, edit, or delete content.
- Publish comments or reactions, follow accounts, remix, or duet.
- Save or publish drafts.
- Encounter a misleading “Your Story” or add-content affordance.

## 15. Deferred decisions

The following require explicit future product decisions:

1. Whether Play becomes a mobile bottom-navigation destination.
2. Whether Play appears in desktop navigation.
3. If a nav slot is needed, whether an existing destination moves elsewhere.
4. When the static Home Reel preview switches to backend-managed pagination and
   product associations.
5. Whether external Story links require a confirmation step in all cases or only
   for untrusted domains.

Until those decisions are made, implementation should not infer them.

## 16. Acceptance principles

The experience is successful when:

- Home remains recognizably a fast hyperlocal shopping storefront.
- Stories are immediately discoverable below the hero without disrupting search,
  location, categories, or commerce sections.
- Play is fully usable across mobile, tablet, desktop, keyboard, touch, pointer,
  light mode, dark mode, and RTL.
- Every visible control reflects a real view-only customer capability.
- Story links are obvious, safe, and preserve return context.
- Media loading or failure never blocks access to linked commerce destinations.
- No social creation affordance appears anywhere in the customer experience.
