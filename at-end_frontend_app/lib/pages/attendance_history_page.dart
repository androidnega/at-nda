import 'package:flutter/material.dart';

import '../models/attendance_record.dart';
import '../theme/soft_ui.dart';
import '../models/student.dart';
import '../services/offline_service.dart';

/// Past marks from SQLite + pending offline queue; optional filter and stats.
class AttendanceHistoryPage extends StatefulWidget {
  const AttendanceHistoryPage({super.key});

  @override
  State<AttendanceHistoryPage> createState() => _AttendanceHistoryPageState();
}

enum _AttendanceUiStatus {
  present,
  late,
  missed,
  pendingSync,
}

class _HistoryRow {
  _HistoryRow({
    required this.courseLabel,
    required this.whenLabel,
    required this.status,
    this.sessionId,
    this.detail,
  });

  final String courseLabel;
  final String whenLabel;
  final _AttendanceUiStatus status;
  final int? sessionId;
  final String? detail;
}

class _AttendanceHistoryPageState extends State<AttendanceHistoryPage>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  Future<_HistoryBundle>? _future;
  String _courseFilter = '';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _reload();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  void _reload() {
    _future = _loadBundle();
  }

  Future<_HistoryBundle> _loadBundle() async {
    final student = await OfflineService.getCurrentStudent();
    if (student == null) {
      return _HistoryBundle(student: null, rows: [], pending: [], byCourse: {});
    }
    final logs = await OfflineService.getAllAttendanceLogsForIndex(
      student.indexNumber,
    );
    final pending = await OfflineService.getPendingRecords();
    final byCourse =
        await OfflineService.countMarksByCourseCode(student.indexNumber);

    final rows = <_HistoryRow>[];

    for (final p in pending) {
      if (p.studentIndex != student.indexNumber) continue;
      final isCheckoutPending = p.endpoint.trim() == 'attendance/checkout';
      final courseLabel = p.courseId > 0
          ? 'Course #${p.courseId}'
          : (p.sessionId != null ? 'Session #${p.sessionId}' : 'Attendance');
      rows.add(
        _HistoryRow(
          courseLabel: courseLabel,
          whenLabel: _formatTs(p.timestamp),
          status: _AttendanceUiStatus.pendingSync,
          sessionId: p.sessionId,
          detail: isCheckoutPending
              ? 'Checkout saved offline — waiting to sync'
              : 'Waiting to sync when you are online',
        ),
      );
    }

    for (final r in logs) {
      final code = r['course_code']?.toString().trim();
      final courseLabel =
          (code != null && code.isNotEmpty) ? code : 'Course';
      final at = r['marked_at']?.toString() ?? '';
      final sid = _sessionIdFromLog(r);
      final rawStatus = r['status']?.toString().toLowerCase().trim();
      _AttendanceUiStatus st = _AttendanceUiStatus.present;
      if (rawStatus == 'late') {
        st = _AttendanceUiStatus.late;
      } else if (rawStatus == 'missed' || rawStatus == 'absent') {
        st = _AttendanceUiStatus.missed;
      }
      rows.add(
        _HistoryRow(
          courseLabel: courseLabel,
          whenLabel: at,
          status: st,
          sessionId: sid,
          detail: sid != null ? 'Session $sid' : null,
        ),
      );
    }

    rows.sort((a, b) => b.whenLabel.compareTo(a.whenLabel));

    return _HistoryBundle(
      student: student,
      rows: rows,
      pending: pending,
      byCourse: byCourse,
    );
  }

  int? _sessionIdFromLog(Map<String, dynamic> r) {
    final sid = r['session_id'];
    if (sid is int) return sid;
    if (sid is num) return sid.toInt();
    return int.tryParse(sid?.toString() ?? '');
  }

  String _formatTs(String iso) {
    try {
      final d = DateTime.parse(iso);
      return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')} '
          '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return iso;
    }
  }

  List<_HistoryRow> _filtered(List<_HistoryRow> rows) {
    if (_courseFilter.isEmpty) return rows;
    return rows.where((r) => r.courseLabel == _courseFilter).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SoftUi.scaffoldBackground(context),
      appBar: AppBar(
        backgroundColor: SoftUi.scaffoldBackground(context),
        title: const Text('Attendance'),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'History'),
            Tab(text: 'Stats'),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => setState(_reload),
          ),
        ],
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildHistoryTab(context),
          _buildStatsTab(context),
        ],
      ),
    );
  }

  Widget _buildHistoryTab(BuildContext context) {
    return FutureBuilder<_HistoryBundle>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        final bundle = snapshot.data!;
        if (bundle.student == null) {
          return const Center(child: Text('Log in to see attendance history.'));
        }
        final courses = bundle.rows.map((r) => r.courseLabel).toSet().toList()
          ..sort();
        final rows = _filtered(bundle.rows);
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Filter by course',
                    style: Theme.of(context).textTheme.labelLarge,
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    initialValue: _courseFilter,
                    decoration: const InputDecoration(
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                    items: [
                      const DropdownMenuItem<String>(
                        value: '',
                        child: Text('All courses'),
                      ),
                      ...courses.map(
                        (c) => DropdownMenuItem<String>(
                          value: c,
                          child: Text(c),
                        ),
                      ),
                    ],
                    onChanged: (v) {
                      setState(() => _courseFilter = v ?? '');
                    },
                  ),
                ],
              ),
            ),
            Expanded(
              child: rows.isEmpty
                  ? Center(
                      child: Text(
                        bundle.rows.isEmpty
                            ? 'No attendance records yet.'
                            : 'No rows match this filter.',
                        textAlign: TextAlign.center,
                      ),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                      itemCount: rows.length,
                      itemBuilder: (_, i) {
                        final r = rows[i];
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Card(
                            elevation: 0,
                            child: ListTile(
                              leading: _statusIcon(r.status, context),
                              title: Text(
                                r.courseLabel,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const SizedBox(height: 4),
                                  Text(r.whenLabel),
                                  if (r.detail != null) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      r.detail!,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .onSurfaceVariant,
                                          ),
                                    ),
                                  ],
                                ],
                              ),
                              trailing: _statusChip(r.status, context),
                              isThreeLine: r.detail != null,
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ],
        );
      },
    );
  }

  Widget _statusIcon(_AttendanceUiStatus s, BuildContext context) {
    final c = Theme.of(context).colorScheme;
    switch (s) {
      case _AttendanceUiStatus.present:
        return Icon(Icons.check_circle_rounded, color: c.primary);
      case _AttendanceUiStatus.late:
        return Icon(Icons.schedule_rounded, color: c.tertiary);
      case _AttendanceUiStatus.missed:
        return Icon(Icons.cancel_outlined, color: c.error);
      case _AttendanceUiStatus.pendingSync:
        return Icon(Icons.cloud_upload_outlined, color: c.secondary);
    }
  }

  Widget _statusChip(_AttendanceUiStatus s, BuildContext context) {
    final c = Theme.of(context).colorScheme;
    String label;
    Color bg;
    Color fg;
    switch (s) {
      case _AttendanceUiStatus.present:
        label = 'Present';
        bg = c.primaryContainer;
        fg = c.onPrimaryContainer;
        break;
      case _AttendanceUiStatus.late:
        label = 'Late';
        bg = c.tertiaryContainer;
        fg = c.onTertiaryContainer;
        break;
      case _AttendanceUiStatus.missed:
        label = 'Missed';
        bg = c.errorContainer;
        fg = c.onErrorContainer;
        break;
      case _AttendanceUiStatus.pendingSync:
        label = 'Pending';
        bg = c.secondaryContainer;
        fg = c.onSecondaryContainer;
        break;
    }
    return Chip(
      label: Text(label, style: TextStyle(fontSize: 12, color: fg)),
      backgroundColor: bg,
      padding: EdgeInsets.zero,
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
      visualDensity: VisualDensity.compact,
    );
  }

  Widget _buildStatsTab(BuildContext context) {
    return FutureBuilder<_HistoryBundle>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        final bundle = snapshot.data!;
        if (bundle.student == null) {
          return const Center(child: Text('Log in to see stats.'));
        }
        final entries = bundle.byCourse.entries.toList()
          ..sort((a, b) => b.value.compareTo(a.value));
        final total = entries.fold<int>(0, (s, e) => s + e.value);

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Recorded marks (this device)',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 8),
            Text(
              'Percentages need total sessions from your school — here you see how many times you recorded attendance per course.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
            const SizedBox(height: 20),
            if (entries.isEmpty)
              const Text('No synced marks yet.')
            else
              ...entries.map((e) {
                final pct = total > 0 ? (e.value / total * 100) : 0.0;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              e.key,
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ),
                          Text('${e.value} mark${e.value == 1 ? '' : 's'}'),
                        ],
                      ),
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: pct / 100,
                          minHeight: 8,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${pct.toStringAsFixed(0)}% of your recorded activity',
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                              color: Theme.of(context).colorScheme.outline,
                            ),
                      ),
                    ],
                  ),
                );
              }),
            if (bundle.pending.isNotEmpty) ...[
              const SizedBox(height: 24),
              Text(
                'You have ${bundle.pending.length} attendance mark(s) waiting to sync.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.tertiary,
                    ),
              ),
            ],
          ],
        );
      },
    );
  }
}

class _HistoryBundle {
  _HistoryBundle({
    required this.student,
    required this.rows,
    required this.pending,
    required this.byCourse,
  });

  final Student? student;
  final List<_HistoryRow> rows;
  final List<AttendanceRecord> pending;
  final Map<String, int> byCourse;
}
