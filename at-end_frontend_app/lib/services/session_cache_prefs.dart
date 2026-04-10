import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Lightweight JSON cache so recently fetched data is still available offline.
class SessionCachePrefs {
  SessionCachePrefs._();

  static const String _legacyKey = 'active_session_cache';
  static const String _activePrefix = 'active_sessions_cache__';
  static const String _timetablePrefix = 'timetable_cache__';

  static String _normalizeIndex(String indexNumber) {
    return indexNumber
        .trim()
        .toUpperCase()
        .replaceAll(RegExp(r'[^A-Z0-9_-]'), '_');
  }

  static String _activeKey(String indexNumber) =>
      '$_activePrefix${_normalizeIndex(indexNumber)}';

  static String _timetableKey(String indexNumber) =>
      '$_timetablePrefix${_normalizeIndex(indexNumber)}';

  static Future<void> saveActiveSessions(
    String indexNumber,
    List<Map<String, dynamic>> sessions,
  ) async {
    if (indexNumber.trim().isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_activeKey(indexNumber), jsonEncode(sessions));
  }

  static Future<List<Map<String, dynamic>>> getActiveSessions(
    String indexNumber,
  ) async {
    if (indexNumber.trim().isEmpty) return const [];
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_activeKey(indexNumber));
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      final out = <Map<String, dynamic>>[];
      for (final e in decoded) {
        if (e is Map) out.add(Map<String, dynamic>.from(e));
      }
      return out;
    } catch (_) {
      return const [];
    }
  }

  static Future<void> saveTimetable(
    String indexNumber,
    Map<String, dynamic> timetableRoot,
  ) async {
    if (indexNumber.trim().isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_timetableKey(indexNumber), jsonEncode(timetableRoot));
  }

  static Future<Map<String, dynamic>?> getTimetable(String indexNumber) async {
    if (indexNumber.trim().isEmpty) return null;
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_timetableKey(indexNumber));
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return Map<String, dynamic>.from(decoded);
    } catch (_) {
      return null;
    }
  }

  static Future<void> clear({String? indexNumber}) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_legacyKey);
    if (indexNumber == null || indexNumber.trim().isEmpty) return;
    await prefs.remove(_activeKey(indexNumber));
    await prefs.remove(_timetableKey(indexNumber));
  }
}
