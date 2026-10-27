# Watch & Buy Backend Contract

The storefront is wired to the backend's Watch & Buy API. Backend resources
named `statuses` are presented as **Stories** throughout the customer UI.

## Endpoints

```text
GET /api/watch-and-buy/config
GET /api/watch-and-buy/reels
GET /api/watch-and-buy/profiles/{username}
GET /api/watch-and-buy/statuses
GET /api/watch-and-buy/profiles/{username}/statuses
PUT /api/watch-and-buy/reels/likes
PUT /api/watch-and-buy/statuses/seen
```

The frontend API client already appends `/api`, so source calls use paths such
as `/watch-and-buy/reels`.

## Configuration

`GET /watch-and-buy/config` returns:

```json
{
  "success": true,
  "message": "Watch & Buy configuration retrieved.",
  "data": {
    "enabled": true,
    "title": "Watch & Buy",
    "maxDurationSeconds": 60,
    "maxUploadMb": 50,
    "maxProductsPerReel": 10,
    "statusesEnabled": true,
    "maxStatusDurationSeconds": 15,
    "maxProductsPerStatus": 5,
    "effectiveMaxUploadMb": 50
  }
}
```

`enabled` controls both surfaces; `statusesEnabled` additionally controls the
Story tray.

## Reel feed

`GET /watch-and-buy/reels` requires `latitude` and `longitude`. Supported feed
parameters include `per_page`, `cursor`, and `session_id`.

```json
{
  "success": true,
  "data": {
    "items": [],
    "meta": {
      "session_id": "opaque-session",
      "next_cursor": null,
      "per_page": 10,
      "slug_not_found": false
    }
  }
}
```

The route and envelope have been validated, but the configured backend did not
have an active Reel in any public delivery zone during integration. The item
adapter therefore accepts the expected aliases defensively and rejects records
that do not contain both playable video and poster media.

Expected Reel content includes:

- stable `id` and preferably public `uuid`;
- title/caption;
- publisher or profile information;
- poster/thumbnail URL and video/media URL;
- duration, distance, and delivery estimate;
- views, likes, and authenticated `is_liked`;
- zero, one, or many products, each optionally carrying its own store and
  location context (`store.id`, `store.slug`, `store.name`,
  `store.distance_km`, and `store.distance_label`);
- optional CTA.

Store distance belongs to the individual linked product rather than the Reel:
a single Reel may promote products from multiple stores. The frontend displays
the backend `distance_label` and formats `distance_km` as a fallback.

## Stories

### Tray

`GET /watch-and-buy/statuses` is public and does not require coordinates. It is
an efficient profile-level tray response:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "profile": {
          "id": 1,
          "type": "platform",
          "username": "hyperlocal",
          "bio": "Official Hyperlocal profile",
          "photo_url": "https://cdn.example.com/profile.webp",
          "status": "active",
          "has_active_status": true,
          "has_unseen_status": true
        },
        "status_count": 7
      }
    ],
    "meta": {
      "session_id": "opaque-session",
      "next_cursor": null,
      "per_page": 10
    }
  }
}
```

When continuing tray pagination, send both the returned `cursor` and the same
`session_id`.

### Profile Stories

`GET /watch-and-buy/profiles/{username}/statuses` is public and does not require
coordinates. The frontend requests this only when the corresponding tray item
is opened (or when resolving a shared Story URL).

Observed Story fields are:

```json
{
  "id": 7,
  "uuid": "public-story-uuid",
  "source": "admin_direct",
  "content_type": "image",
  "text": "A nearby update",
  "media_url": "https://cdn.example.com/story.webp",
  "media_width": 1080,
  "media_height": 1920,
  "duration_ms": null,
  "seen_by_me": false,
  "products": [],
  "created_at": "2026-08-29T08:00:00Z",
  "expires_at": "2026-08-30T08:00:00Z"
}
```

Profile Story pagination uses `cursor` without a tray `session_id`. The
frontend uses `uuid` for public selection/share state and keeps numeric `id`
for authenticated mutations. Images with `duration_ms: null` display for five
seconds.

Backend profile type `platform` maps to frontend publisher type `admin`.

## Authenticated mutations

Authentication uses the existing customer bearer token interceptor.

### Like/unlike Reels

```http
PUT /api/watch-and-buy/reels/likes
Content-Type: application/json
Authorization: Bearer {customer-token}
```

```json
{
  "latitude": 23.242,
  "longitude": 69.6669,
  "items": [
    {
      "reel_id": 42,
      "liked": true
    }
  ]
}
```

The request validation contract is confirmed. A successful live mutation still
needs an active Reel record for final verification.

### Mark Stories seen

```http
PUT /api/watch-and-buy/statuses/seen
Content-Type: application/json
Authorization: Bearer {customer-token}
```

```json
{
  "status_ids": [7]
}
```

Successful response:

```json
{
  "success": true,
  "message": "Watch & Buy Stories marked as seen.",
  "data": [
    {
      "status_id": 7,
      "seen_at": "2026-08-29T09:44:12.778435Z"
    }
  ]
}
```

Both mutation endpoints require customer authentication and return `401` for
guests.

## Media delivery

Video media must support HTTP byte ranges for progressive playback. A sampled
Story MP4 returned `206 Partial Content` correctly. Production media responses
should additionally include explicit CORS and `Accept-Ranges: bytes` headers.

## Production hardening

Laravel debug output and Debugbar must be disabled in production. Invalid
profiles, cursors, or IDs must return a sanitized JSON `4xx` response and must
never expose stack traces, controller source locations, or absolute server
paths.
