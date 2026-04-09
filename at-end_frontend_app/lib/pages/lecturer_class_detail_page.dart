import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:share_plus/share_plus.dart';

import '../services/api_service.dart';
import '../services/notification_bridge.dart';
import '../theme/flat_dashboard.dart';
import '../utils/constants.dart';
import '../widgets/attendance_trend_chart.dart';
import '../widgets/modern_pull_to_refresh.dart';

/// Course drill-down: trend, session history, flagged students, export last session CSV.
class LecturerClassDetailPage extends StatefulWidget {
  const LecturerClassDetailPage({super.key, required this.courseId});

  final int courseId;

  @override
  State<LecturerClassDetailPage> createState() => _LecturerClassDetailPageState();
}

class _LecturerClassDetailPageState extends State<LecturerClassDetailPage> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _course;
  List<Map<String, dynamic>> _trend = [];
  List<Map<String, dynamic>> _sessions = [];
  List<Map<String, dynamic>> _flagged = [];

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
      final res = await ApiService.lecturerCourseDetail(widget.courseId);
      final body = jsonDecode(res.body);
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          body is Map &&
          body['success'] == true &&
          body['data'] is Map) {
        final d = Map<String, dynamic>.from(body['data'] as Map);
        final c = d['course'];
        final trend = d['attendance_trend'];
        final sess = d['sessions'];
        final flag = d['flagged_students'];
        if (!mounted) return;
        setState(() {
          _course = c is Map ? Map<String, dynamic>.from(c) : null;
          _trend = trend is List
              ? trend.map((e) => Map<String, dynamic>.from(e as Map)).toList()
              : [];
          _sessions = sess is List
              ? sess.map((e) => Map<String, dynamic>.from(e as Map)).toList()
              : [];
          _flagged = flag is List
              ? flag.map((e) => Map<String, dynamic>.from(e as Map)).toList()
              : [];
          _loading = false;
        });
      } else {
        final msg = body is Map ? body['message']?.toString() : '';
        if (!mounted) return;
        setState(() {
          _error = msg?.isNotEmpty == true ? msg! : 'Could not load course';
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

  Future<void> _exportSessionCsv(int sessionId) async {
    try {
      final uri = Uri.parse(
        '${Constants.baseUrl.trim().replaceAll(RegExp(r'/+$'), '')}/attendance/$sessionId/export/csv',
      );
      final res = await http
          .get(uri, headers: ApiService.requestHeaders())
          .timeout(ApiService.httpTimeout);
      if (res.statusCode < 200 || res.statusCode >= 300) {
        if (mounted) {
          NotificationBridge.showSnackBar(
            const SnackBar(content: Text('Export failed — check permissions')),
          );
        }
        return;
      }
      await Share.share(
        res.body,
        subject: 'Attendance session $sessionId',
      );
    } catch (e) {
      if (mounted) {
        NotificationBridge.showSnackBar(
          SnackBar(content: Text('Export error: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final name = _course?['course_name']?.toString() ?? 'Course';
    return Scaffold(
      appBar: AppBar(
        title: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, textAlign: TextAlign.center))
              : ModernPullToRefresh(
                  onRefresh: _load,
                  child: ListView(
                    physics: modernPullToRefreshPhysics,
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                    children: [
                      if (_course != null) ...[
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: FlatDashboard.cardDecoration(),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _course!['course_code']?.toString() ?? '',
                                style: FlatDashboard.captionStyle(context),
                              ),
                              Text(
                                name,
                                style: FlatDashboard.titleStyle(context),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                '${_course!['student_count'] ?? 0} students',
                                style: FlatDashboard.captionStyle(context),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],
                      AttendanceTrendChart(
                        points: _trend,
                        title: 'Attendance % (recent weeks)',
                      ),
                      const SizedBox(height: 20),
                      Text(
                        'Session history',
                        style: FlatDashboard.titleStyle(context),
                      ),
                      const SizedBox(height: 8),
                      if (_sessions.isEmpty)
                        Text(
                          'No sessions yet.',
                          style: FlatDashboard.captionStyle(context),
                        )
                      else
                        ..._sessions.map((s) {
                          final id = s['id'];
                          final sid = id is int
                              ? id
                              : int.tryParse(id?.toString() ?? '') ?? 0;
                          final active = s['is_active'] == true;
                          return Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            decoration: FlatDashboard.cardDecoration(),
                            child: ListTile(
                              title: Text(
                                s['session_code']?.toString() ?? 'Session $sid',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                  color: FlatDashboard.textPrimary,
                                ),
                              ),
                              subtitle: Text(
                                '${s['start_time'] ?? ''}',
                                style: FlatDashboard.captionStyle(context),
                              ),
                              trailing: sid > 0
                                  ? TextButton(
                                      onPressed: () => _exportSessionCsv(sid),
                                      child: const Text('CSV'),
                                    )
                                  : null,
                              leading: Icon(
                                active ? Icons.radio_button_checked : Icons.history,
                                color: active
                                    ? Colors.green.shade700
                                    : FlatDashboard.textSecondary,
                              ),
                            ),
                          );
                        }),
                      const SizedBox(height: 20),
                      Text(
                        'Flagged students (3+ consecutive misses)',
                        style: FlatDashboard.titleStyle(context),
                      ),
                      const SizedBox(height: 8),
                      if (_flagged.isEmpty)
                        Text(
                          'None in this class.',
                          style: FlatDashboard.captionStyle(context),
                        )
                      else
                        ..._flagged.map(
                          (f) => Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.all(12),
                            decoration: FlatDashboard.cardDecoration(),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        f['name']?.toString() ?? '',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                          color: FlatDashboard.textPrimary,
                                        ),
                                      ),
                                      Text(
                                        f['index_number']?.toString() ?? '',
                                        style: FlatDashboard.captionStyle(
                                          context,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Text(
                                  '${f['consecutive_missed'] ?? 0} miss',
                                  style: TextStyle(
                                    fontWeight: FontWeight.w800,
                                    color: Colors.orange.shade800,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
