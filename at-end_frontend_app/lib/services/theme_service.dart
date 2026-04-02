import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Persists theme mode and notifies [modeNotifier] for MaterialApp.
class ThemeService {
  ThemeService._();

  static const String _prefsKey = 'theme_mode_v1';

  static final ValueNotifier<ThemeMode> modeNotifier =
      ValueNotifier<ThemeMode>(ThemeMode.dark);

  static Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final v = prefs.getString(_prefsKey);
    if (v == 'light') {
      modeNotifier.value = ThemeMode.light;
    } else if (v == 'system') {
      modeNotifier.value = ThemeMode.system;
    } else {
      modeNotifier.value = ThemeMode.dark;
    }
  }

  static Future<void> setTheme(ThemeMode mode) async {
    modeNotifier.value = mode;
    final prefs = await SharedPreferences.getInstance();
    final s = mode == ThemeMode.light
        ? 'light'
        : (mode == ThemeMode.dark ? 'dark' : 'system');
    await prefs.setString(_prefsKey, s);
  }
}
