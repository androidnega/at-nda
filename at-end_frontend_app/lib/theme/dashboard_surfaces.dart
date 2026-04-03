import 'package:flutter/material.dart';

/// Card and chip surfaces for dashboards — avoids hard-coded [Colors.white] /
/// light greys that break in dark mode.
abstract final class DashboardSurfaces {
  static Color panelBackground(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Theme.of(context).brightness == Brightness.dark
        ? cs.surfaceContainer
        : cs.surface;
  }

  static Color panelBorder(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final a = Theme.of(context).brightness == Brightness.dark ? 0.42 : 0.65;
    return cs.outlineVariant.withValues(alpha: a);
  }

  /// Settings, attendance records header, lecturer hero, session rows.
  static BoxDecoration cardDecoration(BuildContext context, {double radius = 16}) {
    return BoxDecoration(
      color: panelBackground(context),
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: panelBorder(context)),
    );
  }

  /// Class rep metric / action tiles: pastel in light, accent wash in dark.
  static Color metricWash(
    BuildContext context, {
    required Color lightPastel,
    required Color darkAccent,
  }) {
    if (Theme.of(context).brightness == Brightness.light) return lightPastel;
    final cs = Theme.of(context).colorScheme;
    return Color.alphaBlend(
      darkAccent.withValues(alpha: 0.34),
      cs.surfaceContainerHigh,
    );
  }

  static BoxBorder? metricCardBorder(BuildContext context) {
    if (Theme.of(context).brightness == Brightness.light) return null;
    final cs = Theme.of(context).colorScheme;
    return Border.all(color: cs.outline.withValues(alpha: 0.38));
  }

  static Color chipBackground(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Theme.of(context).brightness == Brightness.dark
        ? cs.surfaceContainerHighest
        : const Color(0xFFE2E8F0);
  }

  static BoxDecoration chipDecoration(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final cs = Theme.of(context).colorScheme;
    return BoxDecoration(
      color: chipBackground(context),
      borderRadius: BorderRadius.circular(999),
      border: dark ? Border.all(color: cs.outline.withValues(alpha: 0.35)) : null,
    );
  }
}
