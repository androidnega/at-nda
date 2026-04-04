/// Maps API / framework error strings to safe, user-facing copy (no stack traces or PHP namespaces).
String sanitizeApiUserMessage(String? raw, {String fallback = 'Something went wrong. Please try again.'}) {
  if (raw == null) return fallback;
  final r = raw.trim();
  if (r.isEmpty) return fallback;

  final lower = r.toLowerCase();

  if (lower.contains('no query results for model') ||
      (lower.contains('could not be found') && lower.contains('model'))) {
    return 'That session could not be found. Check the session ID, or open this screen right after a class session you manage.';
  }

  if (lower == 'not allowed' ||
      (lower.contains('forbidden') && r.length < 60)) {
    return 'You do not have permission to view these attendance records.';
  }

  if (lower.contains('unauthenticated') || lower == 'unauthorized') {
    return 'Please sign in again and try.';
  }

  if (lower.contains('course not found')) {
    return 'This session is no longer available.';
  }

  // Never show framework paths, SQL, or Laravel internals.
  if (r.contains('\\') ||
      r.contains('Illuminate\\') ||
      r.contains('App\\Models\\') ||
      r.contains('SQLSTATE') ||
      r.contains('vendor/') ||
      r.contains('stack trace') ||
      r.contains('Exception:')) {
    return fallback;
  }

  return r;
}
