import 'package:custom_refresh_indicator/custom_refresh_indicator.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Bounce + always scrollable so pull-to-refresh works with short content.
ScrollPhysics get modernPullToRefreshPhysics =>
    const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics());

/// Fluid pull-to-refresh: elastic overscroll (via [modernPullToRefreshPhysics]),
/// morphing arrow → ring → spinner, threshold haptic, tuned phase durations.
class ModernPullToRefresh extends StatelessWidget {
  const ModernPullToRefresh({
    super.key,
    required this.onRefresh,
    required this.child,
    this.edgeOffset = 0,
    this.offsetToArmed = 76,
  });

  final Future<void> Function() onRefresh;
  final Widget child;

  /// Extra top inset (e.g. under status bar / safe area already applied).
  final double edgeOffset;

  /// Pull distance (px) to reach armed threshold (release then runs [onRefresh]).
  final double offsetToArmed;

  static const _durations = RefreshIndicatorDurations(
    cancelDuration: Duration(milliseconds: 300),
    settleDuration: Duration(milliseconds: 240),
    finalizeDuration: Duration(milliseconds: 320),
    completeDuration: Duration(milliseconds: 140),
  );

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return CustomRefreshIndicator(
      offsetToArmed: offsetToArmed,
      leadingScrollIndicatorVisible: false,
      trailingScrollIndicatorVisible: false,
      durations: _durations,
      onStateChanged: (change) {
        if (change.didChange(to: IndicatorState.armed)) {
          HapticFeedback.mediumImpact();
        }
        if (change.didChange(to: IndicatorState.complete)) {
          HapticFeedback.selectionClick();
        }
      },
      onRefresh: () async {
        await onRefresh();
      },
      child: child,
      builder: (context, scrollableChild, controller) {
        final v = controller.value.clamp(0.0, 1.5);
        // Subtle content follow + “liquid” stretch (keeps motion smooth at 60fps).
        final pullT = Curves.easeOutCubic.transform((v / 1.2).clamp(0.0, 1.0));
        final translateY = 14.0 * pullT;
        final scaleY = 1.0 + 0.012 * pullT;

        return Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.topCenter,
          children: [
            Transform.translate(
              offset: Offset(0, translateY),
              child: Transform.scale(
                alignment: Alignment.topCenter,
                scaleX: 1.0,
                scaleY: scaleY,
                child: scrollableChild,
              ),
            ),
            Positioned(
              top: edgeOffset + (-28 + v * 56).clamp(4.0, 120.0),
              left: 0,
              right: 0,
              child: IgnorePointer(
                child: AnimatedOpacity(
                  duration: const Duration(milliseconds: 80),
                  opacity: v > 0.02 || controller.state.isLoading ? 1 : 0,
                  child: _PullIndicatorGlyph(
                    controller: controller,
                    color: cs.primary,
                    trackColor: cs.surfaceContainerHighest,
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _PullIndicatorGlyph extends StatelessWidget {
  const _PullIndicatorGlyph({
    required this.controller,
    required this.color,
    required this.trackColor,
  });

  final IndicatorController controller;
  final Color color;
  final Color trackColor;

  @override
  Widget build(BuildContext context) {
    final c = controller;
    final ringProgress =
        (c.value / CustomRefreshIndicator.armedFromValue).clamp(0.0, 1.0);

    final showBusy = c.state.isLoading;

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 220),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      transitionBuilder: (child, anim) => FadeTransition(opacity: anim, child: child),
      child: showBusy
          ? SizedBox(
              key: const ValueKey('busy'),
              width: 32,
              height: 32,
              child: CircularProgressIndicator(
                strokeWidth: 2.75,
                strokeCap: StrokeCap.round,
                color: color,
                backgroundColor: trackColor.withValues(alpha: 0.35),
              ),
            )
          : SizedBox(
              key: const ValueKey('pull-morph'),
              width: 40,
              height: 40,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  SizedBox.expand(
                    child: CircularProgressIndicator(
                      value: ringProgress,
                      strokeWidth: 2.5,
                      strokeCap: StrokeCap.round,
                      color: color.withValues(alpha: 0.85),
                      backgroundColor: trackColor.withValues(alpha: 0.2),
                    ),
                  ),
                  Transform.rotate(
                    angle: ringProgress * 3.1415926535897932,
                    child: Icon(
                      Icons.arrow_downward_rounded,
                      size: 18,
                      color: color.withValues(alpha: 0.5 + 0.5 * ringProgress),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
