import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../utils/app_selectable_scope.dart';
import '../utils/connectivity_util.dart';
import '../theme/soft_ui.dart';
import '../widgets/modern_pull_to_refresh.dart';
import 'login_page.dart';

/// Week-over-week attendance insights — opened from class rep drawer.
class RepInsightsPage extends StatefulWidget {
  const RepInsightsPage({super.key});

  @override
  State<RepInsightsPage> createState() => _RepInsightsPageState();
}

class _RepInsightsPageState extends State<RepInsightsPage> {
  Student? _student;
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _insights = {};

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
          _error = 'Sign in again to load insights.';
          _insights = {};
        });
      }
      return;
    }
    if (!await hasInternetConnectivity()) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'You appear to be offline.';
          _insights = {};
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
        final ins = d['insights'];
        if (mounted) {
          setState(() {
            _loading = false;
            _error = null;
            _insights = ins is Map ? Map<String, dynamic>.from(ins) : {};
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _loading = false;
            _error = ApiService.messageFromHttpResponse(res).isEmpty
                ? 'Could not load insights.'
                : ApiService.messageFromHttpResponse(res);
            _insights = {};
          });
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Could not load insights.';
          _insights = {};
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
            'Insights',
            style: tt.labelMedium?.copyWith(
              color: cs.onSurface,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _insightsCard(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final dir = _insights['direction']?.toString() ?? 'flat';
    final deltaRaw = _insights['delta_pct'];
    final d = deltaRaw is num
        ? deltaRaw.toDouble()
        : double.tryParse(deltaRaw?.toString() ?? '') ?? 0;
    final weekly = _insights['weekly_trend_label']?.toString() ?? '—';

    final String changeLine;
    if (dir == 'up') {
      changeLine =
          'Attendance is up ${d.abs().toStringAsFixed(1)}% compared to the previous week.';
    } else if (dir == 'down') {
      changeLine =
          'Attendance is down ${d.abs().toStringAsFixed(1)}% compared to the previous week.';
    } else {
      changeLine = 'Week-over-week attendance is stable.';
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cs.surfaceContainerLow,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.65)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Summary',
            style: tt.titleMedium?.copyWith(
              color: cs.onSurface,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            changeLine,
            style: tt.bodyMedium?.copyWith(
              color: cs.onSurface,
              height: 1.35,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Weekly trend: $weekly',
            style: tt.bodySmall?.copyWith(
              color: cs.onSurfaceVariant,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final s = _student;

    return Scaffold(
      backgroundColor: SoftUi.scaffoldBackground(context),
      appBar: AppBar(
        backgroundColor: SoftUi.scaffoldBackground(context),
        title: const Text('Insights'),
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
                        ],
                        if (_error == null)
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                            sliver: SliverToBoxAdapter(
                              child: _insightsCard(context),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
    );
  }
}
