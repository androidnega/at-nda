import 'package:flutter/material.dart';

import '../models/student.dart';
import 'profile_avatar.dart';

/// Fourth theme inspired by "Team Reach" clean blue cards.
class StudentTeamReachDashboard extends StatelessWidget {
  const StudentTeamReachDashboard({
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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final tt = Theme.of(context).textTheme;
    final page = isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F6FF);
    final panel = isDark ? const Color(0xFF111C34) : Colors.white;
    final panelBorder = isDark ? const Color(0xFF22304D) : const Color(0xFFE5ECF9);
    final headline = isDark ? const Color(0xFFE2E8F0) : const Color(0xFF111827);
    const blue = Color(0xFF1F6CFF);
    const blueDark = Color(0xFF1149B5);
    final muted = isDark ? const Color(0xFF94A3B8) : const Color(0xFF5C6C8C);

    return ColoredBox(
      color: page,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                14,
                MediaQuery.paddingOf(context).top > 0 ? 6 : 14,
                14,
                8,
              ),
              child: Row(
                children: [
                  IconButton(
                    onPressed: onOpenDrawer,
                    icon: const Icon(Icons.menu_rounded),
                  ),
                  Expanded(
                    child: Text(
                      'Hey, ${student.greetingLastName}',
                      style: tt.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: headline,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: onBell,
                    icon: const Icon(Icons.notifications_none_rounded),
                  ),
                  ProfileAvatar(student: student, radius: 20),
                ],
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
              child: Text(
                'Simple, Fast Attendance',
                style: tt.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: headline,
                ),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            sliver: SliverToBoxAdapter(
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  gradient: const LinearGradient(
                    colors: [blue, blueDark],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      heroTitle,
                      style: tt.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (heroSubtitle.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        heroSubtitle,
                        style: tt.bodySmall?.copyWith(color: Colors.white.withValues(alpha: 0.9)),
                      ),
                    ],
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: showMarkButton ? onMarkAttendance : onOpenFullTimetable,
                        icon: Icon(showMarkButton ? Icons.how_to_reg_rounded : Icons.calendar_month_rounded),
                        style: FilledButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: blueDark,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        label: Text(showMarkButton ? 'Mark attendance' : 'Open class timetable'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            sliver: SliverToBoxAdapter(
              child: Row(
                children: [
                  _tiny(
                    context,
                    'Classes',
                    '$statsClassesToday',
                    panel: panel,
                    panelBorder: panelBorder,
                    textColor: headline,
                    muted: muted,
                  ),
                  const SizedBox(width: 10),
                  _tiny(
                    context,
                    'Live',
                    '$statsLiveSessions',
                    panel: panel,
                    panelBorder: panelBorder,
                    textColor: headline,
                    muted: muted,
                  ),
                  const SizedBox(width: 10),
                  _tiny(
                    context,
                    'Marked',
                    '$statsMarkedToday',
                    panel: panel,
                    panelBorder: panelBorder,
                    textColor: headline,
                    muted: muted,
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 26),
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
                  if (riskSection != null) ...[
                    riskSection!,
                    const SizedBox(height: 10),
                  ],
                  if (warningBanner != null) warningBanner!,
                  if (pendingSyncChip != null) ...[
                    const SizedBox(height: 10),
                    pendingSyncChip!,
                  ],
                  if (demoBanner != null) ...[
                    const SizedBox(height: 8),
                    demoBanner!,
                  ],
                  if (errorText != null && errorText!.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      errorText!,
                      style: TextStyle(
                        color: isDark ? const Color(0xFFFCA5A5) : Colors.red,
                      ),
                    ),
                  ],
                  const SizedBox(height: 8),
                  Text(
                    lastCheckInLine,
                    textAlign: TextAlign.center,
                    style: tt.labelMedium?.copyWith(color: muted),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _tiny(
    BuildContext context,
    String k,
    String v, {
    required Color panel,
    required Color panelBorder,
    required Color textColor,
    required Color muted,
  }) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: panel,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: panelBorder),
        ),
        child: Column(
          children: [
            Text(
              v,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                    color: textColor,
                  ),
            ),
            const SizedBox(height: 2),
            Text(
              k,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(color: muted),
            ),
          ],
        ),
      ),
    );
  }
}
