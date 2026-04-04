import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../utils/api_user_message.dart';

/// Weekly schedule from `GET /api/timetable` (Sanctum Bearer).
class TimetablePage extends StatefulWidget {
  const TimetablePage({super.key});

  @override
  State<TimetablePage> createState() => _TimetablePageState();
}

class _TimetablePageState extends State<TimetablePage> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _weekProgress;
  List<String> _orderedDays = [];
  final Map<String, List<Map<String, dynamic>>> _byDay = {};

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _fetch() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiService.getTimetable();
      if (res.statusCode == 401) {
        setState(() {
          _loading = false;
          _error = 'Please sign in again.';
        });
        return;
      }
      if (!ApiService.isSuccessfulHttp(res.statusCode)) {
        final msg = ApiService.messageFromHttpResponse(res);
        setState(() {
          _loading = false;
          _error = msg.isEmpty ? 'Could not load your timetable.' : msg;
        });
        return;
      }
      final decoded = jsonDecode(res.body);
      if (decoded is! Map) {
        setState(() {
          _loading = false;
          _error = 'Could not read timetable data.';
        });
        return;
      }
      var root = Map<String, dynamic>.from(decoded);
      // v1-style envelope: { data: { ordered_days, by_day, ... } }
      if (!root.containsKey('ordered_days') &&
          !root.containsKey('by_day') &&
          root['data'] is Map) {
        root = Map<String, dynamic>.from(root['data'] as Map);
      }
      _orderedDays = [];
      _byDay.clear();
      final od = root['ordered_days'];
      if (od is List) {
        _orderedDays = od.map((e) => e.toString()).toList();
      }
      final by = decoded['by_day'];
      if (by is Map) {
        by.forEach((k, v) {
          if (v is! List) return;
          _byDay[k.toString()] = v
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        });
      }
      final wp = root['week_progress'];
      _weekProgress = wp is Map ? Map<String, dynamic>.from(wp) : null;
      if (mounted) {
        setState(() => _loading = false);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = sanitizeApiUserMessage(e.toString());
        });
      }
    }
  }

  Widget _summaryRow(BuildContext context) {
    final wp = _weekProgress;
    if (wp == null) return const SizedBox.shrink();
    final rem = wp['lectures_remaining'];
    final ch = wp['credit_hours_remaining'];
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        children: [
          Expanded(
            child: _statTile(
              context,
              '$rem',
              'Lectures left',
              cs.primaryContainer.withValues(alpha: 0.55),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _statTile(
              context,
              '$ch',
              'Credit hours left',
              cs.secondaryContainer.withValues(alpha: 0.45),
            ),
          ),
        ],
      ),
    );
  }

  Widget _statTile(
    BuildContext context,
    String value,
    String label,
    Color bg,
  ) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
          ),
        ],
      ),
    );
  }

  Widget _dayCard(BuildContext context, String day) {
    final slots = _byDay[day] ?? [];
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: cs.outlineVariant.withValues(alpha: 0.5),
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              color: cs.surfaceContainerHighest.withValues(alpha: 0.4),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      day,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                  ),
                  Text(
                    '${slots.length}',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: cs.primary,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ],
              ),
            ),
            for (var i = 0; i < slots.length; i++) ...[
              if (i > 0) const Divider(height: 1),
              _slotTile(context, slots[i]),
            ],
          ],
        ),
      ),
    );
  }

  Widget _slotTile(BuildContext context, Map<String, dynamic> c) {
    final code = c['course_code']?.toString() ?? '';
    final name = c['course_name']?.toString() ?? 'Course';
    final start = c['start_time']?.toString() ?? '';
    final end = c['end_time']?.toString() ?? '';
    final lecturer = c['lecturer_name']?.toString() ?? '—';
    final venue = c['venue']?.toString() ?? '—';
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            code.isNotEmpty ? '$name ($code)' : name,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
          const SizedBox(height: 6),
          Text(
            '$start – $end',
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: Theme.of(context).colorScheme.primary,
                  fontWeight: FontWeight.w700,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
          ),
          const SizedBox(height: 4),
          Text(
            lecturer,
            style: Theme.of(context).textTheme.bodySmall,
          ),
          Text(
            venue,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Timetable'),
      ),
      body: RefreshIndicator(
        onRefresh: _fetch,
        child: _loading
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  SizedBox(
                    height: MediaQuery.sizeOf(context).height * 0.35,
                  ),
                  const Center(child: CircularProgressIndicator()),
                ],
              )
            : _error != null
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      SizedBox(
                        height: MediaQuery.sizeOf(context).height * 0.25,
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 24),
                        child: Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: cs.error,
                            fontSize: 15,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  )
                : _orderedDays.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          SizedBox(
                            height: MediaQuery.sizeOf(context).height * 0.22,
                          ),
                          Icon(
                            Icons.calendar_today_outlined,
                            size: 52,
                            color: cs.outline,
                          ),
                          const SizedBox(height: 16),
                          Text(
                            'No timetable yet',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                          ),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(36, 10, 36, 0),
                            child: Text(
                              'Your courses may not have days and times set yet, or you may need a class assigned.',
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: cs.onSurfaceVariant,
                                    height: 1.45,
                                  ),
                            ),
                          ),
                        ],
                      )
                    : ListView(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          _summaryRow(context),
                          ..._orderedDays.map((d) => _dayCard(context, d)),
                        ],
                      ),
      ),
    );
  }
}
