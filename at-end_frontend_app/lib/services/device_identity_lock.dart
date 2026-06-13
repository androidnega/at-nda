import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// One device → one student. Prevents:
///   (a) logging in with two different index numbers on the same phone, and
///   (b) marking attendance for two different students in the same session on
///       the same phone.
///
/// Both rules are enforced client-side as a safety net; the server still has
/// the final word via `AttendanceFraudGuard` and the new `device_id` check in
/// `AttendanceController::markAttendance`. The lock can only be cleared via
/// [reset] (used by administrators / institutional support) — a normal logout
/// keeps the lock intact so the same phone cannot just log out → log into
/// another index to bypass.
class DeviceIdentityLock {
  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const String _kBoundIndex = 'device_bound_index_number';
  static const String _kBoundFirstSeenAt = 'device_bound_first_seen_at';
  static const String _kLastSession = 'device_last_session_id';
  static const String _kLastSessionIndex = 'device_last_session_index';

  /// Normalise an index number to a canonical form (upper / trimmed). Email
  /// usernames stay lower-case; everything else is upper-cased to match what
  /// the API stores.
  static String canonicalize(String raw) {
    final v = raw.trim();
    if (v.isEmpty) return '';
    if (v.contains('@')) return v.toLowerCase();
    return v.toUpperCase();
  }

  /// Returns the index this device is bound to (if any).
  static Future<String?> boundIndex() async {
    final v = await _storage.read(key: _kBoundIndex);
    if (v == null) return null;
    final t = v.trim();
    return t.isEmpty ? null : t;
  }

  /// True if [index] matches the bound one, OR the device is unbound.
  static Future<bool> isAllowedForLogin(String index) async {
    final bound = await boundIndex();
    if (bound == null || bound.isEmpty) return true;
    return canonicalize(index) == bound;
  }

  /// Record the FIRST successful login. Subsequent calls with the same index
  /// are a no-op; calls with a DIFFERENT index are ignored (caller must run
  /// [isAllowedForLogin] first and reject).
  static Future<void> recordSuccessfulLogin(String index) async {
    final canon = canonicalize(index);
    if (canon.isEmpty) return;
    final existing = await boundIndex();
    if (existing == null) {
      await _storage.write(key: _kBoundIndex, value: canon);
      await _storage.write(
        key: _kBoundFirstSeenAt,
        value: DateTime.now().toIso8601String(),
      );
    }
  }

  /// First-bind timestamp for the "Bound to this device since…" line on the
  /// profile screen. Null if no bind happened yet.
  static Future<DateTime?> firstSeenAt() async {
    final v = await _storage.read(key: _kBoundFirstSeenAt);
    if (v == null || v.trim().isEmpty) return null;
    return DateTime.tryParse(v);
  }

  /// Hostile signal for the UI: a different index is trying to sign in.
  /// Returns the index this device was bound to, or null if this device is
  /// not bound yet (i.e. login is allowed).
  static Future<String?> conflictingBoundIndex(String attemptedIndex) async {
    final bound = await boundIndex();
    if (bound == null) return null;
    final attempt = canonicalize(attemptedIndex);
    return attempt == bound ? null : bound;
  }

  /// Remember which session+student this device most recently marked. Lets
  /// the attendance page block "swap accounts then mark again" before the
  /// network request.
  static Future<void> rememberSessionMark({
    required int sessionId,
    required String indexNumber,
  }) async {
    if (sessionId <= 0) return;
    final canon = canonicalize(indexNumber);
    if (canon.isEmpty) return;
    await _storage.write(key: _kLastSession, value: sessionId.toString());
    await _storage.write(key: _kLastSessionIndex, value: canon);
  }

  /// Returns the index that already marked attendance from THIS device for
  /// [sessionId], if it was a *different* student. Null = OK to proceed.
  static Future<String?> conflictingMarkForSession({
    required int sessionId,
    required String indexNumber,
  }) async {
    if (sessionId <= 0) return null;
    final stored = await _storage.read(key: _kLastSession);
    if (stored == null || stored.trim().isEmpty) return null;
    final storedSession = int.tryParse(stored.trim());
    if (storedSession != sessionId) return null;
    final storedIndex = (await _storage.read(key: _kLastSessionIndex))?.trim();
    if (storedIndex == null || storedIndex.isEmpty) return null;
    final attempt = canonicalize(indexNumber);
    return attempt == storedIndex ? null : storedIndex;
  }

  /// Wipe the bind. Reserved for institutional support flows (factory-reset
  /// the phone-binding when a student loses access or transfers devices).
  static Future<void> reset() async {
    await _storage.delete(key: _kBoundIndex);
    await _storage.delete(key: _kBoundFirstSeenAt);
    await _storage.delete(key: _kLastSession);
    await _storage.delete(key: _kLastSessionIndex);
  }
}
