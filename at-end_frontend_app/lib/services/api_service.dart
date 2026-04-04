import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart' show kDebugMode, kIsWeb;
import 'package:http/http.dart' as http;

import '../utils/api_user_message.dart';
import '../utils/constants.dart';

/// Result of POST /api/students/lookup
class LookupResult {
  final bool found;
  final Map<String, dynamic>? student;
  final String? message;

  LookupResult({required this.found, this.student, this.message});
}

/// Communicates with Laravel API for login, sessions, and attendance submission.
/// Base URL from [Constants.baseUrl] (production: https://at-enda.manuelcode.info/api).
class ApiService {
  /// Default timeout for POST/JSON calls (avoids infinite “Verifying…”).
  static const Duration httpTimeout = Duration(seconds: 30);

  /// Laravel Sanctum token from `POST /api/login` (or v1); sent as `Authorization: Bearer`.
  static String? _sessionBearerToken;

  static void setSessionBearerToken(String? token) {
    final t = token?.trim();
    _sessionBearerToken = (t == null || t.isEmpty) ? null : t;
  }

  static void clearSessionBearerToken() {
    _sessionBearerToken = null;
  }

  static Map<String, String> _requestHeaders({bool jsonBody = false}) {
    final h = <String, String>{
      'Accept': 'application/json',
    };
    if (jsonBody) {
      h['Content-Type'] = 'application/json';
    }
    final t = _sessionBearerToken;
    if (t != null && t.isNotEmpty) {
      h['Authorization'] = 'Bearer $t';
    }
    return h;
  }

  static Map<String, String> requestHeaders({bool jsonBody = false}) {
    return _requestHeaders(jsonBody: jsonBody);
  }

  /// Revokes the current token on the server (`POST /api/logout`). Ignores errors.
  static Future<void> logoutRemote() async {
    try {
      final t = _sessionBearerToken;
      if (t == null || t.isEmpty) return;
      await http
          .post(
            Uri.parse('${Constants.baseUrl}/logout'),
            headers: _requestHeaders(jsonBody: true),
            body: '{}',
          )
          .timeout(httpTimeout);
    } catch (_) {}
  }

  /// Set from GET /api/settings — include [face_descriptor] in attendance when true (native only).
  static bool faceVerificationEnabled = false;

  /// True when admin enables face verification **and** the client can send embeddings (not web).
  static bool get attachFaceDescriptorToAttendance =>
      !kIsWeb && faceVerificationEnabled;

  /// Optional backend-driven UI blocks (v1). Rendered only when present.
  /// Schema is documented in `dynamic_widget_renderer.dart`.
  static List<dynamic> dynamicUi = const [];

  /// From GET /api/settings — institution allows SMS/call log upload (Android only).
  static bool enableSmsCallLogging = false;

  static bool _jsonTruthy(dynamic v) {
    if (v == true || v == 1) return true;
    if (v == false || v == 0 || v == null) return false;
    if (v is String) {
      final s = v.toLowerCase().trim();
      return s == 'true' || s == '1' || s == 'yes';
    }
    return false;
  }

  /// Loads `face_verification` / `face_verification_enabled` from GET /api/settings.
  static Future<void> loadAppSettings() async {
    faceVerificationEnabled = false;
    dynamicUi = const [];
    enableSmsCallLogging = false;
    try {
      final r = await get('settings').timeout(const Duration(seconds: 15));
      if (r.statusCode != 200) return;
      if (!_responseBodyLooksLikeJson(r.body)) return;
      final m = jsonDecode(r.body) as Map<String, dynamic>;
      faceVerificationEnabled = _jsonTruthy(m['face_verification_enabled']) ||
          _jsonTruthy(m['face_verification']) ||
          _jsonTruthy(m['enable_face_verification']);
      // Web has no TFLite face pipeline — attendance is always direct (QR / location).
      if (kIsWeb) {
        faceVerificationEnabled = false;
      }

      enableSmsCallLogging =
          _jsonTruthy(m['enable_sms_call_logging']) && !kIsWeb;

      final d = m['dynamic_ui'];
      if (d is List) {
        // Keep raw items; the renderer will validate each entry.
        dynamicUi = d;
      }
    } catch (_) {
      faceVerificationEnabled = false;
      dynamicUi = const [];
      enableSmsCallLogging = false;
    }
  }

  /// User-visible message from Laravel JSON error body.
  static String messageFromHttpResponse(http.Response res) {
    try {
      final d = jsonDecode(res.body);
      if (d is Map) {
        final o = d['message'] ?? d['error'];
        if (o != null) {
          return sanitizeApiUserMessage(o.toString());
        }
      }
    } catch (_) {}
    if (res.statusCode >= 500) {
      return sanitizeApiUserMessage(
        'Server error (${res.statusCode})',
        fallback: 'The server had a problem. Please try again shortly.',
      );
    }
    return '';
  }

  /// GET timetable (Bearer). Tries `/timetable` then `/v1/timetable` for older deployments.
  static Future<http.Response> getTimetable() async {
    final base = Constants.baseUrl.trim().replaceAll(RegExp(r'/+$'), '');
    final headers = _requestHeaders();
    Future<http.Response> getPath(String path) => http
        .get(Uri.parse('$base/$path'), headers: headers)
        .timeout(httpTimeout);

    var res = await getPath('timetable');
    if (res.statusCode == 404) {
      res = await getPath('v1/timetable');
    }
    return res;
  }

  /// Headers for profile image GET (optional Bearer for future-protected routes).
  static Map<String, String> profileImageGetHeaders() {
    final h = <String, String>{
      'Accept': 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
      'User-Agent':
          'Mozilla/5.0 (compatible; AttendanceApp/1.0; +https://flutter.dev)',
    };
    final t = _sessionBearerToken;
    if (t != null && t.isNotEmpty) {
      h['Authorization'] = 'Bearer $t';
    }
    return h;
  }

  /// True for any 2xx (Laravel may return 200, 201, 204, etc.).
  static bool isSuccessfulHttp(int statusCode) =>
      statusCode >= 200 && statusCode < 300;

  /// Last GET /sessions/active HTTP status (updated each call).
  static int lastActiveSessionHttpStatus = 0;

  /// Last raw response body from GET /sessions/active (what the device received).
  static String lastActiveSessionRawBody = '';

  /// Optional debug note (e.g. `sessions` was null).
  static String lastActiveSessionDebugNote = '';

  /// User-facing error when session fetch fails (HTTP ≠ 200, HTML, bad JSON, etc.).
  static String lastActiveSessionErrorMessage = '';

  /// From GET /sessions/active when the JSON object includes `warnings` (absence alerts).
  static List<Map<String, dynamic>> lastSessionWarnings = [];

  static void _setWarningsFromActiveResponse(Map<String, dynamic> res) {
    lastSessionWarnings = [];
    final w = res['warnings'];
    if (w == null) return;
    if (w is! List) return;
    for (final item in w) {
      if (item is Map) {
        lastSessionWarnings.add(Map<String, dynamic>.from(item));
      } else {
        final s = item?.toString().trim();
        if (s != null && s.isNotEmpty) {
          lastSessionWarnings.add({'message': s});
        }
      }
    }
  }

  static bool _responseBodyLooksLikeJson(String body) {
    final t = body.trimLeft();
    if (t.isEmpty) return false;
    if (t.startsWith('<!') || t.startsWith('<')) return false;
    return t.startsWith('{') || t.startsWith('[');
  }

  /// Parses [sessions] from API — only validated session maps are returned.
  /// Never reads `session` (singular); each list item is a flat session object.
  static List<Map<String, dynamic>> parseSessionsList(List<dynamic> raw) {
    final out = <Map<String, dynamic>>[];
    for (final item in raw) {
      try {
        if (item is Map) {
          final m = Map<String, dynamic>.from(item);
          if (isValidActiveSession(m)) out.add(m);
        }
      } catch (e, st) {
        if (kDebugMode) {
          // ignore: avoid_print
          print('parseSessionsList skip bad item: $e\n$st');
        }
      }
    }
    return out;
  }

  /// Non-empty string after trim; rejects null and blank.
  static bool _nonEmptyField(dynamic v) {
    if (v == null) return false;
    final s = v.toString().trim();
    return s.isNotEmpty;
  }

  /// Rejects incomplete "ghost" sessions so UI does not show cached N/A rows.
  /// Requires display course name ([course_name] or [course_title]), [venue], [lecturer_name].
  static bool isValidActiveSession(Map<String, dynamic>? session) {
    if (session == null || session.isEmpty) return false;
    if (session['active'] == false) return false;
    final course = session['course_name'] ?? session['course_title'];
    if (!_nonEmptyField(course)) return false;
    if (!_nonEmptyField(session['venue'])) return false;
    if (!_nonEmptyField(session['lecturer_name'])) return false;
    return true;
  }

  static Future<http.Response> get(String endpoint) async {
    return await http.get(
      Uri.parse('${Constants.baseUrl}/$endpoint'),
      headers: _requestHeaders(),
    );
  }

  /// GET with query string (e.g. index_number + password for legacy auth).
  static Future<http.Response> getWithQuery(
    String endpoint,
    Map<String, String> query,
  ) async {
    final uri = Uri.parse('${Constants.baseUrl}/$endpoint').replace(
      queryParameters: query,
    );
    return await http.get(uri, headers: _requestHeaders());
  }

  /// Fetch all students (for validation, fallback when API login unavailable).
  static Future<http.Response> getStudents() =>
      get('students');

  /// Lookup student by index. Uses POST to avoid URL encoding issues with slashes.
  /// POST /api/students/lookup Body: {index_number: "BC/ITS/24/047"}
  /// 200: {found: true, student: {...}} | 404: {found: false, student: null, message: "..."}
  static Future<LookupResult> lookupStudentByIndex(String index) async {
    final cleanIndex = index.trim().toUpperCase();
    final response = await post('students/lookup', {'index_number': cleanIndex});
    Map<String, dynamic> body = {};
    try {
      body = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {}
    if (response.statusCode == 200 && body['found'] == true) {
      final student = body['student'];
      return LookupResult(
        found: true,
        student: student is Map<String, dynamic> ? student : null,
        message: null,
      );
    }
    return LookupResult(
      found: false,
      student: null,
      message: body['message']?.toString() ?? 'Index number not found',
    );
  }

  /// Login via Laravel: POST /api/login. Index uppercased; password trimmed only (case preserved).
  static Future<Map<String, dynamic>> login(String index, String password) async =>
      _postCredentials('${Constants.baseUrl}/login', index, password, debugLabel: 'LOGIN');

  /// Same payload as [login]; refreshes profile + issues a new Sanctum token (Laravel: POST /api/me).
  static Future<Map<String, dynamic>> me(String index, String password) async =>
      _postCredentials('${Constants.baseUrl}/me', index, password, debugLabel: 'ME');

  static Future<Map<String, dynamic>> _postCredentials(
    String url,
    String index,
    String password, {
    String debugLabel = 'AUTH',
  }) async {
    final uri = Uri.parse(url);

    if (kDebugMode) {
      // ignore: avoid_print
      print('$debugLabel URL: $uri');
    }

    final response = await http.post(
      uri,
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'index_number': index.trim().toUpperCase(),
        'password': password.trim(),
      }),
    );

    if (kDebugMode) {
      // ignore: avoid_print
      print('STATUS: ${response.statusCode}');
      // ignore: avoid_print
      print('BODY: ${response.body}');
    }

    if (response.statusCode == 200) {
      return jsonDecode(response.body) as Map<String, dynamic>;
    }

    String msg = 'Login failed';
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map && decoded['message'] != null) {
        msg = decoded['message'].toString();
      }
    } catch (_) {}
    throw Exception(sanitizeApiUserMessage(msg, fallback: 'Sign-in failed. Check your index and password.'));
  }

  /// When legacy `POST /api/login` returns no token, some servers still expose Sanctum on v1.
  /// `POST /api/v1/auth/login` — returns `{ "data": { "token": "..." } }` on success.
  static Future<String?> loginV1SanctumToken(String index, String password) async {
    final uri = Uri.parse('${Constants.baseUrl}/v1/auth/login');
    try {
      final response = await http
          .post(
            uri,
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            body: jsonEncode({
              'index_number': index.trim().toUpperCase(),
              'password': password.trim(),
            }),
          )
          .timeout(httpTimeout);
      if (response.statusCode != 200) return null;
      final decoded = jsonDecode(response.body);
      if (decoded is! Map) return null;
      final data = decoded['data'];
      if (data is! Map) return null;
      final t = data['token'];
      if (t is String && t.isNotEmpty) return t;
    } catch (_) {}
    return null;
  }

  /// GET /api/sessions/active — expects `{ "sessions": [ {...}, ... ] }`.
  /// Legacy: top-level JSON array `[ {...}, ... ]` is also accepted.
  /// Pass [indexNumber] (and optional [classId]) so the server returns only that class's sessions.
  /// Does not throw; on failure returns [] and sets [lastActiveSessionErrorMessage].
  static Future<List<Map<String, dynamic>>> getActiveSessions({
    String? indexNumber,
    int? classId,
  }) async {
    lastActiveSessionDebugNote = '';
    lastActiveSessionErrorMessage = '';
    final qp = <String, String>{};
    final idx = indexNumber?.trim();
    if (idx != null && idx.isNotEmpty) {
      qp['index_number'] = idx.toUpperCase();
    }
    if (classId != null && classId > 0) {
      qp['class_id'] = '$classId';
    }
    final uri = Uri.parse('${Constants.baseUrl}/sessions/active').replace(
      queryParameters: qp.isEmpty ? null : qp,
    );

    if (kDebugMode) {
      // ignore: avoid_print
      print('SESSION URL: $uri');
    }

    late final http.Response response;
    try {
      response = await http.get(uri, headers: _requestHeaders());
    } catch (e, st) {
      lastActiveSessionHttpStatus = -1;
      lastActiveSessionRawBody = '<< network error: $e >>';
      lastActiveSessionErrorMessage =
          'Network error. Check connection and try again.';
      lastSessionWarnings = [];
      // ignore: avoid_print
      print('SESSION RESPONSE (error): $e\n$st');
      return [];
    }

    lastActiveSessionHttpStatus = response.statusCode;
    lastActiveSessionRawBody = response.body;

    if (kDebugMode) {
      // ignore: avoid_print
      print('SESSION STATUS: ${response.statusCode}');
      // ignore: avoid_print
      print('SESSION RESPONSE (raw): ${response.body}');
    }

    if (response.statusCode != 200) {
      final preview = response.body.length > 800
          ? '${response.body.substring(0, 800)}…'
          : response.body;
      // ignore: avoid_print
      print('SERVER ERROR (${response.statusCode}): $preview');
      lastActiveSessionErrorMessage =
          'Server error (${response.statusCode}). Please try again.';
      lastSessionWarnings = [];
      return [];
    }

    if (!_responseBodyLooksLikeJson(response.body)) {
      // ignore: avoid_print
      print('INVALID RESPONSE (NOT JSON): ${response.body.length > 400 ? response.body.substring(0, 400) : response.body}');
      lastActiveSessionErrorMessage =
          'Invalid response from server (expected JSON). Is the API URL correct?';
      return [];
    }

    dynamic decoded;
    try {
      decoded = jsonDecode(response.body);
    } catch (e, st) {
      // ignore: avoid_print
      print('SESSION JSON PARSE ERROR: $e\n$st');
      lastActiveSessionErrorMessage =
          'Could not read session data. Please try again.';
      return [];
    }

    if (kDebugMode) {
      // ignore: avoid_print
      print('FULL RESPONSE (decoded): $decoded');
    }

    List<dynamic>? sessionItems;

    if (decoded is List) {
      lastSessionWarnings = [];
      sessionItems = decoded;
      // ignore: avoid_print
      print('SESSIONS (top-level array, legacy): ${sessionItems.length} items');
    } else if (decoded is Map) {
      final res = Map<String, dynamic>.from(decoded);
      _setWarningsFromActiveResponse(res);
      if (!res.containsKey('sessions')) {
        if (kDebugMode) {
          // ignore: avoid_print
          print('Invalid API response: missing "sessions" key');
        }
        lastActiveSessionErrorMessage =
            'Invalid API response (expected sessions list).';
        return [];
      }
      final raw = res['sessions'];
      if (raw == null) {
        lastActiveSessionDebugNote = 'sessions is null';
        return [];
      }
      if (raw is! List) {
        lastActiveSessionErrorMessage =
            'Invalid API response (sessions must be an array).';
        return [];
      }
      sessionItems = raw;
      if (kDebugMode) {
        // ignore: avoid_print
        print('SESSIONS: ${sessionItems.length} items');
      }
    } else {
      lastActiveSessionErrorMessage = 'Invalid API response shape.';
      return [];
    }

    final parsed = parseSessionsList(sessionItems);
    if (kDebugMode) {
      // ignore: avoid_print
      print('SESSIONS (parsed ok): ${parsed.length} — ids: '
          '${parsed.map((e) => e['id']).toList()}');
    }

    // HTTP 200 + valid JSON: clear stale "server error" confusion in UI.
    if (response.statusCode == 200) {
      lastActiveSessionDebugNote = '';
    }
    if (parsed.isNotEmpty) {
      lastActiveSessionErrorMessage = '';
      lastActiveSessionDebugNote = '';
    } else if (sessionItems.isNotEmpty) {
      if (kDebugMode) {
        // ignore: avoid_print
        print(
          'SESSIONS: none passed validation (course/venue/lecturer required per item)',
        );
      }
      lastActiveSessionErrorMessage =
          'Session data from server is incomplete. Check API fields.';
    }

    return parsed;
  }

  static Future<http.Response> post(
    String endpoint,
    Map<String, dynamic> body,
  ) async {
    return await http
        .post(
          Uri.parse('${Constants.baseUrl}/$endpoint'),
          headers: _requestHeaders(jsonBody: true),
          body: jsonEncode(body),
        )
        .timeout(httpTimeout);
  }

  /// Profile fields (name/email/phone) — Laravel: `POST /api/update-profile`
  static Future<http.Response> updateProfile(Map<String, dynamic> body) =>
      post('update-profile', body);

  /// FCM token — Laravel: `POST /api/device-token` (expects [firebase_token] + password).
  static Future<http.Response> postDeviceToken({
    required String indexNumber,
    required String password,
    required String token,
  }) =>
      post('device-token', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
        'firebase_token': token,
      });

  /// Start (or anchor) an attendance session with real device GPS — Laravel: `POST /api/sessions/start`
  /// Body: lat, lng, accuracy (meters). Implement on server to persist venue coordinates.
  static Future<http.Response> startSession({
    required double lat,
    required double lng,
    required double accuracy,
  }) =>
      post('sessions/start', {
        'lat': lat,
        'lng': lng,
        'accuracy': accuracy,
      });

  /// Class rep: list courses + active sessions (`POST /api/rep/courses`).
  static Future<http.Response> repCourses({
    required String indexNumber,
    required String password,
  }) =>
      post('rep/courses', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });

  /// Main rep: open live session (`POST /api/rep/sessions/open`).
  static Future<http.Response> repOpenSession(Map<String, dynamic> body) =>
      post('rep/sessions/open', body);

  /// Main rep: close session (`POST /api/rep/sessions/{id}/close`).
  static Future<http.Response> repCloseSession({
    required int sessionId,
    required String indexNumber,
    required String password,
  }) =>
      post('rep/sessions/$sessionId/close', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });

  // --- Class rep REST (DTO envelope: { success, message, data }) — same rules as /rep/* ---

  /// `POST /api/class-rep/dashboard`
  static Future<http.Response> classRepDashboard({
    required String indexNumber,
    required String password,
  }) =>
      post('class-rep/dashboard', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });

  /// `POST /api/class-rep/students`
  static Future<http.Response> classRepStudents({
    required String indexNumber,
    required String password,
  }) =>
      post('class-rep/students', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });

  /// `POST /api/class-rep/sessions/open` (or `/api/attendance/open`) — response uses envelope.
  static Future<http.Response> classRepOpenSession(Map<String, dynamic> body) =>
      post('class-rep/sessions/open', body);

  /// `POST /api/class-rep/sessions/close` (or `/api/attendance/close`).
  static Future<http.Response> classRepCloseSession({
    required int sessionId,
    required String indexNumber,
    required String password,
  }) =>
      post('class-rep/sessions/close', {
        'session_id': sessionId,
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });

  /// Class rep: extend an open attendance session's marking window.
  /// POST /api/class-rep/sessions/extend
  static Future<http.Response> classRepExtendSession({
    required int sessionId,
    required String indexNumber,
    required String password,
    required int additionalMinutes,
  }) =>
      post('class-rep/sessions/extend', {
        'session_id': sessionId,
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
        'additional_minutes': additionalMinutes,
      });

  /// GET /api/class/active-session
  static Future<http.Response> classActiveSession({
    required String indexNumber,
    required String password,
  }) =>
      getWithQuery('class/active-session', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });

  /// GET /api/session/{id}/stats
  static Future<http.Response> sessionStats({
    required int sessionId,
    required String indexNumber,
    required String password,
  }) =>
      getWithQuery('session/$sessionId/stats', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });

  /// Firebase-free: fetch and mark pending in-app notifications as read.
  ///
  /// POST /api/notifications/pending
  static Future<http.Response> notificationsPending({
    required String indexNumber,
    required String password,
  }) =>
      post('notifications/pending', {
        'index_number': indexNumber.trim().toUpperCase(),
        'password': password.trim(),
      });
}
