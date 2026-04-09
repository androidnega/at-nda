import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Backend-driven color variant for app-wide theme feel.
class InstitutionThemeService {
  InstitutionThemeService._();

  static const String _prefsThemeSeed = 'mobile_app_theme_seed';
  static const String defaultSeed = 'teal';
  static const Set<String> _allowed = {
    'teal',
    'blue',
    'indigo',
    'emerald',
    'rose',
    'amber',
  };

  static final ValueNotifier<String> seedNotifier =
      ValueNotifier<String>(defaultSeed);

  static Future<void> loadCached() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final v = prefs.getString(_prefsThemeSeed)?.trim().toLowerCase();
      if (v != null && _allowed.contains(v)) {
        seedNotifier.value = v;
      }
    } catch (_) {}
  }

  static Future<void> applyFromApi(String? seed) async {
    final v = (seed ?? '').trim().toLowerCase();
    if (!_allowed.contains(v)) return;
    if (seedNotifier.value != v) {
      seedNotifier.value = v;
    }
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefsThemeSeed, v);
    } catch (_) {}
  }
}
