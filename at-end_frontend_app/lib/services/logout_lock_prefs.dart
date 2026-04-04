import 'package:shared_preferences/shared_preferences.dart';

/// Stay signed in: no logout for 30 days after a fresh login. After that window,
/// the user may log out. If they open the app again while still signed in without
/// having logged out, the lock extends by 90 days (repeats for each period).
class LogoutLockPrefs {
  static const _kUntilMs = 'logout_locked_until_ms';
  static const _kGraceUsed = 'logout_lock_grace_used';

  static Future<DateTime?> lockedUntil() async {
    final p = await SharedPreferences.getInstance();
    final v = p.getInt(_kUntilMs);
    if (v == null) return null;
    return DateTime.fromMillisecondsSinceEpoch(v);
  }

  /// Call after a successful online API login (or localAuthOnly signup).
  static Future<void> recordFreshLoginBinding() async {
    final p = await SharedPreferences.getInstance();
    await p.remove(_kGraceUsed);
    final until = DateTime.now().add(const Duration(days: 30));
    await p.setInt(_kUntilMs, until.millisecondsSinceEpoch);
  }

  /// After the lock end: first resume = grace (logout allowed). Next resume while
  /// still signed in extends [until] by 90 days.
  static Future<void> applyGracePeriodAndExtension({
    required bool hasSession,
  }) async {
    if (!hasSession) return;
    final p = await SharedPreferences.getInstance();
    final v = p.getInt(_kUntilMs);
    if (v == null) return;
    var until = DateTime.fromMillisecondsSinceEpoch(v);
    final now = DateTime.now();
    if (!now.isAfter(until)) {
      await p.remove(_kGraceUsed);
      return;
    }
    final graceUsed = p.getBool(_kGraceUsed) ?? false;
    if (!graceUsed) {
      await p.setBool(_kGraceUsed, true);
      return;
    }
    await p.remove(_kGraceUsed);
    until = until.add(const Duration(days: 90));
    await p.setInt(_kUntilMs, until.millisecondsSinceEpoch);
  }

  /// No stored lock → allow logout (existing installs).
  static Future<bool> canLogoutNow() async {
    final u = await lockedUntil();
    if (u == null) return true;
    return DateTime.now().isAfter(u);
  }

  static Future<void> clear() async {
    final p = await SharedPreferences.getInstance();
    await p.remove(_kUntilMs);
    await p.remove(_kGraceUsed);
  }

  /// Short hint when logout is blocked (null if allowed or no lock).
  static Future<String?> signOutBlockedHint() async {
    final u = await lockedUntil();
    if (u == null) return null;
    if (await canLogoutNow()) return null;
    final y = u.year;
    final m = u.month.toString().padLeft(2, '0');
    final d = u.day.toString().padLeft(2, '0');
    return 'Sign out unlocks on $y-$m-$d (this device stays signed in until then).';
  }
}
