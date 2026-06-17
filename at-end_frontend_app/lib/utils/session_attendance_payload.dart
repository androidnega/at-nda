// Parse IDs and build POST /api/attendance bodies from sessions/active rows.
// Never derive course_id from course name, code, or list index — only from JSON fields.

/// Parses int from API (int, double, or numeric string).
int? parseApiInt(dynamic v) {
  if (v == null) return null;
  if (v is int) return v;
  if (v is num) return v.toInt();
  final s = v.toString().trim();
  if (s.isEmpty) return null;
  return int.tryParse(s);
}

/// Session primary key from the active session object — required for attendance.
int? parseSessionId(Map<String, dynamic> session) {
  final id = parseApiInt(session['id']);
  if (id == null || id <= 0) return null;
  return id;
}

/// [course_id] from the same session row as [session_id] — omit if absent/invalid.
int? parseOptionalCourseId(Map<String, dynamic> session) {
  final id = parseApiInt(session['course_id']);
  if (id == null || id <= 0) return null;
  return id;
}

/// Only include [week_id] when the API sent a valid positive id.
int? parseOptionalWeekId(Map<String, dynamic> session) {
  final id = parseApiInt(session['week_id']);
  if (id == null || id <= 0) return null;
  return id;
}

String? sessionQrToken(Map<String, dynamic> session) {
  final t = session['qr_token']?.toString();
  if (t == null) return null;
  final s = t.trim();
  return s.isEmpty ? null : s;
}

/// Backend: when false (e.g. mode `qr`), do not call Geolocator before attendance POST.
/// Prefer [mode] over [location_required] — the API used to send `location_required: false` for all rows.
bool sessionLocationRequired(Map<String, dynamic> s) {
  final mode = s['mode']?.toString().trim().toLowerCase();
  if (mode == 'location' || mode == 'hybrid') return true;
  if (mode == 'qr' || mode == 'wifi' || mode == 'online') return false;
  final v = s['location_required'];
  if (v is bool) return v;
  if (v is num) return v != 0;
  if (v is String) {
    final t = v.toLowerCase().trim();
    if (t == 'true' || t == '1') return true;
    if (t == 'false' || t == '0') return false;
  }
  return true;
}

/// When true, POST must include proof (`qr_code` / `session_token` from scan or session).
bool sessionRequiresQrProof(Map<String, dynamic> s) {
  final v = s['requires_qr_proof'];
  if (v is bool) return v;
  if (v is num) return v != 0;
  if (v is String) {
    final t = v.toLowerCase().trim();
    if (t == 'true' || t == '1') return true;
    if (t == 'false' || t == '0') return false;
  }
  final m = s['mode']?.toString().trim().toLowerCase();
  if (m == 'qr' || m == 'hybrid') return true;
  return false;
}

/// Builds JSON-safe map: ids are Dart [int]s (not strings).
/// [session_id] optional if [qr_code], [course_id], or [sessionCode] satisfies the API contract.
/// Omits [course_id] / [week_id] when null. Location keys only when [includeLocation].
Map<String, dynamic> buildAttendancePostBody({
  required String indexNumber,
  int? sessionId,
  int? courseId,
  int? weekId,
  double? lat,
  double? lng,
  bool includeLocation = true,
  String? qrCode,
  String? sessionToken,
  String? sessionCode,
  dynamic faceDescriptor,
  String? deviceIp,
  String? deviceId,
  double? accuracyMeters,
  Map<String, dynamic>? clientMeta,
  required String timestamp,
}) {
  final m = <String, dynamic>{
    'index_number': indexNumber,
  };
  if (sessionId != null && sessionId > 0) {
    m['session_id'] = sessionId;
  }
  if (courseId != null && courseId > 0) {
    m['course_id'] = courseId;
  }
  if (weekId != null && weekId > 0) {
    m['week_id'] = weekId;
  }
  if (includeLocation && lat != null && lng != null) {
    m['lat'] = lat;
    m['lng'] = lng;
    if (accuracyMeters != null &&
        accuracyMeters.isFinite &&
        accuracyMeters >= 0) {
      m['accuracy'] = accuracyMeters;
    }
  }
  if (qrCode != null && qrCode.isNotEmpty) {
    m['qr_code'] = qrCode;
  }
  if (sessionToken != null && sessionToken.isNotEmpty) {
    m['session_token'] = sessionToken;
  }
  final trimmedCode = sessionCode?.trim();
  if (trimmedCode != null && trimmedCode.isNotEmpty) {
    m['session_code'] = trimmedCode;
  }
  if (faceDescriptor != null) {
    m['face_descriptor'] = faceDescriptor;
  }
  if (deviceIp != null) m['device_ip'] = deviceIp;
  if (deviceId != null) m['device_id'] = deviceId;
  if (clientMeta != null && clientMeta.isNotEmpty) {
    m['client_meta'] = clientMeta;
  }
  m['timestamp'] = timestamp;

  final hasSession = sessionId != null && sessionId > 0;
  final hasCourse = courseId != null && courseId > 0;
  final hasQr = qrCode != null && qrCode.isNotEmpty;
  final hasCode = trimmedCode != null && trimmedCode.isNotEmpty;
  if (!hasSession && !hasCourse && !hasQr && !hasCode) {
    throw StateError(
      'Attendance body needs session_id, course_id, qr_code, or session_code (per API contract).',
    );
  }
  return m;
}
