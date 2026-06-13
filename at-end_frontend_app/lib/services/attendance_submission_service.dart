import 'package:flutter/foundation.dart' show kIsWeb;

import '../models/outbox_record.dart';
import '../models/outbox_status.dart';
import '../utils/attendance_uuid.dart';
import 'attendance_outbox_repository.dart';
import 'device_service.dart';
import 'sync_engine.dart';
import 'sync_failure_classifier.dart';
import 'sync_retry_policy.dart';

/// Outcome surfaced to the UI after `submit()`. Wraps [SyncOutcome] but
/// also carries the persisted outbox row's local id so the UI can deep
/// link into the Sync Status detail.
class AttendanceSubmissionResult {
  AttendanceSubmissionResult({
    required this.outcome,
    required this.record,
  });

  final SyncOutcome outcome;
  final OutboxRecord record;

  /// True when the row was accepted or `already_marked` — UI shows a
  /// success toast.
  bool get isAccepted =>
      outcome.status == OutboxStatus.synced;

  /// True when the row was captured for lecturer approval (Quarantined
  /// + late_capture). UI shows the "awaiting approval" banner.
  bool get isLateCapture =>
      outcome.status == OutboxStatus.quarantined &&
      outcome.code == 'late_capture';

  /// True when the row needs further automated retries (Failed/Pending).
  bool get isQueuedForRetry =>
      outcome.status == OutboxStatus.failed ||
      outcome.status == OutboxStatus.pending;

  /// True when the row is permanently dropped (Rejected/Quarantined non-late).
  bool get isPermanentFailure =>
      outcome.status == OutboxStatus.rejected ||
      (outcome.status == OutboxStatus.quarantined && outcome.code != 'late_capture');
}

/// Offline-first attendance submit path.
///
/// Pipeline (per architecture review § Phase 1):
///
///   1. Generate `attendance_uuid` on the client.
///   2. Save the row to `attendance_outbox` with status=Pending.
///   3. Attempt an immediate sync against the live mark endpoint.
///   4. Update the row to Synced / Failed / Rejected / Quarantined based
///      on the response.
///
/// No path in the UI should POST attendance directly any more — every
/// student submission goes through this service. That makes the outbox
/// the single source of truth.
class AttendanceSubmissionService {
  AttendanceSubmissionService._();

  /// Enqueue + attempt-sync one attendance submission.
  ///
  /// [payload] is the pre-built request body (output of
  /// `buildAttendancePostBody`). The service will inject
  /// `attendance_uuid` and `device_id` automatically if not already set.
  ///
  /// [endpoint] defaults to `attendance`. Pass `attendance/checkout` for
  /// the check-out flow.
  static Future<AttendanceSubmissionResult> submit({
    required String studentIndex,
    required Map<String, dynamic> payload,
    String endpoint = 'attendance',
    int? sessionId,
    int? courseId,
    int? studentId,
    String? attendanceUuid,
    String? deviceIdOverride,
  }) async {
    final uuid = attendanceUuid ?? AttendanceUuid.generate();
    final deviceId =
        deviceIdOverride ?? (kIsWeb ? '' : await DeviceService.getDeviceId());

    final body = Map<String, dynamic>.from(payload);
    body.putIfAbsent('attendance_uuid', () => uuid);
    if (deviceId.isNotEmpty) {
      body.putIfAbsent('device_id', () => deviceId);
    }

    final record = OutboxRecord(
      attendanceUuid: uuid,
      studentIndex: studentIndex.trim().toUpperCase(),
      studentId: studentId,
      deviceId: deviceId,
      sessionId: sessionId,
      courseId: courseId,
      endpoint: endpoint,
      payload: body,
      status: OutboxStatus.pending,
    );

    // 1. Save first — survives every crash from this point on.
    int localId;
    try {
      localId = await AttendanceOutboxRepository.insert(record);
    } catch (_) {
      // Most likely a unique-uuid collision — caller passed a uuid that
      // already exists. Use the existing row.
      final existing =
          await AttendanceOutboxRepository.findByUuid(uuid);
      if (existing == null) {
        return AttendanceSubmissionResult(
          outcome: const SyncOutcome(
            status: OutboxStatus.failed,
            code: 'local_persist_failed',
            message: 'Could not save attendance locally. Please retry.',
            retryable: true,
          ),
          record: record,
        );
      }
      localId = existing.id!;
    }

    final persisted = record.copyWith(id: localId);

    // 2. Mark `syncing` so the UI knows we are mid-attempt.
    await AttendanceOutboxRepository.update(
      localId,
      status: OutboxStatus.syncing,
    );

    // 3. Attempt one live POST. The engine handles transport errors and
    // returns a classified outcome.
    final outcome = await SyncEngine.attemptSingle(persisted);

    // 4. Persist the outcome.
    await _persistOutcome(persisted, outcome);

    // 5. Reload for accurate counters.
    final fresh = await AttendanceOutboxRepository.findByUuid(uuid) ??
        persisted.copyWith(status: outcome.status);

    return AttendanceSubmissionResult(outcome: outcome, record: fresh);
  }

  static Future<void> _persistOutcome(
    OutboxRecord record,
    SyncOutcome outcome,
  ) async {
    if (record.id == null) return;

    if (outcome.status == OutboxStatus.synced ||
        outcome.status == OutboxStatus.rejected ||
        outcome.status == OutboxStatus.quarantined) {
      await AttendanceOutboxRepository.update(
        record.id!,
        status: outcome.status,
        lastErrorCode: outcome.status == OutboxStatus.synced ? null : outcome.code,
        lastErrorMessage:
            outcome.status == OutboxStatus.synced ? null : outcome.message,
        lastErrorAt: outcome.status == OutboxStatus.synced
            ? null
            : DateTime.now().toUtc(),
      );
      return;
    }

    // Failed / pending — bump attempt count and schedule next retry.
    final attempts = record.attemptCount + 1;
    final nextAt = SyncRetryPolicy.nextAttemptAfter(attempts);
    if (nextAt == null) {
      await AttendanceOutboxRepository.update(
        record.id!,
        status: OutboxStatus.quarantined,
        attemptCount: attempts,
        lastErrorCode: outcome.code,
        lastErrorMessage: outcome.message,
        lastErrorAt: DateTime.now().toUtc(),
      );
      return;
    }
    await AttendanceOutboxRepository.update(
      record.id!,
      status: OutboxStatus.failed,
      attemptCount: attempts,
      lastErrorCode: outcome.code,
      lastErrorMessage: outcome.message,
      lastErrorAt: DateTime.now().toUtc(),
      nextAttemptAfter: nextAt,
    );
  }
}
