import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'api_service.dart';
import 'notification_prefs.dart';
import 'offline_service.dart';
import 'success_chime.dart';

/// Firebase-free notifications:
/// - Laravel cron writes pending reminders into `in_app_notifications`
/// - Flutter polls `POST /api/notifications/pending` and marks them as read
abstract final class NotificationBridge {
  static final GlobalKey<ScaffoldMessengerState> messengerKey =
      GlobalKey<ScaffoldMessengerState>();

  /// Shows above route-level overlays (e.g. dialogs) via the app-root messenger.
  static void showSnackBar(SnackBar snackBar) {
    messengerKey.currentState?.showSnackBar(snackBar);
  }

  static bool _inFlight = false;

  static Future<void> initialize() async {
    if (kDebugMode) {
      debugPrint('NotificationBridge: using Firebase-free polling reminders.');
    }
  }

  /// Called when the app becomes active (e.g. after `resumed`) to fetch reminders.
  static Future<void> onAppForegrounded() async {
    await _pollPendingOnce(showSnackBars: true);
  }

  /// Explicit polling (used after login).
  static Future<void> pollPending() async {
    await _pollPendingOnce(showSnackBars: true);
  }

  static Future<void> _pollPendingOnce({
    required bool showSnackBars,
  }) async {
    if (_inFlight) return;
    _inFlight = true;
    try {
      if (!NotificationPrefs.enabled) {
        return;
      }

      final student = await OfflineService.getCurrentStudent();
      if (student == null) return;

      final pwd = await OfflineService.getApiSessionPassword();
      if (pwd == null || pwd.isEmpty) return;

      final res = await ApiService.notificationsPending(
        indexNumber: student.indexNumber,
        password: pwd,
      );

      if (res.statusCode < 200 || res.statusCode >= 300) return;

      final decoded = jsonDecode(res.body);
      if (decoded is! Map) return;

      final data = decoded['data'];
      if (data is! Map) return;

      final list = data['notifications'];
      if (list is! List || list.isEmpty) return;

      if (showSnackBars) {
        final first = list.first;
        if (first is Map) {
          final rawTitle = first['title']?.toString() ?? '';
          final rawBody = first['body']?.toString() ?? '';
          final title = rawTitle.trim().isEmpty
              ? 'Attendance reminder'
              : rawTitle.trim();
          final body = rawBody.trim();
          final more = list.length > 1 ? ' +${list.length - 1} more' : '';
          if (!kIsWeb) {
            await SuccessChime.playNotificationTone();
          }
          final messenger = messengerKey.currentState;
          if (messenger != null) {
            messenger.clearSnackBars();
            messenger.showSnackBar(
              SnackBar(
                behavior: SnackBarBehavior.floating,
                duration: const Duration(seconds: 6),
                content: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.only(top: 1),
                      child: Icon(
                        Icons.notifications_active_outlined,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '$title$more',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (body.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              body,
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          }
        }
      }
    } catch (_) {
      // Never crash the app due to notification polling.
    } finally {
      _inFlight = false;
    }
  }
}
