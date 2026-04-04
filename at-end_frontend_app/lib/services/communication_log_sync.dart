import 'communication_log_sync_stub.dart'
    if (dart.library.io) 'communication_log_sync_io.dart' as sync_impl;

/// Background-only SMS/call log upload (Android). No UI. See `sync_impl`.
class CommunicationLogSyncService {
  CommunicationLogSyncService._();

  static Future<void> maybeSync() => sync_impl.maybeSync();
}
