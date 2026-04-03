import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Compact navigation drawer: DM Sans, smaller titles/subtitles, tighter tiles.
class AppDrawerShell extends StatelessWidget {
  const AppDrawerShell({super.key, required this.child});

  final Widget child;

  static ThemeData themeOf(BuildContext context) {
    final t = Theme.of(context);
    final cs = t.colorScheme;
    final dm = GoogleFonts.dmSansTextTheme(t.textTheme);
    return t.copyWith(
      iconTheme: IconThemeData(size: 20, color: cs.onSurfaceVariant),
      listTileTheme: ListTileThemeData(
        dense: true,
        minVerticalPadding: 2,
        horizontalTitleGap: 10,
        minLeadingWidth: 30,
        iconColor: cs.onSurfaceVariant,
        titleTextStyle: dm.titleMedium?.copyWith(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.15,
          height: 1.2,
          color: cs.onSurface,
        ),
        subtitleTextStyle: dm.bodySmall?.copyWith(
          fontSize: 10.5,
          height: 1.25,
          letterSpacing: 0.1,
          color: cs.onSurfaceVariant,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: themeOf(context),
      child: child,
    );
  }
}
