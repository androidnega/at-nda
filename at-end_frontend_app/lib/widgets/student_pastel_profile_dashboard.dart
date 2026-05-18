import 'package:flutter/material.dart';

import '../models/student.dart';

/// Pastel “profile + progress” student home (admin-selectable theme).
class StudentPastelProfileDashboard extends StatefulWidget {
  const StudentPastelProfileDashboard({
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

  @override
  State<StudentPastelProfileDashboard> createState() =>
      _StudentPastelProfileDashboardState();
}

class _StudentPastelProfileDashboardState
    extends State<StudentPastelProfileDashboard> {
  static const List<String> _pillLabels = [
    'Today',
    'Yesterday',
    'Week',
    'Month',
  ];
  int _diaryPill = 0;

  static const Color _pageBg = Color(0xFFFFFFFF);
  static const Color _pageBgDark = Color(0xFF121212);

  double _attendanceFraction() {
    final c = widget.statsClassesToday;
    if (c <= 0) return widget.dayProgress.clamp(0.0, 1.0);
    return (widget.statsMarkedToday / c).clamp(0.0, 1.0);
  }

  String _progressTitle() {
    if (widget.statsClassesToday > 0) {
      return 'Today’s attendance';
    }
    return 'School day progress';
  }

  String _progressCurrentLabel() {
    if (widget.statsClassesToday > 0) {
      return '${widget.statsMarkedToday} of ${widget.statsClassesToday}';
    }
    return '${(_attendanceFraction() * 100).round()}%';
  }

  String _progressGoalLabel() {
    if (widget.statsClassesToday > 0) {
      return 'Goal: all classes marked';
    }
    return 'Goal: end of day';
  }

  String _formatClockParts(List<String> segs) {
    if (segs.length < 3) return '';
    final a = segs[0];
    final b = segs[1];
    final c = segs[2];
    if (c == 'AM' || c == 'PM') {
      final hh = int.tryParse(a) ?? 0;
      return '$hh:${b.padLeft(2, '0')} $c';
    }
    return '$a:$b:$c';
  }

  @override
  Widget build(BuildContext context) {
    final b = Theme.of(context).brightness;
    final tt = Theme.of(context).textTheme;
    final isDark = b == Brightness.dark;
    final pageBg = isDark ? _pageBgDark : _pageBg;
    final textPrimary = isDark ? Colors.white : const Color(0xFF18181B);
    final textMuted = isDark ? const Color(0xFFB0B0B0) : const Color(0xFF52525B);
    final card = isDark ? const Color(0xFF1E1E1E) : Colors.white;
    final border = isDark
        ? const Color(0xFF333333)
        : const Color(0xFFE8E0F0);
    final frac = _attendanceFraction();

    return ColoredBox(
      color: pageBg,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.fromLTRB(8, MediaQuery.paddingOf(context).top > 0 ? 4 : 12, 12, 0),
              child: Row(
                children: [
                  IconButton(
                    icon: Icon(Icons.menu_rounded, color: textPrimary),
                    onPressed: widget.onOpenDrawer,
                    tooltip: 'Menu',
                  ),
                  Expanded(
                    child: Text(
                      'at-enda',
                      style: tt.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: textPrimary,
                      ),
                    ),
                  ),
                  Material(
                    color: card,
                    shape: const CircleBorder(),
                    elevation: isDark ? 0 : 1,
                    shadowColor: Colors.black.withValues(alpha: 0.06),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: widget.onBell,
                      child: SizedBox(
                        width: 46,
                        height: 46,
                        child: Stack(
                          alignment: Alignment.center,
                          children: [
                            Icon(
                              Icons.more_horiz_rounded,
                              color: textPrimary,
                              size: 22,
                            ),
                            if (widget.unmarkedSessions.isNotEmpty)
                              Positioned(
                                right: 10,
                                top: 10,
                                child: Container(
                                  width: 8,
                                  height: 8,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFFEF4444),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 8),
              child: Column(
                children: [
                  Container(
                    width: 104,
                    height: 104,
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF2A2A2A) : const Color(0xFFEDE7F3),
                      shape: BoxShape.circle,
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      widget.student.greetingLastName.isNotEmpty
                          ? widget.student.greetingLastName[0].toUpperCase()
                          : 'S',
                      style: tt.displaySmall?.copyWith(
                        color: textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    widget.student.name.trim().isNotEmpty
                        ? widget.student.name
                        : widget.student.greetingLastName,
                    textAlign: TextAlign.center,
                    style: tt.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: textPrimary,
                      letterSpacing: -0.4,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    widget.heroSubtitle,
                    textAlign: TextAlign.center,
                    style: tt.bodyMedium?.copyWith(
                      color: textMuted,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  if (widget.dashboardClockLabel.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      widget.dashboardClockLabel,
                      textAlign: TextAlign.center,
                      style: tt.labelSmall?.copyWith(
                        color: textMuted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _formatClockParts(widget.dashboardClockSegments),
                      textAlign: TextAlign.center,
                      style: tt.titleMedium?.copyWith(
                        color: textPrimary,
                        fontWeight: FontWeight.w800,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
              child: CustomPaint(
                painter: _DashedRRectPainter(
                  color: border,
                  strokeWidth: 1.6,
                  dashWidth: 6,
                  gap: 5,
                  radius: 28,
                ),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        _progressTitle(),
                        style: tt.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: textPrimary,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Text(
                            _progressCurrentLabel(),
                            style: tt.titleMedium?.copyWith(
                              fontWeight: FontWeight.w900,
                              color: textPrimary,
                            ),
                          ),
                          const Spacer(),
                          Text(
                            _progressGoalLabel(),
                            style: tt.bodySmall?.copyWith(
                              color: textMuted,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(14),
                        child: SizedBox(
                          height: 14,
                          child: Stack(
                            fit: StackFit.expand,
                            children: [
                              ColoredBox(
                                color: isDark
                                    ? const Color(0xFF2C2C2C)
                                    : const Color(0xFFE8E3F0),
                              ),
                              FractionallySizedBox(
                                alignment: Alignment.centerLeft,
                                widthFactor: frac,
                                child: const DecoratedBox(
                                  decoration: BoxDecoration(
                                    gradient: LinearGradient(
                                      colors: [
                                        Color(0xFF5B8DEF),
                                        Color(0xFF9B7FD9),
                                        Color(0xFFE878B8),
                                        Color(0xFFFFA07A),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      if (widget.showMarkButton) ...[
                        const SizedBox(height: 14),
                        FilledButton(
                          onPressed: widget.onMarkAttendance,
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(48),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(20),
                            ),
                          ),
                          child: Text(
                            widget.primaryActionLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
              child: Text(
                'Your diary',
                style: tt.titleMedium?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: textPrimary,
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: List.generate(_pillLabels.length, (i) {
                    final on = i == _diaryPill;
                    return Padding(
                      padding: const EdgeInsets.only(right: 10),
                      child: Material(
                        color: on ? card : Colors.transparent,
                        borderRadius: BorderRadius.circular(24),
                        elevation: on && !isDark ? 2 : 0,
                        shadowColor: Colors.black.withValues(alpha: 0.08),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(24),
                          onTap: () => setState(() => _diaryPill = i),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 18,
                              vertical: 10,
                            ),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(24),
                              border: Border.all(
                                color: on
                                    ? border
                                    : border.withValues(alpha: 0.55),
                              ),
                            ),
                            child: Text(
                              _pillLabels[i],
                              style: tt.labelLarge?.copyWith(
                                fontWeight:
                                    on ? FontWeight.w800 : FontWeight.w600,
                                color: textPrimary,
                              ),
                            ),
                          ),
                        ),
                      ),
                    );
                  }),
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (widget.dynamicBlocks.isNotEmpty) ...[
                    ...widget.dynamicBlocks,
                    const SizedBox(height: 12),
                  ],
                  if (widget.classRepCard != null) ...[
                    widget.classRepCard!,
                    const SizedBox(height: 12),
                  ],
                  if (widget.warningBanner != null) widget.warningBanner!,
                  if (widget.pendingSyncChip != null) ...[
                    const SizedBox(height: 10),
                    widget.pendingSyncChip!,
                  ],
                  if (widget.errorText != null &&
                      widget.errorText!.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      widget.errorText!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                  if (widget.demoBanner != null) ...[
                    const SizedBox(height: 8),
                    widget.demoBanner!,
                  ],
                  Text(
                    widget.lastCheckInLine,
                    style: tt.bodySmall?.copyWith(
                      color: textMuted,
                      height: 1.35,
                    ),
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

class _DashedRRectPainter extends CustomPainter {
  _DashedRRectPainter({
    required this.color,
    required this.strokeWidth,
    required this.dashWidth,
    required this.gap,
    required this.radius,
  });

  final Color color;
  final double strokeWidth;
  final double dashWidth;
  final double gap;
  final double radius;

  @override
  void paint(Canvas canvas, Size size) {
    final r = RRect.fromRectAndRadius(
      Rect.fromLTWH(
        strokeWidth / 2,
        strokeWidth / 2,
        size.width - strokeWidth,
        size.height - strokeWidth,
      ),
      Radius.circular(radius),
    );
    final path = Path()..addRRect(r);
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth;
    final dashed = _dashPath(path, dashWidth, gap);
    canvas.drawPath(dashed, paint);
  }

  Path _dashPath(Path source, double dashLen, double gapLen) {
    final metrics = source.computeMetrics();
    final out = Path();
    for (final m in metrics) {
      var d = 0.0;
      while (d < m.length) {
        final next = (d + dashLen).clamp(0.0, m.length);
        out.addPath(m.extractPath(d, next), Offset.zero);
        d = next + gapLen;
      }
    }
    return out;
  }

  @override
  bool shouldRepaint(covariant _DashedRRectPainter oldDelegate) =>
      oldDelegate.color != color ||
      oldDelegate.strokeWidth != strokeWidth ||
      oldDelegate.radius != radius;
}
