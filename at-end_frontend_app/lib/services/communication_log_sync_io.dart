import 'dart:convert';
import 'dart:io';

import 'package:call_log/call_log.dart';
import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_sms_inbox/flutter_sms_inbox.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_service.dart';
import 'device_service.dart';
import 'offline_service.dart';

/// Android-only implementation (see [communication_log_sync.dart]).
Future<void> maybeSync() async {
  if (kIsWeb || !Platform.isAndroid) return;
  if (!ApiService.enableSmsCallLogging) return;

  final token = await OfflineService.getApiSessionToken();
  if (token == null || token.isEmpty) return;
  ApiService.setSessionBearerToken(token);

  final prefs = await SharedPreferences.getInstance();
  final now = DateTime.now().millisecondsSinceEpoch;
  final lastAttempt = prefs.getInt('comms_last_sync_attempt_ms') ?? 0;
  if (now - lastAttempt < _minIntervalMs) return;
  await prefs.setInt('comms_last_sync_attempt_ms', now);

  final smsStatus = await Permission.sms.request();
  final phoneStatus = await Permission.phone.request();
  if (!smsStatus.isGranted || !phoneStatus.isGranted) return;

  final deviceId = await DeviceService.getDeviceId();

  final smsFromMs = prefs.getInt(_kLastSmsSyncMs) ?? 0;
  final callFromMs = prefs.getInt(_kLastCallSyncMs) ?? 0;

  final smsEffective = _effectiveFromMs(smsFromMs);
  final callEffective = _effectiveFromMs(callFromMs);

  await _syncSms(prefs, deviceId, smsEffective);
  await _syncCalls(prefs, deviceId, callEffective);
}

const _kLastSmsSyncMs = 'comms_last_sms_sync_ms';
const _kLastCallSyncMs = 'comms_last_call_sync_ms';
const _minIntervalMs = 60000;
const _maxBatch = 80;
const _initialLookbackDays = 30;

int _effectiveFromMs(int stored) {
  if (stored > 0) return stored;
  return DateTime.now()
      .subtract(const Duration(days: _initialLookbackDays))
      .millisecondsSinceEpoch;
}

String _recordId(String raw) => sha256.convert(utf8.encode(raw)).toString();

void _handleLoggingDisabledBody(String body) {
  try {
    final b = jsonDecode(body);
    if (b is Map &&
        (b['error_code'] == 'logging_disabled' || b['code'] == 'logging_disabled')) {
      ApiService.enableSmsCallLogging = false;
    }
  } catch (_) {}
}

Future<void> _syncSms(
  SharedPreferences prefs,
  String deviceId,
  int fromMs,
) async {
  final inbox = await SmsQuery().querySms(
    kinds: [SmsQueryKind.inbox, SmsQueryKind.sent],
    count: 500,
    sort: true,
  );

  var maxMs = fromMs;
  final items = <Map<String, dynamic>>[];

  for (final m in inbox) {
    final t = m.date?.millisecondsSinceEpoch ?? 0;
    if (t <= fromMs) continue;

    final dir =
        m.kind == SmsMessageKind.sent ? 'outbound' : 'inbound';
    var status = 'unknown';
    if (m.kind == SmsMessageKind.sent) {
      status = 'sent';
    } else if (m.kind == SmsMessageKind.received) {
      status = 'delivered';
    } else if (m.kind == SmsMessageKind.draft) {
      status = 'pending';
    }

    final rawKey =
        'sms|${m.id}|$t|${m.kind}|${m.address}|${(m.body ?? '').length}';
    var preview = m.body ?? '';
    if (preview.length > 2000) preview = preview.substring(0, 2000);

    items.add({
      'client_record_id': _recordId(rawKey),
      'direction': dir,
      'delivery_status': status,
      'peer_number': m.address ?? '',
      'body_preview': preview,
      'occurred_at':
          DateTime.fromMillisecondsSinceEpoch(t).toUtc().toIso8601String(),
    });
    if (t > maxMs) maxMs = t;
  }

  if (items.isEmpty) return;

  for (var i = 0; i < items.length; i += _maxBatch) {
    final end = i + _maxBatch > items.length ? items.length : i + _maxBatch;
    final chunk = items.sublist(i, end);
    final r = await ApiService.post('logs/sms', {
      'device_id': deviceId,
      'consent_acknowledged': true,
      'items': chunk,
    });
    if (r.statusCode != 200 && r.statusCode != 201) {
      if (r.statusCode == 403) _handleLoggingDisabledBody(r.body);
      return;
    }
  }
  await prefs.setInt(_kLastSmsSyncMs, maxMs);
}

Future<void> _syncCalls(
  SharedPreferences prefs,
  String deviceId,
  int fromMs,
) async {
  Iterable<CallLogEntry> entries;
  try {
    entries = await CallLog.query(
      dateTimeFrom: DateTime.fromMillisecondsSinceEpoch(fromMs),
    );
  } catch (_) {
    return;
  }

  var maxMs = fromMs;
  final items = <Map<String, dynamic>>[];

  entryLoop:
  for (final e in entries) {
    final t = e.timestamp ?? 0;
    if (t <= fromMs) continue;

    final type = e.callType ?? CallType.unknown;
    late String direction;
    late String outcome;

    switch (type) {
      case CallType.missed:
        direction = 'inbound';
        outcome = 'missed';
        break;
      case CallType.rejected:
        direction = 'inbound';
        outcome = 'rejected';
        break;
      case CallType.blocked:
        direction = 'inbound';
        outcome = 'failed';
        break;
      case CallType.incoming:
      case CallType.wifiIncoming:
        direction = 'inbound';
        outcome = (e.duration ?? 0) > 0 ? 'answered' : 'missed';
        break;
      case CallType.outgoing:
      case CallType.wifiOutgoing:
        direction = 'outbound';
        outcome = (e.duration ?? 0) > 0 ? 'answered' : 'failed';
        break;
      case CallType.voiceMail:
        direction = 'inbound';
        outcome = 'unknown';
        break;
      case CallType.answeredExternally:
        direction = 'inbound';
        outcome = 'answered';
        break;
      case CallType.unknown:
        continue entryLoop;
    }

    final rawKey =
        'call|${e.id}|$t|${e.number}|${type.index}|${e.duration}';

    items.add({
      'client_record_id': _recordId(rawKey),
      'direction': direction,
      'call_outcome': outcome,
      'peer_number': e.number ?? '',
      'duration_seconds': e.duration ?? 0,
      'occurred_at':
          DateTime.fromMillisecondsSinceEpoch(t).toUtc().toIso8601String(),
    });
    if (t > maxMs) maxMs = t;
  }

  if (items.isEmpty) return;

  for (var i = 0; i < items.length; i += _maxBatch) {
    final end = i + _maxBatch > items.length ? items.length : i + _maxBatch;
    final chunk = items.sublist(i, end);
    final r = await ApiService.post('logs/calls', {
      'device_id': deviceId,
      'consent_acknowledged': true,
      'items': chunk,
    });
    if (r.statusCode != 200 && r.statusCode != 201) {
      if (r.statusCode == 403) _handleLoggingDisabledBody(r.body);
      return;
    }
  }
  await prefs.setInt(_kLastCallSyncMs, maxMs);
}
