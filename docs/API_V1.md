# API v1 (production contract)

- **Base path:** `/api/v1`
- **Legacy:** Unversioned routes under `/api/*` are **unchanged** (same URLs and JSON shapes as before).
- **Envelope:** All v1 responses use:

```json
{
  "status": true,
  "message": "…",
  "data": { },
  "errors": null,
  "meta": null
}
```

Errors use `"status": false` and populate `errors` / `message` as appropriate.

## Authentication (Laravel Sanctum)

1. `POST /api/v1/auth/login` with JSON `{ "index_number": "…", "password": "…" }`
2. Read `data.token` from the response.
3. Send `Authorization: Bearer <token>` on protected routes.
4. `POST /api/v1/auth/logout` revokes the current token.

Optional: set `SANCTUM_TOKEN_EXPIRATION` (minutes) in `.env` and `expiration` in `config/sanctum.php` (null = no expiry).

## Rate limits

- **Login:** `api-v1-login` — 5 requests / minute / IP  
- **Other v1 routes:** `api-v1` — 60 requests / minute / user id or IP  

Defined in `AppServiceProvider::boot()`.

## Endpoints

| Method | Path | Auth | Notes |
|--------|------|------|--------|
| POST | `/api/v1/auth/login` | No | Returns `data.user` (includes `attendance_data_version`), `data.token`, `data.token_type` |
| POST | `/api/v1/auth/logout` | Bearer | |
| GET | `/api/v1/auth/me` | Bearer | `data.user` — authenticated student profile |
| GET | `/api/v1/profile` | Bearer | `data.user` — same fields as `StudentApiPayload`, includes `weekly_timetable` (lectures + credit-hour progress) |
| GET | `/api/v1/sessions` | Bearer | Paginated active sessions (`page`, `per_page`); response `meta.pagination` |
| GET | `/api/v1/sessions/active` | Bearer | Query: `course_id`, `class_id`, `include_missed_warnings`, `min_missed`, `lookback_days` — `data.sessions`, optional `warnings` / `warnings_map`; each session includes `credit_hours` |
| POST | `/api/v1/attendance` | Bearer | Same body as legacy `POST /api/attendance`; server injects `index_number` from the token |
| GET | `/api/v1/settings` | No | Cached 60s; `meta.cached_seconds` |
| GET | `/api/v1/students/removed` | No | Same as legacy `GET /api/students/removed` — deleted indexes for cache sync |
| GET | `/api/v1/students/status` | No | Same as legacy — `was_removed` vs unknown index |

## Validation & errors

- `422`: validation — `errors` is a Laravel-style field map.  
- `401`: missing/invalid token (`api/v1/*` only uses the strict envelope for `AuthenticationException`).  
- Legacy `/api/*` error JSON is unchanged.

## HTTPS (production)

- `/api/v1/*` enforces HTTPS in production.
- Insecure requests are redirected with HTTP `308` to `https://...`.

## Caching

- `GET /api/v1/settings` uses `Cache::remember` (60s). Do not cache user-specific attendance data here.

## Attendance data resets (admin)

When an admin clears weeks/sessions/attendance for a course, class, or the whole system, the server:

- Increments `data.attendance_data_version` (integer, starts at 0).
- Sets `data.last_attendance_reset_at` (ISO8601 or `null` if never reset).
- Sends FCM **data** `action=attendance_data_reset` (plus `attendance_data_version`, `scope`) to students in affected classes.
- Broadcasts (if `BROADCAST_DRIVER` is not `log`/`null`) on public channels `app.attendance.sync` and `class.{classId}.sync` as `attendance.data_reset`.

**Flutter:** Persist `attendance_data_version` (also included in `data.user` from login/profile and in legacy `POST /api/login`). When it increases vs your stored value, **clear local attendance SQLite** and run `GET /api/attendance/sync` (credentials), then recompute sidebar stats from the new rows. Same check on FCM `attendance_data_reset`. `GET /api/attendance/sync` also returns `attendance_data_version` at the top level.

## Logging

- `api.v1.login.success` / `api.v1.login.failed` — auth  
- `api.v1.ACTIVE_SESSIONS` — session list counts  
- `api.v1.sessions/active failed` — exceptions  

Extend with `Log::info` / `Log::warning` in controllers as needed.

## Postman

Import a collection with:

- Base URL: `{{baseUrl}}` = e.g. `http://localhost:8000`
- Folder **Auth:** POST `{{baseUrl}}/api/v1/auth/login` → save `data.token` to collection variable `token`
- Authorization type **Bearer Token** = `{{token}}` for protected requests.

## Swagger / OpenAPI

Not generated in-repo; add `darka1/laravel-swagger` or `knuckleswtf/scribe` later if you want interactive docs.
