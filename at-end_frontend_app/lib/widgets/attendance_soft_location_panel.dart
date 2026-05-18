import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Reference palette: soft attendance / location check-in screen.
abstract final class AttendanceSoftPalette {
  static const Color orange = Color(0xFFFF8A00);
  static const Color green = Color(0xFF10A345);
  static const Color purple = Color(0xFF8B7EC8);
  static const Color cream = Color(0xFFF9F5F2);
}

class AttendanceSoftLocationBackground extends StatelessWidget {
  const AttendanceSoftLocationBackground({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: Colors.white,
      child: SizedBox(
        width: double.infinity,
        height: double.infinity,
        child: child,
      ),
    );
  }
}

class AttendancePillTabBar extends StatelessWidget {
  const AttendancePillTabBar({
    super.key,
    required this.selectedIndex,
    required this.onSelect,
  });

  final int selectedIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
      child: Container(
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.9),
          borderRadius: BorderRadius.circular(999),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 14,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: Row(
          children: [
            Expanded(
              child: _pill(
                index: 0,
                label: "Today's attendance",
              ),
            ),
            Expanded(
              child: _pill(
                index: 1,
                label: 'Attendance list',
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pill({required int index, required String label}) {
    final sel = selectedIndex == index;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => onSelect(index),
        borderRadius: BorderRadius.circular(999),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOutCubic,
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
          decoration: BoxDecoration(
            color: sel ? AttendanceSoftPalette.orange : Colors.transparent,
            borderRadius: BorderRadius.circular(999),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 2,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 12.5,
              height: 1.2,
              color: sel ? Colors.white : const Color(0xFF424242),
            ),
          ),
        ),
      ),
    );
  }
}

/// Stylized map tiles + purple pin with continuous “breathing” scale + halo.
class BreathingMapMarker extends StatefulWidget {
  const BreathingMapMarker({super.key});

  @override
  State<BreathingMapMarker> createState() => _BreathingMapMarkerState();
}

class _BreathingMapMarkerState extends State<BreathingMapMarker>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2400),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = Curves.easeInOut.transform(_controller.value);
        final scale = 1.0 + 0.16 * t;
        final haloScale = 1.0 + 0.55 * t;
        final haloOpacity = 0.22 + 0.2 * (1 - t);
        return Stack(
          alignment: Alignment.center,
          clipBehavior: Clip.none,
          children: [
            Opacity(
              opacity: haloOpacity,
              child: Transform.scale(
                scale: haloScale,
                child: Container(
                  width: 76,
                  height: 76,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AttendanceSoftPalette.purple.withValues(alpha: 0.35),
                  ),
                ),
              ),
            ),
            Transform.scale(
              scale: scale,
              child: Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AttendanceSoftPalette.purple,
                  boxShadow: [
                    BoxShadow(
                      color: AttendanceSoftPalette.purple.withValues(alpha: 0.5),
                      blurRadius: 16,
                      spreadRadius: 1,
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.location_on_rounded,
                  color: Colors.white,
                  size: 30,
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _FakeMapPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final base = Paint()..color = const Color(0xFFE5E8EC);
    canvas.drawRect(Offset.zero & size, base);

    final rnd = math.Random(21);
    // Land blocks.
    final block = Paint()..color = const Color(0xFFD4DADF);
    for (var i = 0; i < 14; i++) {
      final w = 26.0 + rnd.nextDouble() * 70;
      final h = 18.0 + rnd.nextDouble() * 46;
      final x = rnd.nextDouble() * (size.width - w);
      final y = rnd.nextDouble() * (size.height - h);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, y, w, h),
          const Radius.circular(7),
        ),
        block,
      );
    }

    // Parks / greener patches.
    final park = Paint()..color = const Color(0xFFC6DBCA);
    for (var i = 0; i < 5; i++) {
      final w = 40.0 + rnd.nextDouble() * 55;
      final h = 20.0 + rnd.nextDouble() * 34;
      final x = rnd.nextDouble() * (size.width - w);
      final y = rnd.nextDouble() * (size.height - h);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, y, w, h),
          const Radius.circular(10),
        ),
        park,
      );
    }

    // Road network.
    final road = Paint()
      ..color = const Color(0xFFF7F9FB)
      ..strokeWidth = 11
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    final roadEdge = Paint()
      ..color = const Color(0xFFD0D7DE)
      ..strokeWidth = 12.5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final roads = <List<Offset>>[
      [
        Offset(size.width * 0.02, size.height * 0.2),
        Offset(size.width * 0.25, size.height * 0.25),
        Offset(size.width * 0.55, size.height * 0.18),
        Offset(size.width * 0.98, size.height * 0.28),
      ],
      [
        Offset(size.width * 0.05, size.height * 0.7),
        Offset(size.width * 0.35, size.height * 0.62),
        Offset(size.width * 0.68, size.height * 0.74),
        Offset(size.width * 0.95, size.height * 0.66),
      ],
      [
        Offset(size.width * 0.2, size.height * 0.05),
        Offset(size.width * 0.32, size.height * 0.34),
        Offset(size.width * 0.28, size.height * 0.58),
        Offset(size.width * 0.36, size.height * 0.95),
      ],
    ];
    for (final pts in roads) {
      final p = Path()..moveTo(pts.first.dx, pts.first.dy);
      for (var i = 1; i < pts.length; i++) {
        p.lineTo(pts[i].dx, pts[i].dy);
      }
      canvas.drawPath(p, roadEdge);
      canvas.drawPath(p, road);
    }

    // Minor streets.
    final minor = Paint()
      ..color = Colors.white.withValues(alpha: 0.88)
      ..strokeWidth = 5.5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    for (var i = 0; i < 7; i++) {
      final y = 12 + i * (size.height - 24) / 7;
      canvas.drawLine(
        Offset(4, y + rnd.nextDouble() * 6 - 3),
        Offset(size.width - 4, y + rnd.nextDouble() * 6 - 3),
        minor,
      );
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class SoftAttendanceMapPreview extends StatelessWidget {
  const SoftAttendanceMapPreview({super.key});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: AspectRatio(
        aspectRatio: 1.5,
        child: Stack(
          fit: StackFit.expand,
          children: [
            CustomPaint(painter: _FakeMapPainter()),
            const Center(child: BreathingMapMarker()),
          ],
        ),
      ),
    );
  }
}

/// Time remaining until session end (or placeholder while unknown).
class SessionCountdownBoxes extends StatelessWidget {
  const SessionCountdownBoxes({super.key, required this.remainingText});

  final String remainingText;

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    Widget box(String text) {
      return Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 6),
          decoration: BoxDecoration(
            color: const Color(0xFFF0F0F0),
            borderRadius: BorderRadius.circular(16),
          ),
          alignment: Alignment.center,
          child: Text(
            text,
            style: tt.titleLarge?.copyWith(
              fontWeight: FontWeight.w800,
              letterSpacing: 0.5,
              color: const Color(0xFF1A1A1A),
            ),
          ),
        ),
      );
    }

    final t = remainingText.trim();
    if (t == 'Session ended' ||
        t == '—' ||
        t == '--:--' ||
        t.isEmpty) {
      return Column(
        children: [
          Text(
            'Time left',
            style: tt.labelLarge?.copyWith(
              fontWeight: FontWeight.w600,
              color: const Color(0xFF616161),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            t.isEmpty || t == '--:--' ? '—' : t,
            textAlign: TextAlign.center,
            style: tt.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: const Color(0xFF424242),
            ),
          ),
        ],
      );
    }

    final parts = t.split(':');
    final seg = parts.length >= 3
        ? parts.sublist(0, 3)
        : parts.length == 2
            ? ['00', parts[0].trim().padLeft(2, '0'), parts[1].trim().padLeft(2, '0')]
            : ['—', '—', '—'];

    return Column(
      children: [
        Text(
          'Time left',
          style: tt.labelLarge?.copyWith(
            fontWeight: FontWeight.w600,
            color: const Color(0xFF616161),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            box(seg[0]),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 6),
              child: Text(
                ':',
                style: tt.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
            ),
            box(seg[1]),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 6),
              child: Text(
                ':',
                style: tt.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
            ),
            box(seg[2]),
          ],
        ),
      ],
    );
  }
}

/// Main white card: date, start time, venue, map, status, check-in, quick icons.
class SoftLocationCheckInCard extends StatelessWidget {
  const SoftLocationCheckInCard({
    super.key,
    required this.dateLabel,
    required this.countdownRemainingText,
    required this.venueLine,
    required this.courseLine,
    required this.statusWidget,
    required this.onCheckIn,
    required this.checkInEnabled,
    required this.checkInBusy,
    required this.checkInLabel,
    this.onCheckOut,
    this.checkOutEnabled = false,
    this.checkOutBusy = false,
    this.checkOutLabel = 'Check-out',
    this.onHistory,
    this.onRefreshLocation,
    this.onScheduleInfo,
    this.showQuickActions = true,
  });

  final String dateLabel;
  /// From session end countdown (e.g. `MM:SS` or `HH:MM:SS`).
  final String countdownRemainingText;
  final String venueLine;
  final String? courseLine;
  final Widget statusWidget;
  final VoidCallback? onCheckIn;
  final bool checkInEnabled;
  final bool checkInBusy;
  final String checkInLabel;
  final VoidCallback? onCheckOut;
  final bool checkOutEnabled;
  final bool checkOutBusy;
  final String checkOutLabel;
  final VoidCallback? onHistory;
  final VoidCallback? onRefreshLocation;
  final VoidCallback? onScheduleInfo;
  final bool showQuickActions;

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.07),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionCountdownBoxes(remainingText: countdownRemainingText),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.location_on_outlined,
                size: 18,
                color: AttendanceSoftPalette.purple.withValues(alpha: 0.9),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  venueLine,
                  textAlign: TextAlign.center,
                  style: tt.bodySmall?.copyWith(
                    color: const Color(0xFF757575),
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const SoftAttendanceMapPreview(),
          const SizedBox(height: 12),
          statusWidget,
          const SizedBox(height: 14),
          if (onCheckOut == null)
            Center(
              child: _actionCircle(
                color: AttendanceSoftPalette.green,
                icon: Icons.touch_app_rounded,
                enabled: checkInEnabled,
                busy: checkInBusy,
                label: checkInLabel,
                onTap: onCheckIn,
              ),
            )
          else
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _actionCircle(
                  color: AttendanceSoftPalette.green,
                  icon: Icons.touch_app_rounded,
                  enabled: checkInEnabled,
                  busy: checkInBusy,
                  label: checkInLabel,
                  onTap: onCheckIn,
                ),
                const SizedBox(width: 16),
                _actionCircle(
                  color: const Color(0xFFEF5350),
                  icon: Icons.logout_rounded,
                  enabled: checkOutEnabled,
                  busy: checkOutBusy,
                  label: checkOutLabel,
                  onTap: onCheckOut,
                ),
              ],
            ),
          if (showQuickActions) ...[
            const SizedBox(height: 18),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _quickIcon(
                  icon: Icons.schedule_outlined,
                  onTap: onScheduleInfo,
                ),
                _quickIcon(
                  icon: Icons.my_location_outlined,
                  onTap: onRefreshLocation,
                ),
                _quickIcon(
                  icon: Icons.history_rounded,
                  onTap: onHistory,
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _actionCircle({
    required Color color,
    required IconData icon,
    required bool enabled,
    required bool busy,
    required String label,
    required VoidCallback? onTap,
  }) {
    final inactive = !enabled && !busy;
    final activeTop = Color.alphaBlend(const Color(0x10000000), color);
    final activeBottom = Color.alphaBlend(const Color(0x22000000), color);
    final inactiveFill = color.withValues(alpha: 0.48);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: enabled && !busy ? onTap : null,
        customBorder: const CircleBorder(),
        child: Ink(
          width: 96,
          height: 96,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: color.withValues(alpha: inactive ? 0.06 : 0.08),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: inactive ? 0.04 : 0.10),
                blurRadius: 8,
                spreadRadius: 0,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(5),
            child: DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: (enabled || busy)
                    ? LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [activeTop, activeBottom],
                      )
                    : LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [inactiveFill, inactiveFill],
                      ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 4,
                    offset: const Offset(0, 1),
                  ),
                ],
              ),
              child: busy
                  ? const Padding(
                      padding: EdgeInsets.all(22),
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Center(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 6),
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                icon,
                                size: 20,
                                color: Colors.white.withValues(alpha: enabled ? 1 : 0.92),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                label,
                                textAlign: TextAlign.center,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: Colors.white.withValues(alpha: enabled ? 1 : 0.94),
                                  fontWeight: FontWeight.w600,
                                  fontSize: 11,
                                  height: 1.1,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _quickIcon({
    required IconData icon,
    required VoidCallback? onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: AttendanceSoftPalette.purple.withValues(alpha: 0.65),
              width: 1.6,
            ),
          ),
          child: Icon(
            icon,
            color: AttendanceSoftPalette.purple,
            size: 22,
          ),
        ),
      ),
    );
  }
}
