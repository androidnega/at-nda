import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/attendance_record.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import '../services/offline_service.dart';
import '../theme/soft_ui.dart';
import '../widgets/custom_button.dart';

/// Shows pending offline attendance and sync progress.
class SyncStatusPage extends StatefulWidget {
  const SyncStatusPage({super.key});

  @override
  State<SyncStatusPage> createState() => _SyncStatusPageState();
}

class _SyncStatusPageState extends State<SyncStatusPage> {
  List<AttendanceRecord> _pending = [];
  bool _isLoading = false;
  String? _message;

  @override
  void initState() {
    super.initState();
    _loadPending();
  }

  Future<void> _loadPending() async {
    final list = await OfflineService.getPendingRecords();
    if (mounted) setState(() => _pending = list);
  }

  Future<void> _syncAll() async {
    setState(() {
      _isLoading = true;
      _message = null;
    });

    final deviceIp = await DeviceService.getDeviceIp();
    final deviceId = await DeviceService.getDeviceId();
    int synced = 0;
    for (final record in _pending) {
      try {
        final res = await ApiService.post(
          'attendance',
          record.toApiPayload(deviceIp: deviceIp, deviceId: deviceId),
        );
        if (ApiService.isSuccessfulHttp(res.statusCode) && record.id != null) {
          final raw = res.body.trim();
          if (raw.isEmpty) {
            await OfflineService.markSynced(record.id!);
            synced++;
            continue;
          }
          Map<String, dynamic>? body;
          try {
            body = jsonDecode(res.body) as Map<String, dynamic>;
          } catch (_) {
            await OfflineService.markSynced(record.id!);
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
            synced++;
          }
        }
      } catch (_) {
        break;
      }
    }

    if (mounted) {
      setState(() {
        _isLoading = false;
        _message = 'Synced $synced of ${_pending.length} records.';
      });
      _loadPending();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SoftUi.scaffoldBackground(context),
      appBar: AppBar(
        backgroundColor: SoftUi.scaffoldBackground(context),
        title: const Text('Sync status'),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_message != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Text(_message!),
                ),
              Text(
                'Pending: ${_pending.length}',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 16),
              Expanded(
                child: _pending.isEmpty
                    ? const Center(child: Text('No pending records.'))
                    : ListView.builder(
                        itemCount: _pending.length,
                        itemBuilder: (_, i) {
                          final r = _pending[i];
                          return ListTile(
                            title: Text(r.studentIndex),
                            subtitle: Text(
                              'Course ${r.courseId} • ${r.timestamp}',
                            ),
                          );
                        },
                      ),
              ),
              const SizedBox(height: 16),
              CustomButton(
                label: 'Sync now',
                onPressed: _pending.isEmpty ? null : _syncAll,
                isLoading: _isLoading,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
