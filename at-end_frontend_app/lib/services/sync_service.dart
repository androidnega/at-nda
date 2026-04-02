import 'dart:convert';

import 'api_service.dart';
import 'device_service.dart';
import 'offline_service.dart';

/// Syncs pending SQLite attendance rows to Laravel.
class SyncService {
  SyncService._();

  /// POST each unsynced record; marks synced on success. Returns count synced.
  static Future<int> syncAttendance() async {
    var synced = 0;
    try {
      final pending = await OfflineService.getPendingRecords();
      final deviceIp = await DeviceService.getDeviceIp();
      final deviceId = await DeviceService.getDeviceId();
      for (final record in pending) {
        try {
          final res = await ApiService.post(
            'attendance',
            record.toApiPayload(deviceIp: deviceIp, deviceId: deviceId),
          );
          if (ApiService.isSuccessfulHttp(res.statusCode) && record.id != null) {
            final raw = res.body.trim();
            if (raw.isEmpty) {
              await OfflineService.markSynced(record.id!);
              await OfflineService.saveAttendanceLogFromSyncedRecord(record);
              synced++;
              continue;
            }
            Map<String, dynamic>? body;
            try {
              body = jsonDecode(res.body) as Map<String, dynamic>;
            } catch (_) {
              await OfflineService.markSynced(record.id!);
              await OfflineService.saveAttendanceLogFromSyncedRecord(record);
              synced++;
              continue;
            }
            final status = body['status'] as String?;
            final ok = status == 'success' ||
                status == 'already_marked' ||
                body['success'] == true ||
                body['already_marked'] == true;
            if (ok) {
              await OfflineService.markSynced(record.id!);
              await OfflineService.saveAttendanceLogFromSyncedRecord(record);
              synced++;
            }
          }
        } catch (_) {
          break;
        }
      }
    } catch (_) {}
    return synced;
  }
}
