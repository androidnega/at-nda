import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/flat_dashboard.dart';
import '../widgets/attendance_trend_chart.dart';
import '../widgets/modern_pull_to_refresh.dart';
import 'lecturer_class_detail_page.dart';

/// Lecturer analytics home (Bearer token from lecturer API login).
class LecturerDashboardPage extends StatefulWidget {
  const LecturerDashboardPage({super.key});

  @override
  State<LecturerDashboardPage> createState() => _LecturerDashboardPageState();
}

class _LecturerDashboardPageState extends State<LecturerDashboardPage> {
  bool _loading = true;
  String? _error;
  String _name = '';
  int _totalClasses = 0;
  double _avgAttendance = 0;
  int _atRisk = 0;
  int _activeSessions = 0;
  List<Map<String, dynamic>> _classes = [];
  List<Map<String, dynamic>> _trend = [];
  Map<String, dynamic> _insights = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiService.lecturerDashboard();
      final body = jsonDecode(res.body);
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          body is Map &&
          body['success'] == true &&
          body['data'] is Map) {
        final d = Map<String, dynamic>.from(body['data'] as Map);
        final classes = d['classes'];
        final trend = d['attendance_trend'];
        final ins = d['insights'];
        if (!mounted) return;
        setState(() {
          _name = d['lecturer_name']?.toString() ?? 'Lecturer';
          _totalClasses = _toInt(d['total_classes']) ?? 0;
          _avgAttendance = _toDouble(d['avg_attendance_pct']) ?? 0;
          _atRisk = _toInt(d['at_risk_count']) ?? 0;
          _activeSessions = _toInt(d['active_sessions']) ?? 0;
          _classes = classes is List
              ? classes.map((e) => Map<String, dynamic>.from(e as Map)).toList()
              : [];
          _trend = trend is List
              ? trend.map((e) => Map<String, dynamic>.from(e as Map)).toList()
              : [];
          _insights = ins is Map ? Map<String, dynamic>.from(ins) : {};
          _loading = false;
        });
      } else {
        final msg = body is Map ? body['message']?.toString() : '';
        if (!mounted) return;
        setState(() {
          _error = msg?.isNotEmpty == true ? msg! : 'Could not load dashboard';
          _loading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
        _loading = false;
      });
    }
  }

  int? _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.round();
    return int.tryParse(v?.toString() ?? '');
  }

  double? _toDouble(dynamic v) {
    if (v is double) return v;
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '');
  }

  Widget _statCard(
    BuildContext context, {
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: FlatDashboard.cardDecoration(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 20, color: FlatDashboard.textSecondary),
            const Spacer(),
            Text(title, style: FlatDashboard.captionStyle(context)),
            const SizedBox(height: 4),
            Text(
              value,
              style: FlatDashboard.valueStyle(context).copyWith(fontSize: 20),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FlatDashboard.background,
      appBar: AppBar(
        title: const Text('Lecturer dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(context).pushNamed('/settings'),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(_error!, textAlign: TextAlign.center),
                  ),
                )
              : ModernPullToRefresh(
                  onRefresh: _load,
                  child: ListView(
                    physics: modernPullToRefreshPhysics,
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                    children: [
                      Text(
                        _name,
                        style: FlatDashboard.titleStyle(context).copyWith(
                          fontSize: 22,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Overview',
                        style: FlatDashboard.captionStyle(context),
                      ),
                      const SizedBox(height: 16),
                      SizedBox(
                        height: 108,
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _statCard(
                              context,
                              title: 'Classes',
                              value: '$_totalClasses',
                              icon: Icons.class_outlined,
                            ),
                            const SizedBox(width: 10),
                            _statCard(
                              context,
                              title: 'Avg attendance',
                              value: '${_avgAttendance.toStringAsFixed(1)}%',
                              icon: Icons.percent,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      SizedBox(
                        height: 108,
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _statCard(
                              context,
                              title: 'At-risk',
                              value: '$_atRisk',
                              icon: Icons.warning_amber_outlined,
                            ),
                            const SizedBox(width: 10),
                            _statCard(
                              context,
                              title: 'Active sessions',
                              value: '$_activeSessions',
                              icon: Icons.sensors,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                      AttendanceTrendChart(
                        points: _trend,
                        title: 'Attendance trend (all your courses)',
                      ),
                      if (_insights.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: FlatDashboard.cardDecoration(),
                          child: Text(
                            'Week-over-week: ${_insights['delta_pct'] ?? 0}% '
                            '(${_insights['direction'] ?? 'flat'})',
                            style: const TextStyle(
                              color: FlatDashboard.textPrimary,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                      const SizedBox(height: 24),
                      Text(
                        'Your classes',
                        style: FlatDashboard.titleStyle(context),
                      ),
                      const SizedBox(height: 10),
                      ..._classes.map((c) {
                        final id = c['course_id'];
                        final cid = id is int
                            ? id
                            : int.tryParse(id?.toString() ?? '') ?? 0;
                        final pct = _toDouble(c['attendance_pct']) ?? 0;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Material(
                            color: Colors.transparent,
                            child: InkWell(
                              onTap: cid > 0
                                  ? () {
                                      Navigator.of(context).push<void>(
                                        MaterialPageRoute<void>(
                                          builder: (_) => LecturerClassDetailPage(
                                            courseId: cid,
                                          ),
                                        ),
                                      );
                                    }
                                  : null,
                              borderRadius: BorderRadius.circular(12),
                              child: Container(
                                padding: const EdgeInsets.all(14),
                                decoration: FlatDashboard.cardDecoration(),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            c['course_name']?.toString() ?? '',
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                              color: FlatDashboard.textPrimary,
                                            ),
                                          ),
                                          Text(
                                            '${c['student_count'] ?? 0} students',
                                            style: FlatDashboard.captionStyle(
                                              context,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Text(
                                      '${pct.toStringAsFixed(0)}%',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 18,
                                        color: FlatDashboard.textPrimary,
                                      ),
                                    ),
                                    const Icon(Icons.chevron_right,
                                        color: FlatDashboard.textSecondary),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        );
                      }),
                      const SizedBox(height: 8),
                      Text(
                        'Open a class for session history, exports, and flagged students.',
                        style: FlatDashboard.captionStyle(context),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text(
                                'Announcements: use the web portal or contact admin when messaging is enabled.',
                              ),
                            ),
                          );
                        },
                        icon: const Icon(Icons.campaign_outlined),
                        label: const Text('Announcements'),
                      ),
                    ],
                  ),
                ),
    );
  }
}
