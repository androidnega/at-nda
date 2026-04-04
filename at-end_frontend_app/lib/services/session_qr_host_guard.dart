/// While a class rep is displaying the live session QR on this device, we block
/// opening the scanner for the same session (same-device scan is unreliable).
class SessionQrHostGuard {
  SessionQrHostGuard._();

  static int? _hostingSessionId;

  /// Call when the rep QR dialog opens; clear with `null` when it closes.
  static void setHostingSessionId(int? sessionId) {
    _hostingSessionId = sessionId;
  }

  static bool isHostingSession(int sessionId) =>
      _hostingSessionId != null && _hostingSessionId == sessionId;
}
