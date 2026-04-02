import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

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

  /// Set from GET /api/settings — include [face_descriptor] in attendance when true.
  static bool faceVerificationEnabled = false;

  /// Loads `face_verification` / `face_verification_enabled` from GET /api/settings.
  static Future<void> loadAppSettings() async {
    faceVerificationEnabled = false;
    try {
      final r = await get('settings').timeout(const Duration(seconds: 15));
      if (r.statusCode != 200) return;
      if (!_responseBodyLooksLikeJson(r.body)) return;
      final m = jsonDecode(r.body) as Map<String, dynamic>;
      faceVerificationEnabled = m['face_verification_enabled'] == true ||
          m['face_verification'] == true ||
          m['enable_face_verification'] == true;
    } catch (_) {
      faceVerificationEnabled = false;
    }
  }

  /// User-visible message from Laravel JSON error body.
  static String messageFromHttpResponse(http.Response res) {
    try {
      final d = jsonDecode(res.body);
      if (d is Map) {
        final o = d['message'] ?? d['error'];
        if (o != null) return o.toString();
      }
    } catch (_) {}
    return '';
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
    return await http.get(Uri.parse('${Constants.baseUrl}/$endpoint'));
  }

  /// Fetch all students (for validation, fallback when API login unavailable).
  static Future<http.Response> getStudents() =>
      get('students');

  /// Lookup student by index. Uses POST to avoid URL encoding issues with slashes.
  /// POST /api/students/lookup Body: {index_number: "BC/ITD/24/001"}
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
  static Future<Map<String, dynamic>> login(String index, String password) async {
    final uri = Uri.parse('${Constants.baseUrl}/login');

    if (kDebugMode) {
      // ignore: avoid_print
      print('LOGIN URL: $uri');
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
    throw Exception(msg);
  }

  /// GET /api/sessions/active — expects `{ "sessions": [ {...}, ... ] }`.
  /// Legacy: top-level JSON array `[ {...}, ... ]` is also accepted.
  /// Does not throw; on failure returns [] and sets [lastActiveSessionErrorMessage].
  static Future<List<Map<String, dynamic>>> getActiveSessions() async {
    lastActiveSessionDebugNote = '';
    lastActiveSessionErrorMessage = '';
    final uri = Uri.parse('${Constants.baseUrl}/sessions/active');

    if (kDebugMode) {
      // ignore: avoid_print
      print('SESSION URL: $uri');
    }

    late final http.Response response;
    try {
      response = await http.get(uri);
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
          headers: {'Content-Type': 'application/json'},
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
}
