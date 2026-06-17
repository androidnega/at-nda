/// From API `activeSession['mode']`. Legacy: [qr_enabled] maps to hybrid vs location.
enum AttendanceFlowMode {
  /// QR scan only — no GPS range UI or checks before scan.
  qr,

  /// GPS range only — submit with `qr_code: null`.
  location,

  /// Range check first, then QR scan and submit.
  hybrid,

  /// Rolling online session code (no GPS / QR).
  online,
}

AttendanceFlowMode resolveAttendanceFlowMode(Map<String, dynamic>? session) {
  if (session == null) return AttendanceFlowMode.location;
  final m = session['mode']?.toString().trim().toLowerCase();
  if (m == 'qr') return AttendanceFlowMode.qr;
  if (m == 'location') return AttendanceFlowMode.location;
  if (m == 'hybrid') return AttendanceFlowMode.hybrid;
  if (m == 'online') return AttendanceFlowMode.online;
  // Legacy API: qr_enabled + range was the old hybrid; else location-only.
  if (session['qr_enabled'] == true) return AttendanceFlowMode.hybrid;
  return AttendanceFlowMode.location;
}
