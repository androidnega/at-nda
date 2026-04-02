import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';

/// On web, wrapping full screens in [SelectionArea] can throw during layout when
/// [SelectableRegion] orders children (e.g. after Google Fonts async load):
/// "RenderBox was not laid out: RenderFractionalTranslation … NEEDS-LAYOUT".
Widget appSelectableScope(Widget child) {
  if (kIsWeb) return child;
  return SelectionArea(child: child);
}
