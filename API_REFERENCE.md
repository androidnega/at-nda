# AT-NDA Laravel API Reference

API base URL: `/api` (e.g. `https://your-domain.com/api`)

---

## 1. Student API

**GET** `/api/students`

Returns JSON array of students.

### Query parameters (optional)
- `index_number` – filter by index
- `class_id` – filter by class
- `course_id` – filter by course (uses course's class)

### Response fields
| Field | Type | Description |
|-------|------|-------------|
| index_number | string | Student index |
| name | string | Full name |
| profile_image | string\|null | URL to profile image |
| class | string | Class name (e.g. Btech I.T Group A L100) |
| faculty | string | Faculty name |
| department | string | Department name |
| level | int | Level (100, 200, etc.) |
| phone | string | Phone number |
| has_password | bool | Whether student has set a password |
| face_descriptor | array | 128 floats (only if face verification enabled) |
| bound_ip | string | Bound IP (only if IP binding enabled) |

---

## 2. Active Session API

**GET** `/api/sessions/active`

Returns **all** currently active sessions. Shape is always:

```json
{
  "sessions": [
    {
      "id": 1,
      "course_name": "…",
      "course_code": "…",
      "venue": "…",
      "lecturer_name": "…",
      "mode": "qr",
      "lat": 5.123,
      "lng": -1.234,
      "range_meters": 150,
      "qr_token": "…",
      "end_time": "2025-03-19T10:00:00+00:00",
      "updated_at": "2025-03-19T09:00:00+00:00",
      "already_marked": false
    }
  ]
}
```

### Query parameters (optional)
- `course_id` – only sessions for that course
- `index_number` – used for `already_marked` and class inference
- `class_id` – filter by class (must match student when both sent)

Errors may return `{ "sessions": [], "message": "…" }` with 403/404/500.

---

## 3. Settings API

**GET** `/api/settings`

Returns global toggles.

### Response
```json
{
  "face_verification": true,
  "qr_code_enabled": true,
  "ip_binding_enabled": false,
  "require_password_on_first_login": true,
  "allow_multiple_index": true,
  "face_match_threshold": 0.5
}
```

---

## 4. Attendance Submission API

**POST** `/api/attendance`

### Request body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| index_number | string | Yes | Student index |
| course_id | int | Yes | Course ID |
| session_id | string | No | Session token (from QR code; alternative to qr_code) |
| qr_code | string | No | Session token (from QR scan) |
| lat | float | Yes* | Latitude |
| lng | float | Yes* | Longitude |
| face_descriptor | array | Yes** | 128 floats |
| timestamp | string | No | ISO8601 (defaults to now) |

\* Required when session has location  
\** Required when face verification is enabled

### Validation rules
- Student exists
- Active session exists
- Location within range (Haversine distance ≤ range_meters)
- Face matches stored descriptor (if face verification enabled)
- QR code valid (if QR mode and session_token provided)
- Device IP matches bound IP (if IP binding enabled)

### Response
```json
{ "status": "success", "message": "Attendance marked" }
```
```json
{ "status": "success", "message": "Already marked" }
```
```json
{ "status": "error", "message": "..." }
```

### Error (422)
```json
{
  "status": "error",
  "message": "Out of range",
  "distance": 120
}
```

---

## 5. Admin Dashboard (Web)

| Action | Route |
|--------|-------|
| Dashboard | `/dashboard` |
| Courses | `/dashboard/courses` |
| Create/Edit Course | `/dashboard/courses/create`, `/dashboard/courses/{id}/edit` |
| Sessions | `/dashboard/portal` |
| Open/Close Session | POST `/dashboard/sessions`, POST `/dashboard/sessions/{id}/close` |
| Session QR | `/dashboard/sessions/{id}/qr` |
| Settings | `/dashboard/settings` |
| Students | `/dashboard/students` |
| Classes | `/dashboard/classes` |

### Settings (admin)
- Face verification (on/off)
- QR code enabled (on/off)
- IP binding (on/off)
- Allow multiple index per device (on/off)
- Face match threshold (0.2–1.0)

---

## 6. Data Structure (Laravel)

### Students
| Column | Type |
|--------|------|
| id | bigint |
| index_number | string |
| first_name | string |
| last_name | string |
| profile_image | string |
| face_descriptor | json |
| phone_number | string |
| bound_ip | string |
| ... | |

### Courses
| Column | Type |
|--------|------|
| id | bigint |
| course_code | string |
| course_name | string |
| lecturer_name | string |
| venue | string |
| location_lat, location_lng | decimal |
| attendance_range_m | int |
| ... | |

### Attendance Sessions
| Column | Type |
|--------|------|
| id | bigint |
| course_id | int |
| attendance_week_id | int |
| is_active | bool |
| mode | string (location, qr, hybrid) |
| venue | from course |
| location_lat, location_lng | decimal |
| attendance_range_m | int |
| session_token | string |
| expires_at | timestamp |
| ... | |

### Attendances
| Column | Type |
|--------|------|
| id | bigint |
| student_id | int |
| course_id | int |
| attendance_session_id | int |
| attendance_week_id | int |
| attendance_time | timestamp |
| status | string |
| ... | |

---

## Flutter Mobile App Integration

- **Student login:** `/student/login` (web) or index number for API
- **Local SQLite:** Cache students, settings, active sessions
- **Offline queue:** Store attendance locally, sync via POST `/api/attendance` or POST `/attendance/sync`
- **Homepage:** Material cards with course list from `/api/sessions/active`
- **Attendance flow:** Location → Face (if enabled) → QR (if enabled)
