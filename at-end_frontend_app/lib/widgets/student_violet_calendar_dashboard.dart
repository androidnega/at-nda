import 'package:flutter/material.dart';

import '../models/student.dart';

class StudentVioletCalendarDashboard extends StatelessWidget {
  const StudentVioletCalendarDashboard({
    super.key,
    required this.student,
    required this.todaySlots,
    required this.unmarkedSessions,
    required this.heroTitle,
    required this.heroSubtitle,
    required this.showMarkButton,
    required this.onMarkAttendance,
    this.primaryActionLabel = 'Mark attendance',
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
  final String primaryActionLabel;
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

  static const Color _cyanAccent = Color(0xFF5DD5F5);
  static const Color _mutedOnDark = Color(0xFFC8C4F0);
  static const Color _base = Color(0xFF4334C4);

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    final topPad = MediaQuery.paddingOf(context).top > 0 ? 6.0 : 12.0;

    return ColoredBox(
      color: _base,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        slivers: [
          SliverToBoxAdapter(
            child: Container(
              color: _base,
              padding: EdgeInsets.fromLTRB(8, topPad, 10, 20),
              child: Column(
                children: [
                  Row(
                    children: [
                      IconButton(
                        onPressed: onOpenDrawer,
                        icon: const Icon(Icons.menu_rounded, color: Colors.white),
                      ),
                      Expanded(
                        child: Text(
                          'Hi ${student.greetingLastName}',
                          style: tt.titleLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.3,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: onBell,
                        icon: const Icon(Icons.notifications_outlined, color: Colors.white),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Container(
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(22),
                    ),
                    padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          heroTitle,
                          style: tt.titleMedium?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          heroSubtitle,
                          style: tt.bodySmall?.copyWith(
                            color: Colors.white.withValues(alpha: 0.82),
                            height: 1.35,
                          ),
                        ),
                        if (dashboardClockLabel.trim().isNotEmpty) ...[
                          const SizedBox(height: 10),
                          Text(
                            dashboardClockLabel,
                            style: tt.labelSmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.72),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            dashboardClockSegments.join(':'),
                            style: tt.headlineSmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton(
                            onPressed: showMarkButton ? onMarkAttendance : onOpenFullTimetable,
                            style: FilledButton.styleFrom(
                              backgroundColor: _cyanAccent,
                              foregroundColor: const Color(0xFF0D1B2A),
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(999),
                              ),
                              elevation: 0,
                            ),
                            child: Text(
                              showMarkButton ? primaryActionLabel : 'Open class timetable',
                              style: const TextStyle(fontWeight: FontWeight.w700),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
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
                  if (riskSection != null) ...[
                    const SizedBox(height: 10),
                    riskSection!,
                  ],
                  if (errorText != null && errorText!.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Text(errorText!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ],
                  if (demoBanner != null) ...[
                    const SizedBox(height: 8),
                    demoBanner!,
                  ],
                  const SizedBox(height: 8),
                  Text(
                    lastCheckInLine,
                    textAlign: TextAlign.center,
                    style: tt.labelMedium?.copyWith(color: _mutedOnDark.withValues(alpha: 0.85)),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
