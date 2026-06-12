import 'dart:async';
import 'dart:convert';

import '../models/outbox_record.dart';
import '../models/outbox_status.dart';
import '../utils/attendance_uuid.dart';
import 'attendance_outbox_repository.dart';
import 'device_service.dart';
import 'offline_service.dart';
import 'offline_sync_coordinator.dart';
import 'sync_engine.dart';
import 'sync_failure_classifier.dart';

/// Outcome of a single "Mark student" attempt from the rep mobile UI.
enum RepMarkResultKind {
  /// Server confirmed the mark in the same request.
  syncedNow,

  /// Network was down (or the server replied with a transient
  /// error) — the row is in the outbox and will replay on the
  /// next connectivity change / heartbeat.
  queuedOffline,

  /// Server returned 409 / "already marked" — the student was
  /// already present and we kept the original record.
  alreadyMarked,

  /// Server rejected the mark for a reason we can't recover from
  /// (e.g. session closed, rep not authorised). Surface the
  /// message to the rep so they can decide.
  rejected,
}

class RepMarkResult {
  RepMarkResult({
    required this.kind,
    required this.message,
    this.attendanceUuid,
    this.serverPayload,
  });

  final RepMarkResultKind kind;
  final String message;
  final String? attendanceUuid;
  final Map<String, dynamic>? serverPayload;

  bool get isSuccess =>
      kind == RepMarkResultKind.syncedNow ||
      kind == RepMarkResultKind.alreadyMarked;
}

/// Rep-side manual marking service. Wraps the existing
/// [AttendanceOutboxRepository] + [SyncEngine] so a rep can mark a
/// student present whether the network is healthy or not — and the
/// row is replayed automatically when connectivity comes back.
class RepMarkService {
  static const String _endpoint = 'class-rep/marks';

  /// Mark [studentId] for [sessionId] with [status]. Returns once
  /// either the network attempt has finished or the row is safely
  /// queued.
  static Future<RepMarkResult> markStudent({
    required int sessionId,
    required int studentId,
    required String studentIndex,
    required String repIndex,
    required String repPassword,
    String status = 'present',
    String reason = '',
  }) async {
    final uuid = AttendanceUuid.generate();
    final deviceId = await DeviceService.getDeviceId();

    // Body the SyncEngine will POST when it processes the outbox
    // row. The classRepApi auth layer accepts index+password in
    // the JSON body, exactly like our other rep endpoints.
    final payload = <String, dynamic>{
      'session_id': sessionId,
      'student_id': studentId,
      'status': status,
      'reason': reason,
      'attendance_uuid': uuid,
      'device_id': deviceId,
      'index_number': repIndex.trim().toUpperCase(),
      'password': repPassword,
    };

    // 1) Persist the intent up front so it survives a kill / crash
    //    before the network attempt completes.
    final record = OutboxRecord(
      attendanceUuid: uuid,
      studentIndex: studentIndex,
      studentId: studentId,
      deviceId: deviceId,
      sessionId: sessionId,
      courseId: null,
      endpoint: _endpoint,
      payload: payload,
      status: OutboxStatus.pending,
    );
    final id = await AttendanceOutboxRepository.insert(record);
    final stored = record.copyWith(id: id, status: OutboxStatus.syncing);
    await AttendanceOutboxRepository.update(id, status: OutboxStatus.syncing);

    // 2) Try once inline so the rep sees an instant confirmation
    //    when online. If the network fails we fall through to the
    //    "queued offline" path; the coordinator will keep
    //    retrying.
    final outcome = await SyncEngine.attemptSingle(stored);
    await _persistOutcome(id, stored.attemptCount, outcome);

    // 3) Pull the latest server message off the outcome so the UI
    //    can show "Marked." vs "Already present." vs the rejection
    //    reason.
    final outcomeMessage = (outcome.message ?? '').trim();

    switch (outcome.status) {
      case OutboxStatus.synced:
        final alreadyCodes = {
          'already',
          'already_marked',
          'idempotent_replay',
        };
        return RepMarkResult(
          kind: alreadyCodes.contains(outcome.code)
              ? RepMarkResultKind.alreadyMarked
              : RepMarkResultKind.syncedNow,
          message: outcomeMessage.isNotEmpty ? outcomeMessage : 'Student marked.',
          attendanceUuid: uuid,
        );
      case OutboxStatus.rejected:
      case OutboxStatus.quarantined:
        // Special-case "already marked" so the UI doesn't show a
        // red error for what is really a no-op.
        if (outcome.code == 'ALREADY_MARKED' ||
            outcome.code == 'already' ||
            outcome.code == 'already_marked') {
          return RepMarkResult(
            kind: RepMarkResultKind.alreadyMarked,
            message: outcomeMessage.isNotEmpty
                ? outcomeMessage
                : 'Already marked for this session.',
            attendanceUuid: uuid,
          );
        }
        return RepMarkResult(
          kind: RepMarkResultKind.rejected,
          message: outcomeMessage.isNotEmpty
              ? outcomeMessage
              : 'Server rejected this mark.',
          attendanceUuid: uuid,
        );
      case OutboxStatus.failed:
      case OutboxStatus.pending:
      case OutboxStatus.syncing:
        // Nudge the coordinator so it'll try again as soon as the
        // network looks healthy. The user can keep marking the
        // next student in the meantime.
        unawaited(
          OfflineSyncCoordinator.instance.requestSync(reason: 'rep_mark'),
        );
        return RepMarkResult(
          kind: RepMarkResultKind.queuedOffline,
          message:
              'Saved on this device. Will sync as soon as the network returns.',
          attendanceUuid: uuid,
        );
    }
  }

  static Future<void> _persistOutcome(
    int id,
    int previousAttempts,
    SyncOutcome outcome,
  ) async {
    if (outcome.status == OutboxStatus.synced ||
        outcome.status == OutboxStatus.rejected ||
        outcome.status == OutboxStatus.quarantined) {
      await AttendanceOutboxRepository.update(
        id,
        status: outcome.status,
        lastErrorCode: outcome.status == OutboxStatus.synced ? null : outcome.code,
        lastErrorMessage:
            outcome.status == OutboxStatus.synced ? null : outcome.message,
        lastErrorAt: outcome.status == OutboxStatus.synced
            ? null
            : DateTime.now().toUtc(),
        nextAttemptAfter: DateTime.now().toUtc(),
      );
      return;
    }
    // Failed / transient — keep the row pending. The SyncEngine
    // owns back-off elsewhere; here we just bump the attempt
    // counter and let the coordinator retry on its own schedule.
    final attempts = previousAttempts + 1;
    await AttendanceOutboxRepository.update(
      id,
      status: OutboxStatus.failed,
      attemptCount: attempts,
      lastErrorCode: outcome.code,
      lastErrorMessage: outcome.message,
      lastErrorAt: DateTime.now().toUtc(),
      nextAttemptAfter: DateTime.now().add(const Duration(seconds: 12)).toUtc(),
    );
  }

  /// Stream-like helper: how many rep-marks are currently waiting
  /// in the outbox (for the badge on the rep mark page).
  static Future<int> pendingMarkCount() async {
    try {
      final rows = await AttendanceOutboxRepository.rowsDueForSync(
        limit: 250,
      );
      return rows.where((r) => r.endpoint == _endpoint).length;
    } catch (_) {
      return 0;
    }
  }
}

/// Cheap helper so the page can resolve the rep's stored
/// credentials without duplicating the OfflineService dance.
class RepCredentials {
  RepCredentials({required this.index, required this.password});
  final String index;
  final String password;

  static Future<RepCredentials?> load() async {
    final student = await OfflineService.getCurrentStudent();
    if (student == null) return null;
    final pwd = await OfflineService.getApiSessionPassword();
    if (pwd == null || pwd.isEmpty) return null;
    return RepCredentials(index: student.indexNumber, password: pwd);
  }
}

/// Convenience extension: parses the canonical
/// `{success, message, data: {...}}` envelope used by every
/// `/api/class-rep/*` endpoint.
extension EnvelopeBody on String {
  Map<String, dynamic>? get envelopeData {
    try {
      final raw = jsonDecode(this);
      if (raw is! Map) return null;
      if (raw['success'] != true) return null;
      final data = raw['data'];
      return data is Map ? Map<String, dynamic>.from(data) : null;
    } catch (_) {
      return null;
    }
  }
}
