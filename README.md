# Lightweight Offline-First Attendance System

Laravel + SQLite + Tailwind CDN + Vanilla JS. No heavy frameworks.

## Setup

```bash
# Install dependencies (already done)
composer install

# Configure .env (SQLite is default)
# DB_CONNECTION=sqlite

# Run migrations
php artisan migrate

# Seed sample data (optional)
php artisan db:seed
```

## Run

**Option A – Artisan (recommended for dev):**
```bash
php artisan serve
```
Visit http://localhost:8000

**Option B – XAMPP:**
Point your browser to `http://localhost/at-nda/public/` (or configure a vhost).

## Flow

### Student
1. Open home page or direct attendance link (e.g. `/attendance/1`)
2. Enter Index Number
3. Allow location access
4. Click Mark Attendance
5. **Online**: Saves to server. Shows ✅ Marked or ❌ Out of range
6. **Offline**: Saves to localStorage. Shows ⏳ Pending Sync. Syncs when back online.

### Admin
1. Go to `/admin`
2. Create courses (name, lat, lng, range in meters)
3. Share attendance link per course
4. View attendance records

## Sample Data (after seeding)

- **Students**: UEB123456, UEB789012
- **Course**: Introduction to Programming (Accra coords, 100m range)

## Tech Stack

- Laravel 13
- SQLite
- Tailwind CSS (CDN)
- Vanilla JavaScript
- localStorage for offline
