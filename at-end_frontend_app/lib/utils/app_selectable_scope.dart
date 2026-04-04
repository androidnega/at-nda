import 'package:flutter/material.dart';

/// No [SelectionArea]: mobile users should not get system copy/select on app UI text;
/// web previously skipped [SelectionArea] due to layout issues with Google Fonts.
Widget appSelectableScope(Widget child) {
  return child;
}
