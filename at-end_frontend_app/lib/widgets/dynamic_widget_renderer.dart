import 'package:flutter/material.dart';

/// Minimal backend-driven UI renderer (v1).
///
/// Backward compatible:
/// - Flutter renders *nothing* when `dynamic_ui` is absent or malformed.
/// - Unknown widget `type` values are ignored (no crash).
///
/// v1 schema (per item):
/// {
///   "type": "text|button|image|divider|list",
///   "value": "...",                      // for text
///   "label": "...",                      // for button
///   "url": "...",                        // for image
///   "items": ["..."],                   // for list
///   "action": {                           // for button
///     "type": "go_to_page",
///     "route": "/profile"
///   }
/// }
class DynamicWidgetRenderer {
  static List<Widget> render(BuildContext context, List<dynamic>? items) {
    if (items == null || items.isEmpty) return const <Widget>[];

    final out = <Widget>[];
    for (final rawItem in items) {
      if (rawItem is! Map) continue;
      try {
        out.add(_renderOne(context, rawItem.cast<String, dynamic>()));
      } catch (e) {
        // Never crash the app due to backend UI.
        // ignore: avoid_print
        print('DynamicWidgetRenderer failed: $e');
      }
    }

    // Flatten possible nested lists (defensive; _renderOne returns a Widget).
    return out.whereType<Widget>().toList();
  }

  static Widget _renderOne(BuildContext context, Map<String, dynamic> item) {
    final type = (item['type'] ?? '').toString().trim().toLowerCase();
    switch (type) {
      case 'text': {
        final value = (item['value'] ?? item['text'] ?? '').toString();
        if (value.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Text(
            value,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        );
      }
      case 'divider':
        return const Divider(height: 24);

      case 'image': {
        final url = (item['url'] ?? item['src'] ?? '').toString();
        if (url.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.network(
              url,
              width: double.infinity,
              height: null,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(
                height: 120,
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
                alignment: Alignment.center,
                child: const Icon(Icons.broken_image_outlined),
              ),
            ),
          ),
        );
      }

      case 'list': {
        final items = item['items'];
        if (items is! List) return const SizedBox.shrink();
        final texts = items.map((e) => e?.toString() ?? '').where((s) => s.isNotEmpty).toList();
        if (texts.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final t in texts)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('• ', style: TextStyle(fontWeight: FontWeight.w700)),
                      Expanded(child: Text(t)),
                    ],
                  ),
                ),
            ],
          ),
        );
      }

      case 'button': {
        final label = (item['label'] ?? item['value'] ?? '').toString();
        if (label.isEmpty) return const SizedBox.shrink();
        final actionRaw = item['action'];

        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: () => _handleButtonAction(context, actionRaw, item),
              child: Text(label),
            ),
          ),
        );
      }

      default:
        // Unknown type -> safely ignore.
        return const SizedBox.shrink();
    }
  }

  static void _handleButtonAction(
    BuildContext context,
    dynamic actionRaw,
    Map<String, dynamic> item,
  ) {
    // v1 supported actions:
    // - go_to_page: requires route in action payload
    final actionMap = actionRaw is Map ? actionRaw.cast<String, dynamic>() : null;
    final actionType = actionMap?['type']?.toString().trim().toLowerCase() ??
        (actionRaw is String ? actionRaw.trim().toLowerCase() : '');

    if (actionType == 'go_to_page') {
      final route = (actionMap?['route'] ?? item['route'] ?? '').toString();
      if (route.isEmpty) return;

      // Allowlist: prevents dynamic UI from navigating to arbitrary routes.
      const allowedRoutes = <String>{
        '/home',
        '/profile',
        '/settings',
        '/rep-home',
        '/rep-sessions',
        '/attendance',
        '/attendance-records',
        '/class-rep/students',
        '/class-rep/flagged',
        '/class-rep/insights',
        '/lecturer-home',
        '/login',
      };
      if (!allowedRoutes.contains(route)) return;
      Navigator.of(context).pushNamed(route);
    }
  }
}

