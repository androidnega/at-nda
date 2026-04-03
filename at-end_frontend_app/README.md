# Attendance App (Flutter)

Student-focused attendance app that connects to a Laravel backend. Supports login, onboarding, location-based attendance, face verification, QR scanning, and offline sync.

## Setup

1. **Install dependencies**
   ```bash
   flutter pub get
   ```

2. **Configure Laravel server URL**
   Edit `lib/utils/constants.dart` and set `baseUrl` to your Laravel server's local IP:
   ```dart
   static const String baseUrl = 'http://192.168.1.100:8000/api';
   ```

3. **Run Laravel on your server**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

4. **Find your server IP**
   - Windows: `ipconfig`
   - Mac/Linux: `ifconfig`

## Run

```bash
flutter run
```

## Project structure

```
lib/
├── main.dart
├── models/
│   ├── student.dart
│   └── attendance_record.dart
├── services/
│   ├── api_service.dart
│   ├── location_service.dart
│   ├── face_service.dart
│   └── offline_service.dart
├── pages/
│   ├── login_page.dart
│   ├── onboarding_page.dart
│   ├── attendance_page.dart
│   ├── qr_scan_page.dart
│   └── sync_status_page.dart
├── widgets/
│   └── custom_button.dart
└── utils/
    └── constants.dart
```

## Laravel API expectations

- `POST /api/login` – index number + phone; returns `needs_onboarding` for first-time users
- `POST /api/onboarding` – profile photo + face descriptor
- `GET /api/session/active` – active session config (lat, lng, range_meters, face_verification, qr_enabled)

## Developer

Manuel
