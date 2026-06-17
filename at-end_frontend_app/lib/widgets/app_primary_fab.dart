import 'package:flutter/material.dart';

import 'app_bottom_nav.dart';

/// Context-aware primary action with a smooth scale / fade animation.
class AppPrimaryFab extends StatefulWidget {
  const AppPrimaryFab({
    super.key,
    required this.role,
    required this.onPressed,
    this.enabled = true,
  });

  final AppNavRole role;
  final VoidCallback? onPressed;
  final bool enabled;

  @override
  State<AppPrimaryFab> createState() => _AppPrimaryFabState();
}

class _AppPrimaryFabState extends State<AppPrimaryFab>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 220),
      reverseDuration: const Duration(milliseconds: 180),
    );
    _scale = Tween<double>(begin: 1, end: 0.94).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic),
    );
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _handleTapDown(TapDownDetails _) {
    if (!widget.enabled) return;
    _controller.reverse();
  }

  void _handleTapUp(TapUpDetails _) {
    if (!widget.enabled) return;
    _controller.forward();
  }

  void _handleTapCancel() {
    if (!widget.enabled) return;
    _controller.forward();
  }

  @override
  Widget build(BuildContext context) {
    final isStudent = widget.role == AppNavRole.student;
    final label = isStudent ? 'Mark attendance' : 'Open session';
    final icon = isStudent
        ? Icons.qr_code_scanner_rounded
        : Icons.play_circle_outline;

    return ScaleTransition(
      scale: _scale,
      child: FadeTransition(
        opacity: _controller.drive(CurveTween(curve: Curves.easeOut)),
        child: GestureDetector(
          onTapDown: _handleTapDown,
          onTapUp: _handleTapUp,
          onTapCancel: _handleTapCancel,
          child: FloatingActionButton.extended(
            heroTag: 'app-primary-fab',
            onPressed: widget.enabled ? widget.onPressed : null,
            elevation: widget.enabled ? 6 : 0,
            icon: Icon(icon),
            label: Text(label),
            backgroundColor: widget.enabled
                ? null
                : Theme.of(context).disabledColor.withValues(alpha: 0.4),
            foregroundColor: widget.enabled
                ? null
                : Theme.of(context).colorScheme.onSurface,
          ),
        ),
      ),
    );
  }
}
