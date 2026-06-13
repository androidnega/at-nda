import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:sqflite/sqflite.dart';

import '../models/outbox_record.dart';
import '../models/outbox_status.dart';
import 'offline_service.dart';

/// CRUD layer for `attendance_outbox`. All UI / sync engine code goes
/// through this repository so we have a single chokepoint for query
/// patterns and status transitions.
///
/// Web has no sqflite — every method becomes a no-op so the rest of the
/// codebase can call the repository unconditionally.
class AttendanceOutboxRepository {
  static const String _table = 'attendance_outbox';

  /// Inserts a row. Returns the row's local id, or -1 on web.
  static Future<int> insert(OutboxRecord record) async {
    if (kIsWeb) return -1;
    final db = await OfflineService.db();
    if (db == null) return -1;
    final map = record.toMap()..remove('id');
    final id = await db.insert(
      _table,
      map,
      conflictAlgorithm: ConflictAlgorithm.abort,
    );
    return id;
  }

  /// Patches selected fields on a row. Bumps `updated_at` automatically.
  static Future<int> update(
    int id, {
    OutboxStatus? status,
    int? attemptCount,
    String? lastErrorCode,
    String? lastErrorMessage,
    DateTime? lastErrorAt,
    DateTime? nextAttemptAfter,
    String? attendanceUuidEcho,
  }) async {
    if (kIsWeb) return 0;
    final db = await OfflineService.db();
    if (db == null) return 0;
    final values = <String, Object?>{
      'updated_at': DateTime.now().toUtc().toIso8601String(),
    };
    if (status != null) values['status'] = status.wireValue;
    if (attemptCount != null) values['attempt_count'] = attemptCount;
    if (lastErrorCode != null) values['last_error_code'] = lastErrorCode;
    if (lastErrorMessage != null) {
      // Truncate so we never blow the row size.
      values['last_error_message'] = lastErrorMessage.length > 500
          ? lastErrorMessage.substring(0, 500)
          : lastErrorMessage;
    }
    if (lastErrorAt != null) {
      values['last_error_at'] = lastErrorAt.toIso8601String();
    }
    if (nextAttemptAfter != null) {
      values['next_attempt_after'] = nextAttemptAfter.toIso8601String();
    }
    return db.update(_table, values, where: 'id = ?', whereArgs: [id]);
  }

  /// Finds an existing row by client uuid. Returns null when nothing matches.
  static Future<OutboxRecord?> findByUuid(String uuid) async {
    if (kIsWeb || uuid.isEmpty) return null;
    final db = await OfflineService.db();
    if (db == null) return null;
    final rows = await db.query(
      _table,
      where: 'attendance_uuid = ?',
      whereArgs: [uuid],
      limit: 1,
    );
    if (rows.isEmpty) return null;
    return OutboxRecord.fromMap(rows.first);
  }

  /// Rows whose backing student matches the active session. Used by Sync
  /// Status to scope the visible queue and by the SyncEngine to refuse
  /// to drain rows owned by a different student.
  static Future<List<OutboxRecord>> rowsForStudent(String studentIndex) async {
    if (kIsWeb || studentIndex.isEmpty) return [];
    final db = await OfflineService.db();
    if (db == null) return [];
    final rows = await db.query(
      _table,
      where: 'student_index = ?',
      whereArgs: [studentIndex],
      orderBy: 'id ASC',
    );
    return rows.map(OutboxRecord.fromMap).toList();
  }

  /// All rows the sync engine should consider on the next sweep. Includes
  /// `Pending`, `Failed`, and `Syncing` (in case a previous sweep crashed
  /// mid-attempt). Excludes terminal states.
  /// Within retryable rows, only those whose `next_attempt_after` already
  /// passed (or is null) are returned, oldest first.
  static Future<List<OutboxRecord>> rowsDueForSync({int limit = 50}) async {
    if (kIsWeb) return [];
    final db = await OfflineService.db();
    if (db == null) return [];
    final nowIso = DateTime.now().toUtc().toIso8601String();
    final rows = await db.rawQuery(
      '''
      SELECT * FROM $_table
      WHERE status IN ('pending','failed','syncing')
        AND (next_attempt_after IS NULL OR next_attempt_after <= ?)
      ORDER BY id ASC
      LIMIT ?
      ''',
      [nowIso, limit],
    );
    return rows.map(OutboxRecord.fromMap).toList();
  }

  /// Counts by status, for the home / profile badges.
  /// Returns map keyed by [OutboxStatus.wireValue].
  static Future<Map<String, int>> statusCounts({String? studentIndex}) async {
    final out = <String, int>{
      for (final s in OutboxStatus.values) s.wireValue: 0,
    };
    if (kIsWeb) return out;
    final db = await OfflineService.db();
    if (db == null) return out;
    final params = <Object>[];
    var where = '';
    if (studentIndex != null && studentIndex.isNotEmpty) {
      where = 'WHERE student_index = ?';
      params.add(studentIndex);
    }
    final rows = await db.rawQuery(
      '''
      SELECT status, COUNT(*) AS n
      FROM $_table
      $where
      GROUP BY status
      ''',
      params,
    );
    for (final r in rows) {
      final s = r['status']?.toString() ?? '';
      final n = r['n'] is int ? r['n'] as int : int.tryParse('${r['n']}') ?? 0;
      out[s] = n;
    }
    return out;
  }

  /// Hard-delete by row id. Reserved for staff tools.
  static Future<int> deleteById(int id) async {
    if (kIsWeb) return 0;
    final db = await OfflineService.db();
    if (db == null) return 0;
    return db.delete(_table, where: 'id = ?', whereArgs: [id]);
  }

  /// Retention sweep: delete `Synced` rows older than [olderThan].
  ///
  /// Contract (POST_IMPLEMENTATION_ARCHITECTURE_AUDIT §P1.4):
  ///   - Only rows whose status is `synced` are eligible.
  ///   - `failed`, `pending`, `syncing`, `rejected`, `quarantined`
  ///     rows are PRESERVED — they may still need user attention or
  ///     server polling (in the case of quarantined-late).
  ///   - The default 30-day window matches the audit's recommendation
  ///     and gives users plenty of time to inspect their Sync Status
  ///     history before the row disappears.
  ///
  /// Returns the number of rows actually deleted.
  static Future<int> pruneSyncedOlderThan({
    Duration olderThan = const Duration(days: 30),
  }) async {
    if (kIsWeb) return 0;
    final db = await OfflineService.db();
    if (db == null) return 0;
    final cutoff =
        DateTime.now().toUtc().subtract(olderThan).toIso8601String();
    return db.delete(
      _table,
      where: "status = 'synced' AND updated_at < ?",
      whereArgs: [cutoff],
    );
  }

  /// Run SQLite `VACUUM` to reclaim the free pages left after pruning.
  /// SQLite never shrinks the file on its own — without an occasional
  /// VACUUM the .db keeps the high-water mark of the largest queue size
  /// ever held. VACUUM is a synchronous, table-locking operation; the
  /// coordinator only calls it on a weekly cadence and only when the
  /// app is foregrounded so user-visible work is never blocked.
  ///
  /// Returns `true` when VACUUM ran, `false` on web / no-db.
  static Future<bool> vacuum() async {
    if (kIsWeb) return false;
    final db = await OfflineService.db();
    if (db == null) return false;
    try {
      await db.execute('VACUUM');
      return true;
    } catch (_) {
      return false;
    }
  }
}
