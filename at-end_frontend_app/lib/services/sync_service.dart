import 'dart:convert';
import 'dart:async';

import 'api_service.dart';
import 'device_service.dart';
import 'offline_service.dart';

/// Syncs pending SQLite attendance rows to Laravel.
class SyncService {
  SyncService._();

  /// Pull the server-side attendance history into the local SQLite
  /// cache. Returns the row count actually written (0 on any failure
  /// — we never throw, we just leave whatever the local cache had).
  ///
  /// Called after a successful API login (so a fresh install never
  /// shows an empty history page even though the server has data),
  /// and again from the attendance-history page's pull-to-refresh.
  static Future<int> pullRemoteAttendanceHistory() async {
    try {
      final student = await OfflineService.getCurrentStudent();
      if (student == null) return 0;
      final password = await OfflineService.getApiSessionPassword();
      if (password == null || password.isEmpty) return 0;

      final body = await ApiService.fetchRemoteAttendanceSync(
        indexNumber: student.indexNumber,
        password: password,
      );
      if (body == null) return 0;
      final rowsRaw = body['attendances'];
      if (rowsRaw is! List) return 0;
      return await OfflineService.bulkUpsertAttendanceLogsFromServer(
        student.indexNumber,
        rowsRaw,
      );
    } catch (_) {
      return 0;
    }
  }

  /// POST each unsynced record; marks synced on success. Returns count synced.
  static Future<int> syncAttendance() async {
    var synced = 0;
    try {
      final pending = await OfflineService.getPendingRecords();
      final deviceIp = await DeviceService.getDeviceIp();
      final deviceId = await DeviceService.getDeviceId();
      for (final record in pending) {
        try {
          final payload =
              record.toApiPayload(deviceIp: deviceIp, deviceId: deviceId);
          final endpoint = record.endpoint.trim().isEmpty
              ? 'attendance'
              : record.endpoint.trim();
          var res = await ApiService.post(endpoint, payload);
          if (!ApiService.isSuccessfulHttp(res.statusCode) && res.statusCode != 409) {
            // Retry once for weak/edge networks before skipping this row.
            await Future<void>.delayed(const Duration(milliseconds: 350));
            try {
              res = await ApiService.post(endpoint, payload);
            } on TimeoutException {
              continue;
            }
          }
          if ((ApiService.isSuccessfulHttp(res.statusCode) || res.statusCode == 409) &&
              record.id != null) {
            final shouldSaveAttendanceLog =
                endpoint == 'attendance' || endpoint.isEmpty;
            final raw = res.body.trim();
            if (raw.isEmpty) {
              await OfflineService.markSynced(record.id!);
              if (shouldSaveAttendanceLog) {
                await OfflineService.saveAttendanceLogFromSyncedRecord(record);
              }
              synced++;
              continue;
            }
            Map<String, dynamic>? body;
            try {
              body = jsonDecode(res.body) as Map<String, dynamic>;
            } catch (_) {
              await OfflineService.markSynced(record.id!);
              if (shouldSaveAttendanceLog) {
                await OfflineService.saveAttendanceLogFromSyncedRecord(record);
              }
              synced++;
              continue;
            }
            final status = body['status']?.toString().toLowerCase().trim();
            final message = body['message']?.toString().toLowerCase().trim() ?? '';
            final ok = status == 'success' ||
                status == 'already_marked' ||
                body['success'] == true ||
                body['already_marked'] == true ||
                message.contains('already checked in') ||
                message.contains('already checked out') ||
                message.contains('already marked') ||
                message.contains('attendance already completed');
            if (ok) {
              await OfflineService.markSynced(record.id!);
              if (shouldSaveAttendanceLog) {
                await OfflineService.saveAttendanceLogFromSyncedRecord(record);
              }
              synced++;
            }
          }
        } catch (_) {
          // Keep syncing other rows instead of aborting the entire queue.
          continue;
        }
      }
    } catch (_) {}
    return synced;
  }
}
