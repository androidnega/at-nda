import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

/// Line chart: [points] entries with `rate` (0–100) and `label` (short string).
/// Uses [ColorScheme] so light/dark text and strokes stay readable (no white-on-white).
class AttendanceTrendChart extends StatelessWidget {
  const AttendanceTrendChart({
    super.key,
    required this.points,
    this.height = 200,
    this.title = 'Attendance % over time',
    /// Tighter padding and chart gutters — use on dense dashboards (e.g. class rep).
    this.compact = false,
    /// Teal–purple hero on class rep: transparent chrome, light line/labels on gradient.
    this.onGradientBackground = false,
  });

  final List<Map<String, dynamic>> points;
  final double height;
  final String title;
  final bool compact;
  final bool onGradientBackground;

  BoxDecoration _cardDecoration(ColorScheme cs) {
    return BoxDecoration(
      color: cs.surfaceContainerLow,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.7)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final embed = onGradientBackground;

    if (points.isEmpty) {
      final emptyStyle = tt.bodySmall?.copyWith(
        color: embed
            ? Colors.white.withValues(alpha: 0.92)
            : cs.onSurfaceVariant,
        fontWeight: FontWeight.w500,
        fontSize: compact ? 12 : null,
      );
      return Container(
        width: double.infinity,
        padding: EdgeInsets.all(compact ? 14 : 20),
        decoration: embed ? null : _cardDecoration(cs),
        child: Text(
          'Not enough completed sessions yet to show a trend.',
          style: emptyStyle,
          textAlign: TextAlign.center,
        ),
      );
    }

    final rates = points
        .map((p) => (p['rate'] is num)
            ? (p['rate'] as num).toDouble()
            : double.tryParse('${p['rate']}') ?? 0.0)
        .toList();
    final labels = points
        .map((p) => p['label']?.toString() ?? '')
        .toList();

    final minY = (rates.reduce((a, b) => a < b ? a : b) - 8).clamp(0.0, 100.0);
    final maxY = (rates.reduce((a, b) => a > b ? a : b) + 8).clamp(0.0, 100.0);

    final lineColor = embed ? Colors.white : cs.primary;
    final gridColor =
        embed ? Colors.white.withValues(alpha: 0.32) : cs.outline;
    final labelColor =
        embed ? Colors.white.withValues(alpha: 0.88) : cs.onSurfaceVariant;
    final dotHalo =
        embed ? Colors.white.withValues(alpha: 0.35) : cs.surface;

    final pad = compact
        ? const EdgeInsets.fromLTRB(10, 12, 10, 6)
        : const EdgeInsets.fromLTRB(12, 16, 12, 8);
    final titleGap = compact ? 8.0 : 12.0;
    final showTitle = title.trim().isNotEmpty;

    return Container(
      width: double.infinity,
      padding: pad,
      decoration: embed ? null : _cardDecoration(cs),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (showTitle) ...[
            Text(
              title,
              style: (compact ? tt.titleSmall : tt.titleMedium)?.copyWith(
                color: embed ? Colors.white : cs.onSurface,
                fontWeight: FontWeight.w700,
              ),
            ),
            SizedBox(height: titleGap),
          ],
          SizedBox(
            height: height,
            width: double.infinity,
            child: LayoutBuilder(
              builder: (context, constraints) {
                return CustomPaint(
                  size: Size(constraints.maxWidth, height),
                  painter: _AttendanceTrendPainter(
                    rates: rates,
                    labels: labels,
                    minY: minY,
                    maxY: maxY,
                    lineColor: lineColor,
                    gridColor: gridColor,
                    labelColor: labelColor,
                    dotHaloColor: dotHalo,
                    compact: compact,
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _AttendanceTrendPainter extends CustomPainter {
  _AttendanceTrendPainter({
    required this.rates,
    required this.labels,
    required this.minY,
    required this.maxY,
    required this.lineColor,
    required this.gridColor,
    required this.labelColor,
    required this.dotHaloColor,
    this.compact = false,
  });

  final List<double> rates;
  final List<String> labels;
  final double minY;
  final double maxY;
  final Color lineColor;
  final Color gridColor;
  final Color labelColor;
  final Color dotHaloColor;
  final bool compact;

  double get _leftGutter => compact ? 34 : 40;
  double get _bottomGutter => compact ? 20 : 28;
  double get _topGutter => compact ? 5 : 8;
  double get _rightGutter => compact ? 6 : 8;

  @override
  void paint(Canvas canvas, Size size) {
    final lg = _leftGutter;
    final bg = _bottomGutter;
    final tg = _topGutter;
    final rg = _rightGutter;
    final chartRect = Rect.fromLTWH(
      lg,
      tg,
      size.width - lg - rg,
      size.height - tg - bg,
    );

    final range = (maxY - minY).abs() < 1e-6 ? 1.0 : (maxY - minY);

    double yCanvas(double rate) {
      final t = (rate - minY) / range;
      return chartRect.bottom - t * chartRect.height;
    }

    double xCanvas(int i) {
      final n = rates.length;
      if (n <= 1) {
        return chartRect.left + chartRect.width / 2;
      }
      return chartRect.left + (i / (n - 1)) * chartRect.width;
    }

    final gridPaint = Paint()
      ..color = gridColor.withValues(alpha: 0.45)
      ..strokeWidth = 1;

    for (var v = 0.0; v <= 100; v += 20) {
      if (v < minY - 0.01 || v > maxY + 0.01) continue;
      final y = yCanvas(v);
      canvas.drawLine(
        Offset(chartRect.left, y),
        Offset(chartRect.right, y),
        gridPaint,
      );

      final tp = TextPainter(
        text: TextSpan(
          text: '${v.round()}%',
          style: TextStyle(
            color: labelColor,
            fontSize: compact ? 9 : 10,
          ),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      tp.paint(canvas, Offset(compact ? 2 : 4, y - tp.height / 2));
    }

    final linePath = Path();
    for (var i = 0; i < rates.length; i++) {
      final ox = xCanvas(i);
      final oy = yCanvas(rates[i]);
      if (i == 0) {
        linePath.moveTo(ox, oy);
      } else {
        linePath.lineTo(ox, oy);
      }
    }

    if (rates.isNotEmpty) {
      final fillPath = Path.from(linePath);
      final lastX = xCanvas(rates.length - 1);
      final firstX = xCanvas(0);
      fillPath
        ..lineTo(lastX, chartRect.bottom)
        ..lineTo(firstX, chartRect.bottom)
        ..close();

      final fillPaint = Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            lineColor.withValues(alpha: 0.22),
            lineColor.withValues(alpha: 0.04),
          ],
        ).createShader(chartRect);
      canvas.drawPath(fillPath, fillPaint);
    }

    final strokeW = compact ? 2.5 : 3.0;
    final strokePaint = Paint()
      ..color = lineColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeW
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(linePath, strokePaint);

    final dotR = compact ? 3.5 : 4.0;
    final dotFill = Paint()..color = lineColor;
    final dotStroke = Paint()
      ..color = dotHaloColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = compact ? 1.5 : 2;
    for (var i = 0; i < rates.length; i++) {
      final c = Offset(xCanvas(i), yCanvas(rates[i]));
      canvas.drawCircle(c, dotR, dotFill);
      canvas.drawCircle(c, dotR, dotStroke);
    }

    final labelInterval = rates.length > 6 ? 2 : 1;
    final bottomLabelSize = compact ? 8.0 : 9.0;
    final bottomLabelTop = compact ? 3.0 : 6.0;
    for (var i = 0; i < labels.length; i++) {
      if (i % labelInterval != 0) continue;
      final tp = TextPainter(
        text: TextSpan(
          text: labels[i],
          style: TextStyle(color: labelColor, fontSize: bottomLabelSize),
        ),
        textDirection: TextDirection.ltr,
        maxLines: 1,
        ellipsis: '…',
      )..layout(maxWidth: compact ? 48 : 56);
      final cx = xCanvas(i);
      tp.paint(canvas, Offset(cx - tp.width / 2, chartRect.bottom + bottomLabelTop));
    }
  }

  @override
  bool shouldRepaint(covariant _AttendanceTrendPainter oldDelegate) {
    return minY != oldDelegate.minY ||
        maxY != oldDelegate.maxY ||
        lineColor != oldDelegate.lineColor ||
        gridColor != oldDelegate.gridColor ||
        labelColor != oldDelegate.labelColor ||
        dotHaloColor != oldDelegate.dotHaloColor ||
        compact != oldDelegate.compact ||
        !listEquals(rates, oldDelegate.rates) ||
        !listEquals(labels, oldDelegate.labels);
  }
}
