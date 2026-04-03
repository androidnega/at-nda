import 'package:shared_preferences/shared_preferences.dart';

/// Limits how often a student can change **display name** or **profile photo** (not phone/email).
/// Default: 90 days between identity edits — same rule on web and mobile.
abstract final class ProfileIdentityCooldown {
  static const _key = 'profile_identity_last_edit_ms';
  static const Duration period = Duration(days: 90);

  static Future<DateTime?> _lastEdit() async {
    final prefs = await SharedPreferences.getInstance();
    final v = prefs.getInt(_key);
    if (v == null) return null;
    return DateTime.fromMillisecondsSinceEpoch(v);
  }

  static Future<bool> canEditIdentity() async {
    final last = await _lastEdit();
    if (last == null) return true;
    return DateTime.now().difference(last) >= period;
  }

  /// Call after a successful name change or photo change.
  static Future<void> recordIdentityEdit() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_key, DateTime.now().millisecondsSinceEpoch);
  }

  static Future<String?> nextAllowedHint() async {
    final last = await _lastEdit();
    if (last == null) return null;
    final next = last.add(period);
    if (DateTime.now().isAfter(next)) return null;
    final d = next.year;
    final m = next.month.toString().padLeft(2, '0');
    final day = next.day.toString().padLeft(2, '0');
    return 'Name or photo can be updated again on $d-$m-$day.';
  }
}
