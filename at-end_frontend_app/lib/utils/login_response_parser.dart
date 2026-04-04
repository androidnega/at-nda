/// Shared parsing for `POST /api/login` and `POST /api/me` JSON bodies.
Map<String, dynamic>? studentMapFromLoginBody(Map<String, dynamic> body) {
  Map<String, dynamic>? primary;
  if (body['student'] is Map) {
    primary = Map<String, dynamic>.from(body['student'] as Map);
  } else if (body['user'] is Map) {
    primary = Map<String, dynamic>.from(body['user'] as Map);
  } else {
    final idx = body['index_number'];
    if (idx != null && idx.toString().trim().isNotEmpty) {
      primary = Map<String, dynamic>.from(body);
    }
  }
  if (primary == null) return null;

  void copyRepKeys(Map<String, dynamic> src) {
    if (src.containsKey('is_class_rep')) {
      primary!['is_class_rep'] = src['is_class_rep'];
    }
    if (src.containsKey('rep_roles')) {
      primary!['rep_roles'] = src['rep_roles'];
    }
    if (src.containsKey('primary_role')) {
      primary!['primary_role'] = src['primary_role'];
    }
  }

  copyRepKeys(body);
  if (body['user'] is Map) {
    copyRepKeys(Map<String, dynamic>.from(body['user'] as Map));
  }

  // Laravel often puts numeric `id` on `user` only; profile image URL needs it on native.
  if (primary['id'] == null) {
    final fromRoot = body['id'];
    if (fromRoot != null) {
      primary['id'] = fromRoot;
    } else if (body['user'] is Map) {
      final uid = (body['user'] as Map)['id'];
      if (uid != null) primary['id'] = uid;
    }
  }

  return primary;
}

/// Non-empty Sanctum token from legacy login/me, or null if absent / JSON null.
String? parseLoginResponseToken(dynamic raw) {
  if (raw == null) return null;
  if (raw is String) {
    final s = raw.trim();
    if (s.isEmpty || s == 'null') return null;
    return s;
  }
  final s = raw.toString().trim();
  if (s.isEmpty || s == 'null') return null;
  return s;
}
