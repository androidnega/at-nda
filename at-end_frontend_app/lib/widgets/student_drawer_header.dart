import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models/student.dart';

/// Drawer header: class + name + index only (no profile photo on app).
class StudentDrawerHeader extends StatelessWidget {
  const StudentDrawerHeader({
    super.key,
    required this.student,
    required this.decorationColor,
  });

  final Student student;
  final Color decorationColor;

  /// High-contrast text on light teal-tinted drawer headers (daylight / normal mode).
  static const Color _lightClassLine = Color(0xFF0F172A);
  static const Color _lightName = Color(0xFF020617);
  static const Color _lightIndex = Color(0xFF334155);

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final isLight = Theme.of(context).brightness == Brightness.light;
    final classLine = student.classGroupWithLevelLabel;
    final displayName = student.greetingLastName;
    final initials = displayName.trim().isNotEmpty
        ? displayName.trim().split(RegExp(r'\s+')).take(2).map((p) => p[0]).join().toUpperCase()
        : 'S';

    final classStyle = GoogleFonts.inter(
      fontSize: isLight ? 13 : 12.5,
      fontWeight: FontWeight.w600,
      height: 1.3,
      letterSpacing: isLight ? 0.15 : 0,
      color: isLight ? _lightClassLine : cs.onSurfaceVariant,
    );
    final nameStyle = GoogleFonts.inter(
      fontSize: isLight ? 17 : 16,
      fontWeight: FontWeight.w700,
      height: 1.25,
      letterSpacing: isLight ? -0.2 : 0,
      color: isLight ? _lightName : cs.onSurface,
    );
    final indexStyle = GoogleFonts.inter(
      fontSize: isLight ? 13 : 12.5,
      fontWeight: FontWeight.w500,
      height: 1.25,
      letterSpacing: 0.25,
      color: isLight ? _lightIndex : cs.onSurfaceVariant,
    );

    return Material(
      color: decorationColor,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: isLight ? Colors.white.withValues(alpha: 0.75) : cs.primary.withValues(alpha: 0.2),
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    initials,
                    style: GoogleFonts.inter(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: isLight ? _lightName : cs.onSurface,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    displayName,
                    style: nameStyle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            if (classLine != null && classLine.isNotEmpty)
              Text(classLine, style: classStyle),
            const SizedBox(height: 6),
            Text(student.indexNumber, style: indexStyle),
          ],
        ),
      ),
    );
  }
}
