import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Persists last successful mark for dashboard when no active session is shown.
class LastAttendancePrefs {
  LastAttendancePrefs._();

  static const String _key = 'last_attendance';

  static Future<void> save({
    required int sessionId,
    required String courseName,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _key,
      jsonEncode({
        'session_id': sessionId,
        'course': courseName,
        'time': DateTime.now().toIso8601String(),
      }),
    );
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }

  /// Loads stored mark if within 60 minutes.
  /// If [currentActiveSessionIds] is non-empty and stored [session_id] is not in the set, clears storage.
  /// Else if [currentActiveSessionId] is set and differs from stored [session_id], clears storage.
  static Future<Map<String, dynamic>?> load({
    int? currentActiveSessionId,
    Set<int>? currentActiveSessionIds,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null) return null;

    Map<String, dynamic> decoded;
    try {
      decoded = jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      await prefs.remove(_key);
      return null;
    }

    final sidRaw = decoded['session_id'];
    final sidInt = sidRaw is int
        ? sidRaw
        : sidRaw is num
            ? sidRaw.toInt()
            : int.tryParse(sidRaw?.toString() ?? '');

    if (currentActiveSessionIds != null &&
        currentActiveSessionIds.isNotEmpty) {
      if (sidInt != null && !currentActiveSessionIds.contains(sidInt)) {
        await prefs.remove(_key);
        return null;
      }
    } else if (currentActiveSessionId != null &&
        sidInt != null &&
        sidInt != currentActiveSessionId) {
      await prefs.remove(_key);
      return null;
    }

    final timeStr = decoded['time']?.toString();
    if (timeStr == null) {
      await prefs.remove(_key);
      return null;
    }

    try {
      final marked = DateTime.parse(timeStr);
      final minutes = DateTime.now().difference(marked).inMinutes;
      if (minutes > 60) {
        await prefs.remove(_key);
        return null;
      }
    } catch (_) {
      await prefs.remove(_key);
      return null;
    }

    return decoded;
  }

  /// Session id from the last stored mark (no time limit). Used to open attendance records for class reps.
  static Future<int?> getLastMarkedSessionId() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null) return null;
    try {
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      final sidRaw = decoded['session_id'];
      if (sidRaw is int) return sidRaw;
      if (sidRaw is num) return sidRaw.toInt();
      return int.tryParse(sidRaw?.toString() ?? '');
    } catch (_) {
      return null;
    }
  }
}
