import 'package:flutter/material.dart';

import '../pages/student_attendance_table_page.dart';
import '../services/student_attendance_grid_service.dart';

/// Animated course summary cards — always fetched live from the backend.
class StudentCourseOverviewCards extends StatefulWidget {
  const StudentCourseOverviewCards({super.key, this.compact = false});

  final bool compact;

  @override
  State<StudentCourseOverviewCards> createState() =>
      _StudentCourseOverviewCardsState();
}

class _StudentCourseOverviewCardsState extends State<StudentCourseOverviewCards>
    with SingleTickerProviderStateMixin {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _data;
  late final AnimationController _stagger;

  @override
  void initState() {
    super.initState();
    _stagger = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _load();
  }

  @override
  void dispose() {
    _stagger.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await StudentAttendanceGridService.fetchLive();
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
        _error = data == null ? 'Could not load course attendance.' : null;
      });
      if (data != null) {
        _stagger.forward(from: 0);
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Could not load course attendance.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 12),
        child: Center(
          child: SizedBox(
            width: 28,
            height: 28,
            child: CircularProgressIndicator(strokeWidth: 2.5),
          ),
        ),
      );
    }

    if (_error != null && _data == null) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Text(
          _error!,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.error,
              ),
        ),
      );
    }

    final data = _data;
    if (data == null) return const SizedBox.shrink();

    final summary = Map<String, dynamic>.from(data['summary'] as Map? ?? {});
    final courses = (data['courses'] as List? ?? const [])
        .whereType<Map>()
        .map((m) => Map<String, dynamic>.from(m))
        .toList();

    if (courses.isEmpty) return const SizedBox.shrink();

    final overall = (summary['percent'] as int?) ?? 0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Your courses',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ),
            if (!widget.compact)
              TextButton.icon(
                onPressed: _loading ? null : _load,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Refresh'),
              ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                '$overall% overall',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        ...List.generate(courses.length, (i) {
          final course = courses[i];
          final anim = CurvedAnimation(
            parent: _stagger,
            curve: Interval(
              (i / courses.length).clamp(0.0, 0.9),
              ((i + 1) / courses.length).clamp(0.1, 1.0),
              curve: Curves.easeOutCubic,
            ),
          );
          return FadeTransition(
            opacity: anim,
            child: SlideTransition(
              position: Tween<Offset>(
                begin: const Offset(0, 0.08),
                end: Offset.zero,
              ).animate(anim),
              child: Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _CourseOverviewCard(
                  course: course,
                  compact: widget.compact,
                ),
              ),
            ),
          );
        }),
      ],
    );
  }
}

class _CourseOverviewCard extends StatelessWidget {
  const _CourseOverviewCard({
    required this.course,
    required this.compact,
  });

  final Map<String, dynamic> course;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final name = course['course_name']?.toString().trim() ?? 'Course';
    final code = course['course_code']?.toString().trim();
    final percent = (course['percent'] as int?) ?? 0;
    final held = (course['held_count'] as int?) ?? 0;
    final present = (course['present_count'] as int?) ?? 0;
    final cs = Theme.of(context).colorScheme;

    Color badgeBg;
    Color badgeFg;
    if (percent >= 75) {
      badgeBg = const Color(0xFFDCFCE7);
      badgeFg = const Color(0xFF14532D);
    } else if (percent >= 50) {
      badgeBg = const Color(0xFFFEF3C7);
      badgeFg = const Color(0xFF92400E);
    } else {
      badgeBg = const Color(0xFFFEE2E2);
      badgeFg = const Color(0xFF991B1B);
    }

    return Material(
      elevation: 0,
      color: cs.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => Navigator.of(context)
            .pushNamed(StudentAttendanceTablePage.routeName),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.5)),
          ),
          padding: EdgeInsets.all(compact ? 12 : 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                          ),
                        ),
                        if (code != null && code.isNotEmpty)
                          Text(
                            code,
                            style: TextStyle(
                              fontSize: 11,
                              color: cs.onSurfaceVariant,
                            ),
                          ),
                      ],
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: badgeBg,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$percent%',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                        color: badgeFg,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: LinearProgressIndicator(
                  value: (percent / 100).clamp(0.0, 1.0),
                  minHeight: 7,
                  backgroundColor: cs.surfaceContainerHighest,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                '$present of $held classes attended',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: cs.onSurfaceVariant,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
