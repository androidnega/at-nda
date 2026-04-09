import 'package:flutter/material.dart';

/// Student / class-rep “soft” surfaces derived from [ColorScheme] so they track
/// the institution seed from Laravel (`mobile_app_theme_seed`).
abstract final class StudentSoftUi {
  static Color pageBackground(ColorScheme cs, Brightness b) =>
      b == Brightness.dark ? const Color(0xFF121212) : Colors.white;

  /// Hero / drawer header: primary in light; blended surface+primary in dark.
  static Color headerBg(ColorScheme cs, Brightness b) {
    if (b == Brightness.dark) {
      return Color.lerp(cs.surface, cs.primary, 0.38)!;
    }
    return cs.primary;
  }

  static Color accent(ColorScheme cs) => cs.primary;

  static Color titleBrown(ColorScheme cs) => cs.onSurface;

  static Color mutedBrown(ColorScheme cs) => cs.onSurfaceVariant;

  static Color cardWhite(ColorScheme cs) => cs.surface;

  /// Light main shell (home, class rep list) — pure white.
  static Color cream(ColorScheme cs) => Colors.white;

  static Color chipBg(ColorScheme cs) => cs.surfaceContainerHighest;

  /// Present / on-track (semantic — not tied to brand seed).
  static const Color statPresent = Color(0xFF6BAF7A);

  /// Attention (semantic).
  static const Color statAttention = Color(0xFFE8A05C);

  /// Missed / risk (semantic).
  static const Color statAbsent = Color(0xFFE0786E);
}
