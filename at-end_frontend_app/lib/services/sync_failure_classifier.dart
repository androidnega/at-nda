import '../models/outbox_status.dart';

/// Result of mapping a server / network response onto an outbox state
/// transition. Created by [SyncFailureClassifier] and consumed by the
/// SyncEngine to decide whether to retry, give up, or quarantine.
class SyncOutcome {
  const SyncOutcome({
    required this.status,
    required this.code,
    this.message,
    this.retryable = false,
  });

  /// Terminal state for the row.
  final OutboxStatus status;

  /// Short stable identifier (`http_500`, `network_offline`, …).
  final String code;

  /// User-facing copy for the Sync Status page.
  final String? message;

  /// Whether the SyncEngine should schedule another attempt according
  /// to the retry policy. False means "stop trying".
  final bool retryable;

  SyncOutcome copyWith({String? message}) => SyncOutcome(
        status: status,
        code: code,
        message: message ?? this.message,
        retryable: retryable,
      );

  // Convenience factories used at the SyncEngine call sites.
  static const synced = SyncOutcome(
    status: OutboxStatus.synced,
    code: 'ok',
    message: 'Attendance recorded successfully.',
  );

  static const already = SyncOutcome(
    status: OutboxStatus.synced,
    code: 'already_marked',
    message: 'Attendance already recorded.',
  );

  static const lateCaptured = SyncOutcome(
    status: OutboxStatus.quarantined,
    code: 'late_capture',
    message: 'Waiting for lecturer approval.',
  );

  static const networkUnavailable = SyncOutcome(
    status: OutboxStatus.failed,
    code: 'network_offline',
    message: 'No network connection.',
    retryable: true,
  );

  static const networkTimeout = SyncOutcome(
    status: OutboxStatus.failed,
    code: 'network_timeout',
    message: 'Request timed out.',
    retryable: true,
  );
}

/// Translates an HTTP status (or transport exception) into a stable
/// [SyncOutcome] the engine can act on.
class SyncFailureClassifier {
  /// Permanent statuses — never retry, mark row Rejected.
  static const Set<int> _permanentStatuses = {400, 401, 403, 404, 410, 422};

  /// Transient statuses — retry under the back-off schedule.
  static const Set<int> _transientStatuses = {408, 425, 429, 500, 502, 503, 504};

  /// Reason strings inside a 422/4xx response body that mean "valid
  /// attendance, but too late". The server now returns these via
  /// AttendanceLateCaptureService with HTTP 202; we keep the keyword
  /// matcher as a belt-and-braces fallback for older deploys.
  static const List<String> _lateKeywords = [
    'late_capture',
    'outside_window',
    'session_expired',
    'outside the attendance window',
    'session has ended',
    'awaiting lecturer approval',
    'awaiting approval',
  ];

  /// Reason strings that indicate the QR proof is unrecoverable. The
  /// server returns these as 403 today — keep a keyword matcher in case
  /// the wording shifts.
  static const List<String> _invalidQrKeywords = [
    'invalid qr',
    'qr expired',
    'invalid qr or session code',
  ];

  /// Classify a successful 2xx response body.
  ///
  /// Returns:
  ///   - `synced` when the row was newly created OR `already_marked` echoed
  ///   - `late` when the server captured the row for lecturer approval
  static SyncOutcome classifySuccess(
    int statusCode,
    Map<String, dynamic> body,
  ) {
    // 202 + `late=true` → quarantined.
    if (statusCode == 202 || body['late'] == true || body['status'] == 'late') {
      return SyncOutcome(
        status: OutboxStatus.quarantined,
        code: 'late_capture',
        message: (body['message'] ?? 'Waiting for lecturer approval.').toString(),
      );
    }

    if (body['already_marked'] == true || body['status'] == 'already_marked') {
      return SyncOutcome.already.copyWith(
        message: body['message']?.toString() ?? 'Attendance already recorded.',
      );
    }

    // Generic success.
    return SyncOutcome.synced.copyWith(
      message: body['message']?.toString() ?? 'Attendance recorded successfully.',
    );
  }

  /// Classify a non-2xx response. Drives the Rejected vs Failed split.
  static SyncOutcome classifyError({
    required int statusCode,
    String? rawBody,
    Map<String, dynamic>? parsedBody,
  }) {
    final bodyText = (rawBody ?? '').toLowerCase();
    final message = parsedBody?['message']?.toString();

    // Late-capture might still be surfaced via 422 on legacy servers.
    if (_matchesAny(bodyText, _lateKeywords) ||
        parsedBody?['late'] == true ||
        parsedBody?['status'] == 'late') {
      return SyncOutcome(
        status: OutboxStatus.quarantined,
        code: 'late_capture',
        message: message ?? 'Waiting for lecturer approval.',
      );
    }

    // Permanent failure — never retry.
    if (_permanentStatuses.contains(statusCode)) {
      // Distinguish "session/window already over" (which is permanent
      // *for this submission only*) from "your account is wrong"
      // (which is a fatal Rejected). Both go to Rejected; the message
      // gives the user the right wording.
      String code;
      if (statusCode == 403 && _matchesAny(bodyText, _invalidQrKeywords)) {
        code = 'invalid_qr';
      } else if (statusCode == 422) {
        code = 'invalid_payload';
      } else {
        code = 'http_$statusCode';
      }
      return SyncOutcome(
        status: OutboxStatus.rejected,
        code: code,
        message: message ?? _permanentMessageFor(statusCode),
        retryable: false,
      );
    }

    if (_transientStatuses.contains(statusCode) || statusCode == 0) {
      return SyncOutcome(
        status: OutboxStatus.failed,
        code: 'http_$statusCode',
        message: message ?? 'Server is busy. We will retry shortly.',
        retryable: true,
      );
    }

    // Unknown shape → retry conservatively.
    return SyncOutcome(
      status: OutboxStatus.failed,
      code: 'http_$statusCode',
      message: message ?? 'Sync failed (HTTP $statusCode).',
      retryable: true,
    );
  }

  /// Classify a transport-level failure (no HTTP response).
  static SyncOutcome classifyTransportError(Object error) {
    final text = error.toString().toLowerCase();
    if (text.contains('timeout') || text.contains('timed out')) {
      return SyncOutcome.networkTimeout;
    }
    return SyncOutcome.networkUnavailable.copyWith(
      message: 'Network unavailable. Will retry when back online.',
    );
  }

  /// Classify a per-record entry from the batch endpoint response.
  ///
  /// The new `POST /api/attendance/sync` returns
  ///   { status: 'synced' | 'already' | 'late' | 'rejected', reason, message, … }
  /// per row. Older deploys return only `synced` / `failed` counts —
  /// callers fall back to a coarse outcome in that case.
  static SyncOutcome classifyBatchResult(Map<String, dynamic> entry) {
    final status = (entry['status'] ?? '').toString().toLowerCase();
    final reason = entry['reason']?.toString();
    final message = entry['message']?.toString();
    switch (status) {
      case 'synced':
        return SyncOutcome.synced.copyWith(message: message);
      case 'already':
        return SyncOutcome.already.copyWith(message: message);
      case 'late':
        return SyncOutcome.lateCaptured.copyWith(message: message);
      case 'rejected':
        return SyncOutcome(
          status: OutboxStatus.rejected,
          code: reason ?? 'rejected',
          message: message ?? 'Sync rejected by server.',
          retryable: false,
        );
      case 'failed':
      default:
        return SyncOutcome(
          status: OutboxStatus.failed,
          code: reason ?? 'failed',
          message: message ?? 'Server could not save this row. Will retry.',
          retryable: true,
        );
    }
  }

  static bool _matchesAny(String haystack, List<String> needles) {
    if (haystack.isEmpty) return false;
    for (final n in needles) {
      if (haystack.contains(n)) return true;
    }
    return false;
  }

  static String _permanentMessageFor(int statusCode) {
    switch (statusCode) {
      case 400:
        return 'The server rejected this submission as malformed.';
      case 401:
        return 'You are signed out. Please log in again and retry.';
      case 403:
        return 'You are not allowed to mark this attendance.';
      case 404:
        return 'The session no longer exists.';
      case 410:
        return 'The session is no longer accepting marks.';
      case 422:
        return 'The server rejected this submission.';
      default:
        return 'Sync rejected (HTTP $statusCode).';
    }
  }
}
