import 'package:flutter/material.dart';

import 'app_bottom_nav.dart';

/// Context-aware primary action button:
///   - Student: "Mark attendance"
///   - Class rep: "Open session"
///
/// Sits above the bottom navigation bar (FAB location =
/// `endFloat`). Disabled while [enabled] is false (e.g. when the
/// home page is still loading the student profile).
class AppPrimaryFab extends StatelessWidget {
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
  Widget build(BuildContext context) {
    final isStudent = role == AppNavRole.student;
    return FloatingActionButton.extended(
      onPressed: enabled ? onPressed : null,
      icon: Icon(isStudent ? Icons.qr_code_scanner_rounded : Icons.play_circle_outline),
      label: Text(isStudent ? 'Mark attendance' : 'Open session'),
      backgroundColor:
          enabled ? null : Theme.of(context).disabledColor.withValues(alpha: 0.4),
      foregroundColor: enabled ? null : Theme.of(context).colorScheme.onSurface,
    );
  }
}
