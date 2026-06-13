import 'package:flutter/material.dart';

import '../models/outbox_record.dart';
import '../models/outbox_status.dart';
import '../services/attendance_outbox_repository.dart';
import '../services/offline_service.dart';
import '../services/offline_sync_coordinator.dart';
import '../services/sync_engine.dart';
import '../theme/soft_ui.dart';

/// Per-row visibility into the outbox queue.
///
/// The page is read-only except for a single "Retry now" action on
/// `Failed` rows. Everything else (status changes, attempt counts,
/// next-retry timestamps) is driven by the [SyncEngine] +
/// [OfflineSyncCoordinator]. Refreshing the page just re-reads the
/// table.
class SyncStatusPage extends StatefulWidget {
  const SyncStatusPage({super.key});

  @override
  State<SyncStatusPage> createState() => _SyncStatusPageState();
}

class _SyncStatusPageState extends State<SyncStatusPage> {
  bool _loading = true;
  bool _syncingAll = false;
  List<OutboxRecord> _rows = const [];
  String? _studentIndex;

  @override
  void initState() {
    super.initState();
    _refresh();
    OfflineSyncCoordinator.instance.lastDrain.addListener(_onCoordinatorTick);
    OfflineSyncCoordinator.instance.statusCounts.addListener(_onCoordinatorTick);
  }

  @override
  void dispose() {
    OfflineSyncCoordinator.instance.lastDrain.removeListener(_onCoordinatorTick);
    OfflineSyncCoordinator.instance.statusCounts.removeListener(_onCoordinatorTick);
    super.dispose();
  }

  void _onCoordinatorTick() {
    if (mounted) _refresh();
  }

  Future<void> _refresh() async {
    if (!mounted) return;
    setState(() => _loading = true);
    final student = await OfflineService.getCurrentStudent();
    final idx = student?.indexNumber ?? '';
    final rows = idx.isEmpty
        ? <OutboxRecord>[]
        : await AttendanceOutboxRepository.rowsForStudent(idx);
    if (!mounted) return;
    setState(() {
      _studentIndex = idx;
      _rows = rows.reversed.toList(growable: false);
      _loading = false;
    });
  }

  Future<void> _syncAll() async {
    if (_syncingAll) return;
    setState(() => _syncingAll = true);
    try {
      await OfflineSyncCoordinator.instance.requestSync(reason: 'manual_sync_all');
    } catch (_) {}
    await _refresh();
    if (mounted) setState(() => _syncingAll = false);
  }

  Future<void> _retryOne(OutboxRecord r) async {
    if (r.id == null) return;
    setState(() => _loading = true);
    try {
      await SyncEngine.retrySingle(r);
    } catch (_) {}
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final counts = _countByStatus(_rows);
    return Scaffold(
      backgroundColor: SoftUi.scaffoldBackground(context),
      appBar: AppBar(
        backgroundColor: SoftUi.scaffoldBackground(context),
        title: const Text('Sync status'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh),
            onPressed: _refresh,
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
            children: [
              _StatusSummaryStrip(counts: counts),
              const SizedBox(height: 12),
              if (_loading && _rows.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 60),
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (_rows.isEmpty)
                _EmptyState(studentIndex: _studentIndex)
              else
                ..._rows.map((r) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _OutboxRowCard(
                        record: r,
                        onRetry: () => _retryOne(r),
                      ),
                    )),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed:
                    _rows.any((r) => r.status.isRetryable) && !_syncingAll
                        ? _syncAll
                        : null,
                icon: _syncingAll
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.cloud_sync),
                label: Text(_syncingAll ? 'Syncing…' : 'Sync now'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Map<OutboxStatus, int> _countByStatus(List<OutboxRecord> rows) {
    final out = <OutboxStatus, int>{
      for (final s in OutboxStatus.values) s: 0,
    };
    for (final r in rows) {
      out[r.status] = (out[r.status] ?? 0) + 1;
    }
    return out;
  }
}

class _StatusSummaryStrip extends StatelessWidget {
  const _StatusSummaryStrip({required this.counts});
  final Map<OutboxStatus, int> counts;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final pending =
        (counts[OutboxStatus.pending] ?? 0) + (counts[OutboxStatus.syncing] ?? 0);
    final failed = counts[OutboxStatus.failed] ?? 0;
    final synced = counts[OutboxStatus.synced] ?? 0;
    final quarantined = counts[OutboxStatus.quarantined] ?? 0;
    final rejected = counts[OutboxStatus.rejected] ?? 0;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cs.primary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cs.outlineVariant),
      ),
      child: Wrap(
        spacing: 14,
        runSpacing: 6,
        children: [
          _summaryChip(context, 'Pending', pending, cs.primary),
          _summaryChip(context, 'Synced', synced, Colors.green.shade600),
          _summaryChip(context, 'Failed', failed, Colors.orange.shade700),
          _summaryChip(
            context,
            'Awaiting approval',
            quarantined,
            Colors.purple.shade400,
          ),
          _summaryChip(context, 'Rejected', rejected, Colors.red.shade400),
        ],
      ),
    );
  }

  Widget _summaryChip(BuildContext context, String label, int n, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(
          '$n $label',
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
        ),
      ],
    );
  }
}

class _OutboxRowCard extends StatelessWidget {
  const _OutboxRowCard({required this.record, required this.onRetry});

  final OutboxRecord record;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final color = _statusColor(record.status, cs);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cs.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration:
                    BoxDecoration(color: color, shape: BoxShape.circle),
              ),
              const SizedBox(width: 8),
              Text(
                record.displayStatusLabel,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: color,
                    ),
              ),
              const Spacer(),
              if (record.sessionId != null)
                Text(
                  'Session ${record.sessionId}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: cs.onSurfaceVariant,
                      ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          _kvRow(context, 'UUID', record.attendanceUuid),
          _kvRow(context, 'Created', _formatTime(record.createdAt)),
          if (record.lastErrorAt != null)
            _kvRow(context, 'Last error', _formatTime(record.lastErrorAt!)),
          if ((record.lastErrorMessage ?? '').isNotEmpty)
            _kvRow(context, 'Reason', record.lastErrorMessage!),
          if (record.nextAttemptAfter != null &&
              record.status == OutboxStatus.failed)
            _kvRow(
              context,
              'Next retry',
              _formatTime(record.nextAttemptAfter!),
            ),
          if (record.status == OutboxStatus.failed) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                const Spacer(),
                OutlinedButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.replay),
                  label: const Text('Retry now'),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _kvRow(BuildContext context, String key, String value) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1.5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 84,
            child: Text(
              key,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: cs.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        ],
      ),
    );
  }

  Color _statusColor(OutboxStatus s, ColorScheme cs) {
    switch (s) {
      case OutboxStatus.pending:
      case OutboxStatus.syncing:
        return cs.primary;
      case OutboxStatus.synced:
        return Colors.green.shade600;
      case OutboxStatus.failed:
        return Colors.orange.shade700;
      case OutboxStatus.rejected:
        return Colors.red.shade400;
      case OutboxStatus.quarantined:
        return Colors.purple.shade400;
    }
  }

  String _formatTime(DateTime t) {
    final l = t.toLocal();
    String two(int v) => v.toString().padLeft(2, '0');
    return '${l.year}-${two(l.month)}-${two(l.day)} ${two(l.hour)}:${two(l.minute)}';
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({this.studentIndex});
  final String? studentIndex;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 60, horizontal: 16),
      child: Column(
        children: [
          Icon(
            Icons.cloud_done_outlined,
            size: 64,
            color: cs.primary.withValues(alpha: 0.5),
          ),
          const SizedBox(height: 12),
          Text(
            'Everything is synced',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 6),
          Text(
            'You have no offline attendance waiting to send.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}
