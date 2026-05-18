import 'package:flutter/material.dart';

/// Subtle concentric arcs (top-right) for violet calendar dashboard headers.
class VioletCalendarArcPainter extends CustomPainter {
  VioletCalendarArcPainter({this.opacity = 0.09});

  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width * 0.88, size.height * 0.12);
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1
      ..color = Colors.white.withValues(alpha: opacity);
    for (var i = 1; i <= 10; i++) {
      canvas.drawCircle(center, 18.0 * i, paint);
    }
  }

  @override
  bool shouldRepaint(covariant VioletCalendarArcPainter oldDelegate) =>
      oldDelegate.opacity != opacity;
}
