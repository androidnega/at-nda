import 'package:flutter/material.dart';

import '../models/student.dart';
import '../widgets/profile_avatar.dart';

/// Third student home theme: dark "task board" inspired layout.
class StudentNoirTaskDashboard extends StatelessWidget {
  const StudentNoirTaskDashboard({
    super.key,
    required this.student,
    required this.todaySlots,
    required this.unmarkedSessions,
    required this.heroTitle,
    required this.heroSubtitle,
    required this.showMarkButton,
    required this.onMarkAttendance,
    required this.lastCheckInLine,
    required this.dayProgress,
    required this.onOpenDrawer,
    required this.onBell,
    required this.onOpenFullTimetable,
    required this.onSeeAllClasses,
    required this.onSlotTap,
    required this.classRepCard,
    required this.dynamicBlocks,
    required this.warningBanner,
    required this.pendingSyncChip,
    required this.errorText,
    required this.riskSection,
    required this.demoBanner,
    this.statsClassesToday = 0,
    this.statsLiveSessions = 0,
    this.statsMarkedToday = 0,
    required this.dashboardClockLabel,
    required this.dashboardClockSegments,
    this.todayVenueHint,
  });

  final Student student;
  final List<Map<String, dynamic>> todaySlots;
  final List<Map<String, dynamic>> unmarkedSessions;
  final String heroTitle;
  final String heroSubtitle;
  final bool showMarkButton;
  final VoidCallback onMarkAttendance;
  final String lastCheckInLine;
  final double dayProgress;
  final VoidCallback onOpenDrawer;
  final VoidCallback onBell;
  final VoidCallback onOpenFullTimetable;
  final VoidCallback onSeeAllClasses;
  final void Function(Map<String, dynamic> slot) onSlotTap;
  final Widget? classRepCard;
  final List<Widget> dynamicBlocks;
  final Widget? warningBanner;
  final Widget? pendingSyncChip;
  final String? errorText;
  final Widget? riskSection;
  final Widget? demoBanner;
  final int statsClassesToday;
  final int statsLiveSessions;
  final int statsMarkedToday;
  final String dashboardClockLabel;
  final List<String> dashboardClockSegments;
  final String? todayVenueHint;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? const Color(0xFF0D0F14) : const Color(0xFFF5F6FA);
    final panel = isDark ? const Color(0xFF171B24) : const Color(0xFF1A1F2B);
    final panelSoft = isDark ? const Color(0xFF1E2430) : const Color(0xFF262E3E);
    final textPrimary = Colors.white;
    final textMuted = const Color(0xFFB5BDCB);
    final accent = const Color(0xFFF3DFC1);

    String clockValue() {
      if (dashboardClockSegments.length < 3) return '';
      final s = dashboardClockSegments;
      if (s[2] == 'AM' || s[2] == 'PM') {
        return '${s[0]}:${s[1]} ${s[2]}';
      }
      return '${s[0]}:${s[1]}:${s[2]}';
    }

    Widget topCard() {
      return Container(
        decoration: BoxDecoration(
          color: panel,
          borderRadius: BorderRadius.circular(26),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.35),
              blurRadius: 18,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Today',
              style: tt.labelLarge?.copyWith(
                color: textMuted,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              heroTitle,
              style: tt.headlineSmall?.copyWith(
                color: textPrimary,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.3,
              ),
            ),
            if (heroSubtitle.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                heroSubtitle,
                style: tt.bodySmall?.copyWith(
                  color: textMuted,
                  height: 1.35,
                ),
              ),
            ],
            if (dashboardClockLabel.trim().isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                dashboardClockLabel,
                style: tt.labelSmall?.copyWith(
                  color: textMuted,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                clockValue(),
                style: tt.titleMedium?.copyWith(
                  color: textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: showMarkButton ? onMarkAttendance : onOpenFullTimetable,
                icon: Icon(
                  showMarkButton ? Icons.how_to_reg_rounded : Icons.calendar_month_rounded,
                ),
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  foregroundColor: const Color(0xFF131722),
                  padding: const EdgeInsets.symmetric(vertical: 13),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
                label: Text(
                  showMarkButton ? 'Mark attendance' : 'Open class timetable',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        ),
      );
    }

    return ColoredBox(
      color: bg,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                14,
                MediaQuery.paddingOf(context).top > 0 ? 6 : 14,
                14,
                10,
              ),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.menu_rounded, color: Colors.white),
                    onPressed: onOpenDrawer,
                  ),
                  Expanded(
                    child: Text(
                      'Hello, ${student.greetingLastName}',
                      style: tt.titleLarge?.copyWith(
                        color: textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.notifications_none_rounded, color: Colors.white),
                    onPressed: onBell,
                  ),
                  ProfileAvatar(student: student, radius: 20),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            sliver: SliverToBoxAdapter(child: topCard()),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            sliver: SliverToBoxAdapter(
              child: Row(
                children: [
                  _mini(context, panelSoft, 'Classes', '$statsClassesToday', textMuted),
                  const SizedBox(width: 10),
                  _mini(context, panelSoft, 'Live', '$statsLiveSessions', textMuted),
                  const SizedBox(width: 10),
                  _mini(context, panelSoft, 'Marked', '$statsMarkedToday', textMuted),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (dynamicBlocks.isNotEmpty) ...[
                    ...dynamicBlocks,
                    const SizedBox(height: 10),
                  ],
                  if (classRepCard != null) ...[
                    classRepCard!,
                    const SizedBox(height: 10),
                  ],
                  if (warningBanner != null) warningBanner!,
                  if (pendingSyncChip != null) ...[
                    const SizedBox(height: 10),
                    pendingSyncChip!,
                  ],
                  if (errorText != null && errorText!.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Text(errorText!, style: TextStyle(color: cs.error)),
                  ],
                  if (demoBanner != null) ...[
                    const SizedBox(height: 8),
                    demoBanner!,
                  ],
                  const SizedBox(height: 10),
                  Text(
                    lastCheckInLine,
                    style: tt.labelMedium?.copyWith(color: textMuted),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _mini(
    BuildContext context,
    Color bg,
    String label,
    String value,
    Color muted,
  ) {
    return Expanded(
      child: Container(
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(16),
        ),
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        child: Column(
          children: [
            Text(
              value,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: muted,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}
