import 'package:flutter/material.dart';

/// Pastel “soft UI” surfaces for secondary screens (timetable, lists, profile tabs).
abstract final class SoftUi {
  /// Aligned with student soft dashboard cream.
  static const Color pageBackgroundLight = Colors.white;

  static const Color tealAccent = Color(0xFF26A69A);
  static const Color tealTint = Color(0xFFE8F7F4);
  static const Color peachAccent = Color(0xFFFF9800);
  static const Color peachTint = Color(0xFFFFEDE0);
  static const Color lilacAccent = Color(0xFF7E57C2);
  static const Color lilacTint = Color(0xFFF0EBFA);

  static Color scaffoldBackground(BuildContext context) {
    return Theme.of(context).brightness == Brightness.light
        ? pageBackgroundLight
        : Theme.of(context).colorScheme.surface;
  }

  static (Color border, Color fill) slotColors(int index) {
    const pairs = [
      (tealAccent, tealTint),
      (peachAccent, peachTint),
      (lilacAccent, lilacTint),
    ];
    return pairs[index % pairs.length];
  }

  static BoxDecoration softCard({
    required Color fill,
    required Color borderColor,
    double radius = 22,
  }) {
    return BoxDecoration(
      color: fill,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(
        color: borderColor.withValues(alpha: 0.35),
      ),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withValues(alpha: 0.04),
          blurRadius: 12,
          offset: const Offset(0, 4),
        ),
      ],
    );
  }
}
