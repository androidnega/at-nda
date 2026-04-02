import 'package:flutter/foundation.dart';

/// Firebase Cloud Messaging (FCM) — server push for session reminders.
///
/// **Next steps (not bundled here — needs native Firebase config):**
/// 1. Add `firebase_core` and `firebase_messaging` to [pubspec.yaml].
/// 2. Run `flutterfire configure` and add `google-services.json` (Android) /
///    `GoogleService-Info.plist` (iOS).
/// 3. In [main.dart], call `await Firebase.initializeApp()` before `runApp`.
/// 4. Implement [registerAfterLogin] below: read password via
///    `OfflineService.getApiSessionPassword()`, then
///    `ApiService.postDeviceToken(indexNumber: ..., password: ..., token: ...)`.
/// 5. Laravel: send notifications when a session starts / ends soon (topic or per-device).
///
/// Until Firebase is configured, [registerAfterLogin] is a safe no-op.
class PushService {
  PushService._();

  /// Register FCM token with Laravel after successful login.
  static Future<void> registerAfterLogin(String indexNumber) async {
    if (kIsWeb) return;
    try {
      // After Firebase is configured:
      // final token = await FirebaseMessaging.instance.getToken();
      // if (token != null) {
      //   await ApiService.postDeviceToken(indexNumber: indexNumber, token: token);
      // }
      // FirebaseMessaging.onMessage.listen((RemoteMessage m) { ... });
    } catch (e) {
      debugPrint('PushService.registerAfterLogin: $e');
    }
  }
}
