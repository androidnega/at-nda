import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

/// Watches `X-Api-Version`, `Deprecation`, and `Sunset` headers emitted by
/// the Laravel backend (see `app/Http/Middleware/ApiVersionHeaders.php`).
///
/// Mobile widgets can call [ApiVersionWatcher.instance.inspect] from any
/// `http.Response` they already receive and then `ListenableBuilder` on
/// [ApiVersionWatcher.instance] to surface a "please update" banner when
/// the backend announces a Sunset date for the legacy `/api/*` routes.
///
/// This file is intentionally a single-purpose, additive helper — it does
/// not change any existing networking code. Pages opt in.
class ApiVersionWatcher extends ChangeNotifier {
  ApiVersionWatcher._();
  static final ApiVersionWatcher instance = ApiVersionWatcher._();

  String? _apiVersion;
  bool _deprecated = false;
  DateTime? _sunsetAt;
  String? _successorVersionLink;

  String? get apiVersion => _apiVersion;
  bool get isLegacy => _apiVersion == 'legacy';
  bool get isDeprecated => _deprecated;
  DateTime? get sunsetAt => _sunsetAt;
  String? get successorVersionLink => _successorVersionLink;

  /// Convenience: true once we're within 60 days of the Sunset date.
  bool get shouldShowUpdateBanner {
    if (!_deprecated) return false;
    final s = _sunsetAt;
    if (s == null) return _deprecated; // generic warning
    final days = s.difference(DateTime.now()).inDays;
    return days <= 60;
  }

  /// Feed any [http.Response] you already have in hand. Idempotent and
  /// cheap (header lookups only); safe to call after every request.
  void inspect(http.Response response) {
    final headers = response.headers;
    final version = _firstHeader(headers, 'x-api-version');
    final deprecation = _firstHeader(headers, 'deprecation');
    final sunset = _firstHeader(headers, 'sunset');
    final link = _firstHeader(headers, 'link');

    var changed = false;
    if (version != null && version != _apiVersion) {
      _apiVersion = version;
      changed = true;
    }
    final dep = deprecation != null && deprecation.toLowerCase() == 'true';
    if (dep != _deprecated) {
      _deprecated = dep;
      changed = true;
    }
    if (sunset != null && sunset.isNotEmpty) {
      final parsed = _tryParseSunset(sunset);
      if (parsed != null && parsed != _sunsetAt) {
        _sunsetAt = parsed;
        changed = true;
      }
    }
    if (link != null && link.contains('rel="successor-version"')) {
      if (link != _successorVersionLink) {
        _successorVersionLink = link;
        changed = true;
      }
    }

    if (changed) notifyListeners();
  }

  String? _firstHeader(Map<String, String> headers, String name) {
    // HTTP headers are case-insensitive; package:http already lower-cases.
    return headers[name];
  }

  DateTime? _tryParseSunset(String value) {
    // Backend emits either RFC 1123 or YYYY-MM-DD. Try both.
    try {
      return DateTime.parse(value).toUtc();
    } catch (_) {}
    try {
      return HttpDate.parse(value).toUtc();
    } catch (_) {}
    return null;
  }
}

/// Re-export of `dart:io`'s HttpDate via a tiny shim so we don't add
/// `dart:io` imports to every consumer page (web targets the same API).
class HttpDate {
  static DateTime parse(String input) {
    // Minimal RFC 1123 parser fallback: e.g. "Wed, 21 Oct 2026 07:28:00 GMT"
    final parts = input.split(' ');
    if (parts.length < 5) {
      throw FormatException('Not RFC 1123: $input');
    }
    const months = {
      'Jan': 1, 'Feb': 2, 'Mar': 3, 'Apr': 4, 'May': 5, 'Jun': 6,
      'Jul': 7, 'Aug': 8, 'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dec': 12,
    };
    final day = int.parse(parts[1]);
    final month = months[parts[2]] ?? 1;
    final year = int.parse(parts[3]);
    final hms = parts[4].split(':');
    return DateTime.utc(
      year,
      month,
      day,
      int.parse(hms[0]),
      int.parse(hms[1]),
      hms.length > 2 ? int.parse(hms[2]) : 0,
    );
  }
}
