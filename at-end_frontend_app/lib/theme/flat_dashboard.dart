import 'package:flutter/material.dart';

/// Flat analytics dashboard surfaces (no elevation / shadows).
abstract final class FlatDashboard {
  static const Color background = Color(0xFFF4F4F5);
  static const Color card = Color(0xFFFFFFFF);
  static const Color cardMuted = Color(0xFFEBEBEC);
  static const Color border = Color(0xFFD4D4D8);
  static const Color textPrimary = Color(0xFF18181B);
  static const Color textSecondary = Color(0xFF52525B);

  static BoxDecoration cardDecoration({Color? color}) {
    return BoxDecoration(
      color: color ?? card,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: border, width: 1),
    );
  }

  static TextStyle titleStyle(BuildContext context) =>
      Theme.of(context).textTheme.titleMedium!.copyWith(
            color: textPrimary,
            fontWeight: FontWeight.w700,
          );

  static TextStyle valueStyle(BuildContext context) =>
      Theme.of(context).textTheme.headlineSmall!.copyWith(
            color: textPrimary,
            fontWeight: FontWeight.w800,
          );

  static TextStyle captionStyle(BuildContext context) =>
      Theme.of(context).textTheme.bodySmall!.copyWith(
            color: textSecondary,
            fontWeight: FontWeight.w500,
          );
}
