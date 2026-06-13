import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/outbox_record.dart';
import '../models/outbox_status.dart';
import '../utils/constants.dart';
import 'api_service.dart';
import 'attendance_outbox_repository.dart';
import 'sync_failure_classifier.dart';
import 'sync_retry_policy.dart';

/// Engine that drains [AttendanceOutboxRepository] against the API.
///
/// Three execution paths:
///
///   1. Inline single-row attempt right after `enqueue()` — covers the
///      "online happy path" so the user sees an instant green tick.
///   2. Batch drain via `POST /api/attendance/sync` — used by the
///      OfflineSyncCoordinator on connectivity restore / app resume /
///      manual retry. Up to 50 rows OR 32 KB per HTTP call.
///   3. Manual retry of a single Failed row from Sync Status — calls
///      [retrySingle].
///
/// All three honour the same retry policy and failure classifier so
/// behaviour is identical regardless of which path triggered the
/// attempt.
class SyncEngine {
  SyncEngine._();

  static const int batchMaxRows = 50;
  static const int batchMaxBytes = 32 * 1024; // 32 KB
  static const Duration httpTimeout = Duration(seconds: 25);

  /// Guards against re-entrant `drain()` calls (e.g. connectivity event
  /// firing twice). Only one drain runs at a time.
  static bool _draining = false;
  static Completer<void>? _drainCompleter;

  /// Endpoint constants — kept here so the engine and the submit path
  /// stay in sync with the server route names.
  static const String _singleEndpoint = 'attendance';
  static const String _batchEndpoint = 'attendance/sync';

  /// Single-row send. Returns the [SyncOutcome] for the row.
  ///
  /// Used by the submit path (`AttendanceSubmissionService`) right after
  /// enqueueing the row so we can give the user immediate feedback
  /// while still keeping the outbox as the source of truth.
  static Future<SyncOutcome> attemptSingle(OutboxRecord record) async {
    final endpoint = record.endpoint.trim().isEmpty
        ? _singleEndpoint
        : record.endpoint.trim();
    // Make sure the uuid is in the wire payload (older callers might
    // have built the body before we generated the uuid).
    final body = Map<String, dynamic>.from(record.payload);
    if (record.attendanceUuid.isNotEmpty &&
        (body['attendance_uuid'] == null || body['attendance_uuid'] == '')) {
      body['attendance_uuid'] = record.attendanceUuid;
    }
    if (record.deviceId.isNotEmpty &&
        (body['device_id'] == null || body['device_id'] == '')) {
      body['device_id'] = record.deviceId;
    }

    return _attemptOnce(endpoint, body);
  }

  /// Drain the outbox using the batch endpoint. Returns the number of
  /// rows that transitioned out of {Pending, Failed, Syncing}.
  ///
  /// Safe to call from anywhere — the method is a no-op while another
  /// drain is in flight.
  static Future<int> drain() async {
    if (_draining) {
      // Reuse the in-flight drain so the caller can `await` it.
      await _drainCompleter?.future;
      return 0;
    }
    _draining = true;
    _drainCompleter = Completer<void>();

    var transitioned = 0;
    try {
      // Run sweeps until either the queue is empty OR a sweep does not
      // transition anything (back-off rows stay put). Cap at 4 sweeps
      // per drain so a misconfigured server does not pin the UI thread.
      for (var pass = 0; pass < 4; pass++) {
        final batch = await AttendanceOutboxRepository.rowsDueForSync(
          limit: batchMaxRows,
        );
        if (batch.isEmpty) break;

        // Split into single-row endpoints (e.g. checkout) and a batch
        // payload. Checkouts can't be merged with the standard sync
        // because they target a different controller method.
        final byEndpoint = <String, List<OutboxRecord>>{};
        for (final r in batch) {
          final ep = r.endpoint.trim().isEmpty
              ? _singleEndpoint
              : r.endpoint.trim();
          byEndpoint.putIfAbsent(ep, () => []).add(r);
        }

        var anyTransitioned = false;
        for (final entry in byEndpoint.entries) {
          if (entry.key == _singleEndpoint) {
            transitioned += await _drainStandardBatch(entry.value);
            anyTransitioned = true;
          } else {
            // Non-batchable endpoints (checkout) — fall back to one
            // request per row.
            for (final row in entry.value) {
              final outcome = await attemptSingle(row);
              await _applyOutcome(row, outcome);
              if (outcome.status != row.status) {
                transitioned++;
                anyTransitioned = true;
              }
            }
          }
        }
        if (!anyTransitioned) break;
      }
    } finally {
      _draining = false;
      _drainCompleter?.complete();
      _drainCompleter = null;
    }
    return transitioned;
  }

  /// Manual retry of one row — used by the Sync Status page.
  static Future<SyncOutcome> retrySingle(OutboxRecord record) async {
    // Reset back-off so the row attempts immediately.
    if (record.id != null) {
      await AttendanceOutboxRepository.update(
        record.id!,
        status: OutboxStatus.pending,
        nextAttemptAfter: DateTime.now().toUtc(),
      );
    }
    final fresh = record.id != null
        ? await AttendanceOutboxRepository.findByUuid(record.attendanceUuid) ??
            record
        : record;
    final outcome = await attemptSingle(fresh);
    await _applyOutcome(fresh, outcome);
    return outcome;
  }

  /// Splits a list of standard-endpoint rows into HTTP batches of at
  /// most [batchMaxRows] or [batchMaxBytes].
  static Future<int> _drainStandardBatch(List<OutboxRecord> rows) async {
    if (rows.isEmpty) return 0;

    final groups = _packIntoBatches(rows);
    var transitioned = 0;
    for (final group in groups) {
      transitioned += await _sendBatch(group);
    }
    return transitioned;
  }

  /// Greedy bin-packer — accumulate rows until the next row would push
  /// us over [batchMaxRows] or [batchMaxBytes]. Always emits at least
  /// one row per group (even oversize) so a single fat row never stalls.
  static List<List<OutboxRecord>> _packIntoBatches(List<OutboxRecord> rows) {
    final out = <List<OutboxRecord>>[];
    var current = <OutboxRecord>[];
    var currentBytes = 0;

    for (final r in rows) {
      final encoded = jsonEncode(_recordToBatchEntry(r));
      final bytes = encoded.length; // 1 char ≈ 1 byte for ASCII payloads
      final wouldOverflow = current.isNotEmpty &&
          (current.length >= batchMaxRows ||
              currentBytes + bytes > batchMaxBytes);
      if (wouldOverflow) {
        out.add(current);
        current = [];
        currentBytes = 0;
      }
      current.add(r);
      currentBytes += bytes;
    }
    if (current.isNotEmpty) out.add(current);
    return out;
  }

  static Map<String, dynamic> _recordToBatchEntry(OutboxRecord r) {
    final body = Map<String, dynamic>.from(r.payload);
    // Server expects `attendance_time` on every batch entry — fall back
    // to `timestamp` (the live mark endpoint accepts both).
    if (body['attendance_time'] == null && body['timestamp'] != null) {
      body['attendance_time'] = body['timestamp'];
    }
    body['attendance_uuid'] = r.attendanceUuid;
    if (r.deviceId.isNotEmpty && body['device_id'] == null) {
      body['device_id'] = r.deviceId;
    }
    return body;
  }

  static Future<int> _sendBatch(List<OutboxRecord> group) async {
    if (group.isEmpty) return 0;

    // Move rows into the "syncing" state so the UI can show a spinner.
    for (final r in group) {
      if (r.id != null && r.status != OutboxStatus.syncing) {
        await AttendanceOutboxRepository.update(
          r.id!,
          status: OutboxStatus.syncing,
        );
      }
    }

    final entries = group.map(_recordToBatchEntry).toList(growable: false);
    http.Response res;
    try {
      res = await http
          .post(
            Uri.parse('${Constants.baseUrl}/$_batchEndpoint'),
            headers: ApiService.requestHeaders(jsonBody: true),
            body: jsonEncode({'records': entries}),
          )
          .timeout(httpTimeout);
    } catch (e) {
      // Transport failure — bump all rows back to Pending/Failed with
      // the same outcome.
      final outcome = SyncFailureClassifier.classifyTransportError(e);
      for (final r in group) {
        await _applyOutcome(r, outcome);
      }
      return group.length;
    }

    if (!ApiService.isSuccessfulHttp(res.statusCode)) {
      Map<String, dynamic>? parsed;
      try {
        parsed = jsonDecode(res.body) as Map<String, dynamic>;
      } catch (_) {}
      final outcome = SyncFailureClassifier.classifyError(
        statusCode: res.statusCode,
        rawBody: res.body,
        parsedBody: parsed,
      );
      // Whole-batch failure (e.g. 401, 500): apply the same outcome to
      // every row. Permanent failures will mark them Rejected; transient
      // failures will reschedule them.
      for (final r in group) {
        await _applyOutcome(r, outcome);
      }
      return group.length;
    }

    // 2xx — parse per-record `results` array. Old servers will not send
    // it; in that case fall back to the legacy `synced` count and
    // optimistically mark all rows as Synced.
    Map<String, dynamic> body;
    try {
      body = jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      body = const {};
    }

    final results = body['results'];
    if (results is List && results.isNotEmpty) {
      var transitioned = 0;
      // Index by attendance_uuid for O(1) lookup; the response order is
      // not guaranteed.
      final byUuid = <String, Map<String, dynamic>>{};
      for (final raw in results) {
        if (raw is Map<String, dynamic>) {
          final uuid = (raw['attendance_uuid'] ?? '').toString();
          if (uuid.isNotEmpty) byUuid[uuid] = raw;
        }
      }
      for (final r in group) {
        final entry = byUuid[r.attendanceUuid];
        if (entry == null) {
          // Server did not echo this uuid — treat as transient failure.
          await _applyOutcome(
            r,
            const SyncOutcome(
              status: OutboxStatus.failed,
              code: 'no_echo',
              message: 'Server response missing this row. Will retry.',
              retryable: true,
            ),
          );
        } else {
          final outcome = SyncFailureClassifier.classifyBatchResult(entry);
          await _applyOutcome(r, outcome);
        }
        transitioned++;
      }
      return transitioned;
    }

    // Legacy response (no `results` array). Optimistic-mark.
    for (final r in group) {
      await _applyOutcome(r, SyncOutcome.synced);
    }
    return group.length;
  }

  /// Single-row POST against the live mark endpoint. Honours the same
  /// classifier so checkout / non-batchable endpoints behave like the
  /// batch path.
  static Future<SyncOutcome> _attemptOnce(
    String endpoint,
    Map<String, dynamic> body,
  ) async {
    http.Response res;
    try {
      res = await http
          .post(
            Uri.parse('${Constants.baseUrl}/$endpoint'),
            headers: ApiService.requestHeaders(jsonBody: true),
            body: jsonEncode(body),
          )
          .timeout(httpTimeout);
    } catch (e) {
      return SyncFailureClassifier.classifyTransportError(e);
    }

    if (ApiService.isSuccessfulHttp(res.statusCode) || res.statusCode == 202) {
      Map<String, dynamic> parsed = const {};
      try {
        parsed = jsonDecode(res.body) as Map<String, dynamic>;
      } catch (_) {}
      return SyncFailureClassifier.classifySuccess(res.statusCode, parsed);
    }
    // Treat 409 as idempotent-already-marked.
    if (res.statusCode == 409) {
      Map<String, dynamic> parsed = const {};
      try {
        parsed = jsonDecode(res.body) as Map<String, dynamic>;
      } catch (_) {}
      return SyncOutcome.already.copyWith(
        message: parsed['message']?.toString() ?? 'Attendance already recorded.',
      );
    }

    Map<String, dynamic>? parsed;
    try {
      parsed = jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {}
    return SyncFailureClassifier.classifyError(
      statusCode: res.statusCode,
      rawBody: res.body,
      parsedBody: parsed,
    );
  }

  /// Persists [outcome] for [record] and computes the next back-off.
  static Future<void> _applyOutcome(
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
        nextAttemptAfter: DateTime.now().toUtc(),
      );
      return;
    }

    // Failed / retryable.
    final attempts = record.attemptCount + 1;
    final nextAt = SyncRetryPolicy.nextAttemptAfter(attempts);
    if (nextAt == null) {
      // Hit quarantine ceiling.
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
