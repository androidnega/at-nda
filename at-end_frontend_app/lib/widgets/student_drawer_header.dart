import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models/student.dart';
import 'profile_avatar.dart';

/// Drawer header: avatar, then class + level, then name, then index (under the image).
class StudentDrawerHeader extends StatelessWidget {
  const StudentDrawerHeader({
    super.key,
    required this.student,
    required this.decorationColor,
  });

  final Student student;
  final Color decorationColor;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final classLine = student.classGroupWithLevelLabel;
    final firstLast = '${student.firstName ?? ''} ${student.lastName ?? ''}'.trim().isEmpty
        ? student.displayFirstLastName
        : '${student.firstName ?? ''} ${student.lastName ?? ''}'.trim();

    return Material(
      color: decorationColor,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ProfileAvatar(
              key: ValueKey<String>(
                '${student.indexNumber}_${student.profileImage.hashCode}_${student.serverId}',
              ),
              student: student,
              radius: 28,
            ),
            SizedBox(
              height: (classLine != null && classLine.isNotEmpty) ? 12 : 10,
            ),
            if (classLine != null && classLine.isNotEmpty)
              Text(
                classLine,
                style: GoogleFonts.dmSans(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  height: 1.25,
                  color: cs.onSurfaceVariant,
                ),
              ),
            const SizedBox(height: 10),
            Text(
              firstLast,
              style: GoogleFonts.dmSans(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                height: 1.2,
                color: cs.onSurface,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              student.indexNumber,
              style: GoogleFonts.dmSans(
                fontSize: 12.5,
                fontWeight: FontWeight.w500,
                height: 1.2,
                letterSpacing: 0.2,
                color: cs.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
