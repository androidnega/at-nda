import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// In-app reminders (polling). Off by default — user opts in from Settings.
abstract final class NotificationPrefs {
  static const String prefsKey = 'receive_in_app_notifications';

  static final ValueNotifier<bool> enabledNotifier = ValueNotifier<bool>(false);

  static Future<void> load() async {
    final p = await SharedPreferences.getInstance();
    enabledNotifier.value = p.getBool(prefsKey) ?? false;
  }

  static Future<void> setEnabled(bool value) async {
    final p = await SharedPreferences.getInstance();
    await p.setBool(prefsKey, value);
    enabledNotifier.value = value;
  }

  static bool get enabled => enabledNotifier.value;
}
