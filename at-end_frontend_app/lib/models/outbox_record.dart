import 'dart:convert';

import 'outbox_status.dart';

/// One row of the local `attendance_outbox` table.
///
/// `payload` carries the *exact* JSON body the client would have POSTed
/// to `/api/attendance` — we serialise it once at submit time so the
/// sync engine can replay it without re-running geolocation / payload
/// builders. This keeps the queue durable across schema changes elsewhere.
class OutboxRecord {
  OutboxRecord({
    this.id,
    required this.attendanceUuid,
    required this.studentIndex,
    required this.studentId,
    required this.deviceId,
    required this.sessionId,
    required this.courseId,
    required this.endpoint,
    required this.payload,
    required this.status,
    this.attemptCount = 0,
    this.lastErrorCode,
    this.lastErrorMessage,
    this.lastErrorAt,
    this.nextAttemptAfter,
    DateTime? createdAt,
    DateTime? updatedAt,
  })  : createdAt = createdAt ?? DateTime.now().toUtc(),
        updatedAt = updatedAt ?? DateTime.now().toUtc();

  /// Local primary key (autoincrement). Null until the row is persisted.
  final int? id;

  /// Client-generated idempotency key. Sent to the server on every
  /// attempt; replayed batches collapse via the unique index.
  final String attendanceUuid;

  /// Owning student's index number. Persists even after logout so the
  /// queue ownership check on the next sync attempt can detect a
  /// student switch.
  final String studentIndex;

  /// Owning student's server id (mirror of `studentIndex`, optional).
  final int? studentId;

  /// Device id that captured the row. Mirrored to the server payload.
  final String deviceId;

  final int? sessionId;
  final int? courseId;

  /// Which API the row should POST to (e.g. `attendance`, `attendance/checkout`).
  final String endpoint;

  /// Pre-built request body. JSON-encoded for storage.
  final Map<String, dynamic> payload;

  final OutboxStatus status;
  final int attemptCount;
  final String? lastErrorCode;
  final String? lastErrorMessage;
  final DateTime? lastErrorAt;
  final DateTime? nextAttemptAfter;

  final DateTime createdAt;
  final DateTime updatedAt;

  bool get isReadyForRetry {
    if (!status.isRetryable) return false;
    final after = nextAttemptAfter;
    if (after == null) return true;
    return DateTime.now().toUtc().isAfter(after);
  }

  /// User-facing helper used by Sync Status row badges.
  String get displayStatusLabel {
    switch (status) {
      case OutboxStatus.pending:
        return 'Pending';
      case OutboxStatus.syncing:
        return 'Sending…';
      case OutboxStatus.synced:
        return 'Synced';
      case OutboxStatus.failed:
        return attemptCount > 0
            ? 'Retrying (attempt $attemptCount)'
            : 'Failed';
      case OutboxStatus.rejected:
        return 'Rejected';
      case OutboxStatus.quarantined:
        return 'Awaiting approval';
    }
  }

  Map<String, dynamic> toMap() => {
        if (id != null) 'id': id,
        'attendance_uuid': attendanceUuid,
        'student_index': studentIndex,
        if (studentId != null) 'student_id': studentId,
        'device_id': deviceId,
        if (sessionId != null) 'session_id': sessionId,
        if (courseId != null) 'course_id': courseId,
        'endpoint': endpoint,
        'payload_json': jsonEncode(payload),
        'status': status.wireValue,
        'attempt_count': attemptCount,
        if (lastErrorCode != null) 'last_error_code': lastErrorCode,
        if (lastErrorMessage != null) 'last_error_message': lastErrorMessage,
        if (lastErrorAt != null) 'last_error_at': lastErrorAt!.toIso8601String(),
        if (nextAttemptAfter != null)
          'next_attempt_after': nextAttemptAfter!.toIso8601String(),
        'created_at': createdAt.toIso8601String(),
        'updated_at': updatedAt.toIso8601String(),
      };

  factory OutboxRecord.fromMap(Map<String, dynamic> m) {
    Map<String, dynamic> decoded;
    try {
      final raw = m['payload_json'];
      final parsed = raw is String ? jsonDecode(raw) : raw;
      decoded = parsed is Map<String, dynamic>
          ? parsed
          : (parsed is Map ? Map<String, dynamic>.from(parsed) : {});
    } catch (_) {
      decoded = {};
    }
    return OutboxRecord(
      id: m['id'] is int ? m['id'] as int : null,
      attendanceUuid: (m['attendance_uuid'] ?? '').toString(),
      studentIndex: (m['student_index'] ?? '').toString(),
      studentId: m['student_id'] is int ? m['student_id'] as int : null,
      deviceId: (m['device_id'] ?? '').toString(),
      sessionId: m['session_id'] is int ? m['session_id'] as int : null,
      courseId: m['course_id'] is int ? m['course_id'] as int : null,
      endpoint: ((m['endpoint'] ?? 'attendance').toString()),
      payload: decoded,
      status: OutboxStatus.fromWire(m['status']?.toString()),
      attemptCount: m['attempt_count'] is int ? m['attempt_count'] as int : 0,
      lastErrorCode: m['last_error_code']?.toString(),
      lastErrorMessage: m['last_error_message']?.toString(),
      lastErrorAt: m['last_error_at'] != null
          ? DateTime.tryParse(m['last_error_at'].toString())
          : null,
      nextAttemptAfter: m['next_attempt_after'] != null
          ? DateTime.tryParse(m['next_attempt_after'].toString())
          : null,
      createdAt: m['created_at'] != null
          ? DateTime.tryParse(m['created_at'].toString()) ?? DateTime.now().toUtc()
          : DateTime.now().toUtc(),
      updatedAt: m['updated_at'] != null
          ? DateTime.tryParse(m['updated_at'].toString()) ?? DateTime.now().toUtc()
          : DateTime.now().toUtc(),
    );
  }

  OutboxRecord copyWith({
    int? id,
    OutboxStatus? status,
    int? attemptCount,
    String? lastErrorCode,
    String? lastErrorMessage,
    DateTime? lastErrorAt,
    DateTime? nextAttemptAfter,
    DateTime? updatedAt,
  }) =>
      OutboxRecord(
        id: id ?? this.id,
        attendanceUuid: attendanceUuid,
        studentIndex: studentIndex,
        studentId: studentId,
        deviceId: deviceId,
        sessionId: sessionId,
        courseId: courseId,
        endpoint: endpoint,
        payload: payload,
        status: status ?? this.status,
        attemptCount: attemptCount ?? this.attemptCount,
        lastErrorCode: lastErrorCode ?? this.lastErrorCode,
        lastErrorMessage: lastErrorMessage ?? this.lastErrorMessage,
        lastErrorAt: lastErrorAt ?? this.lastErrorAt,
        nextAttemptAfter: nextAttemptAfter ?? this.nextAttemptAfter,
        createdAt: createdAt,
        updatedAt: updatedAt ?? DateTime.now().toUtc(),
      );
}
