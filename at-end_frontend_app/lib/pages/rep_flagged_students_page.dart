import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../utils/app_selectable_scope.dart';
import '../utils/connectivity_util.dart';
import '../widgets/modern_pull_to_refresh.dart';
import 'login_page.dart';

/// Flagged students (3+ consecutive misses) — opened from class rep drawer.
class RepFlaggedStudentsPage extends StatefulWidget {
  const RepFlaggedStudentsPage({super.key});

  @override
  State<RepFlaggedStudentsPage> createState() => _RepFlaggedStudentsPageState();
}

class _RepFlaggedStudentsPageState extends State<RepFlaggedStudentsPage> {
  Student? _student;
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _rows = [];

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final s = await OfflineService.getCurrentStudent();
    if (!mounted) return;
    if (s == null) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => appSelectableScope(const LoginPage()),
        ),
        (_) => false,
      );
      return;
    }
    setState(() => _student = s);
    await _load(s);
  }

  Future<void> _load(Student s) async {
    if (!await OfflineService.hasPasswordOrApiToken()) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Sign in again to load this list.';
          _rows = [];
        });
      }
      return;
    }
    if (!await hasInternetConnectivity()) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'You appear to be offline.';
          _rows = [];
        });
      }
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final pwd = await OfflineService.getApiSessionPassword();
      final res = await ApiService.classRepDashboard(
        indexNumber: s.indexNumber,
        password: pwd ?? '',
      );
      final raw = jsonDecode(res.body);
      if (res.statusCode == 200 &&
          raw is Map &&
          raw['success'] == true &&
          raw['data'] is Map) {
        final d = Map<String, dynamic>.from(raw['data'] as Map);
        final flaggedRaw = d['flagged_students'];
        final list = <Map<String, dynamic>>[];
        if (flaggedRaw is List) {
          for (final e in flaggedRaw) {
            if (e is Map) list.add(Map<String, dynamic>.from(e));
          }
        }
        if (mounted) {
          setState(() {
            _loading = false;
            _error = null;
            _rows = list;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _loading = false;
            _error = ApiService.messageFromHttpResponse(res).isEmpty
                ? 'Could not load flagged students.'
                : ApiService.messageFromHttpResponse(res);
            _rows = [];
          });
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Could not load flagged students.';
          _rows = [];
        });
      }
    }
  }

  Widget _breadcrumb(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 0, 4, 12),
      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 4,
        children: [
          Text(
            'Class rep',
            style: tt.labelMedium?.copyWith(
              color: cs.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          Icon(Icons.chevron_right, size: 18, color: cs.outline),
          Text(
            'Flagged students',
            style: tt.labelMedium?.copyWith(
              color: cs.onSurface,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  BoxDecoration _cardDecoration(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.65)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final s = _student;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        title: const Text('Flagged students'),
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      body: s == null
          ? const Center(child: CircularProgressIndicator())
          : _loading
              ? const Center(child: CircularProgressIndicator())
              : SafeArea(
                  child: ModernPullToRefresh(
                    showIndicator: false,
                    playChime: false,
                    onRefresh: () => _load(s),
                    child: CustomScrollView(
                      physics: modernPullToRefreshPhysics,
                      slivers: [
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                          sliver: SliverToBoxAdapter(
                            child: _breadcrumb(context),
                          ),
                        ),
                        if (_error != null)
                          SliverPadding(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            sliver: SliverToBoxAdapter(
                              child: Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: cs.errorContainer.withValues(alpha: 0.35),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: cs.error.withValues(alpha: 0.35),
                                  ),
                                ),
                                child: Text(
                                  _error!,
                                  style: tt.bodyMedium?.copyWith(
                                    color: cs.onSurface,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        if (_error != null) ...[
                          const SliverToBoxAdapter(child: SizedBox(height: 12)),
                          SliverToBoxAdapter(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 16),
                              child: FilledButton.tonal(
                                onPressed: () => _load(s),
                                child: const Text('Retry'),
                              ),
                            ),
                          ),
                          const SliverToBoxAdapter(child: SizedBox(height: 24)),
                        ],
                        if (_rows.isEmpty && _error == null)
                          SliverFillRemaining(
                            hasScrollBody: false,
                            child: Center(
                              child: Padding(
                                padding: const EdgeInsets.all(24),
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(
                                      Icons.check_circle_outline,
                                      size: 48,
                                      color: cs.onSurfaceVariant,
                                    ),
                                    const SizedBox(height: 16),
                                    Text(
                                      'No students flagged',
                                      textAlign: TextAlign.center,
                                      style: tt.titleMedium?.copyWith(
                                        color: cs.onSurface,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      'Nobody has 3+ consecutive missed sessions right now.',
                                      textAlign: TextAlign.center,
                                      style: tt.bodyMedium?.copyWith(
                                        color: cs.onSurfaceVariant,
                                        height: 1.35,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        if (_rows.isNotEmpty)
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                            sliver: SliverList.separated(
                              itemCount: _rows.length,
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: 10),
                              itemBuilder: (context, i) {
                                final row = _rows[i];
                                final name = row['name']?.toString() ?? '—';
                                final course = row['course_name']?.toString();
                                final missed = row['consecutive_missed'];
                                final missStr = missed is num
                                    ? missed.round().toString()
                                    : (missed?.toString() ?? '—');
                                return Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(14),
                                  decoration: _cardDecoration(context),
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              name,
                                              style: tt.titleSmall?.copyWith(
                                                fontWeight: FontWeight.w600,
                                                color: cs.onSurface,
                                              ),
                                            ),
                                            if (course != null &&
                                                course.isNotEmpty) ...[
                                              const SizedBox(height: 4),
                                              Text(
                                                course,
                                                style: tt.bodySmall?.copyWith(
                                                  color: cs.onSurfaceVariant,
                                                  height: 1.3,
                                                ),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Text(
                                        '$missStr missed',
                                        style: tt.labelLarge?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          color: cs.onSurface,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
    );
  }
}
