import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../utils/session_attendance_payload.dart';
import 'notification_prefs.dart';

/// Local notifications: live session appeared, checkout window, check-in/out.
/// Skips [kIsWeb]. Uses [NotificationPrefs] (same toggle as in-app reminders).
abstract final class AttendanceLocalNotify {
  static const _channelId = 'attendance_live';
  static const _prefsBaseline = 'att_notify_baseline_ids';
  static const _prefsCheckout = 'att_notify_checkout_ids';
  static const _prefsStudent = 'att_notify_student_index';

  static final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();

  static bool _initialized = false;

  static Future<void> init() async {
    if (kIsWeb) return;
    if (_initialized) return;

    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosInit = DarwinInitializationSettings(
      requestAlertPermission: false,
      requestBadgePermission: false,
      requestSoundPermission: false,
    );
    await _plugin.initialize(
      const InitializationSettings(android: androidInit, iOS: iosInit),
    );

    final androidImpl = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await androidImpl?.createNotificationChannel(
      const AndroidNotificationChannel(
        _channelId,
        'Attendance',
        description: 'Live sessions, check-in, and checkout reminders',
        importance: Importance.high,
        playSound: true,
        enableVibration: true,
      ),
    );

    _initialized = true;
  }

  /// Call when the user enables reminders in Settings so OS permission prompts run early.
  static Future<void> ensureOsPermission() async {
    if (kIsWeb || !_initialized) return;
    await _ensurePermissions();
  }

  static Future<bool> _ensurePermissions() async {
    if (kIsWeb) return false;

    if (defaultTargetPlatform == TargetPlatform.android) {
      final st = await Permission.notification.status;
      if (!st.isGranted) {
        final r = await Permission.notification.request();
        if (!r.isGranted) return false;
      }
    }

    if (defaultTargetPlatform == TargetPlatform.iOS) {
      final ios = _plugin.resolvePlatformSpecificImplementation<
          IOSFlutterLocalNotificationsPlugin>();
      final ok = await ios?.requestPermissions(
            alert: true,
            badge: true,
            sound: true,
          ) ??
          false;
      if (!ok) return false;
    }

    return true;
  }

  static Future<void> _vibrateShort() async {
    try {
      await HapticFeedback.heavyImpact();
      await Future<void>.delayed(const Duration(milliseconds: 45));
      await HapticFeedback.mediumImpact();
    } catch (_) {}
  }

  static Future<void> _show(
    int id, {
    required String title,
    required String body,
  }) async {
    if (kIsWeb || !_initialized) return;
    if (!NotificationPrefs.enabled) return;
    if (!await _ensurePermissions()) return;

    await _vibrateShort();

    final android = AndroidNotificationDetails(
      _channelId,
      'Attendance',
      channelDescription: 'Live sessions, check-in, and checkout reminders',
      importance: Importance.high,
      priority: Priority.high,
      playSound: true,
      enableVibration: true,
      vibrationPattern: Int64List.fromList([0, 380, 140, 380]),
      styleInformation: BigTextStyleInformation(body),
    );
    const ios = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    await _plugin.show(
      id,
      title,
      body,
      NotificationDetails(android: android, iOS: ios),
    );
  }

  static int _notifIdForSession(int sessionId, int salt) {
    return 10000 + (sessionId * 17 + salt) % 500000;
  }

  static String _joinIds(Set<int> ids) {
    final list = ids.toList()..sort((a, b) => a.compareTo(b));
    return list.join(',');
  }

  static String _courseName(Map<String, dynamic> s) {
    final n = (s['course_name'] ?? s['course_title'] ?? '').toString().trim();
    final c = (s['course_code'] ?? '').toString().trim();
    if (n.isNotEmpty && c.isNotEmpty) return '$c · $n';
    if (n.isNotEmpty) return n;
    if (c.isNotEmpty) return c;
    return 'your class';
  }

  static bool _isDemoSession(Map<String, dynamic> s) {
    final code = (s['course_code'] ?? '').toString().trim().toUpperCase();
    return code == 'DEMO-101';
  }

  static bool _pendingCheckout(Map<String, dynamic> s) {
    if ((s['attendance_mode']?.toString() ?? '') != 'checkin_checkout') {
      return false;
    }
    final inT = (s['check_in_time']?.toString() ?? '').trim();
    final outT = (s['check_out_time']?.toString() ?? '').trim();
    return inT.isNotEmpty && outT.isEmpty;
  }

  static bool _checkoutGateOpen(Map<String, dynamic> s) {
    return s['checkout_enabled'] == true || s['can_check_out'] == true;
  }

  static bool _shouldAlertNewLiveSession(Map<String, dynamic> s) {
    if (_isDemoSession(s)) return false;
    if (s['already_marked'] == true) return false;
    if ((s['attendance_mode']?.toString() ?? '') == 'checkin_checkout') {
      final inT = (s['check_in_time']?.toString() ?? '').trim();
      if (inT.isNotEmpty) return false;
    }
    return true;
  }

  static Set<int> _parseIdSet(String? raw) {
    if (raw == null || raw.isEmpty) return {};
    return raw
        .split(',')
        .map((e) => int.tryParse(e.trim()))
        .whereType<int>()
        .toSet();
  }

  /// [silentRefresh] false = first load / full reload: only refresh baseline (no alerts).
  /// true = pull-to-refresh or app resume: diff against baseline and notify.
  static Future<void> afterSessionsRefresh(
    List<Map<String, dynamic>> sessions, {
    required bool silentRefresh,
    required bool isClassRep,
    required String studentIndex,
  }) async {
    if (kIsWeb || !_initialized) return;
    if (studentIndex.isEmpty) return;
    if (isClassRep) return;
    if (!NotificationPrefs.enabled) return;

    final p = await SharedPreferences.getInstance();
    final prevStudent = p.getString(_prefsStudent) ?? '';
    if (prevStudent != studentIndex) {
      await p.setString(_prefsStudent, studentIndex);
      await p.remove(_prefsBaseline);
      await p.remove(_prefsCheckout);
    }

    final ids = <int>{};
    for (final s in sessions) {
      final id = parseSessionId(Map<String, dynamic>.from(s));
      if (id != null) ids.add(id);
    }

    if (!silentRefresh) {
      await p.setString(_prefsBaseline, _joinIds(ids));
      var checkoutFired = _parseIdSet(p.getString(_prefsCheckout));
      checkoutFired = checkoutFired.intersection(ids);
      await p.setString(_prefsCheckout, _joinIds(checkoutFired));
      return;
    }

    final baseline = _parseIdSet(p.getString(_prefsBaseline));
    final newIds = ids.difference(baseline);

    for (final s in sessions) {
      final sid = parseSessionId(Map<String, dynamic>.from(s));
      if (sid == null) continue;
      if (!newIds.contains(sid)) continue;
      if (!_shouldAlertNewLiveSession(s)) continue;
      final name = _courseName(s);
      await _show(
        _notifIdForSession(sid, 1),
        title: 'Attendance started',
        body: '$name is live now. Open the app to check in.',
      );
    }

    var checkoutFired = _parseIdSet(p.getString(_prefsCheckout));
    for (final s in sessions) {
      final sid = parseSessionId(Map<String, dynamic>.from(s));
      if (sid == null) continue;

      if (!_pendingCheckout(s)) {
        checkoutFired.remove(sid);
        continue;
      }

      if (_checkoutGateOpen(s) && !checkoutFired.contains(sid)) {
        checkoutFired.add(sid);
        final name = _courseName(s);
        await _show(
          _notifIdForSession(sid, 2),
          title: 'Checkout available',
          body: 'You can check out for $name now.',
        );
      }
    }

    await p.setString(_prefsCheckout, _joinIds(checkoutFired));
    await p.setString(_prefsBaseline, _joinIds(ids));
  }

  static Future<void> notifyCheckedIn(String courseLabel) async {
    await _show(
      920001,
      title: 'Check-in confirmed',
      body: 'Your check-in for $courseLabel was saved.',
    );
  }

  static Future<void> notifyCheckedOut(String courseLabel) async {
    await _show(
      920002,
      title: 'Checkout confirmed',
      body: 'Your checkout for $courseLabel was saved.',
    );
  }
}
