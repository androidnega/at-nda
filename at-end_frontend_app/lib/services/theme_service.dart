import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Persists theme mode and notifies [modeNotifier] for MaterialApp.
class ThemeService {
  ThemeService._();

  static const String _prefsKey = 'theme_mode_v1';

  static final ValueNotifier<ThemeMode> modeNotifier =
      ValueNotifier<ThemeMode>(ThemeMode.light);

  static Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final v = prefs.getString(_prefsKey);
    if (v == 'dark') {
      modeNotifier.value = ThemeMode.dark;
    } else if (v == 'system') {
      modeNotifier.value = ThemeMode.system;
    } else {
      // New installs and unknown values default to light (matches app chrome).
      modeNotifier.value = ThemeMode.light;
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
