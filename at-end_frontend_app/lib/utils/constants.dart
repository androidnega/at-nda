/// App-wide constants.
/// [baseUrl] MUST include `/api` (no trailing slash). Override for local Laravel:
/// `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api`
class Constants {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://at-enda.manuelcode.info/api',
  );

  /// When true: login accepts any index + password locally (no student API lookup).
  /// When false: login uses Laravel API (POST /api/login).
  static const bool localAuthOnly = false;

  /// When API has no active session, use this demo session so the flow completes.
  /// Set false to require a real session from Laravel.
  static const bool useDemoActiveSessionWhenEmpty = false;

  /// Demo session (wide GPS range, face/QR off for easier testing).
  static Map<String, dynamic> get demoActiveSession => {
        'id': 1,
        'active': true,
        'course_id': 1,
        'course_code': 'DEMO-101',
        'course_title': 'Demo Course — Attendance Lab',
        'course_name': 'Demo Course — Attendance Lab',
        'week_id': 1,
        'lecturer_name': 'Dr. Demo',
        'venue': 'Main Campus — Hall A',
        'lat': 5.6037,
        'lng': -0.1870,
        'range_meters': 50000.0,
        'mode': 'location',
        'location_required': true,
        'requires_qr_proof': false,
        'qr_enabled': false,
        'face_verification': false,
        'ip_binding': false,
        'remaining_minutes': 90,
        // ISO8601 — Flutter countdown uses this (preferred over decrementing minutes).
        'end_time': DateTime.now().add(const Duration(hours: 2)).toIso8601String(),
      };

  /// Face match threshold for TFLite verification (lower = stricter).
  static const double faceMatchThreshold = 0.5;

  /// Default GPS range in meters for attendance validation.
  static const double defaultRangeMeters = 50.0;

  /// When true: home shows raw GET /sessions/active body (for phone debugging).
  /// Set to true while testing; keep false for production.
  static const bool debugShowSessionApiResponseOnHome = false;

  /// Shared secret for HMAC-signed attendance QRs (must match Laravel).
  /// **Do not hardcode production secrets in source.** Use compile-time defines:
  /// `flutter run --dart-define=QR_SECRET=your_key` or CI/CD secrets for release builds.
  static const String qrSecret = String.fromEnvironment(
    'QR_SECRET',
    defaultValue: '',
  );

  /// Laravel `saveProfileImageFromBase64` expects a data URL prefix.
  static String jpegDataUriFromRawBase64(String rawBase64) =>
      'data:image/jpeg;base64,$rawBase64';
}
