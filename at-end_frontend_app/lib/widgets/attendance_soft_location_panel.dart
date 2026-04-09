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
    return Container(
      width: double.infinity,
      height: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFFE8E0F5),
            Color(0xFFFFEDE5),
            Color(0xFFF9F5F2),
          ],
          stops: [0.0, 0.42, 1.0],
        ),
      ),
      child: child,
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
    final base = Paint()..color = const Color(0xFFE4E4E4);
    canvas.drawRect(Offset.zero & size, base);

    final rnd = math.Random(42);
    for (var i = 0; i < 10; i++) {
      final ox = rnd.nextDouble() * size.width;
      final oy = rnd.nextDouble() * size.height;
      final r = 18.0 + rnd.nextDouble() * 40;
      final colors = [
        const Color(0xFFB8D4B8),
        const Color(0xFFC8D0E0),
        const Color(0xFFE8DFD0),
      ];
      canvas.drawCircle(
        Offset(ox, oy),
        r,
        Paint()..color = colors[i % colors.length].withValues(alpha: 0.55),
      );
    }

    final grid = Paint()
      ..color = Colors.white.withValues(alpha: 0.4)
      ..strokeWidth = 0.7;
    for (var x = 0.0; x < size.width; x += 22) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), grid);
    }
    for (var y = 0.0; y < size.height; y += 22) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
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

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.fromLTRB(22, 22, 22, 18),
      decoration: BoxDecoration(
        color: AttendanceSoftPalette.cream,
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
          Text(
            dateLabel,
            style: tt.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: const Color(0xFF1A1A1A),
            ),
          ),
          if (courseLine != null && courseLine!.trim().isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              courseLine!,
              style: tt.bodySmall?.copyWith(
                color: const Color(0xFF757575),
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
          const SizedBox(height: 18),
          SessionCountdownBoxes(remainingText: countdownRemainingText),
          const SizedBox(height: 18),
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
          const SizedBox(height: 16),
          const SoftAttendanceMapPreview(),
          const SizedBox(height: 16),
          statusWidget,
          const SizedBox(height: 18),
          if (onCheckOut == null)
            Center(
              child: _actionCircle(
                color: AttendanceSoftPalette.green,
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
                  enabled: checkInEnabled,
                  busy: checkInBusy,
                  label: checkInLabel,
                  onTap: onCheckIn,
                ),
                const SizedBox(width: 24),
                _actionCircle(
                  color: const Color(0xFFEF5350),
                  enabled: checkOutEnabled,
                  busy: checkOutBusy,
                  label: checkOutLabel,
                  onTap: onCheckOut,
                ),
              ],
            ),
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
      ),
    );
  }

  Widget _actionCircle({
    required Color color,
    required bool enabled,
    required bool busy,
    required String label,
    required VoidCallback? onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: enabled && !busy ? onTap : null,
        customBorder: const CircleBorder(),
        child: Ink(
          width: 116,
          height: 116,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: enabled ? color : color.withValues(alpha: 0.35),
            border: Border.all(color: Colors.white, width: 5),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.13),
                blurRadius: 12,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: busy
              ? const Padding(
                  padding: EdgeInsets.all(30),
                  child: CircularProgressIndicator(
                    strokeWidth: 2.5,
                    color: Colors.white,
                  ),
                )
              : Center(
                  child: Text(
                    label,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
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
