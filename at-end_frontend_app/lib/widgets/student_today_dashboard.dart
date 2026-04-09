import 'package:flutter/material.dart';

import '../models/student.dart';
import '../theme/student_soft_ui.dart';
import '../widgets/profile_avatar.dart';

/// Soft student home — colors follow [ColorScheme] (institution seed).
abstract final class StudentDashboardPalette {
  static Color headerGreen(BuildContext context) {
    final t = Theme.of(context);
    return StudentSoftUi.headerBg(t.colorScheme, t.brightness);
  }

  static Color accentGreen(BuildContext context) =>
      StudentSoftUi.accent(Theme.of(context).colorScheme);

  static Color cardBg(BuildContext context) {
    final b = Theme.of(context).brightness;
    final cs = Theme.of(context).colorScheme;
    return b == Brightness.dark
        ? const Color(0xFF1A1A1A)
        : StudentSoftUi.cardWhite(cs);
  }

  static Color cardMuted(BuildContext context) {
    final b = Theme.of(context).brightness;
    final cs = Theme.of(context).colorScheme;
    return b == Brightness.dark
        ? const Color(0xFF2C2C2C)
        : StudentSoftUi.chipBg(cs);
  }

  static Color upcomingBadge(BuildContext context) =>
      Theme.of(context).colorScheme.primaryContainer;

  static Color upcomingBadgeFg(BuildContext context) =>
      Theme.of(context).colorScheme.onPrimaryContainer;
}

/// Roman period label for timetable row [index] (1-based).
String studentDashboardRomanPeriod(int index1Based) {
  const m = [
    '',
    'I',
    'II',
    'III',
    'IV',
    'V',
    'VI',
    'VII',
    'VIII',
    'IX',
    'X',
    'XI',
    'XII',
  ];
  if (index1Based >= 1 && index1Based < m.length) return m[index1Based];
  return '$index1Based';
}

enum StudentSlotStatus { now, upcoming, done }

StudentSlotStatus studentSlotStatusForNow({
  required DateTime now,
  required DateTime slotStart,
  required DateTime slotEnd,
}) {
  if (now.isBefore(slotStart)) return StudentSlotStatus.upcoming;
  if (now.isAfter(slotEnd)) return StudentSlotStatus.done;
  return StudentSlotStatus.now;
}

/// Scrollable dashboard: green header, overlapping summary card, upcoming classes.
class StudentTodayDashboard extends StatelessWidget {
  const StudentTodayDashboard({
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
    required this.dayProgress, // 0–1 through "working day" window
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

  /// Quick stats for the attendance-focused dashboard.
  final int statsClassesToday;
  final int statsLiveSessions;
  final int statsMarkedToday;

  /// e.g. “Session ends in”, “Local time”.
  final String dashboardClockLabel;

  /// Three segments for the clock row (countdown or hh / mm / AM|PM).
  final List<String> dashboardClockSegments;

  /// First class venue / campus line under the clock when available.
  final String? todayVenueHint;

  @override
  Widget build(BuildContext context) {
    final b = Theme.of(context).brightness;
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final headerColor = StudentDashboardPalette.headerGreen(context);
    final headerIsDark =
        ThemeData.estimateBrightnessForColor(headerColor) == Brightness.dark;
    final onHeader = headerIsDark ? Colors.white : const Color(0xFF0F172A);
    final dateLabel =
        'Today · ${DateTime.now().day} ${_monthShort(DateTime.now().month)} ${DateTime.now().year}';

    return ColoredBox(
      color: StudentSoftUi.pageBackground(cs, b),
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        slivers: [
          SliverToBoxAdapter(
            child: Column(
              children: [
              Container(
                width: double.infinity,
                decoration: BoxDecoration(
                  color: headerColor,
                  borderRadius: const BorderRadius.vertical(
                    bottom: Radius.elliptical(260, 56),
                  ),
                ),
                padding: EdgeInsets.fromLTRB(
                  8,
                  MediaQuery.paddingOf(context).top > 0 ? 4 : 12,
                  12,
                  92,
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    IconButton(
                      icon: Icon(Icons.menu_rounded, color: onHeader),
                      onPressed: onOpenDrawer,
                      tooltip: 'Menu',
                    ),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Student',
                              style: tt.labelMedium?.copyWith(
                                color: onHeader.withValues(alpha: 0.9),
                                fontWeight: FontWeight.w600,
                                letterSpacing: 0.3,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              student.greetingLastName,
                              style: tt.headlineSmall?.copyWith(
                                color: onHeader,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.3,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: 4, right: 6),
                      child: Material(
                        color: onHeader.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          onTap: onBell,
                          borderRadius: BorderRadius.circular(14),
                          child: SizedBox(
                            width: 44,
                            height: 44,
                            child: Stack(
                              clipBehavior: Clip.none,
                              alignment: Alignment.center,
                              children: [
                                Icon(
                                  Icons.notifications_outlined,
                                  color: onHeader.withValues(alpha: 0.95),
                                  size: 22,
                                ),
                                if (unmarkedSessions.isNotEmpty)
                                  Positioned(
                                    right: 8,
                                    top: 8,
                                    child: Container(
                                      width: 8,
                                      height: 8,
                                      decoration: const BoxDecoration(
                                        color: Color(0xFFFF6B6B),
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: 4, right: 4),
                      child: ProfileAvatar(student: student, radius: 22),
                    ),
                  ],
                ),
              ),
              Transform.translate(
                offset: const Offset(0, -62),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: _SummaryOverlapCard(
                    brightness: b,
                    dateLabel: dateLabel,
                    heroTitle: heroTitle,
                    heroSubtitle: heroSubtitle,
                    showMarkButton: showMarkButton,
                    onMarkAttendance: onMarkAttendance,
                    primaryActionLabel: primaryActionLabel,
                    lastCheckInLine: lastCheckInLine,
                    dayProgress: dayProgress,
                    onOpenFullTimetable: onOpenFullTimetable,
                    clockLabel: dashboardClockLabel,
                    clockSegments: dashboardClockSegments,
                    venueHint: todayVenueHint,
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (dynamicBlocks.isNotEmpty) ...[
                  ...dynamicBlocks,
                  const SizedBox(height: 12),
                ],
                if (classRepCard != null) ...[
                  classRepCard!,
                  const SizedBox(height: 12),
                ],
                if (warningBanner != null) warningBanner!,
                if (pendingSyncChip != null) ...[
                  const SizedBox(height: 10),
                  pendingSyncChip!,
                ],
                if (errorText != null && errorText!.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(
                    errorText!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
                if (demoBanner != null) ...[
                  const SizedBox(height: 8),
                  demoBanner!,
                ],
              ],
            ),
          ),
        ),
        ],
      ),
    );
  }

}

String _monthShort(int m) {
  const names = [
    '',
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  if (m < 1 || m > 12) return '';
  return names[m];
}

class _SummaryOverlapCard extends StatelessWidget {
  const _SummaryOverlapCard({
    required this.brightness,
    required this.dateLabel,
    required this.heroTitle,
    required this.heroSubtitle,
    required this.showMarkButton,
    required this.onMarkAttendance,
    required this.primaryActionLabel,
    required this.lastCheckInLine,
    required this.dayProgress,
    required this.onOpenFullTimetable,
    required this.clockLabel,
    required this.clockSegments,
    this.venueHint,
  });

  final Brightness brightness;
  final String dateLabel;
  final String heroTitle;
  final String heroSubtitle;
  final bool showMarkButton;
  final VoidCallback onMarkAttendance;
  final String primaryActionLabel;
  final String lastCheckInLine;
  final double dayProgress;
  final VoidCallback onOpenFullTimetable;
  final String clockLabel;
  final List<String> clockSegments;
  final String? venueHint;

  @override
  Widget build(BuildContext context) {
    final b = brightness;
    final accent = StudentDashboardPalette.accentGreen(context);
    final tt = Theme.of(context).textTheme;
    final card = StudentDashboardPalette.cardBg(context);
    final border = b == Brightness.light
        ? const Color(0xFFE8DDD4)
        : Theme.of(context).colorScheme.outline.withValues(alpha: 0.12);

    return Material(
      color: card,
      elevation: b == Brightness.dark ? 6 : 4,
      shadowColor: Colors.black.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(26),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: border),
        ),
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              "Today's attendance",
              textAlign: TextAlign.center,
              style: tt.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: b == Brightness.light
                    ? StudentSoftUi.titleBrown(Theme.of(context).colorScheme)
                    : Theme.of(context).colorScheme.onSurface,
              ),
            ),
            const SizedBox(height: 6),
            Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                decoration: BoxDecoration(
                  color: StudentDashboardPalette.cardMuted(context),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  dateLabel,
                  style: tt.labelMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: b == Brightness.light
                        ? StudentSoftUi.mutedBrown(Theme.of(context).colorScheme)
                        : Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ),
            if (clockLabel.trim().isNotEmpty) ...[
              const SizedBox(height: 14),
              _DashboardClockRow(
                brightness: b,
                label: clockLabel,
                segments: clockSegments,
              ),
            ],
            if (venueHint != null && venueHint!.trim().isNotEmpty) ...[
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.place_outlined,
                    size: 16,
                    color: b == Brightness.light
                        ? StudentSoftUi.mutedBrown(Theme.of(context).colorScheme)
                        : Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      venueHint!.trim(),
                      textAlign: TextAlign.center,
                      style: tt.bodySmall?.copyWith(
                        color: b == Brightness.light
                            ? StudentSoftUi.mutedBrown(Theme.of(context).colorScheme)
                            : Theme.of(context).colorScheme.onSurfaceVariant,
                        height: 1.35,
                      ),
                    ),
                  ),
                ],
              ),
            ],
            const SizedBox(height: 12),
            Text(
              heroTitle,
              textAlign: TextAlign.center,
              style: tt.titleLarge?.copyWith(
                fontWeight: FontWeight.w800,
                letterSpacing: -0.3,
                fontFeatures: const [FontFeature.tabularFigures()],
                color: b == Brightness.light
                    ? StudentSoftUi.titleBrown(Theme.of(context).colorScheme)
                    : Theme.of(context).colorScheme.onSurface,
              ),
            ),
            if (heroSubtitle.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                heroSubtitle,
                textAlign: TextAlign.center,
                style: tt.bodySmall?.copyWith(
                  color: b == Brightness.light
                      ? StudentSoftUi.mutedBrown(Theme.of(context).colorScheme)
                      : Theme.of(context).colorScheme.onSurfaceVariant,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
            const SizedBox(height: 14),
            _DayTimeline(progress: dayProgress.clamp(0.0, 1.0), activeColor: accent),
            const SizedBox(height: 16),
            if (showMarkButton)
              Stack(
                clipBehavior: Clip.none,
                children: [
                  FilledButton.icon(
                    onPressed: onMarkAttendance,
                    icon: const Icon(Icons.how_to_reg_rounded, size: 22),
                    style: FilledButton.styleFrom(
                      backgroundColor: accent,
                      foregroundColor: Theme.of(context).colorScheme.onPrimary,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(18),
                      ),
                    ),
                    label: Text(
                      primaryActionLabel,
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                    ),
                  ),
                  const Positioned(
                    right: 12,
                    top: -6,
                    child: _BreathingMarkDot(),
                  ),
                ],
              )
            else
              FilledButton.icon(
                onPressed: onOpenFullTimetable,
                icon: const Icon(Icons.calendar_month_rounded, size: 22),
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  foregroundColor: Theme.of(context).colorScheme.onPrimary,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
                label: const Text(
                  'Open class timetable',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                ),
              ),
            if (showMarkButton) ...[
              const SizedBox(height: 8),
              TextButton(
                onPressed: onOpenFullTimetable,
                child: Text(
                  'View full week',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: accent,
                  ),
                ),
              ),
            ],
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: StudentDashboardPalette.cardMuted(context),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Text(
                lastCheckInLine,
                textAlign: TextAlign.center,
                style: tt.labelMedium?.copyWith(
                  color: b == Brightness.light
                      ? StudentSoftUi.mutedBrown(Theme.of(context).colorScheme)
                      : Theme.of(context).colorScheme.onSurfaceVariant,
                  fontWeight: FontWeight.w500,
                  fontSize: 12,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashboardClockRow extends StatelessWidget {
  const _DashboardClockRow({
    required this.brightness,
    required this.label,
    required this.segments,
  });

  final Brightness brightness;
  final String label;
  final List<String> segments;

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    final b = brightness;
    final parts = segments.length >= 3
        ? segments.sublist(0, 3)
        : <String>[
            ...segments,
            for (var i = segments.length; i < 3; i++) '—',
          ];

    Widget box(String text) {
      return Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: b == Brightness.light
                ? const Color(0xFFF0EBE6)
                : const Color(0xFF2C2C2C),
            borderRadius: BorderRadius.circular(16),
          ),
          alignment: Alignment.center,
          child: Text(
            text,
            style: tt.titleLarge?.copyWith(
              fontWeight: FontWeight.w800,
              letterSpacing: 0.5,
              fontFeatures: const [FontFeature.tabularFigures()],
              color: b == Brightness.light
                  ? StudentSoftUi.titleBrown(Theme.of(context).colorScheme)
                  : Colors.white,
            ),
          ),
        ),
      );
    }

    return Column(
      children: [
        Text(
          label,
          style: tt.labelLarge?.copyWith(
            fontWeight: FontWeight.w600,
            color: b == Brightness.light
                ? StudentSoftUi.mutedBrown(Theme.of(context).colorScheme)
                : Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            box(parts[0]),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 5),
              child: Text(
                ':',
                style: tt.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
            ),
            box(parts[1]),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 5),
              child: Text(
                ':',
                style: tt.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
            ),
            box(parts[2]),
          ],
        ),
      ],
    );
  }
}

class _BreathingMarkDot extends StatefulWidget {
  const _BreathingMarkDot();

  @override
  State<_BreathingMarkDot> createState() => _BreathingMarkDotState();
}

class _BreathingMarkDotState extends State<_BreathingMarkDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween<double>(begin: 0.5, end: 1).animate(_controller),
      child: ScaleTransition(
        scale: Tween<double>(begin: 0.85, end: 1.15).animate(_controller),
        child: Container(
          width: 12,
          height: 12,
          decoration: BoxDecoration(
            color: const Color(0xFFFF4D4F),
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: const Color(0xFFFF4D4F).withValues(alpha: 0.45),
                blurRadius: 10,
                spreadRadius: 1.5,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DayTimeline extends StatelessWidget {
  const _DayTimeline({
    required this.progress,
    required this.activeColor,
  });

  final double progress;
  final Color activeColor;

  @override
  Widget build(BuildContext context) {
    final track = Theme.of(context).colorScheme.outline.withValues(alpha: 0.25);
    return Column(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: SizedBox(
            height: 6,
            child: Stack(
              fit: StackFit.expand,
              children: [
                ColoredBox(color: track),
                Align(
                  alignment: Alignment.centerLeft,
                  child: FractionallySizedBox(
                    widthFactor: progress,
                    child: ColoredBox(color: activeColor),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              '9 AM',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
            ),
            Text(
              '6 PM',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ],
        ),
      ],
    );
  }
}

