import 'package:flutter/material.dart';

import '../models/attendance_record.dart';
import '../models/student.dart';
import '../services/offline_service.dart';

/// Course-level mark counts from local logs (same logic as History → Stats tab).
class AttendanceStatsPage extends StatefulWidget {
  const AttendanceStatsPage({super.key});

  @override
  State<AttendanceStatsPage> createState() => _AttendanceStatsPageState();
}

class _AttendanceStatsPageState extends State<AttendanceStatsPage> {
  Future<_StatsBundle>? _future;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _future = _load();
  }

  Future<_StatsBundle> _load() async {
    final student = await OfflineService.getCurrentStudent();
    if (student == null) {
      return _StatsBundle(student: null, byCourse: {}, pending: []);
    }
    final byCourse =
        await OfflineService.countMarksByCourseCode(student.indexNumber);
    final pending = await OfflineService.getPendingRecords();
    return _StatsBundle(student: student, byCourse: byCourse, pending: pending);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Statistics'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.of(context).pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => setState(_reload),
          ),
        ],
      ),
      body: FutureBuilder<_StatsBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          final bundle = snapshot.data!;
          if (bundle.student == null) {
            return const Center(child: Text('Log in to see statistics.'));
          }
          if (bundle.byCourse.isEmpty && bundle.pending.isEmpty) {
            return const Center(child: Text('No data yet.'));
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
                'School-wide attendance rates need total sessions from your institution.',
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
                  '${bundle.pending.length} mark(s) waiting to sync.',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _StatsBundle {
  _StatsBundle({
    required this.student,
    required this.byCourse,
    required this.pending,
  });

  final Student? student;
  final Map<String, int> byCourse;
  final List<AttendanceRecord> pending;
}
