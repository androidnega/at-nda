import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/services.dart' show PlatformException;
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// OS-level biometric (fingerprint / Face ID) gate for the attendance
/// flow. Wraps `local_auth` so the rest of the app never has to think
/// about platform branching, web stubs, or "feature missing" errors.
///
/// Usage:
/// ```dart
/// final ok = await BiometricService.authenticate(
///   reason: 'Confirm with biometrics before marking attendance',
/// );
/// if (!ok) return;
/// ```
///
/// On unsupported platforms (web, devices without enrolled biometrics
/// when the user has not opted out) we return `true` so the existing
/// flow keeps working — the gate is *enforcing*, not *blocking*.
class BiometricService {
  BiometricService._();

  static final LocalAuthentication _auth = LocalAuthentication();
  static const String _prefsEnabledKey = 'biometric_required_for_mark';

  /// `true` when the device has biometric hardware and the OS reports
  /// at least one enrolled credential. Cached for the lifetime of the
  /// app launch since it can't change without a settings round-trip.
  static bool? _cachedAvailable;

  /// Whether the user has opted to require biometrics before marking
  /// attendance (defaults to `true` so secure-by-default).
  static Future<bool> isRequiredForMark() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_prefsEnabledKey) ?? true;
  }

  /// Persist the user's preference.
  static Future<void> setRequiredForMark(bool required) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_prefsEnabledKey, required);
  }

  /// True when this device can actually prompt for biometrics.
  static Future<bool> isAvailable() async {
    if (kIsWeb) return false;
    if (_cachedAvailable != null) return _cachedAvailable!;
    try {
      final supported = await _auth.isDeviceSupported();
      if (!supported) {
        _cachedAvailable = false;
        return false;
      }
      final canCheck = await _auth.canCheckBiometrics;
      if (!canCheck) {
        _cachedAvailable = false;
        return false;
      }
      final enrolled = await _auth.getAvailableBiometrics();
      _cachedAvailable = enrolled.isNotEmpty;
      return _cachedAvailable!;
    } on PlatformException {
      _cachedAvailable = false;
      return false;
    } catch (_) {
      _cachedAvailable = false;
      return false;
    }
  }

  /// Prompt the user. Returns `true` when verified, `false` when the
  /// user cancelled or the prompt failed. When biometrics aren't
  /// available on this device (no enrolled fingerprint / Face ID),
  /// the method returns `true` so the existing flow isn't blocked —
  /// the backend's own checks (geofence, QR signature, IP binding)
  /// remain the source of truth for security.
  static Future<bool> authenticate({
    required String reason,
    bool biometricOnly = true,
  }) async {
    if (kIsWeb) return true;
    final available = await isAvailable();
    if (!available) return true;
    try {
      return await _auth.authenticate(
        localizedReason: reason,
        options: AuthenticationOptions(
          biometricOnly: biometricOnly,
          // `stickyAuth` keeps the prompt alive across brief
          // backgrounding (e.g. notification shade) so we don't make
          // the student re-tap if their phone is being noisy.
          stickyAuth: true,
          useErrorDialogs: true,
        ),
      );
    } on PlatformException {
      return false;
    } catch (_) {
      return false;
    }
  }
}
