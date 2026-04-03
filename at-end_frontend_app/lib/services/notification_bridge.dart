import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'api_service.dart';
import 'offline_service.dart';

/// Firebase-free notifications:
/// - Laravel cron writes pending reminders into `in_app_notifications`
/// - Flutter polls `POST /api/notifications/pending` and marks them as read
abstract final class NotificationBridge {
  static final GlobalKey<ScaffoldMessengerState> messengerKey =
      GlobalKey<ScaffoldMessengerState>();

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
          final title = first['title']?.toString() ?? 'Reminder';
          final body = first['body']?.toString() ?? '';
          final messenger = messengerKey.currentState;
          if (messenger != null) {
            messenger.showSnackBar(
              SnackBar(
                behavior: SnackBarBehavior.floating,
                content: Text(
                  body.isEmpty ? title : '$title\n$body',
                  maxLines: 3,
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
