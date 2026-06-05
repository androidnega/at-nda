# a-tenda mobile ↔ Laravel backend — sync notes

This document lives **inside `at-end_frontend_app/`** on purpose. The mobile
client is a separate project from the Laravel backend — please do not move
this file into the backend tree and please do not commit backend-only
changes alongside mobile-only changes.

Last refreshed: 2026-06-05.

## What the backend just changed

The Laravel side rolled out a set of attendance-integrity, fraud-prevention,
and performance improvements. Everything below is already live behind
`/api/v1/*` and the legacy `/api/*` routes — but the mobile app has to opt
in to make the most of them.

### 1. API versioning (action required: low)

Every `/api/*` response now carries:

| Header             | Value                                            |
| ------------------ | ------------------------------------------------ |
| `X-Api-Version`    | `v1` for `/api/v1/*`, `legacy` for the rest      |
| `X-Api-Supported`  | `v1` (the highest version this build understands) |
| `Deprecation`      | `true` (only on legacy responses)                |
| `Sunset`           | `2027-06-01` (legacy retirement target)          |
| `Link`             | `</api/v1>; rel="successor-version"`             |

A new helper, `lib/services/api_version_watcher.dart`, listens for these
headers. Any place that already has an `http.Response` in hand should call:

```dart
ApiVersionWatcher.instance.inspect(response);
```

Then any widget can react via `ListenableBuilder(listenable: ApiVersionWatcher.instance, ...)`
to show a one-time "please update the app" banner when
`shouldShowUpdateBanner` is true. This will start firing automatically as
the sunset date approaches; no manual flag flips needed.

### 2. Rotating QR code (action required: medium)

`GET /api/v1/sessions/current-qr/{session}` already returns the live signed
token. On the **web** rep page we now poll every `ttl-2` seconds and
re-render the image, which produces a brand-new HMAC-signed token on every
poll. Screenshots of the QR die within the TTL window (default 18 seconds).

For the Flutter rep session screen (`lib/pages/rep_session_page.dart`):

1. After opening a session, kick off a `Timer.periodic` (TTL-2 seconds)
   that calls the current-QR endpoint.
2. Use the returned `payload_raw` string (or `payload.token`) as the QR
   image data instead of the cached `qr_token` from `activeSession`.
3. Drop the timer on dispose / when the session ends.

The student-side scanner does not need to change — the signed token is
validated server-side; whatever the student scans is accepted as long as
the signature is intact and the inner `expires_at` has not elapsed.

### 3. Single QR / manual code on the student side

The web student page now hides the manual session-code input by default
and only reveals it after a scan failure (camera unavailable / decode
error / invalid code). The Flutter `attendance_page.dart` should do the
same: do not show the manual entry textbox until the user explicitly taps
"Can't scan?" or until the camera plugin reports failure.

### 4. Per-row attendance details (location, device, IP)

`GET /api/v1/attendance/{session}/records` and the per-student endpoints
now include:

- `lat`, `lng` (nullable doubles)
- `device_ip` (string)
- `user_agent` (string, capped at 255 chars)
- `marked_manually_by_id` (nullable int) — present if a rep recorded the row
- `manual_reason` (nullable string)

The mobile rep "student detail" page should surface these the same way the
web view does:

- Coordinates as a tappable link to Google Maps (`https://www.google.com/maps?q=LAT,LNG`).
- A small badge ("Manual mark") next to the row when `marked_manually_by_id` is set.

### 5. Manual mark + delete for reps

Two new rep endpoints on the web side; both are gated by middleware so
they only work for authenticated class reps:

- `POST  /classrep/courses/{course}/attendance/manual-mark`
  - Body: `student_id`, `week_number` (optional, defaults to current),
    `reason` (required), `status` (`present` | `late`, optional).
  - Creates a backdated `AttendanceSession` if no live session exists for
    that week. Always closed in the past.
- `POST  /classrep/attendance/{attendance}/delete`
  - Body: `reason` (required).
  - Only succeeds when `allow_rep_attendance_deletion` is true on the
    super-admin settings page; otherwise returns HTTP 403.

These are currently only wired through the **web** dashboard, not the
mobile `ClassRepApiController`. When you're ready to expose them in the
app, mirror the methods on `lib/services/api_service.dart` and have them
post to the `/api/v1/class-rep/*` equivalents (or add them now). All
deletions and manual marks are written to the `audit_logs` table on the
backend, so we keep an immutable trail.

### 6. Redis cache toggle

The super-admin settings page now exposes a "Cache & Redis" panel. When
the admin flips the cache driver to Redis and the connection succeeds,
attendance-mark locking and short-lived caching go through Redis — which
removes the "resource exhausted" failures on shared hosting under load.
**Nothing changes for the mobile app**; the existing endpoints are
unchanged. This note is purely informational so you know why mark latency
might drop noticeably after the admin enables it.

### 7. Week numbering

Cancelled weeks are now skipped when computing the next week label, so
the visible `week_number` stays small after an admin reset. The mobile app
already renders whatever number the backend sends, so no code change is
needed here either — just verify that any local cache of week numbers
gets invalidated when the rep opens a new session.

## Architectural rule

To keep the projects clean:

- **Mobile code** lives only in `at-end_frontend_app/`. Anything Flutter
  related (Dart, Gradle, iOS, etc.) belongs here.
- **Backend code** lives in the Laravel tree (`app/`, `routes/`, `resources/`,
  `database/`, etc.). It must never depend on anything inside
  `at-end_frontend_app/`.
- New API endpoints should be added **only** under `routes/api/v1.php`.
  Legacy unversioned routes are frozen — patch bugs only.

## Suggested follow-up tasks (mobile)

In rough priority order:

1. Wire `ApiVersionWatcher.instance.inspect(response)` into the existing
   `ApiService` helpers so the watcher catches every response without
   each call site having to remember.
2. Rep session page: rotate the displayed QR every 18 seconds using
   `/api/v1/sessions/current-qr/{session}`.
3. Student attendance page: hide the manual code field by default.
4. Rep "student detail" page: render lat / lng / device / IP per row.
5. Add `manualMarkAttendance` and `deleteAttendance` methods to
   `ApiService`, plus screens that call them, behind a "rep tools" menu.
6. Add a top-level banner that listens to `ApiVersionWatcher.instance`
   and prompts the user when `shouldShowUpdateBanner` is true.

Once these land, the mobile app will be feature-parity with the web client
and the legacy `/api/*` calls can be retired on `2027-06-01`.
