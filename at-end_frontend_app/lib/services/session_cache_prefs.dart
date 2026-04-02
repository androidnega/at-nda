import 'package:shared_preferences/shared_preferences.dart';

/// Cleared on each dashboard / attendance refresh so no stale session is reused.
class SessionCachePrefs {
  SessionCachePrefs._();

  static const String key = 'active_session_cache';

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
  }
}
