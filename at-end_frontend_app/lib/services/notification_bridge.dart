import 'package:flutter/foundation.dart';

/// **Push notifications** (broadcast + per-user + class reminders) need:
/// - Firebase Cloud Messaging on the client (`firebase_core`, `firebase_messaging`)
/// - Server: store device tokens, schedule jobs (e.g. 30 minutes before lecture from timetable)
/// - Android: `google-services.json` · iOS: `GoogleService-Info.plist`
///
/// This stub keeps a single hook so you can wire FCM without scattering `TODO`s.
abstract final class NotificationBridge {
  static Future<void> initialize() async {
    if (kDebugMode) {
      debugPrint(
        'NotificationBridge: wire firebase_messaging + backend for reminders & announcements.',
      );
    }
  }

  /// Call when the app becomes active (e.g. after `resumed`) to sync topics / token refresh.
  static Future<void> onAppForegrounded() async {}

  /// Future: schedule local/remote reminder **~30 minutes before** lecture start using
  /// server timetable (day, date, time). Requires backend cron + FCM data messages.
  static Future<void> scheduleClassRemindersPlaceholder() async {}
}
